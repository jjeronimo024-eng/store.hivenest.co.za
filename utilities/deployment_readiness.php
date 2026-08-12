<?php
declare(strict_types=1);

/**
 * Authoritative, read-only deployment requirements used by System Health.
 * Values are never returned; environment checks expose configuration state only.
 */

function hivenest_readiness_table_groups(): array
{
    return [
        'Core commerce' => [
            'admin_users', 'customers', 'products', 'product_categories',
            'product_pricing', 'orders', 'order_items', 'services',
            'support_tickets', 'support_ticket_replies',
        ],
        'PayPal and provisioning bridge' => [
            'payment_gateway_transactions', 'paypal_checkout_sessions',
            'paypal_webhook_events', 'provisioning_jobs',
            'provisioning_worker_runs', 'myorderbox_contacts',
            'product_provider_mappings',
        ],
        'Customer operations' => [
            'domain_registrations', 'hosting_accounts', 'customer_service_onboarding',
            'service_workflow_stages', 'service_workflow_deliverables',
            'service_workflow_comments', 'service_requests', 'service_notes',
            'service_status_history', 'service_files', 'service_file_downloads',
            'service_renewals',
        ],
        'CRM and communication' => [
            'customer_notes', 'crm_work_items', 'crm_work_item_history',
            'admin_notifications', 'customer_notifications', 'service_notices',
            'live_chat_sessions', 'live_chat_messages', 'payment_refunds',
        ],
        'Identity and email delivery' => [
            'customer_email_verifications', 'customer_password_resets',
            'two_factor_challenges', 'two_factor_recovery_codes',
            'outbound_mail_queue', 'email_templates', 'mail_delivery_events',
            'mail_suppressions',
        ],
        'Security and operations' => [
            'service_credentials', 'service_credential_access_audit',
            'monitoring_nodes', 'monitoring_samples', 'monitoring_alerts',
        ],
    ];
}

function hivenest_readiness_column_groups(): array
{
    return [
        'Two-factor authentication' => [
            'admin_users' => ['auth_version', 'two_factor_enabled', 'two_factor_secret', 'two_factor_confirmed_at'],
            'customers' => ['auth_version', 'two_factor_enabled', 'two_factor_secret', 'two_factor_confirmed_at'],
        ],
        'Provider and provisioning state' => [
            'customers' => ['myorderbox_customer_id'],
            'orders' => ['provisioning_status'],
            'order_items' => ['service_id', 'provisioning_status', 'service_ready_notified_at'],
            'domain_registrations' => ['provider_order_id'],
            'provisioning_jobs' => ['idempotency_key'],
        ],
        'Currency and catalogue' => [
            'orders' => [
                'display_currency', 'display_exchange_rate', 'display_subtotal',
                'display_tax_amount', 'display_discount_amount',
                'display_total_amount', 'display_rate_source',
                'display_rate_captured_at',
            ],
            'product_pricing' => ['sort_order', 'accent_color', 'glow_color', 'bundle_items'],
        ],
        'Mail queue audit' => [
            'outbound_mail_queue' => [
                'manual_retry_count', 'last_retried_by', 'last_retried_at',
                'template_key', 'template_version',
            ],
        ],
        'Support and promotion reconciliation' => [
            'support_tickets' => ['order_id'],
            'promotion_redemptions' => ['reversed_at', 'reversal_event_id', 'reversal_reason'],
        ],
    ];
}

function hivenest_readiness_environment_requirements(): array
{
    return [
        'Database' => ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'],
        'Application security' => [
            'JWT_SECRET_KEY', 'TWO_FACTOR_ENCRYPTION_KEY',
            'SERVICE_CREDENTIAL_ENCRYPTION_KEY',
        ],
        'PayPal and MyOrderBox' => [
            'PAYPAL_CLIENT_ID', 'PAYPAL_CLIENT_SECRET', 'PAYPAL_WEBHOOK_ID',
            'MYORDERBOX_RESELLER_ID', 'MYORDERBOX_API_KEY', 'MYORDERBOX_ENV',
        ],
        'Workers and ingestion' => [
            'PROVISIONING_WORKER_TOKEN', 'MAIL_WORKER_TOKEN',
            'MONITORING_INGEST_SECRET', 'MAIL_EVENT_WEBHOOK_SECRET',
        ],
        'Authenticated SMTP' => [
            'MAIL_TRANSPORT', 'SMTP_HOST', 'SMTP_PORT',
            'SMTP_USERNAME|SMTP_USER', 'SMTP_PASSWORD',
            'MAIL_FROM_ADDRESS|SMTP_FROM_EMAIL',
        ],
    ];
}

function hivenest_readiness_env_values(?string $path = null): array
{
    $path = $path ?: (defined('HIVENEST_ENV_PATH') ? HIVENEST_ENV_PATH : __DIR__ . '/../Backend/.env');
    $values = [];
    foreach (is_readable($path) ? (@file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) continue;
        $values[$key] = trim(trim($value), "\"'");
    }
    return $values;
}

function hivenest_readiness_value_configured(string $requirement, array $values): bool
{
    foreach (explode('|', $requirement) as $key) {
        $value = trim((string)($values[$key] ?? getenv($key) ?: ''));
        if ($value === '') continue;
        if (preg_match('/CHANGE_ME|PASTE_|YOUR_|PLACEHOLDER|REPLACE_ME/i', $value)) continue;
        return true;
    }
    return false;
}

function hivenest_readiness_required_extensions(): array
{
    return ['pdo_mysql', 'curl', 'openssl', 'fileinfo', 'json'];
}
