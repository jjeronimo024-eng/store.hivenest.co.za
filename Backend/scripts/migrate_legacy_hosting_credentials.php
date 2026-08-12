<?php
declare(strict_types=1);

/**
 * One-time migration from hosting_accounts.account_password to the encrypted
 * service credential vault.
 *
 * Dry run:
 *   php Backend/scripts/migrate_legacy_hosting_credentials.php
 *
 * Commit:
 *   php Backend/scripts/migrate_legacy_hosting_credentials.php --confirm
 *
 * The script never overwrites an existing active "Legacy hosting account"
 * credential. Review those rows manually before clearing the legacy column.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../access/dbconfig.php';
require_once __DIR__ . '/../../utilities/service_credentials.php';

$confirm = in_array('--confirm', $argv, true);
$db = hivenest_db();
if (!$db) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(1);
}

try {
    hivenest_service_credentials_key();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

$tableCheck = $db->query(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='service_credentials'"
);
if ((int)$tableCheck->fetchColumn() !== 1) {
    fwrite(STDERR, "Import Database/service_credential_vault.sql first.\n");
    exit(1);
}

$rows = $db->query(
    "SELECT h.id,h.service_id,h.customer_id,h.account_username,h.account_password,
            h.hosting_type,h.control_panel,h.server_id
     FROM hosting_accounts h
     WHERE h.account_password IS NOT NULL AND h.account_password <> ''
     ORDER BY h.id"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];

echo ($confirm ? "COMMIT MODE" : "DRY RUN") . ': ' . count($rows) . " legacy credential(s) found.\n";
$migrated = 0;
$skipped = 0;

foreach ($rows as $row) {
    $existing = $db->prepare(
        'SELECT id FROM service_credentials
         WHERE service_id=:service_id AND customer_id=:customer_id
           AND credential_type="control_panel" AND label="Legacy hosting account"
           AND status="active" LIMIT 1'
    );
    $existing->execute([
        'service_id' => (int)$row['service_id'],
        'customer_id' => (int)$row['customer_id'],
    ]);
    if ($existing->fetchColumn()) {
        $skipped++;
        echo "SKIP hosting_accounts #{$row['id']}: an active vault record already exists; legacy value was not cleared.\n";
        continue;
    }
    echo "READY hosting_accounts #{$row['id']} -> service #{$row['service_id']}.\n";
    if (!$confirm) continue;

    try {
        $db->beginTransaction();
        $insert = $db->prepare(
            'INSERT INTO service_credentials
                (uuid,service_id,customer_id,credential_type,label,username,secret_ciphertext,metadata_json)
             VALUES (:uuid,:service_id,:customer_id,"control_panel","Legacy hosting account",
                     :username,:ciphertext,:metadata)'
        );
        $insert->execute([
            'uuid' => hivenest_service_credentials_uuid(),
            'service_id' => (int)$row['service_id'],
            'customer_id' => (int)$row['customer_id'],
            'username' => $row['account_username'] ?: null,
            'ciphertext' => hivenest_service_credentials_encrypt((string)$row['account_password']),
            'metadata' => json_encode([
                'source' => 'hosting_accounts',
                'hosting_account_id' => (int)$row['id'],
                'hosting_type' => $row['hosting_type'],
                'control_panel' => $row['control_panel'],
                'server_id' => $row['server_id'],
            ], JSON_UNESCAPED_SLASHES),
        ]);
        $credentialId = (int)$db->lastInsertId();
        hivenest_service_credentials_audit(
            $db,
            $credentialId,
            (int)$row['service_id'],
            (int)$row['customer_id'],
            'system',
            null,
            'migration'
        );
        $clear = $db->prepare('UPDATE hosting_accounts SET account_password=NULL WHERE id=:id');
        $clear->execute(['id' => (int)$row['id']]);
        $db->commit();
        $migrated++;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        fwrite(STDERR, "FAILED hosting_accounts #{$row['id']}: {$e->getMessage()}\n");
        exit(1);
    }
}

if (!$confirm && $rows) {
    echo "No database changes were made. Re-run with --confirm after reviewing the output and taking a backup.\n";
} else {
    echo "Migrated {$migrated}; skipped {$skipped}.\n";
}
