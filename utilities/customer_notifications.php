<?php
declare(strict_types=1);

function hivenest_customer_notifications_ensure(PDO $db): void
{
    $db->exec("
        CREATE TABLE IF NOT EXISTS customer_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            uuid VARCHAR(36) NOT NULL UNIQUE,
            customer_id INT NOT NULL,
            notification_type VARCHAR(50) NOT NULL DEFAULT 'info',
            title VARCHAR(180) NOT NULL,
            message TEXT NOT NULL,
            link_url VARCHAR(500) NULL,
            entity_type VARCHAR(50) NULL,
            entity_id INT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            read_at TIMESTAMP NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_customer_notifications_recipient (customer_id, is_read, created_at),
            INDEX idx_customer_notifications_entity (entity_type, entity_id),
            CONSTRAINT fk_customer_notifications_customer
                FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function hivenest_customer_notification_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function hivenest_notify_customer(
    PDO $db,
    int $customerId,
    string $type,
    string $title,
    string $message,
    ?string $linkUrl = null,
    ?string $entityType = null,
    ?int $entityId = null
): int {
    if ($customerId <= 0) return 0;
    hivenest_customer_notifications_ensure($db);
    $stmt = $db->prepare("
        INSERT INTO customer_notifications
            (uuid, customer_id, notification_type, title, message, link_url, entity_type, entity_id)
        VALUES
            (:uuid, :customer_id, :type, :title, :message, :link_url, :entity_type, :entity_id)
    ");
    $stmt->execute([
        'uuid' => hivenest_customer_notification_uuid(),
        'customer_id' => $customerId,
        'type' => substr(trim($type) ?: 'info', 0, 50),
        'title' => substr(trim($title), 0, 180),
        'message' => trim($message),
        'link_url' => $linkUrl !== null ? substr(trim($linkUrl), 0, 500) : null,
        'entity_type' => $entityType !== null ? substr(trim($entityType), 0, 50) : null,
        'entity_id' => $entityId,
    ]);
    return (int)$db->lastInsertId();
}

function hivenest_notify_customer_once(
    PDO $db,
    int $customerId,
    string $type,
    string $title,
    string $message,
    ?string $linkUrl,
    string $entityType,
    int $entityId
): int {
    if ($customerId <= 0 || $entityId <= 0) return 0;
    hivenest_customer_notifications_ensure($db);
    $exists = $db->prepare("
        SELECT id
        FROM customer_notifications
        WHERE customer_id = :customer_id
          AND entity_type = :entity_type
          AND entity_id = :entity_id
        LIMIT 1
    ");
    $exists->execute([
        'customer_id' => $customerId,
        'entity_type' => substr(trim($entityType), 0, 50),
        'entity_id' => $entityId,
    ]);
    $existingId = (int)($exists->fetchColumn() ?: 0);
    if ($existingId > 0) return $existingId;
    return hivenest_notify_customer(
        $db,
        $customerId,
        $type,
        $title,
        $message,
        $linkUrl,
        $entityType,
        $entityId
    );
}
