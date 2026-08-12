<?php
declare(strict_types=1);

require_once __DIR__ . '/mail_delivery.php';

/**
 * Database-backed transactional email templates.
 *
 * The rendered subject/body are still stored in outbound_mail_queue. Template
 * metadata is diagnostic only, so queued mail remains an immutable delivery
 * snapshot even after a newer template version is published.
 */

function hivenest_email_templates_ready(PDO $db): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        $stmt = $db->query("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_templates'
        ");
        return $ready = (int)$stmt->fetchColumn() === 1;
    } catch (Throwable $e) {
        return $ready = false;
    }
}

function hivenest_email_template_tokens(string $value): array
{
    preg_match_all('/{{\s*([a-z][a-z0-9_]*)\s*}}/i', $value, $matches);
    return array_values(array_unique(array_map('strtolower', $matches[1] ?? [])));
}

function hivenest_email_template_replace(string $template, array $variables, bool $html): string
{
    return preg_replace_callback(
        '/{{\s*([a-z][a-z0-9_]*)\s*}}/i',
        static function (array $match) use ($variables, $html): string {
            $key = strtolower($match[1]);
            $value = array_key_exists($key, $variables) ? (string)$variables[$key] : '';
            return $html
                ? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                : $value;
        },
        $template
    ) ?? $template;
}

function hivenest_email_template_render(
    string $templateKey,
    array $variables,
    string $fallbackSubject,
    string $fallbackBody,
    string $fallbackContentType = 'text/plain'
): array {
    $templateKey = strtolower(trim($templateKey));
    $normalized = [];
    foreach ($variables as $key => $value) {
        if (is_scalar($value) || $value === null) {
            $normalized[strtolower((string)$key)] = (string)($value ?? '');
        }
    }

    $template = null;
    $db = hivenest_db();
    if ($db && hivenest_email_templates_ready($db)) {
        try {
            $stmt = $db->prepare("
                SELECT template_key,version,subject_template,body_template,content_type
                FROM email_templates
                WHERE template_key=:template_key AND is_active=1
                ORDER BY version DESC LIMIT 1
            ");
            $stmt->execute(['template_key' => $templateKey]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            error_log('HiveNest email template lookup failed: ' . $e->getMessage());
        }
    }

    $subjectTemplate = (string)($template['subject_template'] ?? $fallbackSubject);
    $bodyTemplate = (string)($template['body_template'] ?? $fallbackBody);
    $contentType = strtolower((string)($template['content_type'] ?? $fallbackContentType)) === 'text/html'
        ? 'text/html'
        : 'text/plain';
    $subject = trim(preg_replace('/[\r\n]+/', ' ', hivenest_email_template_replace($subjectTemplate, $normalized, false)) ?? '');
    $body = hivenest_email_template_replace($bodyTemplate, $normalized, $contentType === 'text/html');

    return [
        'template_key' => $template !== null ? $templateKey : null,
        'template_version' => $template !== null ? (int)$template['version'] : null,
        'subject' => $subject !== '' ? $subject : trim($fallbackSubject),
        'body' => $body,
        'content_type' => $contentType,
        'used_fallback' => $template === null,
    ];
}

function hivenest_mail_send_template(
    string $to,
    string $templateKey,
    array $variables,
    string $fallbackSubject,
    string $fallbackBody,
    array $headers = [],
    ?string $dedupeKey = null,
    string $fallbackContentType = 'text/plain'
): bool {
    $rendered = hivenest_email_template_render(
        $templateKey,
        $variables,
        $fallbackSubject,
        $fallbackBody,
        $fallbackContentType
    );
    $filteredHeaders = array_values(array_filter(
        $headers,
        static fn(string $header): bool => stripos($header, 'Content-Type:') !== 0
    ));
    $filteredHeaders[] = 'Content-Type: ' . $rendered['content_type'] . '; charset=UTF-8';

    return hivenest_mail_send(
        $to,
        (string)$rendered['subject'],
        (string)$rendered['body'],
        implode("\r\n", $filteredHeaders),
        $dedupeKey,
        $rendered['template_key'],
        $rendered['template_version']
    );
}
