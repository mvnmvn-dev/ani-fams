<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\DB;

trait CreatesTestUsers
{
    /**
     * @param  list<string>  $permissions
     */
    protected function createUserWithPermissions(array $permissions, array $userOverrides = []): User
    {
        $roleCode = $userOverrides['role_code'] ?? ('role_' . substr(md5(implode(',', $permissions) . microtime(true)), 0, 8));

        $roleId = DB::table('roles')->where('code', $roleCode)->value('id');
        if (! $roleId) {
            $roleId = DB::table('roles')->insertGetId([
                'code' => $roleCode,
                'name' => $userOverrides['role_name'] ?? 'Test Role',
                'description' => 'Test role',
            ]);
        }

        foreach ($permissions as $code) {
            $existing = DB::table('permissions')->where('code', $code)->value('id');
            if ($existing) {
                $permId = (int) $existing;
            } else {
                $permId = DB::table('permissions')->insertGetId([
                    'code' => $code,
                    'description' => $code,
                ]);
            }
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permId,
            ]);
        }

        $userId = DB::table('users')->insertGetId([
            'full_name' => $userOverrides['full_name'] ?? 'Test User',
            'email' => $userOverrides['email'] ?? ('test' . uniqid('', true) . '@example.com'),
            'password_hash' => bcrypt($userOverrides['password'] ?? 'password'),
            'role_id' => $roleId,
            'department_id' => $userOverrides['department_id'] ?? null,
            'status' => $userOverrides['status'] ?? 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::findOrFail($userId);
    }
}
