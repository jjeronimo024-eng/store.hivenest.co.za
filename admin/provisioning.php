<?php
declare(strict_types=1);

require_once __DIR__ . '/../utilities/admin_auth.php';
requireAdminAuth();
$admin = currentAdmin();

require_once __DIR__ . '/../utilities/myorderbox_bridge.php';
require_once __DIR__ . '/../utilities/order_notifications.php';
require_once __DIR__ . '/../utilities/crm_notifications.php';

$db = hivenest_db();
$csrf = csrfToken();
$message = null;
$messageType = 'info';
$planLookupKey = '';
$planLookupAuthUserId = '';
$planLookupEnv = '';
$planLookupResult = null;
$orderLookupProductKey = '';
$orderLookupOrderId = '';
$orderLookupDomain = '';
$orderLookupResult = null;
$onboardingSubmissions = [];
$mappingCoverage = [];
$coverageSummary = ['visible' => 0, 'provider_required' => 0, 'mapped' => 0, 'missing' => 0, 'team' => 0, 'builtin' => 0];
$operationalAlerts = [];
$providerBalance = null;

if (!empty($_SESSION['provisioning_flash']) && is_array($_SESSION['provisioning_flash'])) {
    $message = (string)($_SESSION['provisioning_flash']['message'] ?? '');
    $messageType = (string)($_SESSION['provisioning_flash']['type'] ?? 'info');
    unset($_SESSION['provisioning_flash']);
}

function hpn_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function hpn_badge_class(string $status): string {
    return match ($status) {
        'completed', 'credited', 'synced' => 'ok',
        'pending', 'processing', 'queued' => 'pending',
        'retry' => 'warn',
        'manual_review' => 'manual',
        'failed', 'error' => 'bad',
        default => 'neutral',
    };
}

function hpn_pick_json_value(array $data, array $keys): string {
    foreach ($keys as $key) {
        if (array_key_exists($key, $data) && is_scalar($data[$key]) && trim((string)$data[$key]) !== '') {
            return trim((string)$data[$key]);
        }
    }
    foreach ($data as $value) {
        if (is_array($value)) {
            $found = hpn_pick_json_value($value, $keys);
            if ($found !== '') return $found;
        }
    }
    return '';
}

function hpn_redirect_after_post(?string $message = null, string $type = 'info'): never {
    if ($message !== null && $message !== '') {
        $_SESSION['provisioning_flash'] = [
            'message' => $message,
            'type' => $type,
        ];
    }
    header('Location: ' . strtok((string)($_SERVER['REQUEST_URI'] ?? 'provisioning.php'), '?'));
    exit;
}

function hpn_env_float(string $key, float $default): float {
    $value = trim(hivenest_bridge_env($key, ''));
    return $value !== '' && is_numeric($value) ? (float)$value : $default;
}

function hpn_env_int(string $key, int $default, int $minimum, int $maximum): int {
    $value = trim(hivenest_bridge_env($key, ''));
    $parsed = $value !== '' && is_numeric($value) ? (int)$value : $default;
    return max($minimum, min($maximum, $parsed));
}

