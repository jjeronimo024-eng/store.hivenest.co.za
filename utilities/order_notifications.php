<?php
declare(strict_types=1);
require_once __DIR__ . '/mail_delivery.php';

require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/customer_notifications.php';

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

function hivenest_order_base_url(): string
{
    $envPath = defined('HIVENEST_ENV_PATH') ? HIVENEST_ENV_PATH : __DIR__ . '/../Backend/.env';
    $base = '';
    if (is_readable($envPath)) {
        $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            if (in_array($key, ['SITE_URL', 'APP_URL', 'HIVENEST_SITE_URL'], true)) {
                $base = trim(trim($value), "\"'");
                break;
            }
        }
    }
    return rtrim($base !== '' ? $base : 'https://hivenest.holohive.co.za', '/');
}

function hivenest_fetch_order_for_email(PDO $db, string $orderNumber): ?array
{
    $orderStmt = $db->prepare("
        SELECT
            o.*,
            c.email,
            c.first_name,
            c.last_name,
            c.company_name,
            c.phone,
            c.address_line1,
            c.address_line2,
            c.city,
            c.state,
            c.postal_code,
            c.country
        FROM orders o
        INNER JOIN customers c ON c.id = o.customer_id
        WHERE o.order_number = :order_number
        LIMIT 1
    ");
    $orderStmt->execute(['order_number' => $orderNumber]);
    $order = $orderStmt->fetch();
    if (!$order) return null;

    $itemStmt = $db->prepare("
        SELECT
            oi.*,
            p.product_type,
            p.slug AS product_slug,
            p.name AS catalogue_product_name
        FROM order_items oi
        LEFT JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id = :order_id
        ORDER BY oi.id ASC
    ");
    $itemStmt->execute(['order_id' => (int)$order['id']]);
    $order['items'] = $itemStmt->fetchAll() ?: [];
    $order['promotion_code'] = '';
    $order['promotion_discount_amount'] = 0.0;
    try {
        $promotionStmt = $db->prepare("
            SELECT code, discount_amount
            FROM promotion_redemptions
            WHERE order_id = :order_id
            LIMIT 1
        ");
        $promotionStmt->execute(['order_id' => (int)$order['id']]);
        $promotion = $promotionStmt->fetch(PDO::FETCH_ASSOC);
        if ($promotion) {
            $order['promotion_code'] = (string)$promotion['code'];
            $order['promotion_discount_amount'] = max(0.0, (float)$promotion['discount_amount']);
        }
    } catch (Throwable $e) {
        // Older installations without promotion_redemptions still render invoices.
    }
    $order['loyalty_discount_amount'] = max(
        0.0,
        round((float)$order['discount_amount'] - (float)$order['promotion_discount_amount'], 2)
    );
    return $order;
}

function hivenest_customer_display_name(array $order): string
{
    $name = trim((string)($order['first_name'] ?? '') . ' ' . (string)($order['last_name'] ?? ''));
    if ($name === '') $name = (string)($order['email'] ?? 'Client');
    $company = trim((string)($order['company_name'] ?? ''));
    return $company !== '' ? $name . ' (' . $company . ')' : $name;
}

function hivenest_money(float $amount, string $currency = 'USD'): string
{
    return $currency . ' ' . number_format($amount, 2, '.', '');
}

function hivenest_html($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function hivenest_order_item_bundle_items(array $item): array
{
    $config = json_decode((string)($item['product_config'] ?? ''), true);
    if (!is_array($config)) return [];
    $bundleItems = $config['bundle_items'] ?? [];
    if (is_string($bundleItems) && trim($bundleItems) !== '') {
        $decoded = json_decode($bundleItems, true);
        $bundleItems = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($bundleItems)) return [];

    $clean = [];
    foreach ($bundleItems as $bundleItem) {
        if (!is_array($bundleItem)) continue;
        $name = trim((string)($bundleItem['name'] ?? $bundleItem['sku'] ?? ''));
        if ($name === '') continue;
        $quantity = max(1, (int)($bundleItem['quantity'] ?? 1));
        $domain = trim((string)($bundleItem['domain_name'] ?? $bundleItem['domain'] ?? $bundleItem['primary_domain'] ?? $item['domain_name'] ?? ''));
        $termMonths = trim((string)($bundleItem['term_months'] ?? $config['term_months'] ?? ''));
        $clean[] = [
            'name' => $name,
            'sku' => trim((string)($bundleItem['sku'] ?? '')),
            'quantity' => $quantity,
            'domain' => $domain,
            'term_months' => $termMonths,
        ];
    }
    return $clean;
}

function hivenest_order_item_bundle_text_lines(array $item): array
{
    $lines = [];
    foreach (hivenest_order_item_bundle_items($item) as $bundleItem) {
        $line = '  Includes: ' . $bundleItem['name'];
        if ((int)$bundleItem['quantity'] > 1) $line .= ' x' . (int)$bundleItem['quantity'];
        if ($bundleItem['domain'] !== '') $line .= ' — ' . $bundleItem['domain'];
        if ($bundleItem['term_months'] !== '') $line .= ' — ' . $bundleItem['term_months'] . ' month' . ($bundleItem['term_months'] === '1' ? '' : 's');
        $lines[] = $line;
    }
    return $lines;
}

function hivenest_order_invoice_text(array $order): string
{
    $currency = (string)($order['currency'] ?? 'USD');
    $lines = [];
    $lines[] = 'HIVENEST PAID INVOICE';
    $lines[] = '==============================================';
    $lines[] = 'Invoice / Order: ' . $order['order_number'];
    $lines[] = 'Payment Status: ' . strtoupper((string)$order['payment_status']);
    $lines[] = 'Order Status: ' . strtoupper((string)$order['order_status']);
    $lines[] = 'Payment Method: ' . strtoupper((string)$order['payment_method']);
    $lines[] = 'Payment Reference: ' . (string)($order['payment_reference'] ?? '');
    $lines[] = 'Date Paid: ' . (string)($order['processed_at'] ?? $order['created_at'] ?? '');
    $lines[] = '';
    $lines[] = 'Bill To:';
    $lines[] = hivenest_customer_display_name($order);
    $lines[] = (string)($order['email'] ?? '');
    foreach (['address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country'] as $field) {
        if (!empty($order[$field])) $lines[] = (string)$order[$field];
    }
    $lines[] = '';
    $lines[] = 'Items:';
    foreach ($order['items'] as $item) {
        $lines[] = '- ' . $item['product_name'] . ' x' . (int)$item['quantity'] . ' = ' . hivenest_money((float)$item['line_total'], $currency);
        if (!empty($item['domain_name'])) $lines[] = '  Domain: ' . $item['domain_name'];
        $lines[] = '  Billing Cycle: ' . $item['billing_cycle'];
        foreach (hivenest_order_item_bundle_text_lines($item) as $bundleLine) {
            $lines[] = $bundleLine;
        }
    }
    $lines[] = '';
    $lines[] = 'Subtotal: ' . hivenest_money((float)$order['subtotal'], $currency);
    $lines[] = 'Tax: ' . hivenest_money((float)$order['tax_amount'], $currency);
    $lines[] = 'Loyalty Discount: ' . hivenest_money((float)($order['loyalty_discount_amount'] ?? $order['discount_amount']), $currency);
    if (!empty($order['promotion_code']) || (float)($order['promotion_discount_amount'] ?? 0) > 0) {
        $lines[] = 'Promotion (' . (string)$order['promotion_code'] . '): '
            . hivenest_money((float)$order['promotion_discount_amount'], $currency);
    }
    $lines[] = 'Total Paid: ' . hivenest_money((float)$order['total_amount'], $currency);
    $lines[] = '==============================================';
    $lines[] = 'Thank you for choosing HiveNest.';
    return implode("\n", $lines);
}

function hivenest_mail_with_invoice(string $to, string $subject, string $textBody, array $order): bool
{
    $boundary = 'HN-' . bin2hex(random_bytes(12));
    $htmlBody = hivenest_paid_order_email_html($order, $textBody);
    $invoice = hivenest_invoice_pdf($order);
    $filename = 'paid-invoice-' . preg_replace('/[^A-Za-z0-9-]/', '-', (string)$order['order_number']) . '.pdf';

    $headers = [
        'From: HiveNest Orders <orders@hivenest.co.za>',
        'Reply-To: HiveNest Support <support@hivenest.co.za>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
    ];

    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $htmlBody . "\r\n\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= 'Content-Type: application/pdf; name="' . $filename . '"' . "\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n";
    $body .= 'Content-Disposition: attachment; filename="' . $filename . '"' . "\r\n\r\n";
    $body .= chunk_split(base64_encode($invoice)) . "\r\n";
    $body .= "--{$boundary}--\r\n";

    return hivenest_mail_send(
        $to,
        $subject,
        $body,
        implode("\r\n", $headers),
        'paid-order:' . (string)$order['order_number']
    );
}

function hivenest_pdf_text(string $value): string
{
    $value = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $value) ?? '';
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
}

function hivenest_invoice_pdf(array $order): string
{
    $currency = (string)($order['currency'] ?? 'USD');
    $pages = [];
    $commands = [];
    $pageNumber = 0;
    $left = 42;
    $right = 570;
    $bottom = 70;
    $tableHeaderY = 0;

    $billLines = array_values(array_filter([
        hivenest_customer_display_name($order),
        $order['email'] ?? '',
        $order['address_line1'] ?? '',
        $order['address_line2'] ?? '',
        trim((string)($order['city'] ?? '') . ' ' . (string)($order['state'] ?? '') . ' ' . (string)($order['postal_code'] ?? '')),
        $order['country'] ?? '',
    ]));

    $text = static function (string $font, int $size, float $r, float $g, float $b, int $x, int $y, string $value): string {
        return "{$r} {$g} {$b} rg /{$font} {$size} Tf {$x} {$y} Td (" . hivenest_pdf_text($value) . ") Tj";
    };
    $rect = static function (float $r, float $g, float $b, int $x, int $y, int $w, int $h): string {
        return "{$r} {$g} {$b} rg {$x} {$y} {$w} {$h} re f";
    };
    $finishPage = static function () use (&$pages, &$commands): void {
        if ($commands) {
            $pages[] = $commands;
            $commands = [];
        }
    };
    $startPage = function (bool $firstPage = false) use (&$commands, &$pageNumber, &$tableHeaderY, $text, $rect, $left, $right, $billLines, $order): int {
        $pageNumber++;
        $commands[] = $rect(0.98, 0.99, 1, 0, 0, 612, 792);
        $commands[] = $rect(0.00, 0.66, 0.66, 42, 704, 528, 3);
        $commands[] = $text('F2', 24, 0, 0, 0, 42, 735, 'HIVENEST PAID INVOICE');
        $commands[] = $text('F1', 10, 0.35, 0.42, 0.46, 42, 716, 'Order #' . (string)$order['order_number']);
        $commands[] = $text('F1', 10, 0.35, 0.42, 0.46, 42, 700, 'Paid: ' . (string)($order['processed_at'] ?: $order['created_at']));
        $commands[] = $rect(0.90, 0.98, 0.90, 455, 722, 72, 26);
        $commands[] = $text('F2', 11, 0, 0.50, 0, 477, 731, 'PAID');
        $commands[] = $text('F1', 9, 0.35, 0.42, 0.46, 42, 680, 'Reference: ' . (string)$order['payment_reference']);

        if ($firstPage) {
            $commands[] = $text('F2', 14, 0, 0.66, 0.66, 42, 640, 'BILLED TO');
            $lineY = 622;
            foreach ($billLines as $line) {
                $commands[] = $text('F1', 10, 0, 0, 0, 42, $lineY, (string)$line);
                $lineY -= 14;
            }
            $commands[] = $text('F2', 14, 0, 0.66, 0.66, 330, 640, 'HIVENEST');
            foreach (['HiveNest Matrix', 'orders@hivenest.co.za', 'support@hivenest.co.za'] as $index => $line) {
                $commands[] = $text('F1', 10, 0, 0, 0, 330, 622 - ($index * 14), $line);
            }
            $tableHeaderY = 520;
        } else {
            $commands[] = $text('F1', 9, 0.35, 0.42, 0.46, 42, 674, 'Continued invoice items');
            $tableHeaderY = 642;
        }

        $commands[] = $rect(0.00, 0.66, 0.66, $left, $tableHeaderY, $right - $left, 2);
        $commands[] = $text('F2', 9, 0, 0, 0, 42, $tableHeaderY + 12, 'Item');
        $commands[] = $text('F2', 9, 0, 0, 0, 290, $tableHeaderY + 12, 'Cycle');
        $commands[] = $text('F2', 9, 0, 0, 0, 370, $tableHeaderY + 12, 'Qty');
        $commands[] = $text('F2', 9, 0, 0, 0, 435, $tableHeaderY + 12, 'Unit');
        $commands[] = $text('F2', 9, 0, 0, 0, 510, $tableHeaderY + 12, 'Total');
        $commands[] = $text('F1', 8, 0.45, 0.45, 0.45, 500, 32, 'Page ' . $pageNumber);

        return $tableHeaderY - 22;
    };

    $y = $startPage(true);
    foreach ($order['items'] as $item) {
        $bundleLines = hivenest_order_item_bundle_text_lines($item);
        $rowHeight = (!empty($item['domain_name']) ? 30 : 18) + (count($bundleLines) * 10);
        if ($y - $rowHeight < $bottom) {
            $finishPage();
            $y = $startPage(false);
        }
        $label = substr((string)$item['product_name'], 0, 38);
        $unit = hivenest_money((float)$item['unit_price'] + (float)$item['setup_fee'], $currency);
        $total = hivenest_money((float)$item['line_total'], $currency);
        $commands[] = $text('F1', 9, 0, 0, 0, 42, $y, $label);
        $commands[] = $text('F1', 9, 0, 0, 0, 290, $y, (string)$item['billing_cycle']);
        $commands[] = $text('F1', 9, 0, 0, 0, 376, $y, (string)(int)$item['quantity']);
        $commands[] = $text('F1', 9, 0, 0, 0, 420, $y, $unit);
        $commands[] = $text('F1', 9, 0, 0, 0, 492, $y, $total);
        if (!empty($item['domain_name'])) {
            $y -= 12;
            $commands[] = $text('F1', 8, 0.35, 0.42, 0.46, 58, $y, 'Domain: ' . (string)$item['domain_name']);
        }
        foreach ($bundleLines as $bundleLine) {
            $y -= 10;
            $commands[] = $text('F1', 7, 0.00, 0.45, 0.45, 58, $y, substr($bundleLine, 0, 82));
        }
        $y -= 18;
    }

    if ($y < 185) {
        $finishPage();
        $y = $startPage(false);
    }
    $totalY = max(88, $y - 18);
    $commands[] = $rect(0.94, 0.98, 0.98, 350, $totalY - 16, 194, 132);
    $commands[] = $text('F1', 10, 0.35, 0.42, 0.46, 368, $totalY + 88, 'Subtotal');
    $commands[] = $text('F1', 10, 0, 0, 0, 465, $totalY + 88, hivenest_money((float)$order['subtotal'], $currency));
    $commands[] = $text('F1', 10, 0.35, 0.42, 0.46, 368, $totalY + 70, 'Tax');
    $commands[] = $text('F1', 10, 0, 0, 0, 465, $totalY + 70, hivenest_money((float)$order['tax_amount'], $currency));
    $commands[] = $text('F1', 9, 0.35, 0.42, 0.46, 368, $totalY + 52, 'Loyalty Discount');
    $commands[] = $text('F1', 10, 0, 0, 0, 465, $totalY + 52, hivenest_money((float)($order['loyalty_discount_amount'] ?? $order['discount_amount']), $currency));
    $promotionLabel = !empty($order['promotion_code'])
        ? 'Promotion (' . substr((string)$order['promotion_code'], 0, 14) . ')'
        : 'Promotion';
    $commands[] = $text('F1', 9, 0.35, 0.42, 0.46, 368, $totalY + 34, $promotionLabel);
    $commands[] = $text('F1', 10, 0, 0, 0, 465, $totalY + 34, hivenest_money((float)($order['promotion_discount_amount'] ?? 0), $currency));
    $commands[] = $text('F2', 13, 0.75, 0, 0.75, 392, $totalY - 8, 'Total Paid');
    $commands[] = $text('F2', 13, 0.75, 0, 0.75, 465, $totalY - 8, hivenest_money((float)$order['total_amount'], $currency));
    $finishPage();

    $pageObjects = [];
    $contentObjects = [];
    $pageCount = count($pages);
    foreach ($pages as $index => $pageCommands) {
        $stream = implode("\n", array_map(static function (string $command): string {
            return str_contains($command, ' Tf ') ? 'BT ' . $command . ' ET' : $command;
        }, $pageCommands)) . "\n";
        $pageObjectNumber = 5 + ($index * 2);
        $contentObjectNumber = $pageObjectNumber + 1;
        $pageObjects[] = "{$pageObjectNumber} 0 R";
        $contentObjects[] = [
            'page' => "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$contentObjectNumber} 0 R >>",
            'content' => "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}endstream",
        ];
    }

    $objects = [
        "<< /Type /Catalog /Pages 2 0 R >>",
        "<< /Type /Pages /Kids [" . implode(' ', $pageObjects) . "] /Count {$pageCount} >>",
        "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
        "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>",
    ];
    foreach ($contentObjects as $pagePair) {
        $objects[] = $pagePair['page'];
        $objects[] = $pagePair['content'];
    }
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $number = $index + 1;
        $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    return $pdf . "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
}

function hivenest_invoice_email_html(array $order): string
{
    $currency = (string)($order['currency'] ?? 'USD');
    $rows = '';
    foreach ($order['items'] as $item) {
        $bundleHtml = '';
        $bundleItems = hivenest_order_item_bundle_items($item);
        if ($bundleItems) {
            $bundleHtml .= '<div style="margin-top:8px;padding:8px 10px;background:#eefcff;border-left:3px solid #00a9a9;color:#334;">'
                . '<div style="font-size:11px;font-weight:bold;color:#00a9a9;letter-spacing:1px;margin-bottom:5px;">INCLUDES</div>';
            foreach ($bundleItems as $bundleItem) {
                $line = $bundleItem['name'];
                if ((int)$bundleItem['quantity'] > 1) $line .= ' x' . (int)$bundleItem['quantity'];
                if ($bundleItem['domain'] !== '') $line .= ' · ' . $bundleItem['domain'];
                if ($bundleItem['term_months'] !== '') $line .= ' · ' . $bundleItem['term_months'] . ' month' . ($bundleItem['term_months'] === '1' ? '' : 's');
                $bundleHtml .= '<div style="font-size:12px;line-height:1.5;">✓ ' . hivenest_html($line) . '</div>';
            }
            $bundleHtml .= '</div>';
        }
        $rows .= '<tr>'
            . '<td style="padding:12px;border-bottom:1px solid #d8e3e8;">' . hivenest_html($item['product_name']) . $bundleHtml . '</td>'
            . '<td style="padding:12px;border-bottom:1px solid #d8e3e8;">' . hivenest_html($item['domain_name'] ?: '-') . '</td>'
            . '<td style="padding:12px;border-bottom:1px solid #d8e3e8;">' . hivenest_html($item['billing_cycle']) . '</td>'
            . '<td style="padding:12px;border-bottom:1px solid #d8e3e8;text-align:center;">' . (int)$item['quantity'] . '</td>'
            . '<td style="padding:12px;border-bottom:1px solid #d8e3e8;text-align:right;">' . hivenest_html(hivenest_money((float)$item['unit_price'] + (float)$item['setup_fee'], $currency)) . '</td>'
            . '<td style="padding:12px;border-bottom:1px solid #d8e3e8;text-align:right;">' . hivenest_html(hivenest_money((float)$item['line_total'], $currency)) . '</td>'
            . '</tr>';
    }

    $address = array_filter([
        hivenest_customer_display_name($order),
        $order['email'] ?? '',
        $order['address_line1'] ?? '',
        $order['address_line2'] ?? '',
        trim((string)($order['city'] ?? '') . ' ' . (string)($order['state'] ?? '') . ' ' . (string)($order['postal_code'] ?? '')),
        $order['country'] ?? '',
    ]);
    $promotionLine = '';
    if (!empty($order['promotion_code']) || (float)($order['promotion_discount_amount'] ?? 0) > 0) {
        $promotionLine = '<p style="display:flex;justify-content:space-between;margin:8px 0;"><span>Promotion ('
            . hivenest_html((string)$order['promotion_code'])
            . ')</span><span>'
            . hivenest_html(hivenest_money((float)$order['promotion_discount_amount'], $currency))
            . '</span></p>';
    }

    return '<!doctype html><html><body style="margin:0;background:#f5f7fb;color:#111;font-family:Arial,Helvetica,sans-serif;">'
        . '<div style="max-width:900px;margin:0 auto;padding:34px;background:#fff;">'
        . '<h1 style="margin:0 0 8px;font-size:34px;letter-spacing:1px;color:#111;">HIVENEST PAID INVOICE</h1>'
        . '<p style="margin:0;color:#65717a;">Order #' . hivenest_html($order['order_number']) . '</p>'
        . '<p style="margin:8px 0 0;color:#65717a;">Paid: ' . hivenest_html($order['processed_at'] ?: $order['created_at']) . '</p>'
        . '<div style="margin:22px 0;display:inline-block;padding:9px 18px;border:1px solid #008000;border-radius:999px;color:#008000;background:#eaf8ea;font-weight:bold;letter-spacing:1px;">PAID</div>'
        . '<p style="margin:0 0 26px;color:#65717a;">Reference: ' . hivenest_html($order['payment_reference']) . '</p>'
        . '<div style="height:1px;background:#00a9a9;margin:0 0 24px;"></div>'
        . '<table role="presentation" style="width:100%;border-collapse:collapse;margin-bottom:28px;"><tr>'
        . '<td style="vertical-align:top;width:50%;"><h2 style="margin:0 0 10px;color:#00a9a9;font-size:22px;">BILLED TO</h2><p style="line-height:1.7;margin:0;">' . hivenest_html(implode("\n", $address)) . '</p></td>'
        . '<td style="vertical-align:top;width:50%;"><h2 style="margin:0 0 10px;color:#00a9a9;font-size:22px;">HIVENEST</h2><p style="line-height:1.7;margin:0;">HiveNest Matrix<br>orders@hivenest.co.za<br>support@hivenest.co.za</p></td>'
        . '</tr></table>'
        . '<table style="width:100%;border-collapse:collapse;margin-top:16px;">'
        . '<thead><tr>'
        . '<th style="padding:12px;border-bottom:2px solid #00a9a9;text-align:left;">Item</th>'
        . '<th style="padding:12px;border-bottom:2px solid #00a9a9;text-align:left;">Domain</th>'
        . '<th style="padding:12px;border-bottom:2px solid #00a9a9;text-align:left;">Cycle</th>'
        . '<th style="padding:12px;border-bottom:2px solid #00a9a9;text-align:center;">Qty</th>'
        . '<th style="padding:12px;border-bottom:2px solid #00a9a9;text-align:right;">Unit</th>'
        . '<th style="padding:12px;border-bottom:2px solid #00a9a9;text-align:right;">Total</th>'
        . '</tr></thead><tbody>' . $rows . '</tbody></table>'
        . '<div style="max-width:340px;margin:26px 0 0 auto;font-size:15px;">'
        . '<p style="display:flex;justify-content:space-between;margin:8px 0;"><span>Subtotal</span><span>' . hivenest_html(hivenest_money((float)$order['subtotal'], $currency)) . '</span></p>'
        . '<p style="display:flex;justify-content:space-between;margin:8px 0;"><span>Tax</span><span>' . hivenest_html(hivenest_money((float)$order['tax_amount'], $currency)) . '</span></p>'
        . '<p style="display:flex;justify-content:space-between;margin:8px 0;"><span>Loyalty Discount</span><span>' . hivenest_html(hivenest_money((float)($order['loyalty_discount_amount'] ?? $order['discount_amount']), $currency)) . '</span></p>'
        . $promotionLine
        . '<p style="display:flex;justify-content:space-between;margin:14px 0 0;padding-top:12px;border-top:1px solid #c8d3d8;color:#c000c0;font-size:19px;font-weight:bold;"><span>Total Paid</span><span>' . hivenest_html(hivenest_money((float)$order['total_amount'], $currency)) . '</span></p>'
        . '</div>'
        . '</div></body></html>';
}

function hivenest_paid_order_email_html(array $order, string $fallbackText): string
{
    $baseUrl = hivenest_order_base_url();
    $invoiceUrl = $baseUrl . '/invoice.php?order=' . rawurlencode((string)$order['order_number']);
    $portalUrl = 'https://cp.hivenest.co.za';
    return '<!doctype html><html><body style="margin:0;background:#080b12;color:#ffffff;font-family:Arial,Helvetica,sans-serif;">'
        . '<div style="max-width:820px;margin:0 auto;padding:28px;">'
        . '<div style="border:1px solid #00ffff;border-radius:14px;padding:26px;background:#10141f;">'
        . '<h1 style="margin:0 0 12px;color:#00ffff;letter-spacing:1px;">HiveNest Paid Order Confirmation</h1>'
        . '<p style="font-size:16px;line-height:1.6;">Dear ' . hivenest_html(hivenest_customer_display_name($order)) . ',</p>'
        . '<p style="font-size:16px;line-height:1.6;">Thank you for your payment. Your HiveNest order has been marked as <strong style="color:#00ff00;">PAID</strong>.</p>'
        . '<div style="margin:22px 0;padding:18px;border:1px solid rgba(0,255,255,0.35);border-radius:10px;background:rgba(0,255,255,0.08);">'
        . '<strong>Order:</strong> #' . hivenest_html($order['order_number']) . '<br>'
        . '<strong>Total Paid:</strong> ' . hivenest_html(hivenest_money((float)$order['total_amount'], (string)$order['currency'])) . '<br>'
        . '<strong>Payment Reference:</strong> ' . hivenest_html($order['payment_reference']) . '</div>'
        . '<p style="font-size:16px;line-height:1.6;">Your styled paid invoice is attached to this email. You can also view, download, print, or save it online.</p>'
        . '<p style="margin:26px 0;">'
        . '<a href="' . hivenest_html($invoiceUrl) . '" style="display:inline-block;background:linear-gradient(135deg,#00ffff,#ff00ff);color:#05070b;text-decoration:none;padding:13px 18px;border-radius:8px;font-weight:bold;margin-right:10px;">VIEW INVOICE</a>'
        . '<a href="' . hivenest_html($portalUrl) . '" style="display:inline-block;border:1px solid #00ffff;color:#00ffff;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:bold;">CLIENT PORTAL</a>'
        . '</p>'
        . '<p style="color:#d7e7ec;line-height:1.6;">You will receive separate provisioning emails for each purchased item. Some services require provider provisioning before access details can be issued.</p>'
        . '<p style="color:#d7e7ec;line-height:1.6;">HiveNest Support<br>support@hivenest.co.za</p>'
        . '</div></div></body></html>';
}

function hivenest_item_email_body(array $order, array $item): string
{
    $name = hivenest_customer_display_name($order);
    $currency = (string)($order['currency'] ?? 'USD');
    $productType = strtolower((string)($item['product_type'] ?? 'service'));
    $domain = trim((string)($item['domain_name'] ?? ''));
    $dueDate = date('Y-m-d', strtotime('+1 year'));

    $body = "Dear {$name},\n\n";
    $body .= "Thank you for choosing HiveNest.\n\n";
    $body .= "Your service order has been received and paid. Provisioning is now pending with the provider/team.\n\n";
    $body .= "==============================================\n";
    $body .= "Your Service Information:\n\n";
    $body .= "Service: {$item['product_name']}\n";
    if ($domain !== '') $body .= "Domain Name: {$domain}\n";
    $body .= "First Payment Amount: " . hivenest_money((float)$item['line_total'], $currency) . "\n";
    $body .= "Recurring Amount: " . hivenest_money(((float)$item['unit_price'] + (float)$item['setup_fee']) * (int)$item['quantity'], $currency) . "\n";
    $body .= "Billing Cycle: {$item['billing_cycle']}\n";
    $body .= "Next Due Date: {$dueDate}\n";
    $body .= "==============================================\n\n";

    if (in_array($productType, ['hosting', 'server'], true)) {
        $body .= "Server / Control Panel Information:\n\n";
        $body .= "Your hosting/server account is awaiting provisioning. Once created, you will receive the server name, IP address, nameservers, control panel login URL, FTP information, and email settings in a follow-up setup email.\n";
    } elseif ($productType === 'domain') {
        $body .= "Domain Registration Information:\n\n";
        $body .= "Your domain is awaiting registrar provisioning. Once completed, you will receive confirmation with registrar status, nameserver information, and management instructions.\n";
    } elseif (in_array($productType, ['design'], true)) {
        $body .= "Design / Website Build Information:\n\n";
        $body .= "To help us start smoothly, please gather your business details, logo files, brand colours, website page list, written content, images, domain/hosting access if applicable, social media links, marketing goals, and examples of designs you like.\n\n";
        $body .= "To process the website build/design request, please log in to the client portal and complete the onboarding system for this service.\n";
    } else {
        $body .= "Provisioning Information:\n\n";
        $body .= "Your service is queued for setup. A follow-up email will be sent with the relevant access details or next steps.\n";
    }

    $body .= "\n==============================================\n";
    $body .= "Client Portal: https://cp.hivenest.co.za\n";
    $body .= "Support: support@hivenest.co.za\n";
    $body .= "==============================================\n\n";
    $body .= "Thank you for choosing HiveNest.\n";
    return $body;
}

function hivenest_item_email_html(array $order, array $item): string
{
    $name = hivenest_customer_display_name($order);
    $currency = (string)($order['currency'] ?? 'USD');
    $productType = strtolower((string)($item['product_type'] ?? 'service'));
    $productSlug = strtolower((string)($item['product_slug'] ?? ''));
    $domain = trim((string)($item['domain_name'] ?? ''));
    $dueDate = date('Y-m-d', strtotime('+1 year'));
    $portalUrl = 'https://cp.hivenest.co.za';

    $info = '';
    if (in_array($productType, ['hosting', 'server'], true)) {
        $info = '<h3 style="color:#00ffff;">Server / Control Panel Information</h3>'
            . '<p>Your hosting/server service is awaiting provider provisioning. Once the MyOrderBox/provider order is completed, your service-ready email should include the server name, server IP, nameservers, control panel URL, username, temporary FTP details, and email settings.</p>'
            . '<p>If these details are not available immediately, the service remains in provisioning pending until the provider returns the account information.</p>';
    } elseif ($productType === 'domain') {
        $info = '<h3 style="color:#00ffff;">Domain Registration Information</h3>'
            . '<p>Your domain is awaiting registrar provisioning. Once completed, you will receive confirmation with registrar status, nameserver information, and management instructions.</p>'
            . '<ul style="line-height:1.8;">'
            . '<li>Confirm the registrant name and contact details are correct</li>'
            . '<li>Confirm the domain nameservers you want to use</li>'
            . '<li>If this is connected to hosting, wait for the hosting nameserver details before changing DNS</li>'
            . '</ul>';
    } elseif (($productType === 'design' && !str_contains($productSlug, 'website-builder') && !str_contains($productSlug, 'website')) || str_contains($productSlug, 'logo') || str_contains($productSlug, 'business-cards') || str_contains($productSlug, 'letterheads') || str_contains($productSlug, 'signatures')) {
        $info = '<h3 style="color:#00ffff;">Neural Graphics Information Needed</h3>'
            . '<p>To start the design process, please gather the details that match your design product:</p>'
            . '<ul style="line-height:1.8;">'
            . '<li>Business name and exact wording to appear on the design</li>'
            . '<li>Existing logo files, brand colours, fonts, and brand guide if available</li>'
            . '<li>Preferred style: clean, bold, luxury, cyber, corporate, playful, etc.</li>'
            . '<li>Examples of designs you like and designs you do not like</li>'
            . '<li>For logos: icon ideas, tagline, colour preferences, and where the logo will be used</li>'
            . '<li>For business cards/letterheads/signatures: staff names, titles, phone numbers, email addresses, website URL, address, and social links</li>'
            . '</ul>'
            . '<p>Please log in to the client portal and complete the graphics/design onboarding form for this service.</p>'
            . '<p><a href="' . hivenest_html($portalUrl) . '" style="display:inline-block;background:linear-gradient(135deg,#00ffff,#ff00ff);color:#05070b;text-decoration:none;padding:12px 16px;border-radius:8px;font-weight:bold;">OPEN CLIENT PORTAL</a></p>';
    } elseif (str_contains($productSlug, 'marketing') || str_contains($productSlug, 'seo') || str_contains($productSlug, 'social-media') || str_contains($productSlug, 'google-marketing')) {
        $info = '<h3 style="color:#00ffff;">Marketing Matrix Information Needed</h3>'
            . '<p>To prepare your campaign, please gather the following:</p>'
            . '<ul style="line-height:1.8;">'
            . '<li>Business goals: leads, sales, awareness, bookings, traffic, or retention</li>'
            . '<li>Target audience, locations, services/products, and monthly budget range</li>'
            . '<li>Website URL, landing pages, Google Business Profile, and social media links</li>'
            . '<li>Existing ad accounts, Google Analytics/Search Console access if available</li>'
            . '<li>Brand assets, offers, promotions, testimonials, and competitor examples</li>'
            . '<li>Any restricted keywords, locations, products, or compliance requirements</li>'
            . '</ul>'
            . '<p>Please log in to the client portal and complete the marketing onboarding system so the campaign brief can be processed.</p>'
            . '<p><a href="' . hivenest_html($portalUrl) . '" style="display:inline-block;background:linear-gradient(135deg,#00ffff,#ff00ff);color:#05070b;text-decoration:none;padding:12px 16px;border-radius:8px;font-weight:bold;">OPEN CLIENT PORTAL</a></p>';
    } elseif (str_contains($productSlug, 'website-builder') || str_contains($productSlug, 'website')) {
        $info = '<h3 style="color:#00ffff;">Website Build Information Needed</h3>'
            . '<p>To process the website build, please gather the following before onboarding:</p>'
            . '<ul style="line-height:1.8;">'
            . '<li>Website goal, page list, and required functionality</li>'
            . '<li>Logo, brand colours, fonts, images, and written page content</li>'
            . '<li>Domain name, current hosting/control panel access if applicable</li>'
            . '<li>Products/services, pricing, contact details, forms, and legal pages</li>'
            . '<li>Examples of websites you like and any design direction</li>'
            . '</ul>'
            . '<p>Please log in to the client portal and complete the website build onboarding system for this service.</p>'
            . '<p><a href="' . hivenest_html($portalUrl) . '" style="display:inline-block;background:linear-gradient(135deg,#00ffff,#ff00ff);color:#05070b;text-decoration:none;padding:12px 16px;border-radius:8px;font-weight:bold;">OPEN CLIENT PORTAL</a></p>';
    } elseif (in_array($productType, ['email'], true) || str_contains($productSlug, 'mail') || str_contains($productSlug, 'workspace')) {
        $info = '<h3 style="color:#00ffff;">Email Service Information Needed</h3>'
            . '<ul style="line-height:1.8;">'
            . '<li>Domain name the email service must use</li>'
            . '<li>Mailbox/user list and required aliases or forwards</li>'
            . '<li>Existing DNS/hosting access if HiveNest is not managing the domain</li>'
            . '<li>Migration requirement: old mailbox provider, mailbox size, and migration timing</li>'
            . '</ul>';
    } elseif (in_array($productType, ['security', 'ssl', 'backup'], true) || str_contains($productSlug, 'ssl') || str_contains($productSlug, 'sitelock') || str_contains($productSlug, 'backup') || str_contains($productSlug, 'xcitium')) {
        $info = '<h3 style="color:#00ffff;">Security / Backup Information Needed</h3>'
            . '<ul style="line-height:1.8;">'
            . '<li>Domain, website, server, or hosting account to protect</li>'
            . '<li>Current hosting/control panel access if setup requires installation</li>'
            . '<li>Preferred contact for security alerts and renewal notices</li>'
            . '<li>For SSL: exact common name/domain and whether wildcard coverage is required</li>'
            . '<li>For backup: files/databases to protect and preferred restore contact</li>'
            . '</ul>';
    } else {
        $info = '<h3 style="color:#00ffff;">Provisioning Information</h3>'
            . '<p>Your service is queued for setup. A follow-up email will be sent with the relevant access details or next steps.</p>';
    }

    return '<!doctype html><html><body style="margin:0;background:#080b12;color:#ffffff;font-family:Arial,Helvetica,sans-serif;">'
        . '<div style="max-width:820px;margin:0 auto;padding:28px;">'
        . '<div style="border:1px solid #00ffff;border-radius:14px;padding:26px;background:#10141f;">'
        . '<h1 style="margin:0 0 12px;color:#00ffff;letter-spacing:1px;">HiveNest Service Provisioning</h1>'
        . '<p style="line-height:1.6;">Dear ' . hivenest_html($name) . ',</p>'
        . '<p style="line-height:1.6;">Thank you for choosing HiveNest. Your service order has been received and paid. Provisioning is now pending with the provider/team.</p>'
        . '<div style="margin:22px 0;padding:18px;border:1px solid rgba(0,255,255,0.35);border-radius:10px;background:rgba(0,255,255,0.08);line-height:1.8;">'
        . '<strong>Service:</strong> ' . hivenest_html($item['product_name']) . '<br>'
        . ($domain !== '' ? '<strong>Domain Name:</strong> ' . hivenest_html($domain) . '<br>' : '')
        . '<strong>First Payment Amount:</strong> ' . hivenest_html(hivenest_money((float)$item['line_total'], $currency)) . '<br>'
        . '<strong>Recurring Amount:</strong> ' . hivenest_html(hivenest_money(((float)$item['unit_price'] + (float)$item['setup_fee']) * (int)$item['quantity'], $currency)) . '<br>'
        . '<strong>Billing Cycle:</strong> ' . hivenest_html($item['billing_cycle']) . '<br>'
        . '<strong>Next Due Date:</strong> ' . hivenest_html($dueDate)
        . '</div>'
        . '<div style="color:#d7e7ec;line-height:1.65;">' . $info . '</div>'
        . '<div style="margin-top:24px;padding-top:18px;border-top:1px solid rgba(0,255,255,0.25);color:#d7e7ec;">'
        . 'Client Portal: <a href="' . hivenest_html($portalUrl) . '" style="color:#00ffff;">' . hivenest_html($portalUrl) . '</a><br>'
        . 'Support: <a href="mailto:support@hivenest.co.za" style="color:#00ffff;">support@hivenest.co.za</a>'
        . '</div>'
        . '</div></div></body></html>';
}

function hivenest_send_paid_order_emails(string $orderNumber): void
{
    $db = hivenest_db();
    if (!$db) return;
    $order = hivenest_fetch_order_for_email($db, $orderNumber);
    if (!$order || empty($order['email'])) return;

    $to = (string)$order['email'];
    $name = hivenest_customer_display_name($order);
    $baseUrl = hivenest_order_base_url();
    $invoiceUrl = $baseUrl . '/invoice.php?order=' . rawurlencode((string)$order['order_number']);
    $portalUrl = 'https://cp.hivenest.co.za';

    $summary = "Dear {$name},\n\n";
    $summary .= "Thank you for your payment. Your HiveNest order has been marked as PAID.\n\n";
    $summary .= hivenest_order_invoice_text($order) . "\n\n";
    $summary .= "View / download invoice: {$invoiceUrl}\n";
    $summary .= "Client portal: {$portalUrl}\n\n";
    $summary .= "You will receive separate provisioning emails for each purchased item. Some services require provider provisioning before access details can be issued.\n\n";
    $summary .= "HiveNest Support\nsupport@hivenest.co.za\n";
    $summarySent = hivenest_mail_with_invoice($to, 'HiveNest Paid Order Confirmation - ' . $order['order_number'], $summary, $order);
    if (!$summarySent) {
        error_log('HiveNest paid order confirmation email failed for order ' . $order['order_number'] . ' to ' . $to);
    }

    foreach ($order['items'] as $item) {
        $subject = 'HiveNest Service Provisioning - ' . $item['product_name'];
        $headers = [
            'From: HiveNest Provisioning <orders@hivenest.co.za>',
            'Reply-To: HiveNest Support <support@hivenest.co.za>',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
        ];
        $itemSent = hivenest_mail_send(
            $to,
            $subject,
            hivenest_item_email_html($order, $item),
            implode("\r\n", $headers),
            'order-item-provisioning:' . (string)$item['id']
        );
        if (!$itemSent) {
            error_log('HiveNest item provisioning email failed for order ' . $order['order_number'] . ', item ' . $item['id'] . ' to ' . $to);
        }
    }
}

function hivenest_order_notifications_column_exists(PDO $db, string $table, string $column): bool
{
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
        ");
        $stmt->execute(['table_name' => $table, 'column_name' => $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function hivenest_send_service_ready_email(int $orderItemId, array $providerDetails = []): bool
{
    $db = hivenest_db();
    if (!$db || $orderItemId <= 0) return false;

    $hasNotifiedColumn = hivenest_order_notifications_column_exists($db, 'order_items', 'service_ready_notified_at');
    $notifiedSelect = $hasNotifiedColumn ? 'oi.service_ready_notified_at' : 'NULL AS service_ready_notified_at';
    $stmt = $db->prepare("
        SELECT
            oi.*,
            {$notifiedSelect},
            o.order_number,
            o.currency,
            c.id AS customer_id,
            c.email,
            c.first_name,
            c.last_name,
            c.company_name,
            p.product_type,
            p.slug AS product_slug
        FROM order_items oi
        INNER JOIN orders o ON o.id=oi.order_id
        INNER JOIN customers c ON c.id=o.customer_id
        LEFT JOIN products p ON p.id=oi.product_id
        WHERE oi.id=:order_item_id
        LIMIT 1
    ");
    $stmt->execute(['order_item_id' => $orderItemId]);
    $item = $stmt->fetch();
    if (!$item || empty($item['email'])) return false;
    try {
        $serviceId = (int)($item['service_id'] ?? 0);
        hivenest_notify_customer_once(
            $db,
            (int)$item['customer_id'],
            'success',
            'Service ready',
            (string)$item['product_name']
                . (trim((string)($item['domain_name'] ?? '')) !== '' ? ' · ' . trim((string)$item['domain_name']) : '')
                . ' is now ready.',
            $serviceId > 0 ? '/services/manage.html?service=' . $serviceId : '/index.html',
            'service_ready_order_item',
            $orderItemId
        );
    } catch (Throwable $notificationError) {
        error_log('HiveNest service ready in-app notification failed for order item ' . $orderItemId . ': ' . $notificationError->getMessage());
    }
    if ($hasNotifiedColumn && !empty($item['service_ready_notified_at'])) return true;

    $name = trim((string)($item['first_name'] ?? '') . ' ' . (string)($item['last_name'] ?? ''));
    if ($name === '') $name = (string)$item['email'];
    $company = trim((string)($item['company_name'] ?? ''));
    if ($company !== '') $name .= ' (' . $company . ')';

    $productType = strtolower((string)($item['product_type'] ?? 'service'));
    $productSlug = strtolower((string)($item['product_slug'] ?? ''));
    $domain = trim((string)($item['domain_name'] ?? ''));
    $portalUrl = 'https://cp.hivenest.co.za';
    $currency = (string)($item['currency'] ?? 'USD');

    $providerOrder = (string)($providerDetails['provider_order_id'] ?? $item['provider_order_id'] ?? '');
    $providerAction = (string)($providerDetails['provider_action_id'] ?? $item['provider_action_id'] ?? '');
    $providerEntity = (string)($providerDetails['provider_entity_id'] ?? $item['provider_entity_id'] ?? '');
    $manual = !empty($providerDetails['completed_manually']);

    $details = '';
    if ($productType === 'domain') {
        $details = '<h3 style="color:#00ffff;">Domain Registration Ready</h3>'
            . '<p>Your domain registration has been submitted/completed with the registrar.</p>'
            . '<ul style="line-height:1.8;">'
            . ($domain !== '' ? '<li><strong>Domain:</strong> ' . hivenest_html($domain) . '</li>' : '')
            . ($providerOrder !== '' ? '<li><strong>Registrar Order ID:</strong> ' . hivenest_html($providerOrder) . '</li>' : '')
            . ($providerAction !== '' ? '<li><strong>Registrar Action ID:</strong> ' . hivenest_html($providerAction) . '</li>' : '')
            . '<li>You can manage this service from the client portal once portal service management is enabled.</li>'
            . '</ul>';
    } elseif (in_array($productType, ['hosting', 'server'], true) || str_contains($productSlug, 'hosting') || str_contains($productSlug, 'server') || str_contains($productSlug, 'wordpress')) {
        $details = '<h3 style="color:#00ffff;">Hosting / Server Service Ready</h3>'
            . '<p>Your hosting/server service has been provisioned or marked ready by the HiveNest team.</p>'
            . '<ul style="line-height:1.8;">'
            . ($domain !== '' ? '<li><strong>Primary Domain:</strong> ' . hivenest_html($domain) . '</li>' : '')
            . ($providerOrder !== '' ? '<li><strong>Provider Order ID:</strong> ' . hivenest_html($providerOrder) . '</li>' : '')
            . '<li>Control panel, FTP, nameserver and email-access details will be visible in the client portal or sent in a secured follow-up if provider credentials are generated separately.</li>'
            . '</ul>';
    } elseif ($productType === 'email' || str_contains($productSlug, 'mail') || str_contains($productSlug, 'workspace')) {
        $details = '<h3 style="color:#00ffff;">Email Service Ready</h3>'
            . '<p>Your email service has been provisioned or marked ready.</p>'
            . '<ul style="line-height:1.8;">'
            . ($domain !== '' ? '<li><strong>Email Domain:</strong> ' . hivenest_html($domain) . '</li>' : '')
            . ($providerOrder !== '' ? '<li><strong>Provider Order ID:</strong> ' . hivenest_html($providerOrder) . '</li>' : '')
            . '<li>Mailbox setup, DNS records and migration steps can be managed through support/client portal workflow.</li>'
            . '</ul>';
    } elseif (in_array($productType, ['ssl', 'security', 'backup'], true) || str_contains($productSlug, 'ssl') || str_contains($productSlug, 'sitelock') || str_contains($productSlug, 'backup')) {
        $details = '<h3 style="color:#00ffff;">Security / Backup Service Ready</h3>'
            . '<p>Your security, SSL or backup service has been provisioned or marked ready.</p>'
            . '<ul style="line-height:1.8;">'
            . ($domain !== '' ? '<li><strong>Protected Domain/Service:</strong> ' . hivenest_html($domain) . '</li>' : '')
            . ($providerOrder !== '' ? '<li><strong>Provider Order ID:</strong> ' . hivenest_html($providerOrder) . '</li>' : '')
            . '<li>If installation or validation is still required, the HiveNest team will continue the setup from the support/provisioning queue.</li>'
            . '</ul>';
    } else {
        $details = '<h3 style="color:#00ffff;">Service Ready</h3>'
            . '<p>Your service has been marked ready by the HiveNest team.</p>'
            . '<p>Please open the client portal for next steps, files, onboarding, or support communication.</p>';
    }

    $body = '<!doctype html><html><body style="margin:0;background:#080b12;color:#ffffff;font-family:Arial,Helvetica,sans-serif;">'
        . '<div style="max-width:820px;margin:0 auto;padding:28px;">'
        . '<div style="border:1px solid #00ffff;border-radius:14px;padding:26px;background:#10141f;">'
        . '<h1 style="margin:0 0 12px;color:#00ff00;letter-spacing:1px;">HiveNest Service Ready</h1>'
        . '<p style="line-height:1.6;">Dear ' . hivenest_html($name) . ',</p>'
        . '<p style="line-height:1.6;">Your HiveNest service is now <strong style="color:#00ff00;">READY</strong>.</p>'
        . '<div style="margin:22px 0;padding:18px;border:1px solid rgba(0,255,255,0.35);border-radius:10px;background:rgba(0,255,255,0.08);line-height:1.8;">'
        . '<strong>Order:</strong> ' . hivenest_html($item['order_number']) . '<br>'
        . '<strong>Service:</strong> ' . hivenest_html($item['product_name']) . '<br>'
        . ($domain !== '' ? '<strong>Domain:</strong> ' . hivenest_html($domain) . '<br>' : '')
        . '<strong>Billing Cycle:</strong> ' . hivenest_html($item['billing_cycle']) . '<br>'
        . '<strong>Amount:</strong> ' . hivenest_html(hivenest_money((float)$item['line_total'], $currency))
        . '</div>'
        . '<div style="color:#d7e7ec;line-height:1.65;">' . $details . '</div>'
        . ($manual ? '<p style="color:#d7e7ec;">This service was completed by the HiveNest support team after manual review.</p>' : '')
        . '<p style="margin:26px 0;">'
        . '<a href="' . hivenest_html($portalUrl) . '" style="display:inline-block;background:linear-gradient(135deg,#00ffff,#ff00ff);color:#05070b;text-decoration:none;padding:13px 18px;border-radius:8px;font-weight:bold;margin-right:10px;">OPEN CLIENT PORTAL</a>'
        . '<a href="mailto:support@hivenest.co.za" style="display:inline-block;border:1px solid #00ffff;color:#00ffff;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:bold;">CONTACT SUPPORT</a>'
        . '</p>'
        . '</div></div></body></html>';

    $headers = [
        'From: HiveNest Provisioning <orders@hivenest.co.za>',
        'Reply-To: HiveNest Support <support@hivenest.co.za>',
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
    ];
    $sent = hivenest_mail_send(
        (string)$item['email'],
        'HiveNest Service Ready - ' . (string)$item['product_name'],
        $body,
        implode("\r\n", $headers),
        'service-ready:' . $orderItemId
    );
    if ($sent && $hasNotifiedColumn) {
        $db->prepare('UPDATE order_items SET service_ready_notified_at=NOW() WHERE id=:id')
            ->execute(['id' => $orderItemId]);
    }
    if (!$sent) {
        error_log('HiveNest service ready email failed for order item ' . $orderItemId . ' to ' . $item['email']);
    }
    return $sent;
}
