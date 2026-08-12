<?php
declare(strict_types=1);

/**
 * CRM role capability matrix.
 *
 * Read-only CRM access remains available to every active administrator account.
 * Mutations must name a capability from this matrix. Unknown roles and unknown
 * capabilities fail closed.
 */
function hivenest_crm_permission_matrix(): array
{
    return [
        'customer.manage' => ['super_admin', 'admin', 'staff'],
        'service.manage' => ['super_admin', 'admin', 'staff'],
        'credential.manage' => ['super_admin', 'admin', 'staff'],
        'credential.reveal' => ['super_admin', 'admin', 'staff'],
        'workflow.manage' => ['super_admin', 'admin', 'staff'],
        'work_queue.manage' => ['super_admin', 'admin', 'staff'],
        'support.manage' => ['super_admin', 'admin', 'staff', 'support'],
        'chat.manage' => ['super_admin', 'admin', 'staff', 'support'],
        'notice.draft' => ['super_admin', 'admin', 'staff'],
        'notice.publish' => ['super_admin', 'admin'],
        'refund.issue' => ['super_admin', 'admin'],
        'report.export' => ['super_admin', 'admin'],
        'mail.retry' => ['super_admin', 'admin'],
        'mail.template.manage' => ['super_admin', 'admin'],
        'mail.suppression.manage' => ['super_admin', 'admin'],
    ];
}

function hivenest_crm_role_allows(array $admin, string $capability): bool
{
    $role = strtolower(trim((string)($admin['role'] ?? '')));
    $matrix = hivenest_crm_permission_matrix();
    return isset($matrix[$capability]) && in_array($role, $matrix[$capability], true);
}

function hivenest_crm_admin_record(PDO $db, int $adminId): array
{
    if ($adminId <= 0) return [];
    $stmt = $db->prepare('SELECT id,username,email,role,permissions FROM admin_users WHERE id=:id AND is_active=1 LIMIT 1');
    $stmt->execute(['id' => $adminId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}
