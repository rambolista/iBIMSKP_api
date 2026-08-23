<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * menu_id => [capability => true, ...] — only true flags need listing,
     * everything else defaults false. Capabilities a menu doesn't actually
     * support are simply inert (Controller::supportsAction() ignores them),
     * so being slightly generous here is harmless.
     */
    private const ROLE_GRANTS = [
        'Punong Barangay' => [
            1 => ['view'],
            308 => ['view'], 321 => ['view'], 322 => ['view'],
            295 => ['view'],
            296 => ['view', 'export', 'print'],
            297 => ['view', 'export', 'print'],
            298 => ['view', 'export', 'print'],
            299 => ['view'],
            300 => ['view', 'approve'],
            302 => ['view', 'approve'],
            309 => ['view'],
            323 => ['view'],
            310 => ['view', 'approve', 'export', 'print'],
            325 => ['view', 'export'],
            285 => ['view'],
            317 => ['view'],
        ],
        'Barangay Secretary' => [
            1 => ['view'],
            295 => ['view'],
            296 => ['view', 'add', 'edit', 'delete', 'export', 'print'],
            297 => ['view', 'add', 'edit', 'delete', 'export', 'print'],
            298 => ['view', 'add', 'edit', 'delete', 'export', 'print'],
            299 => ['view'],
            300 => ['view', 'add', 'edit', 'delete', 'cancel'],
            302 => ['view', 'add', 'edit', 'delete', 'cancel'],
            307 => ['view'],
            303 => ['view', 'add', 'edit', 'delete', 'export', 'print'],
            305 => ['view', 'add', 'edit', 'delete', 'export', 'print'],
            309 => ['view'],
            310 => ['view', 'export', 'print'],
            311 => ['view', 'export', 'print'],
            304 => ['view', 'add', 'edit', 'delete', 'cancel', 'reverse', 'export', 'print'],
            325 => ['view', 'export'],
        ],
        'Lupon Secretary' => [
            1 => ['view'],
            309 => ['view'],
            323 => ['view'],
            310 => ['view', 'add', 'edit', 'delete', 'approve', 'execute', 'export', 'print'],
            311 => ['view', 'add', 'edit', 'delete', 'execute', 'export', 'print'],
            312 => ['view', 'add', 'edit', 'delete', 'export', 'print'],
            313 => ['view'],
            325 => ['view', 'export'],
        ],
        'Barangay Treasurer' => [
            1 => ['view'],
            320 => ['view', 'add', 'edit', 'cancel', 'reverse', 'export', 'print'],
            301 => ['view', 'edit'],
            325 => ['view', 'export'],
        ],
        'Barangay Staff' => [
            1 => ['view'],
            295 => ['view'],
            296 => ['view'],
            299 => ['view'],
            300 => ['view', 'add', 'edit', 'cancel'],
            302 => ['view', 'add', 'edit', 'cancel'],
        ],
    ];

    /** Reports (menu 325) has 5 category tabs — scope each role to only what it was asked for. */
    private const REPORT_TAB_SCOPE = [
        'Punong Barangay' => ['resident', 'certificate', 'lupon'],
        'Barangay Secretary' => ['resident', 'certificate', 'lupon', 'blotter'],
        'Lupon Secretary' => ['lupon'],
        'Barangay Treasurer' => ['financial'],
    ];

    private const ROLE_META = [
        'Super Administrator' => ['description' => 'Full system access.', 'key_responsibilities' => 'All modules, Users, Roles, System configuration, Backup, Audit logs', 'icon' => 'shield-check'],
        'Punong Barangay' => ['description' => 'Barangay Captain.', 'key_responsibilities' => 'Dashboard, Resident records, Certificates, Lupon cases, Approvals, Reports, Authorized administrative functions', 'icon' => 'gavel'],
        'Barangay Secretary' => ['description' => 'Barangay records and documentation officer.', 'key_responsibilities' => 'Residents, Certificates, Documents, Lupon records, Blotter, Reports', 'icon' => 'notebook'],
        'Lupon Secretary' => ['description' => 'Katarungang Pambarangay case administrator.', 'key_responsibilities' => 'Lupon cases, Hearings, Summons, Case documents, Settlements, Case reports', 'icon' => 'scale'],
        'Barangay Treasurer' => ['description' => 'Collections and financial officer.', 'key_responsibilities' => 'Payments, Fees, Receipts, Collections, Financial reports', 'icon' => 'cash-banknote'],
        'Barangay Staff' => ['description' => 'Front-desk / general staff.', 'key_responsibilities' => 'Resident lookup, Service requests, Basic transactions, Other functions granted by administrator', 'icon' => 'user'],
    ];

    public function up(): void
    {
        $now = now();

        DB::transaction(function () use ($now): void {
            foreach (self::ROLE_META as $name => $meta) {
                DB::table('roles')->updateOrInsert(
                    ['name' => $name],
                    [...$meta, 'created_at' => $now, 'updated_at' => $now]
                );
            }

            $roleIds = DB::table('roles')->whereIn('name', array_keys(self::ROLE_META))->pluck('id', 'name');
            $adminRoleId = DB::table('roles')->where('name', 'Admin')->value('id');

            // Super Administrator = exact clone of Admin's current (already-validated) full access.
            $superAdminRoleId = $roleIds['Super Administrator'];
            if ($adminRoleId) {
                foreach (DB::table('role_menu_permissions')->where('role_id', $adminRoleId)->get() as $grant) {
                    DB::table('role_menu_permissions')->updateOrInsert(
                        ['role_id' => $superAdminRoleId, 'menu_id' => $grant->menu_id],
                        [
                            'can_view' => $grant->can_view, 'can_add' => $grant->can_add, 'can_edit' => $grant->can_edit,
                            'can_delete' => $grant->can_delete, 'can_approve' => $grant->can_approve, 'can_execute' => $grant->can_execute,
                            'can_cancel' => $grant->can_cancel, 'can_reverse' => $grant->can_reverse, 'can_export' => $grant->can_export,
                            'can_print' => $grant->can_print, 'created_at' => $now, 'updated_at' => $now,
                        ]
                    );
                }
                foreach (DB::table('role_menu_tab_permissions')->where('role_id', $adminRoleId)->get() as $grant) {
                    DB::table('role_menu_tab_permissions')->updateOrInsert(
                        ['role_id' => $superAdminRoleId, 'menu_tab_id' => $grant->menu_tab_id],
                        [
                            'can_view' => $grant->can_view, 'can_add' => $grant->can_add, 'can_edit' => $grant->can_edit,
                            'can_delete' => $grant->can_delete, 'can_approve' => $grant->can_approve, 'can_execute' => $grant->can_execute,
                            'can_cancel' => $grant->can_cancel, 'can_reverse' => $grant->can_reverse, 'can_export' => $grant->can_export,
                            'can_print' => $grant->can_print, 'created_at' => $now, 'updated_at' => $now,
                        ]
                    );
                }
            }

            // The other 5 roles: explicit per-menu grants, mirrored onto every tab of each granted menu.
            foreach (self::ROLE_GRANTS as $roleName => $menuGrants) {
                $roleId = $roleIds[$roleName];

                foreach ($menuGrants as $menuId => $actions) {
                    $flags = array_fill_keys(['view', 'add', 'edit', 'delete', 'approve', 'execute', 'cancel', 'reverse', 'export', 'print'], false);
                    foreach ($actions as $action) {
                        $flags[$action] = true;
                    }

                    DB::table('role_menu_permissions')->updateOrInsert(
                        ['role_id' => $roleId, 'menu_id' => $menuId],
                        [
                            'can_view' => $flags['view'], 'can_add' => $flags['add'], 'can_edit' => $flags['edit'],
                            'can_delete' => $flags['delete'], 'can_approve' => $flags['approve'], 'can_execute' => $flags['execute'],
                            'can_cancel' => $flags['cancel'], 'can_reverse' => $flags['reverse'], 'can_export' => $flags['export'],
                            'can_print' => $flags['print'], 'created_at' => $now, 'updated_at' => $now,
                        ]
                    );

                    if (! $flags['view']) {
                        continue;
                    }

                    $tabIds = DB::table('menu_tabs')->where('menu_id', $menuId)->pluck('id', 'key');

                    if ($menuId === 325 && isset(self::REPORT_TAB_SCOPE[$roleName])) {
                        $allowedKeys = self::REPORT_TAB_SCOPE[$roleName];
                        foreach ($tabIds as $key => $tabId) {
                            if (! in_array($key, $allowedKeys, true)) {
                                continue;
                            }
                            DB::table('role_menu_tab_permissions')->updateOrInsert(
                                ['role_id' => $roleId, 'menu_tab_id' => $tabId],
                                ['can_view' => true, 'can_export' => $flags['export'], 'can_add' => false, 'can_edit' => false, 'can_delete' => false, 'can_approve' => false, 'can_execute' => false, 'can_cancel' => false, 'can_reverse' => false, 'can_print' => false, 'created_at' => $now, 'updated_at' => $now]
                            );
                        }
                        continue;
                    }

                    foreach ($tabIds as $tabId) {
                        DB::table('role_menu_tab_permissions')->updateOrInsert(
                            ['role_id' => $roleId, 'menu_tab_id' => $tabId],
                            [
                                'can_view' => $flags['view'], 'can_add' => $flags['add'], 'can_edit' => $flags['edit'],
                                'can_delete' => $flags['delete'], 'can_approve' => $flags['approve'], 'can_execute' => $flags['execute'],
                                'can_cancel' => $flags['cancel'], 'can_reverse' => $flags['reverse'], 'can_export' => $flags['export'],
                                'can_print' => $flags['print'], 'created_at' => $now, 'updated_at' => $now,
                            ]
                        );
                    }
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $roleIds = DB::table('roles')->whereIn('name', array_keys(self::ROLE_META))->pluck('id');
            DB::table('role_menu_tab_permissions')->whereIn('role_id', $roleIds)->delete();
            DB::table('role_menu_permissions')->whereIn('role_id', $roleIds)->delete();
            DB::table('role_user')->whereIn('role_id', $roleIds)->delete();
            DB::table('roles')->whereIn('id', $roleIds)->delete();
        });
    }
};