function hpn_publish_operational_alerts(PDO $db, array $alerts): void {
    if (!$alerts || !hivenest_bridge_table_exists($db, 'admin_users')) return;
    try {
        hivenest_crm_notifications_ensure($db);
        $adminIds = $db->query('SELECT id FROM admin_users WHERE is_active=1')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $exists = $db->prepare("
            SELECT COUNT(*)
            FROM admin_notifications
            WHERE admin_user_id=:admin_id
              AND entity_type=:entity_type
              AND (
                    (entity_id IS NULL AND :entity_id_null IS NULL)
                    OR entity_id=:entity_id_match
              )
              AND title=:title
              AND created_at >= CURRENT_DATE()
              AND created_at < DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY)
        ");
        foreach ($alerts as $alert) {
            foreach ($adminIds as $adminId) {
                $entityId = isset($alert['entity_id']) ? (int)$alert['entity_id'] : null;
                $exists->execute([
                    'admin_id' => (int)$adminId,
                    'entity_type' => (string)$alert['entity_type'],
                    'entity_id_null' => $entityId,
                    'entity_id_match' => $entityId,
                    'title' => (string)$alert['title'],
                ]);
                if ((int)$exists->fetchColumn() > 0) continue;
                hivenest_crm_notify_admin(
                    $db,
                    (int)$adminId,
                    (string)$alert['type'],
                    (string)$alert['title'],
                    (string)$alert['message'],
                    (string)($alert['link_url'] ?? '/admin/provisioning.php'),
                    (string)$alert['entity_type'],
                    $entityId
                );
            }
        }
    } catch (Throwable $e) {
        error_log('Provisioning operational alert publication failed: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie($_POST['csrf_token'] ?? '');
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'process_jobs') {
        $limit = max(1, min(50, (int)($_POST['limit'] ?? 10)));
        $result = hivenest_process_provisioning_jobs($limit);
        $messageType = !empty($result['ok']) ? 'success' : 'error';
        $message = !empty($result['ok'])
            ? 'Processed ' . (int)$result['processed'] . ' provisioning job(s).'
            : 'Worker failed: ' . (string)($result['error'] ?? 'Unknown error');
    } elseif ($action === 'lookup_plan_details' && $db) {
        $planLookupKey = trim((string)($_POST['product_key'] ?? ''));
        $planLookupAuthUserId = trim((string)($_POST['mob_auth_userid'] ?? ''));
        $planLookupApiKey = trim((string)($_POST['mob_api_key'] ?? ''));
        $planLookupEnv = trim((string)($_POST['mob_lookup_env'] ?? ''));
        if (!in_array($planLookupEnv, ['', 'test', 'live'], true)) $planLookupEnv = '';
        $planLookupResult = hivenest_mob_plan_details($db, $planLookupKey, $planLookupAuthUserId, $planLookupApiKey, $planLookupEnv);
        $messageType = !empty($planLookupResult['ok']) ? 'success' : 'error';
        $message = !empty($planLookupResult['ok'])
            ? 'Plan details fetched from MyOrderBox.'
            : 'Plan lookup failed: ' . (string)($planLookupResult['error'] ?? 'Unknown error');
    } elseif ($action === 'lookup_product_order' && $db) {
        $orderLookupProductKey = trim((string)($_POST['order_product_key'] ?? ''));
        $orderLookupOrderId = trim((string)($_POST['mob_order_id'] ?? ''));
        $orderLookupDomain = trim((string)($_POST['mob_domain_name'] ?? ''));
        $orderLookupResult = hivenest_mob_rest_product_order_lookup($db, $orderLookupProductKey, $orderLookupOrderId, $orderLookupDomain);
        $messageType = !empty($orderLookupResult['ok']) ? 'success' : 'error';
        $message = !empty($orderLookupResult['ok'])
            ? 'Existing MyOrderBox order details fetched.'
            : 'Order lookup failed: ' . (string)($orderLookupResult['error'] ?? 'Unknown error');
    } elseif ($action === 'retry_job' && $db) {
        $jobId = (int)($_POST['job_id'] ?? 0);
        if ($jobId > 0) {
            $jobStmt = $db->prepare("
                SELECT
                    pj.id,
                    pj.order_item_id,
                    pj.service_id,
                    pj.job_type,
                    oi.product_name,
                    oi.product_config,
                    p.product_type,
                    p.slug AS product_slug
                FROM provisioning_jobs pj
                LEFT JOIN order_items oi ON oi.id = pj.order_item_id
                LEFT JOIN products p ON p.id = oi.product_id
                WHERE pj.id = :id
                  AND pj.status IN ('retry','failed','manual_review')
                LIMIT 1
            ");
            $jobStmt->execute(['id' => $jobId]);
            $retryJob = $jobStmt->fetch(PDO::FETCH_ASSOC);

            if ($retryJob) {
                $config = json_decode((string)($retryJob['product_config'] ?? ''), true);
                if (!is_array($config)) $config = [];
                $sku = (string)($config['sku'] ?? '');
                $serviceType = hivenest_bridge_service_type(
                    (string)($retryJob['product_type'] ?? ''),
                    (string)($retryJob['product_slug'] ?? ''),
                    $sku,
                    (string)($retryJob['product_name'] ?? '')
                );
                $jobType = hivenest_bridge_job_type($serviceType, $sku, (string)($retryJob['product_name'] ?? ''));
                $manualQueue = in_array($jobType, ['design_queue','marketing_queue','manual_queue'], true);

                $db->beginTransaction();
                $stmt = $db->prepare("
                    UPDATE provisioning_jobs
                    SET job_type = :job_type,
                        status = :status,
                        next_attempt_at = :next_attempt_at,
                        error_message = :error_message
                    WHERE id = :id
                ");
                $stmt->execute([
                    'job_type' => $jobType,
                    'status' => $manualQueue ? 'manual_review' : 'pending',
                    'next_attempt_at' => $manualQueue ? null : gmdate('Y-m-d H:i:s'),
                    'error_message' => $manualQueue ? 'Team action required for this service. Complete it manually in the provisioning monitor when ready.' : null,
                    'id' => $jobId,
                ]);

                if ((int)($retryJob['service_id'] ?? 0) > 0) {
                    $serviceUpdate = $db->prepare("UPDATE services SET service_type = :service_type WHERE id = :service_id");
                    $serviceUpdate->execute([
                        'service_type' => $serviceType,
                        'service_id' => (int)$retryJob['service_id'],
                    ]);
                }

                if ((int)($retryJob['order_item_id'] ?? 0) > 0) {
                    $itemUpdate = $db->prepare("
                        UPDATE order_items
                        SET provisioning_status = :status,
                            provisioning_error = NULL
                        WHERE id = :order_item_id
                    ");
                    $itemUpdate->execute([
                        'status' => $manualQueue ? 'manual_review' : 'queued',
                        'order_item_id' => (int)$retryJob['order_item_id'],
                    ]);
                }

                $db->commit();
            }
            $messageType = 'success';
            $message = 'Job #' . $jobId . ' queued for retry.';
        }
    } elseif ($action === 'mark_manual' && $db) {
        $jobId = (int)($_POST['job_id'] ?? 0);
        if ($jobId > 0) {
            $stmt = $db->prepare("UPDATE provisioning_jobs SET status='manual_review', error_message=COALESCE(error_message, 'Marked for manual review by admin') WHERE id=:id");
            $stmt->execute(['id' => $jobId]);
            $messageType = 'success';
            $message = 'Job #' . $jobId . ' marked for manual review.';
        }
    } elseif ($action === 'complete_manual_job' && $db) {
        $jobId = (int)($_POST['job_id'] ?? 0);
        $providerOrderId = trim((string)($_POST['provider_order_id'] ?? ''));
        $providerActionId = trim((string)($_POST['provider_action_id'] ?? ''));
        $providerEntityId = trim((string)($_POST['provider_entity_id'] ?? ''));
        if ($jobId > 0) {
            $jobStmt = $db->prepare('SELECT * FROM provisioning_jobs WHERE id=:id LIMIT 1');
            $jobStmt->execute(['id' => $jobId]);
            $job = $jobStmt->fetch(PDO::FETCH_ASSOC);
            if ($job) {
                $db->beginTransaction();
                $db->prepare("
                    UPDATE provisioning_jobs
                    SET status='completed',
                        response_payload=:response_payload,
                        error_message=NULL
                    WHERE id=:id
                ")->execute([
                    'response_payload' => json_encode([
                        'completed_manually' => true,
                        'completed_by' => $admin['username'] ?? 'admin',
                        'provider_order_id' => $providerOrderId,
                        'provider_action_id' => $providerActionId,
                        'provider_entity_id' => $providerEntityId,
                        'completed_at' => gmdate('c'),
                    ], JSON_UNESCAPED_SLASHES),
                    'id' => $jobId,
                ]);

                if (!empty($job['order_item_id'])) {
                    $db->prepare("
                        UPDATE order_items
                        SET provisioning_status='completed',
                            provider_order_id=:provider_order_id,
                            provider_action_id=:provider_action_id,
                            provider_entity_id=:provider_entity_id,
                            provisioning_error=NULL
                        WHERE id=:order_item_id
                    ")->execute([
                        'provider_order_id' => $providerOrderId !== '' ? $providerOrderId : null,
                        'provider_action_id' => $providerActionId !== '' ? $providerActionId : null,
                        'provider_entity_id' => $providerEntityId !== '' ? $providerEntityId : ($providerOrderId !== '' ? $providerOrderId : null),
                        'order_item_id' => (int)$job['order_item_id'],
                    ]);
                }

                if (!empty($job['service_id'])) {
                    $db->prepare("
                        UPDATE services
                        SET service_status='active',
                            setup_date=COALESCE(setup_date, NOW()),
                            service_config=JSON_SET(
                                COALESCE(service_config, '{}'),
                                '$.completed_manually', true,
                                '$.provider_order_id', :provider_order_id,
                                '$.provider_action_id', :provider_action_id,
                                '$.provider_entity_id', :provider_entity_id
                            )
                        WHERE id=:service_id
                    ")->execute([
                        'provider_order_id' => $providerOrderId,
                        'provider_action_id' => $providerActionId,
                        'provider_entity_id' => $providerEntityId !== '' ? $providerEntityId : $providerOrderId,
                        'service_id' => (int)$job['service_id'],
                    ]);
                }

                if (!empty($job['order_item_id'])) {
                    hivenest_refresh_order_item_from_jobs($db, (int)$job['order_item_id']);
                }

                if (!empty($job['order_item_id']) && hivenest_order_item_ready_to_notify($db, (int)$job['order_item_id'])) {
                    hivenest_send_service_ready_email((int)$job['order_item_id'], [
                        'completed_manually' => true,
                        'provider_order_id' => $providerOrderId,
                        'provider_action_id' => $providerActionId,
                        'provider_entity_id' => $providerEntityId !== '' ? $providerEntityId : $providerOrderId,
                    ]);
                }

                hivenest_refresh_order_provisioning_status($db, (int)$job['order_id']);
                $db->commit();
                $messageType = 'success';
                $message = 'Job #' . $jobId . ' marked completed manually.';
            }
        }
    } elseif ($action === 'resend_ready_email' && $db) {
        $jobId = (int)($_POST['job_id'] ?? 0);
        if ($jobId > 0) {
            $stmt = $db->prepare('SELECT order_item_id FROM provisioning_jobs WHERE id=:id AND status="completed" LIMIT 1');
            $stmt->execute(['id' => $jobId]);
            $orderItemId = (int)$stmt->fetchColumn();
            if ($orderItemId > 0) {
                if (hivenest_bridge_column_exists($db, 'order_items', 'service_ready_notified_at')) {
                    $db->prepare('UPDATE order_items SET service_ready_notified_at=NULL WHERE id=:id')
                        ->execute(['id' => $orderItemId]);
                }
                $sent = hivenest_send_service_ready_email($orderItemId, ['resent_by_admin' => true]);
                $messageType = $sent ? 'success' : 'error';
                $message = $sent ? 'Service-ready email resent.' : 'Service-ready email failed. Check PHP mail/server logs.';
            } else {
                $messageType = 'error';
                $message = 'This job has no order item to email.';
            }
        }
    } elseif ($action === 'resolve_review_job' && $db) {
        $jobId = (int)($_POST['job_id'] ?? 0);
        $resolutionNote = trim((string)($_POST['resolution_note'] ?? ''));
        if ($jobId > 0) {
            $jobStmt = $db->prepare('SELECT * FROM provisioning_jobs WHERE id=:id AND status IN ("manual_review","failed","retry") LIMIT 1');
            $jobStmt->execute(['id' => $jobId]);
            $job = $jobStmt->fetch(PDO::FETCH_ASSOC);
            if ($job) {
                $response = [
                    'resolved_by_admin' => true,
                    'resolved_by' => $admin['username'] ?? 'admin',
                    'resolution_note' => $resolutionNote,
                    'resolved_at' => gmdate('c'),
                ];
                $db->prepare("
                    UPDATE provisioning_jobs
                    SET status='completed',
                        response_payload=:response_payload,
                        error_message=NULL
                    WHERE id=:id
                ")->execute([
                    'response_payload' => json_encode($response, JSON_UNESCAPED_SLASHES),
                    'id' => $jobId,
                ]);
                if (!empty($job['order_item_id'])) {
                    $db->prepare("
                        UPDATE order_items
                        SET provisioning_status='completed',
                            provisioning_error=NULL
                        WHERE id=:order_item_id
                    ")->execute(['order_item_id' => (int)$job['order_item_id']]);
                }
                hivenest_refresh_order_provisioning_status($db, (int)$job['order_id']);
                $messageType = 'success';
                $message = 'Review job #' . $jobId . ' resolved.';
            }
        }
    } elseif ($action === 'save_mapping' && $db) {
        $mappingId = (int)($_POST['mapping_id'] ?? 0);
        $productId = (int)($_POST['product_id'] ?? 0);
        $productSku = trim((string)($_POST['product_sku'] ?? ''));
        $jobType = trim((string)($_POST['job_type'] ?? ''));
        $endpoint = trim((string)($_POST['provider_endpoint'] ?? ''));
        $planId = trim((string)($_POST['provider_plan_id'] ?? ''));
        $region = trim((string)($_POST['provider_region'] ?? ''));
        $months = max(1, min(120, (int)($_POST['default_months'] ?? 12)));
        $requiresDomain = !empty($_POST['requires_domain']) ? 1 : 0;
        $autoRenew = !empty($_POST['auto_renew']) ? 1 : 0;
        $isActive = !empty($_POST['is_active']) ? 1 : 0;
        $extraParamsRaw = trim((string)($_POST['extra_params'] ?? ''));
        $allowedJobTypes = ['hosting_setup','email_setup','ssl_setup','backup_setup','security_setup'];

        if ($productSku === '' || $endpoint === '' || $planId === '' || !in_array($jobType, $allowedJobTypes, true)) {
            $messageType = 'error';
            $message = 'Provider mapping needs SKU, job type, endpoint and plan ID.';
        } else {
            $extraParams = null;
            if ($extraParamsRaw !== '') {
                $decoded = json_decode($extraParamsRaw, true);
                if (!is_array($decoded)) {
                    $messageType = 'error';
                    $message = 'Extra params must be valid JSON.';
                } else {
                    $extraParams = json_encode($decoded, JSON_UNESCAPED_SLASHES);
                }
            }
            if ($messageType !== 'error') {
                if ($mappingId > 0) {
                    $stmt = $db->prepare("
                        UPDATE product_provider_mappings
                        SET product_id=:product_id,
                            product_sku=:product_sku,
                            job_type=:job_type,
                            provider_endpoint=:provider_endpoint,
                            provider_plan_id=:provider_plan_id,
                            provider_region=:provider_region,
                            default_months=:default_months,
                            requires_domain=:requires_domain,
                            auto_renew=:auto_renew,
                            extra_params=:extra_params,
                            is_active=:is_active
                        WHERE id=:id
                    ");
                    $stmt->execute([
                        'product_id' => $productId > 0 ? $productId : null,
                        'product_sku' => $productSku,
                        'job_type' => $jobType,
                        'provider_endpoint' => $endpoint,
                        'provider_plan_id' => $planId,
                        'provider_region' => $region !== '' ? $region : null,
                        'default_months' => $months,
                        'requires_domain' => $requiresDomain,
                        'auto_renew' => $autoRenew,
                        'extra_params' => $extraParams,
                        'is_active' => $isActive,
                        'id' => $mappingId,
                    ]);
                    $message = 'Provider mapping updated.';
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO product_provider_mappings
                            (uuid, product_id, product_sku, job_type, provider_endpoint, provider_plan_id, provider_region, default_months, requires_domain, auto_renew, extra_params, is_active)
                        VALUES
                            (:uuid, :product_id, :product_sku, :job_type, :provider_endpoint, :provider_plan_id, :provider_region, :default_months, :requires_domain, :auto_renew, :extra_params, :is_active)
                    ");
                    $stmt->execute([
                        'uuid' => hivenest_bridge_uuid(),
                        'product_id' => $productId > 0 ? $productId : null,
                        'product_sku' => $productSku,
                        'job_type' => $jobType,
                        'provider_endpoint' => $endpoint,
                        'provider_plan_id' => $planId,
                        'provider_region' => $region !== '' ? $region : null,
                        'default_months' => $months,
                        'requires_domain' => $requiresDomain,
                        'auto_renew' => $autoRenew,
                        'extra_params' => $extraParams,
                        'is_active' => $isActive,
                    ]);
                    $message = 'Provider mapping added.';
                }
                $messageType = 'success';
            }
        }
    } elseif ($action === 'toggle_mapping' && $db) {
        $mappingId = (int)($_POST['mapping_id'] ?? 0);
        if ($mappingId > 0) {
            $stmt = $db->prepare('UPDATE product_provider_mappings SET is_active = IF(is_active=1,0,1) WHERE id=:id');
            $stmt->execute(['id' => $mappingId]);
            $messageType = 'success';
            $message = 'Provider mapping visibility changed.';
        }
    }

    if (!in_array($action, ['lookup_plan_details', 'lookup_product_order'], true)) {
        hpn_redirect_after_post($message, $messageType);
    }
}

if ($db && hivenest_bridge_schema_ready($db)) {
    try {
        $privacyOrders = $db->query("
            SELECT DISTINCT order_id
            FROM provisioning_jobs
            WHERE job_type='manual_queue'
              AND status IN ('pending','manual_review','retry')
              AND (
                  LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(request_payload, '$.product_name')), '')) LIKE '%privacy%'
                  OR LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(request_payload, '$.sku')), '')) = 'domain-privacy'
              )
        ")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $db->exec("
            UPDATE provisioning_jobs pj
            LEFT JOIN order_items oi ON oi.id = pj.order_item_id
            SET pj.status='completed',
                pj.error_message=NULL,
                oi.provisioning_status='completed',
                oi.provisioning_error=NULL
            WHERE pj.job_type='manual_queue'
              AND pj.status IN ('pending','manual_review','retry')
              AND (
                  LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(pj.request_payload, '$.product_name')), '')) LIKE '%privacy%'
                  OR LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(pj.request_payload, '$.sku')), '')) = 'domain-privacy'
              )
        ");
        foreach ($privacyOrders as $privacyOrderId) {
            hivenest_refresh_order_provisioning_status($db, (int)$privacyOrderId);
        }
        $db->exec("
            UPDATE provisioning_jobs
            SET status='manual_review',
                next_attempt_at=NULL,
                error_message=COALESCE(error_message, 'Team action required for this service. Complete it manually in the provisioning monitor when ready.')
            WHERE status='pending'
              AND job_type IN ('design_queue','marketing_queue','manual_queue')
        ");
        $db->exec("
            UPDATE order_items oi
            INNER JOIN provisioning_jobs pj ON pj.order_item_id = oi.id
            SET oi.provisioning_status='manual_review',
                oi.provisioning_error=COALESCE(oi.provisioning_error, 'Team action required for this service. Complete it manually in the provisioning monitor when ready.')
            WHERE pj.status='manual_review'
              AND pj.job_type IN ('design_queue','marketing_queue','manual_queue')
              AND oi.provisioning_status='pending'
        ");
    } catch (Throwable $ignored) {
    }
}

