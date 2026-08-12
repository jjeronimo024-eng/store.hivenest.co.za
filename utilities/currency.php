<?php
declare(strict_types=1);

/**
 * Storefront currency display support.
 *
 * All catalogue, order and PayPal values remain USD. These rates are used only
 * to show an indicative amount in the customer's preferred display currency.
 */

function hivenest_currency_codes(): array
{
    return ['USD', 'ZAR', 'EUR', 'SGD'];
}

function hivenest_currency_default_rates(): array
{
    return [
        'USD' => 1.0,
        'ZAR' => 18.0,
        'EUR' => 0.92,
        'SGD' => 1.35,
    ];
}

function hivenest_currency_rates(?PDO $db = null): array
{
    $rates = hivenest_currency_default_rates();
    if (!$db) return $rates;

    try {
        $keys = [
            'display_rate_zar_per_usd' => 'ZAR',
            'display_rate_eur_per_usd' => 'EUR',
            'display_rate_sgd_per_usd' => 'SGD',
        ];
        $quoted = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $db->prepare(
            "SELECT setting_key, setting_value
             FROM system_settings
             WHERE setting_key IN ({$quoted})"
        );
        $stmt->execute(array_keys($keys));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $value = (float)($row['setting_value'] ?? 0);
            if ($value > 0 && $value < 100000) {
                $rates[$keys[(string)$row['setting_key']]] = $value;
            }
        }
    } catch (Throwable $e) {
        error_log('Currency display settings lookup failed: ' . $e->getMessage());
    }

    return $rates;
}

function hivenest_currency_preference(?PDO $db = null, int $customerId = 0): string
{
    $allowed = hivenest_currency_codes();
    $sessionCurrency = strtoupper((string)($_SESSION['display_currency'] ?? ''));
    if (in_array($sessionCurrency, $allowed, true)) return $sessionCurrency;

    if ($db && $customerId > 0) {
        try {
            $stmt = $db->prepare('SELECT preferred_currency FROM customers WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $customerId]);
            $saved = strtoupper((string)$stmt->fetchColumn());
            if (in_array($saved, $allowed, true)) return $saved;
        } catch (Throwable $e) {
            error_log('Currency customer preference lookup failed: ' . $e->getMessage());
        }
    }

    return 'USD';
}

/**
 * Capture an immutable display-currency view of legal USD order amounts.
 * The returned values are stored with the order and must never be recalculated.
 */
function hivenest_currency_order_snapshot(PDO $db, int $customerId, array $usdAmounts): array
{
    $currency = hivenest_currency_preference($db, $customerId);
    $rates = hivenest_currency_rates($db);
    $rate = (float)($rates[$currency] ?? 1.0);
    if ($rate <= 0 || !is_finite($rate)) {
        $currency = 'USD';
        $rate = 1.0;
    }
    $convert = static fn(string $key): float => round(max(0.0, (float)($usdAmounts[$key] ?? 0)) * $rate, 2);
    return [
        'display_currency' => $currency,
        'display_exchange_rate' => round($rate, 8),
        'display_subtotal' => $convert('subtotal'),
        'display_tax_amount' => $convert('tax_amount'),
        'display_discount_amount' => $convert('discount_amount'),
        'display_total_amount' => $convert('total_amount'),
        'display_rate_source' => $currency === 'USD' ? 'usd_base' : 'system_settings',
        'display_rate_captured_at' => gmdate('Y-m-d H:i:s'),
    ];
}
