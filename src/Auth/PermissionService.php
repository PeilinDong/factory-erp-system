<?php

declare(strict_types=1);

namespace Erp\Auth;

final class PermissionService
{
    /**
     * @var array<string, array<int, string>>
     */
    private const ROLE_RULES = [
        'admin' => ['*'],
        'general_manager' => ['*'],
        'supervisor' => [
            'users.manage',
            'purchase.manage',
            'purchase.receive',
            'work_order.manage',
            'work_order.issue',
            'work_order.complete',
            'planning.view',
            'inventory.manage',
        ],
        'planner' => ['work_order.manage', 'planning.view'],
        'warehouse' => ['inventory.manage', 'purchase.receive', 'work_order.issue', 'work_order.complete'],
        'purchasing' => ['purchase.manage', 'purchase.receive'],
    ];

    /**
     * @param null|array<string, mixed> $user
     */
    public static function can(?array $user, string $permission): bool
    {
        if ($user === null) {
            return false;
        }

        $roleCode = (string) ($user['role_code'] ?? 'admin');
        $rules = self::ROLE_RULES[$roleCode] ?? [];

        return in_array('*', $rules, true) || in_array($permission, $rules, true);
    }
}
