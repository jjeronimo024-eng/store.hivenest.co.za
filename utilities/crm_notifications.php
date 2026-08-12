<?php
declare(strict_types=1);

/**
 * Persistent, per-administrator CRM notifications.
 *
 * Producers may target one administrator or fan a notification out to every
 * active administrator. Each recipient gets a separate row so reading a
 * notification never marks it read for another staff member.
 */

function hivenest_crm_notifications_ensure(PDO $db): void
{
    $db->exec("
        CREATE TABLE IF NOT EXISTS admin_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            uuid VARCHAR(36) NOT NULL UNIQUE,
            admin_user_id INT NOT NULL,
            notification_type VARCHAR(50) NOT NULL DEFAULT 'info',
            title VARCHAR(180) NOT NULL,
            message TEXT NOT NULL,
            link_url VARCHAR(500) NULL,
            entity_type VARCHAR(50) NULL,
            entity_id INT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            read_at TIMESTAMP NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin_notifications_recipient (admin_user_id, is_read, created_at),
            INDEX idx_admin_notifications_entity (entity_type, entity_id),
            CONSTRAINT fk_admin_notifications_admin
                FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function hivenest_crm_notification_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function hivenest_crm_notify_admin(
    PDO $db,
    int $adminId,
    string $type,
    string $title,
    string $message,
    ?string $linkUrl = null,
    ?string $entityType = null,
    ?int $entityId = null
): int {
    hivenest_crm_notifications_ensure($db);
    $stmt = $db->prepare("
        INSERT INTO admin_notifications
            (uuid, admin_user_id, notification_type, title, message, link_url, entity_type, entity_id)
        VALUES
            (:uuid, :admin_id, :type, :title, :message, :link_url, :entity_type, :entity_id)
    ");
    $stmt->execute([
        'uuid' => hivenest_crm_notification_uuid(),
        'admin_id' => $adminId,
        'type' => substr(trim($type) ?: 'info', 0, 50),
        'title' => substr(trim($title), 0, 180),
        'message' => trim($message),
        'link_url' => $linkUrl !== null ? substr(trim($linkUrl), 0, 500) : null,
        'entity_type' => $entityType !== null ? substr(trim($entityType), 0, 50) : null,
        'entity_id' => $entityId,
    ]);
    return (int)$db->lastInsertId();
}

function hivenest_crm_notify_all_admins(
    PDO $db,
    string $type,
    string $title,
    string $message,
    ?string $linkUrl = null,
    ?string $entityType = null,
    ?int $entityId = null
): int {
    hivenest_crm_notifications_ensure($db);
    $adminIds = $db->query('SELECT id FROM admin_users WHERE is_active = 1')->fetchAll(PDO::FETCH_COLUMN);
    $created = 0;
    foreach ($adminIds as $adminId) {
        hivenest_crm_notify_admin(
            $db,
            (int)$adminId,
            $type,
            $title,
            $message,
            $linkUrl,
            $entityType,
            $entityId
        );
        $created++;
    }
    return $created;
}