$statusFilter = (string)($_GET['status'] ?? '');
$allowedStatuses = ['', 'pending', 'processing', 'completed', 'retry', 'failed', 'manual_review'];
if (!in_array($statusFilter, $allowedStatuses, true)) $statusFilter = '';

$stats = [
    'pending' => 0,
    'processing' => 0,
    'completed' => 0,
    'retry' => 0,
    'failed' => 0,
    'manual_review' => 0,
];
$jobs = [];
$transactions = [];
$orders = [];
$mappings = [];
$products = [];
$webhookEvents = [];
$checkoutSessions = [];
$workerRuns = [];
$diagnostics = [];
$schemaReady = false;

if ($db) {
    $schemaReady = hivenest_bridge_schema_ready($db);
    $requiredTables = [
        'payment_gateway_transactions',
        'paypal_checkout_sessions',
        'paypal_webhook_events',
        'provisioning_jobs',
        'provisioning_worker_runs',
        'myorderbox_contacts',
        'product_provider_mappings',
    ];
    foreach ($requiredTables as $table) {
        $diagnostics[] = [
            'label' => 'Table: ' . $table,
            'ok' => hivenest_bridge_table_exists($db, $table),
            'detail' => hivenest_bridge_table_exists($db, $table) ? 'Ready' : 'Missing - run paypal_myorderbox_bridge.sql',
        ];
    }
    $requiredColumns = [
        ['customers', 'myorderbox_customer_id'],
        ['orders', 'provisioning_status'],
        ['order_items', 'service_id'],
        ['order_items', 'provisioning_status'],
        ['order_items', 'service_ready_notified_at'],
        ['domain_registrations', 'provider_order_id'],
    ];
    foreach ($requiredColumns as [$table, $column]) {
        $exists = hivenest_bridge_column_exists($db, $table, $column);
        $diagnostics[] = [
            'label' => 'Column: ' . $table . '.' . $column,
            'ok' => $exists,
            'detail' => $exists ? 'Ready' : 'Missing - run paypal_myorderbox_bridge.sql',
        ];
    }
    $envChecks = [
        'MYORDERBOX_RESELLER_ID' => 'Required for MyOrderBox API',
        'MYORDERBOX_API_KEY' => 'Required for MyOrderBox API',
        'MYORDERBOX_ENV' => 'test or production',
        'PAYPAL_CLIENT_ID' => 'Required for PayPal API',
        'PAYPAL_CLIENT_SECRET' => 'Required for PayPal API',
        'PAYPAL_WEBHOOK_ID' => 'Required for PayPal webhook verification',
        'PROVISIONING_WORKER_TOKEN' => 'Required for web worker URL',
        'PROVISIONING_PROCESS_IMMEDIATELY' => 'Recommended true',
        'MYORDERBOX_DEFAULT_NAMESERVERS' => 'Recommended for domain registration',
    ];
    foreach ($envChecks as $key => $detail) {
        $value = hivenest_bridge_env($key, '');
        $diagnostics[] = [
            'label' => 'Env: ' . $key,
            'ok' => trim($value) !== '',
            'detail' => trim($value) !== '' ? 'Configured' : $detail,
        ];
    }
    $diagnostics[] = [
        'label' => 'PHP cURL extension',
        'ok' => function_exists('curl_init'),
        'detail' => function_exists('curl_init') ? 'Enabled' : 'Required for PayPal/MyOrderBox API calls',
    ];
    $diagnostics[] = [
        'label' => 'PHP mail function',
        'ok' => function_exists('mail'),
        'detail' => function_exists('mail') ? 'Available' : 'Required for order/provisioning emails unless SMTP mailer is added',
    ];
    if ($schemaReady) {
        $balanceCacheSeconds = hpn_env_int('MYORDERBOX_BALANCE_CACHE_SECONDS', 300, 60, 3600);
        $balanceCache = $_SESSION['myorderbox_balance_cache'] ?? null;
        if (
            is_array($balanceCache)
            && isset($balanceCache['checked_at'], $balanceCache['result'])
            && (time() - (int)$balanceCache['checked_at']) < $balanceCacheSeconds
        ) {
            $providerBalance = is_array($balanceCache['result']) ? $balanceCache['result'] : null;
        } else {
            $providerBalance = hivenest_mob_reseller_balance($db);
            $_SESSION['myorderbox_balance_cache'] = [
                'checked_at' => time(),
                'result' => $providerBalance,
            ];
        }

        $lowBalanceThreshold = max(0.0, hpn_env_float('MYORDERBOX_LOW_BALANCE_THRESHOLD', 100.0));
        if (is_array($providerBalance) && !empty($providerBalance['ok']) && (float)$providerBalance['available'] < $lowBalanceThreshold) {
            $balanceLabel = trim((string)$providerBalance['currency']) . ' ' . number_format((float)$providerBalance['available'], 2);
            $operationalAlerts[] = [
                'type' => 'urgent',
                'title' => 'MyOrderBox balance is low',
                'message' => 'Available provider balance is ' . trim($balanceLabel)
                    . '; configured threshold is ' . number_format($lowBalanceThreshold, 2) . '.',
                'link_url' => '/admin/provisioning.php',
                'entity_type' => 'myorderbox_balance',
                'entity_id' => null,
            ];
        }

        $unresolvedMinutes = hpn_env_int('PROVISIONING_UNRESOLVED_ALERT_MINUTES', 60, 15, 10080);
        $unresolvedCutoff = gmdate('Y-m-d H:i:s', time() - ($unresolvedMinutes * 60));
        $unresolvedStmt = $db->prepare("
            SELECT o.id, o.order_number, o.provisioning_status, o.total_amount, o.currency,
                   c.email AS customer_email,
                   TIMESTAMPDIFF(MINUTE, COALESCE(o.processed_at, o.created_at), NOW()) AS age_minutes
            FROM orders o
            INNER JOIN customers c ON c.id=o.customer_id
            WHERE o.payment_status='paid'
              AND COALESCE(o.provisioning_status, 'pending') <> 'completed'
              AND COALESCE(o.processed_at, o.created_at) <= :unresolved_cutoff
            ORDER BY COALESCE(o.processed_at, o.created_at) ASC
            LIMIT 25
        ");
        $unresolvedStmt->execute(['unresolved_cutoff' => $unresolvedCutoff]);
        foreach ($unresolvedStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $unresolvedOrder) {
            $operationalAlerts[] = [
                'type' => 'urgent',
                'title' => 'Paid order needs provisioning attention',
                'message' => (string)$unresolvedOrder['order_number']
                    . ' for ' . (string)$unresolvedOrder['customer_email']
                    . ' has remained ' . (string)$unresolvedOrder['provisioning_status']
                    . ' for ' . (int)$unresolvedOrder['age_minutes'] . ' minutes.',
                'link_url' => '/admin/provisioning.php?order=' . rawurlencode((string)$unresolvedOrder['order_number']),
                'entity_type' => 'unresolved_paid_order',
                'entity_id' => (int)$unresolvedOrder['id'],
            ];
        }
        hpn_publish_operational_alerts($db, $operationalAlerts);

        foreach ($db->query("SELECT status, COUNT(*) total FROM provisioning_jobs GROUP BY status")->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $stats[(string)$row['status']] = (int)$row['total'];
        }

        $where = $statusFilter !== '' ? 'WHERE j.status = :status' : '';
        $jobSql = "
            SELECT
                j.*,
                o.order_number,
                o.payment_status,
                o.provisioning_status AS order_provisioning_status,
                c.email AS customer_email,
                c.myorderbox_customer_id,
                oi.product_name,
                oi.domain_name,
                oi.provisioning_status AS item_provisioning_status,
                oi.provider_order_id,
                oi.provider_action_id
            FROM provisioning_jobs j
            INNER JOIN orders o ON o.id = j.order_id
            INNER JOIN customers c ON c.id = j.customer_id
            LEFT JOIN order_items oi ON oi.id = j.order_item_id
            {$where}
            ORDER BY
                CASE j.status
                    WHEN 'manual_review' THEN 0
                    WHEN 'failed' THEN 1
                    WHEN 'retry' THEN 2
                    WHEN 'pending' THEN 3
                    WHEN 'processing' THEN 4
                    ELSE 5
                END,
                j.updated_at DESC,
                j.id DESC
            LIMIT 100
        ";
        $stmt = $db->prepare($jobSql);
        $stmt->execute($statusFilter !== '' ? ['status' => $statusFilter] : []);
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $transactions = $db->query("
            SELECT pgt.*, o.order_number, c.email customer_email
            FROM payment_gateway_transactions pgt
            INNER JOIN orders o ON o.id=pgt.order_id
            INNER JOIN customers c ON c.id=pgt.customer_id
            ORDER BY pgt.id DESC
            LIMIT 20
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $orders = $db->query("
            SELECT o.id, o.order_number, o.payment_status, o.provisioning_status, o.total_amount, o.currency, o.myorderbox_transaction_id, c.email customer_email
            FROM orders o
            INNER JOIN customers c ON c.id=o.customer_id
            WHERE o.payment_status='paid'
            ORDER BY o.id DESC
            LIMIT 20
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (hivenest_bridge_table_exists($db, 'customer_service_onboarding')) {
            $onboardingSubmissions = $db->query("
                SELECT
                    cso.*,
                    c.email AS customer_email,
                    s.service_name,
                    s.domain_name,
                    o.order_number
                FROM customer_service_onboarding cso
                INNER JOIN customers c ON c.id = cso.customer_id
                LEFT JOIN services s ON s.id = cso.service_id
                LEFT JOIN orders o ON o.id = cso.order_id
                ORDER BY cso.id DESC
                LIMIT 20
            ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if (hivenest_bridge_table_exists($db, 'paypal_webhook_events')) {
            $webhookEvents = $db->query("
                SELECT *
                FROM paypal_webhook_events
                ORDER BY id DESC
                LIMIT 20
            ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if (hivenest_bridge_table_exists($db, 'paypal_checkout_sessions')) {
            $checkoutSessions = $db->query("
                SELECT pcs.*, c.email customer_email
                FROM paypal_checkout_sessions pcs
                INNER JOIN customers c ON c.id=pcs.customer_id
                ORDER BY pcs.id DESC
                LIMIT 20
            ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if (hivenest_bridge_table_exists($db, 'provisioning_worker_runs')) {
            $workerRuns = $db->query("
                SELECT *
                FROM provisioning_worker_runs
                ORDER BY id DESC
                LIMIT 20
            ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $products = $db->query("
            SELECT id, name, slug, product_type
            FROM products
            WHERE is_active=1
            ORDER BY product_type, name
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $mappings = $db->query("
            SELECT ppm.*, p.name product_name, p.slug product_slug
            FROM product_provider_mappings ppm
            LEFT JOIN products p ON p.id=ppm.product_id
            ORDER BY ppm.is_active DESC, ppm.job_type, ppm.product_sku
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $coverageRows = $db->query("
            SELECT
                p.id product_id, p.name product_name, p.slug product_slug,
                p.product_type, p.service_type, p.page_url,
                pp.id pricing_id, pp.tier_name, pp.tier_slug, pp.billing_cycle,
                pp.price, pp.bundle_items,
                pc.name category_name
            FROM products p
            INNER JOIN product_pricing pp ON pp.product_id=p.id AND pp.is_active=1
            LEFT JOIN product_categories pc ON pc.id=p.category_id
            WHERE p.is_active=1
              AND (pc.id IS NULL OR pc.is_active=1)
            ORDER BY pc.sort_order, p.sort_order, pp.sort_order, pp.id
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($coverageRows as $coverageRow) {
            $sku = trim((string)$coverageRow['product_slug']) . '--' . trim((string)$coverageRow['tier_slug']);
            $baseSku = trim((string)$coverageRow['product_slug']);
            $serviceType = trim((string)($coverageRow['service_type'] ?: $coverageRow['product_type']));
            $jobType = hivenest_bridge_job_type($serviceType, $sku, (string)$coverageRow['product_name']);
            $productType = strtolower(trim((string)$coverageRow['product_type']));
            $bundleItems = json_decode((string)($coverageRow['bundle_items'] ?? ''), true);
            $status = 'missing';
            $statusLabel = 'Mapping missing';
            $mapping = null;
            if ($productType === 'domain') {
                $status = 'builtin';
                $statusLabel = 'Domain registration bridge';
                $jobType = 'domain_registration';
            } elseif (is_array($bundleItems) && $bundleItems) {
                $status = 'team';
                $statusLabel = 'Bundle child jobs';
                $jobType = 'bundle';
            } elseif (in_array($jobType, ['design_queue','marketing_queue','manual_queue'], true)) {
                $status = 'team';
                $statusLabel = 'HiveNest team workflow';
            } else {
                foreach ($mappings as $candidate) {
                    if ((string)$candidate['job_type'] !== $jobType) continue;
                    if (!in_array((string)$candidate['product_sku'], [$sku, $baseSku], true)) continue;
                    if ((int)$candidate['is_active'] === 1) {
                        $mapping = $candidate;
                        $status = 'mapped';
                        $statusLabel = (string)$candidate['product_sku'] === $sku ? 'Exact active mapping' : 'Active base mapping';
                        break;
                    }
                    if ($mapping === null) {
                        $mapping = $candidate;
                        $statusLabel = 'Mapping is inactive';
                    }
                }
            }
            $coverageSummary['visible']++;
            if (in_array($jobType, ['hosting_setup','email_setup','ssl_setup','backup_setup','security_setup'], true)) {
                $coverageSummary['provider_required']++;
            }
            if (isset($coverageSummary[$status])) $coverageSummary[$status]++;
            $mappingCoverage[] = $coverageRow + [
                'sku' => $sku,
                'job_type' => $jobType,
                'coverage_status' => $status,
                'coverage_label' => $statusLabel,
                'mapping' => $mapping,
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provisioning Monitor | HiveNest Admin</title>
    <style>
        body { margin:0; font-family: Arial, sans-serif; background:#f4f7fb; color:#101323; }
        .wrap { padding:24px; }
        .hero { background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; border-radius:10px; padding:28px; display:flex; justify-content:space-between; align-items:center; gap:16px; }
        .hero h1 { margin:0 0 8px; font-size:30px; }
        .hero p { margin:0; opacity:.9; }
        .btn { border:0; border-radius:7px; padding:12px 16px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-primary { background:#1d8cf8; color:#fff; }
        .btn-green { background:#2fb344; color:#fff; }
        .btn-warn { background:#f59f00; color:#101323; }
        .btn-red { background:#fa5252; color:#fff; }
        .btn-muted { background:#e9ecef; color:#101323; }
        .panel { background:#fff; border-radius:10px; padding:20px; margin-top:20px; box-shadow:0 8px 24px rgba(15,23,42,.08); }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; }
        .diag-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:10px; }
        .diag { border:1px solid #e2e8f0; border-radius:8px; padding:12px; background:#f8fafc; }
        .diag.ok { border-color:#8ce99a; background:#ebfbee; }
        .diag.bad { border-color:#ffa8a8; background:#fff5f5; }
        label { display:block; font-size:12px; color:#475569; font-weight:700; margin-bottom:5px; text-transform:uppercase; }
        input[type=text], input[type=number], select, textarea { box-sizing:border-box; width:100%; padding:11px; border:1px solid #cbd5e1; border-radius:7px; background:#fff; }
        textarea { min-height:80px; resize:vertical; font-family:Consolas,monospace; }
        .stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:14px; margin-top:20px; }
        .stat { background:#fff; border-radius:10px; padding:18px; box-shadow:0 8px 24px rgba(15,23,42,.08); text-align:center; }
        .stat strong { display:block; font-size:28px; color:#667eea; }
        .toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between; }
        .filters { display:flex; flex-wrap:wrap; gap:8px; }
        .badge { border-radius:999px; padding:5px 10px; font-size:12px; font-weight:700; text-transform:uppercase; display:inline-block; }
        .badge.ok { background:#d3f9d8; color:#087f23; }
        .badge.pending { background:#d0ebff; color:#0b5ed7; }
        .badge.warn { background:#fff3bf; color:#ad6800; }
        .badge.manual { background:#ffe3e3; color:#c92a2a; }
        .badge.bad { background:#ffc9c9; color:#a51111; }
        .badge.neutral { background:#e9ecef; color:#495057; }
        table { width:100%; border-collapse:collapse; margin-top:14px; }
        th, td { text-align:left; padding:12px; border-bottom:1px solid #edf2f7; vertical-align:top; }
        th { background:#f8fafc; color:#334155; font-size:13px; text-transform:uppercase; }
        .small { font-size:12px; color:#64748b; }
        .mono { font-family: Consolas, monospace; font-size:12px; }
        .message { padding:14px 16px; border-radius:8px; margin-top:20px; }
        .message.success { background:#d3f9d8; color:#087f23; }
        .message.error { background:#ffe3e3; color:#c92a2a; }
        .message.info { background:#d0ebff; color:#0b5ed7; }
        .errorbox { background:#ffe3e3; color:#c92a2a; padding:18px; border-radius:10px; margin-top:20px; }
        .actions { display:flex; flex-wrap:wrap; gap:6px; }
        .complete-form { display:grid; grid-template-columns:1fr; gap:5px; margin-top:6px; min-width:180px; }
        .complete-form input { padding:7px; font-size:12px; }
        .resolve-form { display:grid; grid-template-columns:1fr; gap:5px; margin-top:6px; min-width:200px; }
        .resolve-form textarea { min-height:54px; font-size:12px; }
        .order-link { color:#0369a1; font-weight:700; text-decoration:none; }
        .order-link:hover, .order-link:focus { color:#7c3aed; text-decoration:underline; }
        .inline-checks { display:flex; flex-wrap:wrap; gap:14px; align-items:center; margin-top:22px; }
        .inline-checks label { display:flex; gap:6px; align-items:center; margin:0; text-transform:none; }
        .worker-limit { width:90px !important; }
        .lookup-form { display:grid; grid-template-columns:minmax(240px,420px) auto; gap:10px; align-items:end; }
        .json-pre { max-width:520px; max-height:220px; overflow:auto; margin:0; white-space:pre-wrap; font-size:11px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:7px; padding:10px; }
        .bad-text { color:#c92a2a; }
        @media (max-width: 900px) { .hero, .toolbar { align-items:flex-start; flex-direction:column; } table { font-size:13px; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="hero">
        <div>
            <h1>⚙️ Provisioning Monitor</h1>
            <p>PayPal → MyOrderBox jobs, service creation, provider crediting and manual review queue.</p>
        </div>
        <div>
            <a class="btn btn-muted" href="productp.php">← Products</a>
            <a class="btn btn-red" href="?logout">Logout</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="message <?php echo hpn_h($messageType); ?>"><?php echo hpn_h($message); ?></div>
    <?php endif; ?>

    <?php if (!$db): ?>
        <div class="errorbox">Database connection unavailable. Check Backend/.env and PHP error logs.</div>
    <?php else: ?>
        <div class="panel">
            <h2 style="margin-top:0;">Bridge Diagnostics</h2>
            <p class="small">This checks whether the PayPal → MyOrderBox bridge is ready on this server. Secrets are not displayed.</p>
            <div class="diag-grid">
                <?php foreach ($diagnostics as $diag): ?>
                    <div class="diag <?php echo !empty($diag['ok']) ? 'ok' : 'bad'; ?>">
                        <strong><?php echo hpn_h($diag['label']); ?></strong><br>
                        <span class="badge <?php echo !empty($diag['ok']) ? 'ok' : 'bad'; ?>"><?php echo !empty($diag['ok']) ? 'PASS' : 'FIX'; ?></span>
                        <span class="small"><?php echo hpn_h($diag['detail']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (!$schemaReady): ?>
            <div class="errorbox">
                Provisioning schema is not ready. Run <span class="mono">Database/paypal_myorderbox_bridge.sql</span> on the live database first.
            </div>
        <?php else: ?>
        <div class="panel">
            <h2 style="margin-top:0;">Operational Alerts</h2>
            <?php if (is_array($providerBalance) && !empty($providerBalance['ok'])): ?>
                <p class="small">
                    MyOrderBox available balance:
                    <strong><?php echo hpn_h(trim((string)$providerBalance['currency']) . ' ' . number_format((float)$providerBalance['available'], 2)); ?></strong>
                    <?php if ((float)$providerBalance['locked'] !== 0.0): ?>
                        · Locked: <?php echo hpn_h(number_format((float)$providerBalance['locked'], 2)); ?>
                    <?php endif; ?>
                </p>
            <?php elseif (is_array($providerBalance)): ?>
                <div class="message error">Balance check unavailable: <?php echo hpn_h($providerBalance['error'] ?? 'Unknown provider response'); ?></div>
            <?php endif; ?>
            <?php if (!$operationalAlerts): ?>
                <div class="diag ok"><strong>No active provisioning alerts.</strong></div>
            <?php else: ?>
                <div class="diag-grid">
                    <?php foreach ($operationalAlerts as $alert): ?>
                        <div class="diag bad">
                            <strong><?php echo hpn_h($alert['title']); ?></strong><br>
                            <span class="small"><?php echo hpn_h($alert['message']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="stats">
            <?php foreach ($stats as $status => $count): ?>
                <div class="stat">
                    <strong><?php echo (int)$count; ?></strong>
                    <span class="badge <?php echo hpn_badge_class($status); ?>"><?php echo hpn_h($status); ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="panel">
            <div class="toolbar">
                <div>
                    <h2 style="margin:0;">Provisioning Jobs</h2>
                    <div class="small">Showing latest 100 jobs<?php echo $statusFilter ? ' filtered by ' . hpn_h($statusFilter) : ''; ?>.</div>
                </div>
                <form method="post" style="display:flex; gap:8px; align-items:center;">
                    <input type="hidden" name="csrf_token" value="<?php echo hpn_h($csrf); ?>">
                    <input type="hidden" name="action" value="process_jobs">
                    <input class="worker-limit" type="number" name="limit" value="10" min="1" max="50">
                    <button class="btn btn-green" type="submit">Process Jobs Now</button>
                </form>
            </div>

            <div class="filters" style="margin-top:16px;">
                <?php foreach ($allowedStatuses as $filter): ?>
                    <a class="btn <?php echo $filter === $statusFilter ? 'btn-primary' : 'btn-muted'; ?>" href="?status=<?php echo urlencode($filter); ?>">
                        <?php echo $filter === '' ? 'All' : hpn_h($filter); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div style="overflow:auto;">
                <table>
                    <thead>
                    <tr>
                        <th>Job</th>
                        <th>Order / Customer</th>
                        <th>Product</th>
                        <th>Status</th>
                        <th>Provider</th>
                        <th>Error</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$jobs): ?>
                        <tr><td colspan="7" class="small">No provisioning jobs found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($jobs as $job): ?>
                        <?php
                            $jobPayload = json_decode((string)($job['request_payload'] ?? ''), true);
                            $jobPayload = is_array($jobPayload) ? $jobPayload : [];
                            $displayProduct = trim((string)($jobPayload['product_name'] ?? ''));
                            if ($displayProduct === '') {
                                $displayProduct = (string)($job['product_name'] ?: 'Order-level job');
                            }
                            $displayDomain = trim((string)($jobPayload['domain_name'] ?? ''));
                            if ($displayDomain === '') {
                                $displayDomain = (string)($job['domain_name'] ?? '');
                            }
                            $displaySku = trim((string)($jobPayload['sku'] ?? ''));
                            $displayParent = trim((string)($jobPayload['bundle_parent_name'] ?? ''));
                            $displayTerm = trim((string)($jobPayload['term_months'] ?? ''));
                        ?>
                        <tr>
                            <td>
                                <strong>#<?php echo (int)$job['id']; ?></strong><br>
                                <span class="mono"><?php echo hpn_h($job['job_type']); ?></span><br>
                                <span class="small">Attempts: <?php echo (int)$job['attempts']; ?> / <?php echo (int)$job['max_attempts']; ?></span>
                            </td>
                            <td>
                                <?php if (!empty($job['order_number'])): ?>
                                    <a href="../invoice.php?order=<?php echo rawurlencode((string)$job['order_number']); ?>" target="_blank" rel="noopener" class="order-link"><strong><?php echo hpn_h($job['order_number']); ?></strong></a><br>
                                <?php else: ?>
                                    <strong>-</strong><br>
                                <?php endif; ?>
                                <span class="small"><?php echo hpn_h($job['customer_email']); ?></span><br>
                                <span class="small">MOB: <?php echo hpn_h($job['myorderbox_customer_id'] ?: 'not synced'); ?></span>
                            </td>
                            <td>
                                <?php echo hpn_h($displayProduct); ?><br>
                                <?php if ($displayParent !== ''): ?><span class="small">Bundle: <?php echo hpn_h($displayParent); ?></span><br><?php endif; ?>
                                <?php if ($displaySku !== ''): ?><span class="small mono"><?php echo hpn_h($displaySku); ?></span><br><?php endif; ?>
                                <?php if ($displayDomain !== ''): ?><span class="mono"><?php echo hpn_h($displayDomain); ?></span><br><?php endif; ?>
                                <?php if ($displayTerm !== ''): ?><span class="small">Term: <?php echo hpn_h($displayTerm); ?> month<?php echo $displayTerm === '1' ? '' : 's'; ?></span><?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo hpn_badge_class((string)$job['status']); ?>"><?php echo hpn_h($job['status']); ?></span><br>
                                <span class="small">Item: <?php echo hpn_h($job['item_provisioning_status'] ?: '-'); ?></span>
                            </td>
                            <td>
                                <span class="mono"><?php echo hpn_h($job['provider']); ?></span><br>
                                <span class="small">Order: <?php echo hpn_h($job['provider_order_id'] ?: '-'); ?></span><br>
                                <span class="small">Action: <?php echo hpn_h($job['provider_action_id'] ?: '-'); ?></span>
                            </td>
                            <td style="max-width:320px;">
                                <span class="small"><?php echo hpn_h($job['error_message'] ?: '-'); ?></span>
                            </td>
                            <td>
                                <div class="actions">
                                    <?php if (in_array($job['status'], ['retry','failed','manual_review'], true)): ?>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?php echo hpn_h($csrf); ?>">
                                            <input type="hidden" name="action" value="retry_job">
                                            <input type="hidden" name="job_id" value="<?php echo (int)$job['id']; ?>">
                                            <button class="btn btn-green" type="submit">Retry</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($job['status'] !== 'manual_review'): ?>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?php echo hpn_h($csrf); ?>">
                                            <input type="hidden" name="action" value="mark_manual">
                                            <input type="hidden" name="job_id" value="<?php echo (int)$job['id']; ?>">
                                            <button class="btn btn-warn" type="submit">Manual</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (in_array($job['status'], ['retry','failed','manual_review'], true)): ?>
                                        <form method="post" class="complete-form">
                                            <input type="hidden" name="csrf_token" value="<?php echo hpn_h($csrf); ?>">
                                            <input type="hidden" name="action" value="complete_manual_job">
                                            <input type="hidden" name="job_id" value="<?php echo (int)$job['id']; ?>">
                                            <input type="text" name="provider_order_id" placeholder="Provider order ID">
                                            <input type="text" name="provider_action_id" placeholder="Provider action ID">
                                            <input type="text" name="provider_entity_id" placeholder="Provider entity ID">
                                            <button class="btn btn-primary" type="submit">Complete Manual</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($job['status'] === 'completed' && !empty($job['order_item_id'])): ?>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?php echo hpn_h($csrf); ?>">
                                            <input type="hidden" name="action" value="resend_ready_email">
                                            <input type="hidden" name="job_id" value="<?php echo (int)$job['id']; ?>">
                                            <button class="btn btn-muted" type="submit">Resend Ready Email</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (in_array($job['status'], ['manual_review','failed','retry'], true)): ?>
                                        <form method="post" class="resolve-form">
                                            <input type="hidden" name="csrf_token" value="<?php echo hpn_h($csrf); ?>">
                                            <input type="hidden" name="action" value="resolve_review_job">
                                            <input type="hidden" name="job_id" value="<?php echo (int)$job['id']; ?>">
                                            <textarea name="resolution_note" placeholder="Resolution note, e.g. refund reviewed, client contacted, no service action needed"></textarea>
                                            <button class="btn btn-green" type="submit">Resolve Review</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <h2 style="margin-top:0;">Recent Payment Gateway Transactions</h2>
            <div style="overflow:auto;">
                <table>
                    <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Capture</th>
                        <th>Amount</th>
                        <th>Provider Credit</th>
                        <th>Error</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$transactions): ?><tr><td colspan="6" class="small">No payment transactions recorded yet.</td></tr><?php endif; ?>
                    <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td>
                                <?php if (!empty($tx['order_number'])): ?>
                                    <a href="../invoice.php?order=<?php echo rawurlencode((string)$tx['order_number']); ?>" target="_blank" rel="noopener" class="order-link"><?php echo hpn_h($tx['order_number']); ?></a>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                            <td><?php echo hpn_h($tx['customer_email']); ?></td>
                            <td class="mono"><?php echo hpn_h($tx['gateway_capture_id']); ?></td>
                            <td><?php echo hpn_h($tx['currency']); ?> <?php echo number_format((float)$tx['amount'], 2); ?></td>
                            <td><span class="badge <?php echo hpn_badge_class((string)$tx['provider_credit_status']); ?>"><?php echo hpn_h($tx['provider_credit_status']); ?></span></td>
                            <td class="small"><?php echo hpn_h($tx['error_message'] ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <h2 style="margin-top:0;">Recent Paid Orders</h2>
            <div style="overflow:auto;">
                <table>
                    <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Provisioning</th>
                        <th>MyOrderBox Tx</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>
                                <?php if (!empty($order['order_number'])): ?>
                                    <a href="../invoice.php?order=<?php echo rawurlencode((string)$order['order_number']); ?>" target="_blank" rel="noopener" class="order-link"><?php echo hpn_h($order['order_number']); ?></a>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                            <td><?php echo hpn_h($order['customer_email']); ?></td>
                            <td><?php echo hpn_h($order['currency']); ?> <?php echo number_format((float)$order['total_amount'], 2); ?></td>
                            <td><span class="badge <?php echo hpn_badge_class((string)$order['payment_status']); ?>"><?php echo hpn_h($order['payment_status']); ?></span></td>
                            <td><span class="badge <?php echo hpn_badge_class((string)$order['provisioning_status']); ?>"><?php echo hpn_h($order['provisioning_status']); ?></span></td>
                            <td class="mono"><?php echo hpn_h($order['myorderbox_transaction_id'] ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <h2 style="margin-top:0;">Client Onboarding Submissions</h2>
            <p class="small">These are requirements submitted from the client portal for design, marketing, manual, or bundled services.</p>
            <div style="overflow:auto;">
                <table>
                    <thead>
                    <tr>
                        <th>Submitted</th>
                        <th>Customer / Order</th>
                        <th>Service</th>
                        <th>Type</th>
                        <th>Brief</th>
                        <th>Files</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$onboardingSubmissions): ?><tr><td colspan="7" class="small">No client onboarding submissions received yet.</td></tr><?php endif; ?>
                    <?php foreach ($onboardingSubmissions as $submission): ?>
                        <?php
                            $brief = json_decode((string)($submission['payload'] ?? ''), true);
                            if (!is_array($brief)) $brief = [];
                            $files = json_decode((string)($submission['uploaded_files'] ?? ''), true);
                            if (!is_array($files)) $files = [];
                            $fileCount = count(array_filter($files, static fn($file) => is_array($file) && empty($file['error'])));
                        ?>
                        <tr>
                            <td>
                                <span class="small"><?php echo hpn_h($submission['created_at']); ?></span><br>
                                <span class="mono">#<?php echo (int)$submission['id']; ?></span>
                            </td>
                            <td>
                                <?php echo hpn_h($submission['customer_email']); ?><br>
                                <span class="small">Order:
                                    <?php if (!empty($submission['order_number'])): ?>
                                        <a href="../invoice.php?order=<?php echo rawurlencode((string)$submission['order_number']); ?>" target="_blank" rel="noopener" class="order-link"><?php echo hpn_h($submission['order_number']); ?></a>
                                    <?php else: ?>-<?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <?php echo hpn_h($submission['service_name'] ?: 'Service'); ?><br>
                                <span class="small"><?php echo hpn_h($submission['domain_name'] ?: '-'); ?></span>
                            </td>
                            <td><span class="badge neutral"><?php echo hpn_h($submission['onboarding_type']); ?></span></td>
                            <td class="small" style="min-width:260px;">
                                <strong><?php echo hpn_h($brief['business_name'] ?? ''); ?></strong><br>
                                <?php echo hpn_h(substr((string)($brief['goals'] ?? ''), 0, 260)); ?><?php echo strlen((string)($brief['goals'] ?? '')) > 260 ? '...' : ''; ?><br>
                                <?php if (!empty($brief['deadline'])): ?><span class="small">Deadline: <?php echo hpn_h($brief['deadline']); ?></span><?php endif; ?>
                            </td>
                            <td class="small">
                                <?php echo (int)$fileCount; ?> file(s)
                                <?php foreach (array_slice($files, 0, 3) as $file): ?>
                                    <?php if (!is_array($file)) continue; ?>
                                    <br><?php echo hpn_h($file['original_name'] ?? ($file['error'] ?? '-')); ?>
                                <?php endforeach; ?>
                            </td>
                            <td><span class="badge <?php echo hpn_badge_class((string)$submission['status']); ?>"><?php echo hpn_h($submission['status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <h2 style="margin-top:0;">Recent PayPal Webhooks</h2>
            <p class="small">Webhook URL: <span class="mono">https://hivenest.holohive.co.za/api/paypal_webhook.php</span></p>
            <div style="overflow:auto;">
                <table>
                    <thead>
                    <tr>
                        <th>Event</th>
                        <th>Type</th>
                        <th>PayPal IDs</th>
                        <th>Verification</th>
                        <th>Processing</th>
                        <th>Error</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$webhookEvents): ?><tr><td colspan="6" class="small">No PayPal webhook events received yet.</td></tr><?php endif; ?>
                    <?php foreach ($webhookEvents as $event): ?>
                        <tr>
                            <td>
                                <span class="mono"><?php echo hpn_h($event['event_id']); ?></span><br>
                                <span class="small"><?php echo hpn_h($event['created_at']); ?></span>
                            </td>
                            <td><?php echo hpn_h($event['event_type']); ?><br><span class="small"><?php echo hpn_h($event['resource_type']); ?></span></td>
                            <td>
                                <span class="small">Order: <?php echo hpn_h($event['paypal_order_id'] ?: '-'); ?></span><br>
                                <span class="small">Capture: <?php echo hpn_h($event['paypal_capture_id'] ?: '-'); ?></span>
                            </td>
                            <td><span class="badge <?php echo hpn_badge_class((string)$event['verification_status']); ?>"><?php echo hpn_h($event['verification_status']); ?></span></td>
                            <td><span class="badge <?php echo hpn_badge_class((string)$event['processing_status']); ?>"><?php echo hpn_h($event['processing_status']); ?></span></td>
                            <td class="small"><?php echo hpn_h($event['error_message'] ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <h2 style="margin-top:0;">Recent PayPal Checkout Sessions</h2>
            <p class="small">These snapshots allow webhook recovery if the browser closes after PayPal captures payment.</p>
            <div style="overflow:auto;">
                <table>
                    <thead>
                    <tr>
                        <th>PayPal Order</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>HiveNest Order</th>
                        <th>Capture</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$checkoutSessions): ?><tr><td colspan="6" class="small">No PayPal checkout sessions recorded yet.</td></tr><?php endif; ?>
                    <?php foreach ($checkoutSessions as $session): ?>
                        <tr>
                            <td>
                                <span class="mono"><?php echo hpn_h($session['paypal_order_id']); ?></span><br>
                                <span class="small"><?php echo hpn_h($session['created_at']); ?></span>
                            </td>
                            <td><?php echo hpn_h($session['customer_email']); ?></td>
                            <td><?php echo hpn_h($session['currency']); ?> <?php echo number_format((float)$session['amount'], 2); ?></td>
                            <td><span class="badge <?php echo hpn_badge_class((string)$session['status']); ?>"><?php echo hpn_h($session['status']); ?></span></td>
                            <td>
                                <?php if (!empty($session['hivenest_order_number'])): ?>
                                    <a href="../invoice.php?order=<?php echo rawurlencode((string)$session['hivenest_order_number']); ?>" target="_blank" rel="noopener" class="order-link"><?php echo hpn_h($session['hivenest_order_number']); ?></a>
                                <?php else: ?>-<?php endif; ?><br>
                                <span class="small">ID: <?php echo hpn_h($session['hivenest_order_id'] ?: '-'); ?></span>
                            </td>
                            <td class="mono"><?php echo hpn_h($session['paypal_capture_id'] ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <h2 style="margin-top:0;">Recent Worker Runs</h2>
            <p class="small">Use this to confirm checkout, webhook, admin, CLI or cron worker execution.</p>
            <div style="overflow:auto;">
                <table>
                    <thead>
                    <tr>
                        <th>Source</th>
                        <th>Processed</th>
                        <th>Status</th>
                        <th>Started</th>
                        <th>Finished</th>
                        <th>Error</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$workerRuns): ?><tr><td colspan="6" class="small">No worker runs logged yet.</td></tr><?php endif; ?>
                    <?php foreach ($workerRuns as $run): ?>
                        <tr>
                            <td><span class="badge neutral"><?php echo hpn_h($run['trigger_source']); ?></span></td>
                            <td><?php echo (int)$run['processed_count']; ?></td>
                            <td><span class="badge <?php echo (int)$run['ok'] === 1 ? 'ok' : 'bad'; ?>"><?php echo (int)$run['ok'] === 1 ? 'ok' : 'failed'; ?></span></td>
                            <td class="small"><?php echo hpn_h($run['started_at']); ?></td>
                            <td class="small"><?php echo hpn_h($run['finished_at'] ?: '-'); ?></td>
                            <td class="small"><?php echo hpn_h($run['error_message'] ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <h2 style="margin-top:0;">MyOrderBox Plan Lookup</h2>
            <p class="small">
                Paste a MyOrderBox product key, for example <span class="mono">wordpresshostingusa</span>,
                then fetch plan IDs directly from the reseller API. Leave the optional credential fields blank to use the server .env values.
                The optional auth-userid is the numeric MyOrderBox API user/reseller ID that owns the API key — not the email login and not the customer ID.
                For Live API lookups, make sure the hosting server's outbound IP is whitelisted in MyOrderBox Settings → API.
            </p>
            <form method="post" class="lookup-form">
                <input type="hidden" name="csrf_token" value="<?php echo hpn_h($csrf); ?>">
                <input type="hidden" name="action" value="lookup_plan_details">
                <input type="text" name="product_key" placeholder="wordpresshostingusa" value="<?php echo hpn_h($planLookupKey); ?>">
                <select name="mob_lookup_env" aria-label="MyOrderBox lookup environment">
                    <option value="" <?php echo $planLookupEnv === '' ? 'selected' : ''; ?>>Use .env environment</option>
                    <option value="test" <?php echo $planLookupEnv === 'test' ? 'selected' : ''; ?>>Test / demo API</option>
                    <option value="live" <?php echo $planLookupEnv === 'live' ? 'selected' : ''; ?>>Live API</option>
                </select>
                <input type="text" name="mob_auth_userid" placeholder="Optional numeric auth-userid / reseller user ID" value="<?php echo hpn_h($planLookupAuthUserId); ?>">
                <input type="password" name="mob_api_key" placeholder="Optional matching API key">
                <button class="btn btn-primary" type="submit">Fetch Plan Details</button>
            </form>
            <?php if (is_array($planLookupResult)): ?>
                <div style="overflow:auto;margin-top:16px;">
                    <?php if (!empty($planLookupResult['ok'])): ?>
                        <?php
                        $planData = is_array($planLookupResult['data'] ?? null) ? $planLookupResult['data'] : [];
                        $selectedPlans = $planLookupKey !== '' && isset($planData[$planLookupKey]) && is_array($planData[$planLookupKey])
                            ? [$planLookupKey => $planData[$planLookupKey]]
                            : $planData;
                        ?>
                        <table>
                            <thead>
                            <tr>
                                <th>Product Key</th>
                                <th>Plan ID</th>
                                <th>Plan Name</th>
                                <th>Status</th>
                                <th>Raw Details</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($selectedPlans as $productKey => $plans): ?>
                                <?php if (!is_array($plans)): continue; endif; ?>
                                <?php foreach ($plans as $planId => $details): ?>
                                    <?php $details = is_array($details) ? $details : ['value' => $details]; ?>
                                    <tr>
                                        <td class="mono"><?php echo hpn_h($productKey); ?></td>
                                        <td class="mono"><?php echo hpn_h($planId); ?></td>
                                        <td><?php echo hpn_h($details['plan_name'] ?? $details['name'] ?? $details['plan-name'] ?? '-'); ?></td>
                                        <td><span class="badge <?php echo strtolower((string)($details['plan_status'] ?? '')) === 'active' ? 'ok' : 'neutral'; ?>"><?php echo hpn_h($details['plan_status'] ?? $details['status'] ?? '-'); ?></span></td>
                                        <td><pre class="json-pre"><?php echo hpn_h(json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="small bad-text"><?php echo hpn_h($planLookupResult['error'] ?? 'Plan lookup failed.'); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <hr style="border:0;border-top:1px solid #e2e8f0;margin:24px 0;">
            <h3 style="margin-top:0;">Existing MyOrderBox Order Lookup</h3>
            <p class="small">
                If you already have an active MyOrderBox service, paste the product key and its MyOrderBox Order ID
                or domain name. This helps reveal the plan/order fields that can be copied into the mapping.
            </p>
            <form method="post" class="lookup-form">
                <input type="hidden" name="csrf_token" value="<?php echo hpn_h($csrf); ?>">
                <input type="hidden" name="action" value="lookup_product_order">
                <input type="text" name="order_product_key" placeholder="wordpresshostingusa" value="<?php echo hpn_h($orderLookupProductKey); ?>">
                <input type="text" name="mob_order_id" placeholder="Order ID, e.g. 126333676" value="<?php echo hpn_h($orderLookupOrderId); ?>">
                <input type="text" name="mob_domain_name" placeholder="Domain, e.g. example.com" value="<?php echo hpn_h($orderLookupDomain); ?>">
                <button class="btn btn-primary" type="submit">Fetch Existing Order</button>
            </form>
            <?php if (is_array($orderLookupResult)): ?>
                <div style="overflow:auto;margin-top:16px;">
                    <?php if (!empty($orderLookupResult['ok'])): ?>
                        <?php
                        $orderData = is_array($orderLookupResult['data'] ?? null) ? $orderLookupResult['data'] : [];
                        $possiblePlanId = hpn_pick_json_value($orderData, ['plan-id','planid','plan_id','planId','plan']);
                        $possiblePlanName = hpn_pick_json_value($orderData, ['plan-name','planname','plan_name','planName','Plan Name']);
                        $possibleOrderId = hpn_pick_json_value($orderData, ['order-id','orderid','order_id','orderId']);
                        $possibleStatus = hpn_pick_json_value($orderData, ['currentstatus','current-status','status','orderstatus']);
                        ?>
                        <table>
                            <thead>
                            <tr>
                                <th>Product Key</th>
                                <th>Order ID</th>
                                <th>Plan ID</th>
                                <th>Plan Name</th>
                                <th>Status</th>
                                <th>Raw Details</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td class="mono"><?php echo hpn_h($orderLookupProductKey); ?></td>
                                <td class="mono"><?php echo hpn_h($possibleOrderId ?: $orderLookupOrderId ?: '-'); ?></td>
                                <td class="mono"><?php echo hpn_h($possiblePlanId ?: 'Not found in response'); ?></td>
                                <td><?php echo hpn_h($possiblePlanName ?: '-'); ?></td>
                                <td><span class="badge <?php echo hpn_badge_class(strtolower((string)$possibleStatus)); ?>"><?php echo hpn_h($possibleStatus ?: '-'); ?></span></td>
                                <td><pre class="json-pre"><?php echo hpn_h(json_encode($orderData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre></td>
                            </tr>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="small bad-text"><?php echo hpn_h($orderLookupResult['error'] ?? 'Order lookup failed.'); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel" id="provider-coverage">
            <?php
            $coveragePercent = $coverageSummary['provider_required'] > 0
                ? round(($coverageSummary['mapped'] / $coverageSummary['provider_required']) * 100, 1)
                : 100;
            ?>
            <h2 style="margin-top:0;">Visible Catalogue Provider Coverage</h2>
            <p class="small">
                This uses the same SKU and job-type rules as checkout provisioning.
                Only active products and active packages are included.
            </p>
            <div class="grid" style="margin-top:16px;">
                <div class="diag"><strong><?php echo (int)$coverageSummary['visible']; ?></strong><br><span class="small">Visible packages</span></div>
                <div class="diag"><strong><?php echo (int)$coverageSummary['provider_required']; ?></strong><br><span class="small">Require provider mapping</span></div>
                <div class="diag ok"><strong><?php echo (int)$coverageSummary['mapped']; ?></strong><br><span class="small">Actively mapped (<?php echo hpn_h($coveragePercent); ?>%)</span></div>
                <div class="diag <?php echo $coverageSummary['missing'] > 0 ? 'bad' : 'ok'; ?>"><strong><?php echo (int)$coverageSummary['missing']; ?></strong><br><span class="small">Unsafe / missing</span></div>
                <div class="diag"><strong><?php echo (int)$coverageSummary['team']; ?></strong><br><span class="small">HiveNest team or bundle</span></div>
                <div class="diag"><strong><?php echo (int)$coverageSummary['builtin']; ?></strong><br><span class="small">Built-in domain bridge</span></div>
            </div>
            <div style="overflow:auto;margin-top:18px;max-height:620px;">
                <table>
                    <thead>
                    <tr>
                        <th>Category / Page</th>
                        <th>Product Package</th>
                        <th>SKU / Job</th>
                        <th>Coverage</th>
                        <th>Provider Details</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$mappingCoverage): ?><tr><td colspan="5" class="small">No visible packages found.</td></tr><?php endif; ?>
                    <?php foreach ($mappingCoverage as $row): ?>
                        <?php
                        $coverageClass = match ((string)$row['coverage_status']) {
                            'mapped', 'builtin' => 'ok',
                            'team' => 'neutral',
                            default => 'bad',
                        };
                        $coverageMapping = is_array($row['mapping'] ?? null) ? $row['mapping'] : [];
                        ?>
                        <tr>
                            <td>
                                <?php echo hpn_h($row['category_name'] ?: 'Uncategorised'); ?><br>
                                <span class="small mono"><?php echo hpn_h($row['page_url'] ?: '-'); ?></span>
                            </td>
                            <td>
                                <?php echo hpn_h($row['product_name']); ?><br>
                                <strong><?php echo hpn_h($row['tier_name']); ?></strong>
                                <span class="small"> · <?php echo hpn_h($row['billing_cycle']); ?> · USD <?php echo hpn_h(number_format((float)$row['price'], 2)); ?></span>
                            </td>
                            <td>
                                <span class="mono"><?php echo hpn_h($row['sku']); ?></span><br>
                                <span class="badge neutral"><?php echo hpn_h($row['job_type']); ?></span>
                            </td>
                            <td>
                                <span class="badge <?php echo $coverageClass; ?>"><?php echo hpn_h($row['coverage_status']); ?></span><br>
                                <span class="small"><?php echo hpn_h($row['coverage_label']); ?></span>
                            </td>
                            <td>
                                <?php if ($coverageMapping): ?>
                                    <span class="mono"><?php echo hpn_h($coverageMapping['provider_endpoint']); ?></span><br>
                                    <span class="small">Plan <?php echo hpn_h($coverageMapping['provider_plan_id']); ?> · <?php echo hpn_h($coverageMapping['provider_region'] ?: 'default region'); ?> · <?php echo (int)$coverageMapping['default_months']; ?> month(s)</span>
                                <?php elseif ($row['coverage_status'] === 'missing'): ?>
                                    <a class="btn btn-warn" href="#provider-mappings" onclick="document.getElementById('product_id').value='<?php echo (int)$row['product_id']; ?>';document.getElementById('product_sku').value='<?php echo hpn_h($row['sku']); ?>';document.getElementById('job_type').value='<?php echo hpn_h($row['job_type']); ?>';">Create mapping</a>
                                <?php else: ?>
                                    <span class="small">No direct provider mapping required.</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel" id="provider-mappings">
            <h2 style="margin-top:0;">Provider Mappings</h2>
            <p class="small">
                Use this only when you have confirmed the exact MyOrderBox endpoint and plan ID.
                Unmapped products stay in manual review, which is safer than sending the wrong provider order.
            </p>

            <form method="post" style="margin-top:16px;">
                <input type="hidden" name="csrf_token" value="<?php echo hpn_h($csrf); ?>">
                <input type="hidden" name="action" value="save_mapping">
                <input type="hidden" name="mapping_id" id="mapping_id" value="0">

                <div class="grid">
                    <div>
                        <label for="product_id">Product</label>
                        <select name="product_id" id="product_id">
                            <option value="0">No linked product</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo (int)$product['id']; ?>">
                                    <?php echo hpn_h($product['product_type'] . ' — ' . $product['name'] . ' (' . $product['slug'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="product_sku">HiveNest SKU</label>
                        <input type="text" name="product_sku" id="product_sku" placeholder="multi-domain-linux-hosting--business" required>
                    </div>
                    <div>
                        <label for="job_type">Job Type</label>
                        <select name="job_type" id="job_type" required>
                            <option value="hosting_setup">hosting_setup</option>
                            <option value="email_setup">email_setup</option>
                            <option value="ssl_setup">ssl_setup</option>
                            <option value="backup_setup">backup_setup</option>
                            <option value="security_setup">security_setup</option>
                        </select>
                    </div>
                    <div>
                        <label for="provider_endpoint">MyOrderBox Endpoint</label>
                        <input type="text" name="provider_endpoint" id="provider_endpoint" placeholder="/api/.../add.json" required>
                    </div>
                    <div>
                        <label for="provider_plan_id">Plan ID</label>
                        <input type="text" name="provider_plan_id" id="provider_plan_id" placeholder="REAL_PLAN_ID" required>
                    </div>
                    <div>
                        <label for="provider_region">Region</label>
                        <input type="text" name="provider_region" id="provider_region" placeholder="za / us / uk">
                    </div>
                    <div>
                        <label for="default_months">Default Months</label>
                        <input type="number" name="default_months" id="default_months" value="12" min="1" max="120">
                    </div>
                    <div>
                        <label for="extra_params">Extra Params JSON</label>
                        <textarea name="extra_params" id="extra_params" placeholder='{"no-of-accounts":1}'></textarea>
                    </div>
                </div>
                <div class="inline-checks">
                    <label><input type="checkbox" name="requires_domain" id="requires_domain" value="1" checked> Requires domain</label>
                    <label title="HiveNest creates renewal invoices and keeps provider-native auto-renew disabled to prevent duplicate renewal.">
                        <input type="checkbox" name="auto_renew" id="auto_renew" value="1" disabled>
                        Provider-native auto renew (disabled; HiveNest controls renewal)
                    </label>
                    <label><input type="checkbox" name="is_active" id="is_active" value="1" checked> Active</label>
                    <button class="btn btn-primary" type="submit">Save Mapping</button>
                    <button class="btn btn-muted" type="button" onclick="resetMappingForm()">Clear</button>
                </div>
            </form>

            <div style="overflow:auto;margin-top:18px;">
                <table>
                    <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Product</th>
                        <th>Job</th>
                        <th>Endpoint / Plan</th>
                        <th>Flags</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$mappings): ?><tr><td colspan="6" class="small">No provider mappings yet.</td></tr><?php endif; ?>
                    <?php foreach ($mappings as $mapping): ?>
                        <tr>
                            <td class="mono"><?php echo hpn_h($mapping['product_sku']); ?></td>
                            <td>
                                <?php echo hpn_h($mapping['product_name'] ?: '-'); ?><br>
                                <span class="small"><?php echo hpn_h($mapping['product_slug'] ?: 'no product link'); ?></span>
                            </td>
                            <td><span class="badge neutral"><?php echo hpn_h($mapping['job_type']); ?></span></td>
                            <td>
                                <span class="mono"><?php echo hpn_h($mapping['provider_endpoint']); ?></span><br>
                                <span class="small">Plan: <?php echo hpn_h($mapping['provider_plan_id']); ?></span>
                            </td>
                            <td>
                                <span class="badge <?php echo (int)$mapping['is_active'] === 1 ? 'ok' : 'bad'; ?>"><?php echo (int)$mapping['is_active'] === 1 ? 'active' : 'inactive'; ?></span>
                                <span class="small">Domain: <?php echo (int)$mapping['requires_domain'] === 1 ? 'yes' : 'no'; ?></span><br>
                                <span class="small">Months: <?php echo (int)$mapping['default_months']; ?></span>
                            </td>
                            <td>
                                <div class="actions">
                                    <button class="btn btn-primary" type="button"
                                            onclick='editMapping(<?php echo json_encode([
                                                'id' => (int)$mapping['id'],
                                                'product_id' => (int)($mapping['product_id'] ?? 0),
                                                'product_sku' => $mapping['product_sku'],
                                                'job_type' => $mapping['job_type'],
                                                'provider_endpoint' => $mapping['provider_endpoint'],
                                                'provider_plan_id' => $mapping['provider_plan_id'],
                                                'provider_region' => $mapping['provider_region'],
                                                'default_months' => (int)$mapping['default_months'],
                                                'requires_domain' => (int)$mapping['requires_domain'],
                                                'auto_renew' => (int)$mapping['auto_renew'],
                                                'is_active' => (int)$mapping['is_active'],
                                                'extra_params' => $mapping['extra_params'],
                                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>Edit</button>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?php echo hpn_h($csrf); ?>">
                                        <input type="hidden" name="action" value="toggle_mapping">
                                        <input type="hidden" name="mapping_id" value="<?php echo (int)$mapping['id']; ?>">
                                        <button class="btn btn-warn" type="submit"><?php echo (int)$mapping['is_active'] === 1 ? 'Disable' : 'Enable'; ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script>
function editMapping(mapping) {
    document.getElementById('mapping_id').value = mapping.id || 0;
    document.getElementById('product_id').value = mapping.product_id || 0;
    document.getElementById('product_sku').value = mapping.product_sku || '';
    document.getElementById('job_type').value = mapping.job_type || 'hosting_setup';
    document.getElementById('provider_endpoint').value = mapping.provider_endpoint || '';
    document.getElementById('provider_plan_id').value = mapping.provider_plan_id || '';
    document.getElementById('provider_region').value = mapping.provider_region || '';
    document.getElementById('default_months').value = mapping.default_months || 12;
    document.getElementById('extra_params').value = mapping.extra_params || '';
    document.getElementById('requires_domain').checked = Number(mapping.requires_domain || 0) === 1;
    document.getElementById('auto_renew').checked = false;
    document.getElementById('is_active').checked = Number(mapping.is_active || 0) === 1;
    document.getElementById('product_sku').scrollIntoView({behavior:'smooth', block:'center'});
}

function resetMappingForm() {
    document.getElementById('mapping_id').value = 0;
    document.getElementById('product_id').value = 0;
    document.getElementById('product_sku').value = '';
    document.getElementById('job_type').value = 'hosting_setup';
    document.getElementById('provider_endpoint').value = '';
    document.getElementById('provider_plan_id').value = '';
    document.getElementById('provider_region').value = '';
    document.getElementById('default_months').value = 12;
    document.getElementById('extra_params').value = '';
    document.getElementById('requires_domain').checked = true;
    document.getElementById('auto_renew').checked = false;
    document.getElementById('is_active').checked = true;
}
</script>
</body>
</html>
