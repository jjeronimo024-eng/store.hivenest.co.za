<?php
declare(strict_types=1);

function hivenest_promotion_table_exists(PDO $db, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) return $cache[$table];
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name');
        $stmt->execute(['table_name' => $table]);
        return $cache[$table] = (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return $cache[$table] = false;
    }
}

function hivenest_promotion_column_exists(PDO $db, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $stmt = $db->prepare('
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
        ');
        $stmt->execute(['table_name' => $table, 'column_name' => $column]);
        return $cache[$key] = (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return $cache[$key] = false;
    }
}

function hivenest_promotion_json_list($value): array
{
    if (is_array($value)) return array_values($value);
    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? array_values($decoded) : [];
}

function hivenest_promotion_item_tokens(array $item): array
{
    $sku = strtolower((string)($item['sku'] ?? ''));
    $tokens = [
        $sku,
        strtolower((string)($item['product_id'] ?? '')),
    ];
    if (strpos($sku, '--') !== false) $tokens[] = substr($sku, 0, strpos($sku, '--'));
    if (str_starts_with($sku, 'domain-')) $tokens = array_merge($tokens, ['domain', 'domains']);
    if (str_contains($sku, 'hosting')) $tokens[] = 'hosting';
    if (str_contains($sku, 'server')) $tokens = array_merge($tokens, ['server', 'hosting']);
    if (str_contains($sku, 'email') || str_contains($sku, 'mail') || str_contains($sku, 'workspace')) $tokens[] = 'email';
    if (str_contains($sku, 'ssl')) $tokens = array_merge($tokens, ['ssl', 'security']);
    if (str_contains($sku, 'sitelock') || str_contains($sku, 'security')) $tokens[] = 'security';
    if (str_contains($sku, 'backup') || str_contains($sku, 'xcitium')) $tokens[] = 'backup';
    if (str_contains($sku, 'logo') || str_contains($sku, 'design') || str_contains($sku, 'builder')) $tokens[] = 'design';
    if (str_contains($sku, 'seo') || str_contains($sku, 'marketing') || str_contains($sku, 'social')) $tokens[] = 'marketing';
    return array_values(array_unique(array_filter($tokens)));
}

function hivenest_promotion_item_matches(array $item, array $restrictions): bool
{
    if (!$restrictions) return true;
    $restrictions = array_map(static fn($value) => strtolower(trim((string)$value)), $restrictions);
    if (in_array('all', $restrictions, true)) return true;
    return count(array_intersect(hivenest_promotion_item_tokens($item), $restrictions)) > 0;
}

/**
 * Validate a promotion against server-verified cart rows.
 *
 * @return array{valid:bool,error:?string,promotion:?array,discount_amount:float,eligible_subtotal:float}
 */
function hivenest_promotion_quote(PDO $db, int $customerId, array $items, float $subtotal, string $rawCode): array
{
    $code = strtoupper(trim($rawCode));
    $empty = ['valid' => true, 'error' => null, 'promotion' => null, 'discount_amount' => 0.0, 'eligible_subtotal' => 0.0];
    if ($code === '') return $empty;
    if (!preg_match('/^[A-Z0-9_-]{3,50}$/', $code)) {
        return array_merge($empty, ['valid' => false, 'error' => 'Promotion code format is invalid.']);
    }
    if (!hivenest_promotion_table_exists($db, 'promotion_codes')) {
        return array_merge($empty, ['valid' => false, 'error' => 'Promotion codes are not available.']);
    }
    if (!hivenest_promotion_table_exists($db, 'promotion_redemptions')) {
        return array_merge($empty, ['valid' => false, 'error' => 'Promotion redemption storage is not installed.']);
    }

    $stmt = $db->prepare("
        SELECT *
        FROM promotion_codes
        WHERE UPPER(code) = :code
          AND is_active = 1
          AND start_date <= NOW()
          AND end_date > NOW()
        LIMIT 1
    ");
    $stmt->execute(['code' => $code]);
    $promotion = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$promotion) {
        return array_merge($empty, ['valid' => false, 'error' => 'Promotion code is invalid, inactive, or expired.']);
    }
    if ((float)$subtotal < (float)$promotion['minimum_order_amount']) {
        return array_merge($empty, ['valid' => false, 'error' => 'This code requires a minimum USD order of ' . number_format((float)$promotion['minimum_order_amount'], 2) . '.']);
    }
    if ((int)$promotion['usage_limit'] > 0 && (int)$promotion['usage_count'] >= (int)$promotion['usage_limit']) {
        return array_merge($empty, ['valid' => false, 'error' => 'This promotion has reached its usage limit.']);
    }

    if ((int)$promotion['customer_usage_limit'] > 0) {
        $activeRedemptionFilter = hivenest_promotion_column_exists($db, 'promotion_redemptions', 'reversed_at')
            ? ' AND reversed_at IS NULL'
            : '';
        $usage = $db->prepare('SELECT COUNT(*) FROM promotion_redemptions WHERE promotion_code_id = :promotion_id AND customer_id = :customer_id' . $activeRedemptionFilter);
        $usage->execute(['promotion_id' => (int)$promotion['id'], 'customer_id' => $customerId]);
        if ((int)$usage->fetchColumn() >= (int)$promotion['customer_usage_limit']) {
            return array_merge($empty, ['valid' => false, 'error' => 'You have already used this promotion the maximum number of times.']);
        }
    }

    $productRestrictions = hivenest_promotion_json_list($promotion['applicable_products'] ?? null);
    $categoryRestrictions = hivenest_promotion_json_list($promotion['applicable_categories'] ?? null);
    $normalise = static fn(array $values): array => array_values(array_unique(array_filter(array_map(
        static fn($value): string => strtolower(trim((string)$value)),
        $values
    ))));
    $productRestrictions = $normalise($productRestrictions);
    $categoryRestrictions = $normalise($categoryRestrictions);
    $allProducts = !$productRestrictions || in_array('all', $productRestrictions, true);
    $allCategories = !$categoryRestrictions || in_array('all', $categoryRestrictions, true);
    if ($allProducts && $allCategories) {
        $restrictions = ['all'];
    } else {
        $restrictions = array_values(array_unique(array_merge(
            $allProducts ? [] : $productRestrictions,
            $allCategories ? [] : $categoryRestrictions
        )));
    }
    $eligibleSubtotal = 0.0;
    foreach ($items as $item) {
        if (hivenest_promotion_item_matches($item, $restrictions)) {
            $eligibleSubtotal += ((float)$item['unit_price'] + (float)$item['setup_fee']) * (int)$item['quantity'];
        }
    }
    $eligibleSubtotal = round($eligibleSubtotal, 2);
    if ($eligibleSubtotal <= 0) {
        return array_merge($empty, ['valid' => false, 'error' => 'This promotion does not apply to any item in your cart.']);
    }

    $type = (string)$promotion['discount_type'];
    $value = max(0.0, (float)$promotion['discount_value']);
    if ($type === 'percentage') {
        $discount = $eligibleSubtotal * min(100.0, $value) / 100;
    } elseif ($type === 'fixed_amount') {
        $discount = min($eligibleSubtotal, $value);
    } else {
        return array_merge($empty, ['valid' => false, 'error' => 'Free-month promotions require manual review and cannot be applied at PayPal checkout.']);
    }

    return [
        'valid' => true,
        'error' => null,
        'promotion' => [
            'id' => (int)$promotion['id'],
            'code' => (string)$promotion['code'],
            'description' => (string)($promotion['description'] ?? ''),
            'discount_type' => $type,
            'discount_value' => $value,
        ],
        'discount_amount' => round($discount, 2),
        'eligible_subtotal' => $eligibleSubtotal,
    ];
}
