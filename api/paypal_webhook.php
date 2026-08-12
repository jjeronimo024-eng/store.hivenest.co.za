<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/myorderbox_bridge.php';
require_once __DIR__ . '/../utilities/customer_loyalty.php';
require_once __DIR__ . '/../utilities/promotions.php';
require_once __DIR__ . '/../utilities/currency.php';

function ppwh_out(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function ppwh_env(string $key, string $default = ''): string {
    static $env = null;
    if ($env === null) {
        $env = [];
        $lines = is_readable(HIVENEST_ENV_PATH) ? (@file(HIVENEST_ENV_PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
            [$name, $value] = explode('=', $line, 2);
            $env[trim($name)] = trim(trim($value), "\"'");
        }
    }
    $process = getenv($key);
    return $process !== false && $process !== '' ? (string)$process : (string)($env[$key] ?? $default);
}

function ppwh_base(): string {
    return strtolower(ppwh_env('PAYPAL_MODE', 'sandbox')) === 'live'
        ? 'https://api-m.paypal.com'
        : 'https://api-m.sandbox.paypal.com';
}

function ppwh_token(): ?string {
    if (!function_exists('curl_init')) return null;
    $client = ppwh_env('PAYPAL_CLIENT_ID');
    $secret = ppwh_env('PAYPAL_CLIENT_SECRET');
    if ($client === '' || $secret === '') return null;
    $ch = curl_init(ppwh_base() . '/v1/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_USERPWD => $client . ':' . $secret,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode((string)$body, true);
    return $status === 200 && !empty($data['access_token']) ? (string)$data['access_token'] : null;
}

function ppwh_header(string $name): string {
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return (string)($_SERVER[$key] ?? '');
}

function ppwh_verify_signature(array $event): array {
    $webhookId = ppwh_env('PAYPAL_WEBHOOK_ID');
    if ($webhookId === '') {
        return ['ok' => false, 'status' => 'skipped', 'error' => 'PAYPAL_WEBHOOK_ID is not configured.'];
    }
    $token = ppwh_token();
    if (!$token) {
        return ['ok' => false, 'status' => 'failed', 'error' => 'Unable to authenticate with PayPal for webhook verification.'];
    }

    $payload = [
        'webhook_id' => $webhookId,
        'transmission_id' => ppwh_header('PAYPAL-TRANSMISSION-ID'),
        'transmission_time' => ppwh_header('PAYPAL-TRANSMISSION-TIME'),
        'cert_url' => ppwh_header('PAYPAL-CERT-URL'),
        'auth_algo' => ppwh_header('PAYPAL-AUTH-ALGO'),
        'transmission_sig' => ppwh_header('PAYPAL-TRANSMISSION-SIG'),
        'webhook_event' => $event,
    ];
    foreach (['transmission_id','transmission_time','cert_url','auth_algo','transmission_sig'] as $required) {
        if (trim((string)$payload[$required]) === '') {
            return ['ok' => false, 'status' => 'failed', 'error' => 'Missing PayPal webhook header: ' . $required];
        }
    }

    $ch = curl_init(ppwh_base() . '/v1/notifications/verify-webhook-signature');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    $data = json_decode((string)$body, true);
    if ($error !== '' || $http < 200 || $http >= 300 || !is_array($data)) {
        $detail = $error ?: 'PayPal webhook verification request failed.';
        if ($http > 0) {
            $detail .= ' HTTP ' . $http;
        }
        if (is_array($data) && !empty($data['message'])) {
            $detail .= ': ' . (string)$data['message'];
        }
        return ['ok' => false, 'status' => 'failed', 'error' => $detail, 'response' => $data ?: ['raw' => substr((string)$body, 0, 500)]];
    }
    $verified = strtoupper((string)($data['verification_status'] ?? '')) === 'SUCCESS';
    return [
        'ok' => $verified,
        'status' => $verified ? 'success' : 'failed',
        'error' => $verified ? null : 'PayPal webhook signature verification failed: ' . (string)($data['verification_status'] ?? 'UNKNOWN'),
        'response' => $data,
    ];
}

function ppwh_capture_ids(array $event): array {
    $resource = is_array($event['resource'] ?? null) ? $event['resource'] : [];
    $relatedIds = is_array($resource['supplementary_data']['related_ids'] ?? null)
        ? $resource['supplementary_data']['related_ids']
        : [];
    // For refund/reversal webhooks resource.id is the refund/reversal ID.
    // The original capture ID is supplied under related_ids.capture_id.
    $captureId = (string)($relatedIds['capture_id'] ?? ($resource['id'] ?? ''));
    $orderId = (string)($relatedIds['order_id'] ?? '');
    if ($orderId === '') $orderId = (string)($resource['invoice_id'] ?? '');
    return [$captureId, $orderId];
}

function ppwh_refunded_total(PDO $db, string $captureId): float {
    if ($captureId === '') return 0.0;
    $stmt = $db->prepare("
        SELECT payload
        FROM paypal_webhook_events
        WHERE paypal_capture_id = :capture_id
          AND event_type IN ('PAYMENT.CAPTURE.REFUNDED','PAYMENT.CAPTURE.REVERSED')
          AND verification_status = 'success'
    ");
    $stmt->execute(['capture_id' => $captureId]);
    $total = 0.0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $payload) {
        $decoded = json_decode((string)$payload, true);
        if (!is_array($decoded)) continue;
        $total += max(0.0, (float)($decoded['resource']['amount']['value'] ?? 0));
    }
    return round($total, 2);
}

function ppwh_uuid(): string {
    if (function_exists('hivenest_bridge_uuid')) return hivenest_bridge_uuid();
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function ppwh_save_order_from_checkout_session(PDO $db, array $checkout, array $event, string $captureId, string $paypalOrderId): ?array {
    if ($captureId !== '' && hivenest_bridge_table_exists($db, 'payment_gateway_transactions')) {
        $existing = $db->prepare("
            SELECT o.id, o.order_number
            FROM payment_gateway_transactions pgt
            INNER JOIN orders o ON o.id=pgt.order_id
            WHERE pgt.gateway='paypal'
              AND pgt.gateway_capture_id=:capture_id
            LIMIT 1
        ");
        $existing->execute(['capture_id' => $captureId]);
        $row = $existing->fetch();
        if ($row) {
            return ['ok' => true, 'order_id' => (int)$row['id'], 'order_number' => (string)$row['order_number'], 'existing' => true];
        }
    }
    $snapshot = json_decode((string)($checkout['cart_snapshot'] ?? ''), true);
    if (!is_array($snapshot) || empty($snapshot['items']) || !is_array($snapshot['items'])) {
        return ['ok' => false, 'error' => 'Stored checkout session has no usable cart snapshot.'];
    }
    if (!empty($checkout['hivenest_order_id'])) {
        return [
            'ok' => true,
            'order_id' => (int)$checkout['hivenest_order_id'],
            'order_number' => (string)$checkout['hivenest_order_number'],
            'existing' => true,
        ];
    }

    $amount = (float)($event['resource']['amount']['value'] ?? $snapshot['total'] ?? 0);
    $currency = (string)($event['resource']['amount']['currency_code'] ?? 'USD');
    if ($currency !== 'USD' || abs($amount - (float)$snapshot['total']) > 0.001) {
        return ['ok' => false, 'error' => 'Webhook capture amount/currency does not match stored checkout session.'];
    }

    $customerId = (int)$checkout['customer_id'];
    if ($customerId <= 0) return ['ok' => false, 'error' => 'Stored checkout session has no customer ID.'];

    try {
        $db->beginTransaction();
        $number = 'HN-' . gmdate('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
        $subtotal = round((float)($snapshot['subtotal'] ?? $snapshot['total']), 2);
        $discount = round((float)($snapshot['discount_amount'] ?? 0), 2);
        $total = round((float)$snapshot['total'], 2);
        $currencySnapshot = is_array($snapshot['currency_snapshot'] ?? null)
            ? $snapshot['currency_snapshot']
            : hivenest_currency_order_snapshot($db, $customerId, [
                'subtotal' => $subtotal,
                'tax_amount' => 0,
                'discount_amount' => $discount,
                'total_amount' => $total,
            ]);
        $orderStmt = $db->prepare("
            INSERT INTO orders
                (uuid, customer_id, order_number, order_status, payment_status,
                 subtotal, tax_amount, discount_amount, total_amount, currency,
                 display_currency, display_exchange_rate, display_subtotal,
                 display_tax_amount, display_discount_amount, display_total_amount,
                 display_rate_source, display_rate_captured_at,
                 payment_method, payment_reference, processed_at)
            VALUES
                (:uuid, :customer_id, :order_number, 'processing', 'paid',
                 :subtotal, 0, :discount, :total, 'USD',
                 :display_currency, :display_exchange_rate, :display_subtotal,
                 :display_tax_amount, :display_discount_amount, :display_total_amount,
                 :display_rate_source, :display_rate_captured_at,
                 'paypal', :payment_reference, NOW())
        ");
        $orderStmt->execute([
            'uuid' => ppwh_uuid(),
            'customer_id' => $customerId,
            'order_number' => $number,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total,
            'display_currency' => $currencySnapshot['display_currency'] ?? 'USD',
            'display_exchange_rate' => $currencySnapshot['display_exchange_rate'] ?? 1,
            'display_subtotal' => $currencySnapshot['display_subtotal'] ?? $subtotal,
            'display_tax_amount' => $currencySnapshot['display_tax_amount'] ?? 0,
            'display_discount_amount' => $currencySnapshot['display_discount_amount'] ?? $discount,
            'display_total_amount' => $currencySnapshot['display_total_amount'] ?? $total,
            'display_rate_source' => $currencySnapshot['display_rate_source'] ?? 'usd_base',
            'display_rate_captured_at' => $currencySnapshot['display_rate_captured_at'] ?? gmdate('Y-m-d H:i:s'),
            'payment_reference' => $captureId,
        ]);
        $orderId = (int)$db->lastInsertId();

        $line = $db->prepare("
            INSERT INTO order_items
                (uuid, order_id, product_id, product_name, domain_name, quantity, unit_price, setup_fee, billing_cycle, line_total, product_config)
            VALUES
                (:uuid, :order_id, :product_id, :product_name, :domain_name, :quantity, :unit_price, :setup_fee, :billing_cycle, :line_total, :product_config)
        ");
        foreach ($snapshot['items'] as $item) {
            $cycle = in_array($item['billing_cycle'] ?? '', ['monthly','quarterly','semi_annually','annually','biennially','triennially'], true)
                ? (string)$item['billing_cycle']
                : 'annually';
            $line->execute([
                'uuid' => ppwh_uuid(),
                'order_id' => $orderId,
                'product_id' => (int)$item['product_id'],
                'product_name' => (string)$item['name'],
                'domain_name' => $item['domain'] ?? null,
                'quantity' => (int)$item['quantity'],
                'unit_price' => (float)$item['unit_price'],
                'setup_fee' => (float)$item['setup_fee'],
                'billing_cycle' => $cycle,
                'line_total' => ((float)$item['unit_price'] + (float)$item['setup_fee']) * (int)$item['quantity'],
                'product_config' => json_encode(array_filter([
                    'sku' => $item['sku'] ?? '',
                    'paypal_order_id' => $paypalOrderId,
                    'years' => $item['years'] ?? null,
                    'domain_action' => $item['domain_action'] ?? null,
                    'created_by' => 'paypal_webhook_recovery',
                ], static fn($value) => $value !== null && $value !== ''), JSON_UNESCAPED_SLASHES),
            ]);
        }

        $promotion = $snapshot['promotion'] ?? null;
        if (is_array($promotion) && !empty($promotion['id']) && !empty($promotion['code'])) {
            if (!hivenest_promotion_table_exists($db, 'promotion_redemptions')) {
                throw new RuntimeException('Promotion redemption storage is unavailable during webhook recovery.');
            }
            $redemption = $db->prepare("
                INSERT INTO promotion_redemptions
                    (uuid, promotion_code_id, customer_id, order_id, code, discount_amount, currency)
                VALUES
                    (:uuid, :promotion_id, :customer_id, :order_id, :code, :discount_amount, 'USD')
            ");
            $redemption->execute([
                'uuid' => ppwh_uuid(),
                'promotion_id' => (int)$promotion['id'],
                'customer_id' => $customerId,
                'order_id' => $orderId,
                'code' => (string)$promotion['code'],
                'discount_amount' => (float)($snapshot['promotion_discount_amount'] ?? 0),
            ]);
            $db->prepare('UPDATE promotion_codes SET usage_count=usage_count+1, updated_at=NOW() WHERE id=:id')
                ->execute(['id' => (int)$promotion['id']]);
        }

        $db->prepare("
            UPDATE paypal_checkout_sessions
            SET status='captured',
                hivenest_order_id=:order_id,
                hivenest_order_number=:order_number,
                paypal_capture_id=:capture_id,
                captured_at=NOW()
            WHERE id=:checkout_id
        ")->execute([
            'order_id' => $orderId,
            'order_number' => $number,
            'capture_id' => $captureId,
            'checkout_id' => (int)$checkout['id'],
        ]);

        $db->commit();
        hivenest_start_order_provisioning($orderId, $captureId, $paypalOrderId);
        hivenest_log_worker_run('webhook', hivenest_process_provisioning_jobs_if_enabled(10));
        try {
            hivenest_customer_loyalty($db, $customerId, true);
        } catch (Throwable $loyaltyError) {
            error_log('Webhook recovery loyalty recalculation failed: ' . $loyaltyError->getMessage());
        }
        hivenest_send_paid_order_emails($number);
        return ['ok' => true, 'order_id' => $orderId, 'order_number' => $number, 'existing' => false];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function ppwh_store_event(PDO $db, array $event, array $headers, string $verificationStatus, ?string $error): array {
    $eventId = (string)($event['id'] ?? '');
    if ($eventId === '') $eventId = 'missing-' . bin2hex(random_bytes(10));
    $eventType = (string)($event['event_type'] ?? 'unknown');
    $resourceType = (string)($event['resource_type'] ?? ($event['resource']['resource_type'] ?? ''));
    [$captureId, $orderId] = ppwh_capture_ids($event);

    try {
        $stmt = $db->prepare("
            INSERT INTO paypal_webhook_events
                (event_id, event_type, resource_type, paypal_order_id, paypal_capture_id, verification_status, processing_status, headers, payload, error_message)
            VALUES
                (:event_id, :event_type, :resource_type, :paypal_order_id, :paypal_capture_id, :verification_status, 'pending', :headers, :payload, :error_message)
        ");
        $stmt->execute([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'resource_type' => $resourceType !== '' ? $resourceType : null,
            'paypal_order_id' => $orderId !== '' ? $orderId : null,
            'paypal_capture_id' => $captureId !== '' ? $captureId : null,
            'verification_status' => $verificationStatus,
            'headers' => json_encode($headers, JSON_UNESCAPED_SLASHES),
            'payload' => json_encode($event, JSON_UNESCAPED_SLASHES),
            'error_message' => $error,
        ]);
        return ['duplicate' => false, 'id' => (int)$db->lastInsertId(), 'event_id' => $eventId, 'event_type' => $eventType, 'capture_id' => $captureId, 'order_id' => $orderId];
    } catch (Throwable $e) {
        if ($e instanceof PDOException && str_contains($e->getMessage(), 'Duplicate')) {
            $db->prepare("UPDATE paypal_webhook_events SET processing_status='duplicate' WHERE event_id=:event_id")
                ->execute(['event_id' => $eventId]);
            return ['duplicate' => true, 'id' => 0, 'event_id' => $eventId, 'event_type' => $eventType, 'capture_id' => $captureId, 'order_id' => $orderId];
        }
        throw $e;
    }
}

function ppwh_process_event(PDO $db, array $stored, array $event): void {
    if (!empty($stored['duplicate'])) return;
    $eventType = (string)$stored['event_type'];
    $captureId = (string)$stored['capture_id'];
    $paypalOrderId = (string)$stored['order_id'];
    $eventRowId = (int)$stored['id'];

    if (!in_array($eventType, ['PAYMENT.CAPTURE.COMPLETED','PAYMENT.CAPTURE.DENIED','PAYMENT.CAPTURE.REFUNDED','PAYMENT.CAPTURE.REVERSED'], true)) {
        $db->prepare("UPDATE paypal_webhook_events SET processing_status='ignored', processed_at=NOW() WHERE id=:id")
            ->execute(['id' => $eventRowId]);
        return;
    }

    $status = match ($eventType) {
        'PAYMENT.CAPTURE.COMPLETED' => 'captured',
        'PAYMENT.CAPTURE.DENIED' => 'denied',
        'PAYMENT.CAPTURE.REFUNDED' => 'refunded',
        'PAYMENT.CAPTURE.REVERSED' => 'reversed',
        default => 'unknown',
    };

    $txStmt = $db->prepare("
        SELECT *
        FROM payment_gateway_transactions
        WHERE gateway='paypal'
          AND (gateway_capture_id=:capture_id OR (:paypal_order_id <> '' AND gateway_order_id=:paypal_order_id))
        LIMIT 1
    ");
    $txStmt->execute(['capture_id' => $captureId, 'paypal_order_id' => $paypalOrderId]);
    $tx = $txStmt->fetch();
    if (!$tx) {
        if ($eventType === 'PAYMENT.CAPTURE.COMPLETED' && $paypalOrderId !== '' && hivenest_bridge_table_exists($db, 'paypal_checkout_sessions')) {
            $checkoutStmt = $db->prepare("SELECT * FROM paypal_checkout_sessions WHERE paypal_order_id=:paypal_order_id LIMIT 1");
            $checkoutStmt->execute(['paypal_order_id' => $paypalOrderId]);
            $checkout = $checkoutStmt->fetch();
            if ($checkout) {
                $recovered = ppwh_save_order_from_checkout_session($db, $checkout, $event, $captureId, $paypalOrderId);
                if (!empty($recovered['ok'])) {
                    $db->prepare("UPDATE paypal_webhook_events SET processing_status='processed', error_message=NULL, processed_at=NOW() WHERE id=:id")
                        ->execute(['id' => $eventRowId]);
                    return;
                }
                $db->prepare("UPDATE paypal_webhook_events SET processing_status='manual_review', error_message=:error, processed_at=NOW() WHERE id=:id")
                    ->execute(['error' => 'Checkout recovery failed: ' . (string)($recovered['error'] ?? 'unknown'), 'id' => $eventRowId]);
                return;
            }
        }
        $db->prepare("UPDATE paypal_webhook_events SET processing_status='manual_review', error_message=:error, processed_at=NOW() WHERE id=:id")
            ->execute(['error' => 'No matching local payment transaction found for PayPal capture/order.', 'id' => $eventRowId]);
        return;
    }

    $createReviewJob = static function (string $reason) use ($db, $tx): void {
        if (!hivenest_bridge_table_exists($db, 'provisioning_jobs')) return;
        try {
            $stmt = $db->prepare("
                INSERT INTO provisioning_jobs
                    (uuid, order_id, order_item_id, service_id, customer_id, job_type, provider, status, request_payload, error_message)
                VALUES
                    (:uuid, :order_id, NULL, NULL, :customer_id, 'manual_queue', 'hivenest_team', 'manual_review', :payload, :error)
            ");
            $stmt->execute([
                'uuid' => ppwh_uuid(),
                'order_id' => (int)$tx['order_id'],
                'customer_id' => (int)$tx['customer_id'],
                'payload' => json_encode([
                    'source' => 'paypal_webhook',
                    'gateway_capture_id' => $tx['gateway_capture_id'],
                    'reason' => $reason,
                ], JSON_UNESCAPED_SLASHES),
                'error' => $reason,
            ]);
        } catch (Throwable $e) {
            error_log('PayPal webhook manual review job creation failed: ' . $e->getMessage());
        }
    };

    $db->prepare("UPDATE payment_gateway_transactions SET gateway_status=:gateway_status, provider_response=:payload WHERE id=:id")
        ->execute(['gateway_status' => $status, 'payload' => json_encode($event, JSON_UNESCAPED_SLASHES), 'id' => (int)$tx['id']]);

    if ($eventType === 'PAYMENT.CAPTURE.REFUNDED' || $eventType === 'PAYMENT.CAPTURE.REVERSED') {
        if ($eventType === 'PAYMENT.CAPTURE.REFUNDED' && hivenest_bridge_table_exists($db, 'payment_refunds')) {
            $providerRefundId = trim((string)($event['resource']['id'] ?? ''));
            if ($providerRefundId !== '') {
                $db->prepare("
                    UPDATE payment_refunds
                    SET status='completed',webhook_event_id=:event_id,provider_response=:payload,
                        error_message=NULL,completed_at=COALESCE(completed_at,NOW())
                    WHERE provider_refund_id=:provider_refund_id
                ")->execute([
                    'event_id' => (string)($event['id'] ?? ''),
                    'payload' => json_encode($event, JSON_UNESCAPED_SLASHES),
                    'provider_refund_id' => $providerRefundId,
                ]);
            }
        }
        $originalAmount = (float)($tx['amount'] ?? 0);
        $refundedTotal = ppwh_refunded_total($db, (string)$tx['gateway_capture_id']);
        if ($eventType === 'PAYMENT.CAPTURE.REVERSED' && $refundedTotal <= 0) {
            $refundedTotal = $originalAmount;
        }
        $paymentStatus = ($refundedTotal > 0 && $originalAmount > 0 && $refundedTotal + 0.001 < $originalAmount)
            ? 'partially_refunded'
            : 'refunded';
        $orderStatus = $paymentStatus === 'refunded' ? 'refunded' : 'processing';
        $createReviewJob(($eventType === 'PAYMENT.CAPTURE.REVERSED' ? 'Reversal' : ($paymentStatus === 'partially_refunded' ? 'Partial refund' : 'Refund')) . ' received from PayPal. Review service status and customer communication.');
        $db->prepare("UPDATE orders SET payment_status=:payment_status, order_status=:order_status, provisioning_status='manual_review' WHERE id=:order_id")
            ->execute(['payment_status' => $paymentStatus, 'order_status' => $orderStatus, 'order_id' => (int)$tx['order_id']]);
        if (
            $paymentStatus === 'refunded'
            && hivenest_promotion_table_exists($db, 'promotion_redemptions')
            && hivenest_promotion_column_exists($db, 'promotion_redemptions', 'reversed_at')
        ) {
            $redemptionStmt = $db->prepare("
                SELECT id, promotion_code_id
                FROM promotion_redemptions
                WHERE order_id=:order_id
                  AND reversed_at IS NULL
                LIMIT 1
            ");
            $redemptionStmt->execute(['order_id' => (int)$tx['order_id']]);
            $redemption = $redemptionStmt->fetch(PDO::FETCH_ASSOC);
            if ($redemption) {
                $reverseStmt = $db->prepare("
                    UPDATE promotion_redemptions
                    SET reversed_at=NOW(),
                        reversal_event_id=:event_id,
                        reversal_reason=:reason
                    WHERE id=:id
                      AND reversed_at IS NULL
                ");
                $reverseStmt->execute([
                    'event_id' => (string)($event['id'] ?? ''),
                    'reason' => $eventType === 'PAYMENT.CAPTURE.REVERSED'
                        ? 'PayPal capture reversed'
                        : 'PayPal order fully refunded',
                    'id' => (int)$redemption['id'],
                ]);
                if ($reverseStmt->rowCount() === 1) {
                    $db->prepare('
                        UPDATE promotion_codes
                        SET usage_count=GREATEST(0, usage_count-1),
                            updated_at=NOW()
                        WHERE id=:promotion_id
                    ')->execute(['promotion_id' => (int)$redemption['promotion_code_id']]);
                }
            }
        }
        try {
            hivenest_customer_loyalty($db, (int)$tx['customer_id'], true);
        } catch (Throwable $loyaltyError) {
            error_log('Refund loyalty recalculation failed: ' . $loyaltyError->getMessage());
        }
    } elseif ($eventType === 'PAYMENT.CAPTURE.DENIED') {
        $createReviewJob('PayPal capture denied. Confirm whether any service was provisioned before denial.');
        $db->prepare("UPDATE orders SET payment_status='failed', order_status='failed', provisioning_status='failed' WHERE id=:order_id")
            ->execute(['order_id' => (int)$tx['order_id']]);
        try {
            hivenest_customer_loyalty($db, (int)$tx['customer_id'], true);
        } catch (Throwable $loyaltyError) {
            error_log('Denied payment loyalty recalculation failed: ' . $loyaltyError->getMessage());
        }
    }

    hivenest_refresh_order_provisioning_status($db, (int)$tx['order_id']);
    $db->prepare("UPDATE paypal_webhook_events SET processing_status='processed', processed_at=NOW() WHERE id=:id")
        ->execute(['id' => $eventRowId]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ppwh_out(405, ['ok' => false, 'error' => 'POST required']);

$db = hivenest_db();
if (!$db) ppwh_out(503, ['ok' => false, 'error' => 'Database unavailable.']);
if (!hivenest_bridge_table_exists($db, 'paypal_webhook_events')) {
    ppwh_out(503, ['ok' => false, 'error' => 'Webhook table missing. Run Database/paypal_myorderbox_bridge.sql.']);
}

$raw = (string)file_get_contents('php://input');
$event = json_decode($raw, true);
if (!is_array($event)) ppwh_out(400, ['ok' => false, 'error' => 'Invalid JSON webhook payload.']);

$headers = [
    'paypal-transmission-id' => ppwh_header('PAYPAL-TRANSMISSION-ID'),
    'paypal-transmission-time' => ppwh_header('PAYPAL-TRANSMISSION-TIME'),
    'paypal-cert-url' => ppwh_header('PAYPAL-CERT-URL'),
    'paypal-auth-algo' => ppwh_header('PAYPAL-AUTH-ALGO'),
    'paypal-transmission-sig' => ppwh_header('PAYPAL-TRANSMISSION-SIG') !== '' ? '[present]' : '',
];

$verification = ppwh_verify_signature($event);
$stored = ppwh_store_event($db, $event, $headers, (string)$verification['status'], $verification['error'] ?? null);

if (!$verification['ok']) {
    // Return 200 after storing the event when the only issue is local config, so
    // PayPal does not hammer the endpoint while the admin fixes PAYPAL_WEBHOOK_ID.
    ppwh_out($verification['status'] === 'skipped' ? 200 : 400, [
        'ok' => false,
        'stored' => true,
        'verification_status' => $verification['status'],
        'error' => $verification['error'],
    ]);
}

ppwh_process_event($db, $stored, $event);
ppwh_out(200, ['ok' => true, 'event_id' => $stored['event_id'], 'duplicate' => !empty($stored['duplicate'])]);
