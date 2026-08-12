<?php
declare(strict_types=1);

/**
 * HiveNest loyalty rules:
 * - Tier 1 starts at USD 0 with no discount.
 * - Every USD 500 of qualifying paid spend adds one tier.
 * - Tier 10 is the maximum.
 * - Each tier above Tier 1 earns another 2% discount.
 *
 * Fully refunded orders are excluded. Partially refunded orders contribute
 * only their net paid amount when verified PayPal refund events are available.
 * This keeps the tier derived from payment records instead of trusting a
 * client-side value or an editable session value.
 */
function hivenest_customer_loyalty(PDO $db, int $customerId, bool $syncCustomer = false): array
{
    $threshold = 500.00;
    $maximumTier = 10;
    $discountPerTier = 2.00;
    $qualifyingOrders = [];
    $qualifyingSpend = 0.00;

    $stmt = $db->prepare("
        SELECT
            o.id,
            o.order_number,
            o.total_amount,
            o.currency,
            o.payment_status,
            o.processed_at,
            o.created_at,
            (
                SELECT pgt.gateway_capture_id
                FROM payment_gateway_transactions pgt
                WHERE pgt.order_id = o.id
                  AND pgt.gateway = 'paypal'
                ORDER BY pgt.id DESC
                LIMIT 1
            ) AS gateway_capture_id
        FROM orders o
        WHERE o.customer_id = :customer_id
          AND o.payment_status IN ('paid','partially_refunded')
          AND UPPER(o.currency) = 'USD'
        ORDER BY COALESCE(o.processed_at, o.created_at) ASC, o.id ASC
    ");
    $stmt->execute(['customer_id' => $customerId]);

    $orderRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $captureIds = array_values(array_unique(array_filter(array_map(
        static fn(array $row): string => trim((string)($row['gateway_capture_id'] ?? '')),
        $orderRows
    ))));
    $refundsByCapture = [];
    if ($captureIds) {
        $placeholders = implode(',', array_fill(0, count($captureIds), '?'));
        $refundStmt = $db->prepare("
            SELECT paypal_capture_id, payload
            FROM paypal_webhook_events
            WHERE paypal_capture_id IN ({$placeholders})
              AND event_type IN ('PAYMENT.CAPTURE.REFUNDED','PAYMENT.CAPTURE.REVERSED')
              AND verification_status = 'success'
        ");
        $refundStmt->execute($captureIds);
        foreach ($refundStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $refundRow) {
            $payload = json_decode((string)($refundRow['payload'] ?? ''), true);
            if (!is_array($payload)) continue;
            $captureId = (string)$refundRow['paypal_capture_id'];
            $refundsByCapture[$captureId] = ($refundsByCapture[$captureId] ?? 0.0)
                + max(0.0, (float)($payload['resource']['amount']['value'] ?? 0));
        }
    }

    foreach ($orderRows as $row) {
        $grossAmount = max(0.0, (float)($row['total_amount'] ?? 0));
        $captureId = trim((string)($row['gateway_capture_id'] ?? ''));
        $refundAmount = $captureId !== '' ? (float)($refundsByCapture[$captureId] ?? 0) : 0.0;
        $amount = max(0.0, $grossAmount - $refundAmount);
        // If an older partial refund has no usable verified event, do not
        // over-credit loyalty spend.
        if (($row['payment_status'] ?? '') === 'partially_refunded' && $refundAmount <= 0) {
            $amount = 0.0;
        }
        $qualifyingSpend += $amount;
        $qualifyingOrders[] = [
            'id' => (int)$row['id'],
            'order_number' => (string)$row['order_number'],
            'amount' => round($amount, 2),
            'currency' => 'USD',
            'refunded_amount' => round($refundAmount, 2),
            'paid_at' => $row['processed_at'] ?: $row['created_at'],
        ];
    }

    $tier = min($maximumTier, 1 + (int)floor(($qualifyingSpend + 0.00001) / $threshold));
    $discount = min(18.00, ($tier - 1) * $discountPerTier);
    $nextTier = $tier < $maximumTier ? $tier + 1 : null;
    $nextThreshold = $tier < $maximumTier ? $tier * $threshold : null;
    $remaining = $nextThreshold !== null ? max(0.0, $nextThreshold - $qualifyingSpend) : 0.0;
    $progressBase = $tier > 1 ? ($tier - 1) * $threshold : 0.0;
    $progress = $tier >= $maximumTier
        ? 100.0
        : min(100.0, max(0.0, (($qualifyingSpend - $progressBase) / $threshold) * 100));

    $history = [];
    $runningSpend = 0.00;
    $previousTier = 1;
    foreach ($qualifyingOrders as $order) {
        $runningSpend += (float)$order['amount'];
        $newTier = min($maximumTier, 1 + (int)floor(($runningSpend + 0.00001) / $threshold));
        if ($newTier > $previousTier) {
            $history[] = [
                'order_number' => $order['order_number'],
                'paid_at' => $order['paid_at'],
                'tier_from' => $previousTier,
                'tier_to' => $newTier,
                'discount_percent' => min(18.00, ($newTier - 1) * $discountPerTier),
                'qualifying_spend' => round($runningSpend, 2),
            ];
            $previousTier = $newTier;
        }
    }

    if ($syncCustomer) {
        $update = $db->prepare("
            UPDATE customers
            SET reseller_discount_percent = :discount
            WHERE id = :customer_id
        ");
        $update->execute([
            'discount' => number_format($discount, 2, '.', ''),
            'customer_id' => $customerId,
        ]);
    }

    return [
        'tier' => $tier,
        'maximum_tier' => $maximumTier,
        'discount_percent' => round($discount, 2),
        'qualifying_spend' => round($qualifyingSpend, 2),
        'currency' => 'USD',
        'threshold_per_tier' => $threshold,
        'next_tier' => $nextTier,
        'next_threshold' => $nextThreshold,
        'amount_remaining' => round($remaining, 2),
        'progress_percent' => round($progress, 2),
        'qualifying_order_count' => count($qualifyingOrders),
        'history' => array_reverse($history),
        'rule' => 'Every USD 500 of paid spend earns the next tier and 2% more discount, up to Tier 10.',
    ];
}
