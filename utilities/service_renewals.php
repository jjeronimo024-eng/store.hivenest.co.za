<?php
declare(strict_types=1);

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/customer_loyalty.php';
require_once __DIR__ . '/customer_notifications.php';
require_once __DIR__ . '/currency.php';

function hivenest_renewal_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function hivenest_renewal_table_exists(PDO $db): bool
{
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'service_renewals'
    ");
    $stmt->execute();
    return (int)$stmt->fetchColumn() > 0;
}

function hivenest_renewal_period_months(string $cycle, array $config): int
{
    $configured = (int)($config['term_months'] ?? 0);
    if ($configured > 0) return min(36, $configured);
    return [
        'monthly' => 1,
        'quarterly' => 3,
        'semi_annually' => 6,
        'annually' => 12,
        'biennially' => 24,
        'triennially' => 36,
    ][$cycle] ?? 12;
}

function hivenest_generate_renewal_invoices(int $daysAhead = 30, int $limit = 100): array
{
    $db = hivenest_db();
    if (!$db) return ['ok' => false, 'created' => 0, 'error' => 'Database unavailable.'];
    if (!hivenest_renewal_table_exists($db)) {
        return ['ok' => false, 'created' => 0, 'error' => 'Import Database/service_renewals.sql first.'];
    }
    $daysAhead = max(1, min(90, $daysAhead));
    $limit = max(1, min(500, $limit));
    $stmt = $db->prepare("
        SELECT
            s.id AS service_id,
            s.customer_id,
            s.product_id,
            s.service_name,
            s.domain_name,
            s.service_type,
            s.billing_cycle,
            s.next_due_date,
            s.service_config,
            oi.id AS source_order_item_id,
            oi.product_name,
            oi.quantity,
            oi.unit_price,
            oi.billing_cycle AS source_billing_cycle,
            oi.product_config,
            p.service_type AS product_service_type,
            dr.id AS domain_registration_id,
            dr.extension,
            dr.privacy_protection,
            de.renew_price AS domain_renew_price
        FROM services s
        INNER JOIN products p ON p.id=s.product_id
        LEFT JOIN order_items oi ON oi.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(s.service_config, '$.order_item_id')) AS UNSIGNED)
        LEFT JOIN domain_registrations dr ON dr.service_id=s.id
        LEFT JOIN domain_extensions de ON de.extension=dr.extension AND de.is_active=1
        WHERE s.auto_renew=1
          AND s.service_status IN ('active','pending')
          AND s.next_due_date IS NOT NULL
          AND s.next_due_date <= DATE_ADD(CURRENT_DATE(), INTERVAL {$daysAhead} DAY)
          AND p.service_type='recurring'
          AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(s.service_config, '$.sku')), '') <> 'domain-privacy'
          AND NOT EXISTS (
              SELECT 1
              FROM service_renewals sr
              WHERE sr.service_id=s.id
                AND sr.renewal_due_date=DATE(s.next_due_date)
          )
        ORDER BY s.next_due_date ASC, s.id ASC
        LIMIT {$limit}
    ");
    $stmt->execute();
    $created = [];
    $skipped = [];
    foreach ($stmt->fetchAll() ?: [] as $service) {
        $serviceConfig = json_decode((string)($service['service_config'] ?? ''), true);
        $serviceConfig = is_array($serviceConfig) ? $serviceConfig : [];
        $productConfig = json_decode((string)($service['product_config'] ?? ''), true);
        $productConfig = is_array($productConfig) ? $productConfig : [];
        $config = array_merge($productConfig, $serviceConfig);
        $quantity = max(1, (int)($service['quantity'] ?? 1));
        $unitPrice = (float)($service['unit_price'] ?? 0);
        if ($service['service_type'] === 'domain' && (float)($service['domain_renew_price'] ?? 0) > 0) {
            $quantity = 1;
            $unitPrice = (float)$service['domain_renew_price'];
        }
        if ($unitPrice <= 0 || (int)$service['source_order_item_id'] <= 0) {
            $skipped[] = ['service_id' => (int)$service['service_id'], 'reason' => 'No valid renewal price/source item.'];
            continue;
        }
        $periodMonths = hivenest_renewal_period_months(
            (string)($service['source_billing_cycle'] ?: $service['billing_cycle']),
            $config
        );
        $subtotal = round($unitPrice * $quantity, 2);
        $privacyPrice = 0.0;
        if ($service['service_type'] === 'domain' && (int)($service['privacy_protection'] ?? 0) === 1) {
            $privacyStmt = $db->prepare("
                SELECT oi.unit_price
                FROM order_items oi
                WHERE oi.domain_name=:domain_name
                  AND JSON_UNQUOTE(JSON_EXTRACT(oi.product_config, '$.sku'))='domain-privacy'
                ORDER BY oi.id DESC
                LIMIT 1
            ");
            $privacyStmt->execute(['domain_name' => (string)$service['domain_name']]);
            $privacyPrice = max(0.0, (float)$privacyStmt->fetchColumn());
            $subtotal = round($subtotal + $privacyPrice, 2);
        }
        try {
            $loyalty = hivenest_customer_loyalty($db, (int)$service['customer_id'], false);
            $discount = round($subtotal * max(0.0, min(18.0, (float)($loyalty['discount_percent'] ?? 0))) / 100, 2);
        } catch (Throwable $e) {
            $discount = 0.0;
        }
        $total = max(0.01, round($subtotal - $discount, 2));
        $dueDate = substr((string)$service['next_due_date'], 0, 10);
        $orderNumber = 'RN-' . gmdate('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
        $currencySnapshot = hivenest_currency_order_snapshot($db, (int)$service['customer_id'], [
            'subtotal' => $subtotal,
            'tax_amount' => 0,
            'discount_amount' => $discount,
            'total_amount' => $total,
        ]);
        try {
            $db->beginTransaction();
            $orderStmt = $db->prepare("
                INSERT INTO orders
                    (uuid, customer_id, order_number, order_status, payment_status,
                     subtotal, tax_amount, discount_amount, total_amount, currency,
                     display_currency, display_exchange_rate, display_subtotal,
                     display_tax_amount, display_discount_amount, display_total_amount,
                     display_rate_source, display_rate_captured_at,
                     payment_method, provisioning_status, order_notes)
                VALUES
                    (:uuid, :customer_id, :order_number, 'pending', 'pending',
                     :subtotal, 0, :discount, :total, 'USD',
                     :display_currency, :display_exchange_rate, :display_subtotal,
                     :display_tax_amount, :display_discount_amount, :display_total_amount,
                     :display_rate_source, :display_rate_captured_at,
                     'paypal', 'awaiting_payment', :order_notes)
            ");
            $orderStmt->execute([
                'uuid' => hivenest_renewal_uuid(),
                'customer_id' => (int)$service['customer_id'],
                'order_number' => $orderNumber,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'display_currency' => $currencySnapshot['display_currency'],
                'display_exchange_rate' => $currencySnapshot['display_exchange_rate'],
                'display_subtotal' => $currencySnapshot['display_subtotal'],
                'display_tax_amount' => $currencySnapshot['display_tax_amount'],
                'display_discount_amount' => $currencySnapshot['display_discount_amount'],
                'display_total_amount' => $currencySnapshot['display_total_amount'],
                'display_rate_source' => $currencySnapshot['display_rate_source'],
                'display_rate_captured_at' => $currencySnapshot['display_rate_captured_at'],
                'order_notes' => 'Renewal invoice for service #' . (int)$service['service_id'] . ' due ' . $dueDate,
            ]);
            $orderId = (int)$db->lastInsertId();
            $renewalConfig = array_filter([
                'sku' => (string)($config['sku'] ?? ''),
                'renewal_service_id' => (int)$service['service_id'],
                'renewal_due_date' => $dueDate,
                'renewal_period_months' => $periodMonths,
                'renewal_type' => (string)$service['service_type'],
                'original_order_item_id' => (int)$service['source_order_item_id'],
                'domain_action' => $service['service_type'] === 'domain' ? 'domain_extend' : null,
                'years' => $service['service_type'] === 'domain' ? max(1, (int)ceil($periodMonths / 12)) : null,
            ], static fn($value) => $value !== null && $value !== '');
            $itemStmt = $db->prepare("
                INSERT INTO order_items
                    (uuid, order_id, service_id, product_id, product_name, domain_name,
                     quantity, unit_price, setup_fee, billing_cycle, line_total,
                     product_config, provisioning_status)
                VALUES
                    (:uuid, :order_id, :service_id, :product_id, :product_name, :domain_name,
                     :quantity, :unit_price, 0, :billing_cycle, :line_total,
                     :product_config, 'awaiting_payment')
            ");
            $itemStmt->execute([
                'uuid' => hivenest_renewal_uuid(),
                'order_id' => $orderId,
                'service_id' => (int)$service['service_id'],
                'product_id' => (int)$service['product_id'],
                'product_name' => 'Renewal: ' . (string)$service['service_name'],
                'domain_name' => $service['domain_name'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'billing_cycle' => (string)$service['billing_cycle'],
                'line_total' => round($unitPrice * $quantity, 2),
                'product_config' => json_encode($renewalConfig, JSON_UNESCAPED_SLASHES),
            ]);
            if ($privacyPrice > 0) {
                $itemStmt->execute([
                    'uuid' => hivenest_renewal_uuid(),
                    'order_id' => $orderId,
                    'service_id' => (int)$service['service_id'],
                    'product_id' => (int)$service['product_id'],
                    'product_name' => 'Renewal add-on: Domain Privacy',
                    'domain_name' => $service['domain_name'],
                    'quantity' => 1,
                    'unit_price' => $privacyPrice,
                    'billing_cycle' => 'annually',
                    'line_total' => $privacyPrice,
                    'product_config' => json_encode([
                        'sku' => 'domain-privacy',
                        'renewal_service_id' => (int)$service['service_id'],
                        'renewal_due_date' => $dueDate,
                        'renewal_child_addon' => true,
                    ], JSON_UNESCAPED_SLASHES),
                ]);
            }
            $ledger = $db->prepare("
                INSERT INTO service_renewals
                    (uuid, service_id, customer_id, renewal_order_id, renewal_due_date, period_months, status)
                VALUES
                    (:uuid, :service_id, :customer_id, :renewal_order_id, :renewal_due_date, :period_months, 'invoice_created')
            ");
            $ledger->execute([
                'uuid' => hivenest_renewal_uuid(),
                'service_id' => (int)$service['service_id'],
                'customer_id' => (int)$service['customer_id'],
                'renewal_order_id' => $orderId,
                'renewal_due_date' => $dueDate,
                'period_months' => $periodMonths,
            ]);
            $db->commit();
            try {
                hivenest_customer_notifications_ensure($db);
                hivenest_notify_customer_once(
                    $db,
                    (int)$service['customer_id'],
                    'warning',
                    'Renewal invoice ready',
                    $orderNumber . ' · USD ' . number_format($total, 2) . ' · due ' . $dueDate,
                    '/billing/index.html',
                    'renewal_invoice',
                    $orderId
                );
            } catch (Throwable $ignored) {
            }
            $created[] = [
                'service_id' => (int)$service['service_id'],
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'due_date' => $dueDate,
                'total' => $total,
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ((string)$e->getCode() !== '23000') {
                $skipped[] = ['service_id' => (int)$service['service_id'], 'reason' => $e->getMessage()];
            }
        }
    }
    return ['ok' => true, 'created' => count($created), 'invoices' => $created, 'skipped' => $skipped];
}
