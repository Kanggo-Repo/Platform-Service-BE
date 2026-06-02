<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $platformOperatorRoleId = DB::table('roles')->where('code', 'platform_operator')->value('id');

        if ($platformOperatorRoleId === null) {
            return;
        }

        $superAdminRoleId = DB::table('roles')->where('code', 'super_admin')->value('id');

        if ($superAdminRoleId === null) {
            $superAdminRoleId = DB::table('roles')->insertGetId([
                'code' => 'super_admin',
                'name' => 'Super Admin',
                'description' => 'Full access to all platform permissions.',
                'is_system' => true,
                'is_deletable' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $userIds = DB::table('user_roles')
            ->where('role_id', $platformOperatorRoleId)
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $alreadyAssigned = DB::table('user_roles')
                ->where('user_id', $userId)
                ->where('role_id', $superAdminRoleId)
                ->exists();

            if (! $alreadyAssigned) {
                DB::table('user_roles')->insert([
                    'user_id' => $userId,
                    'role_id' => $superAdminRoleId,
                ]);
            }
        }

        DB::table('user_roles')->where('role_id', $platformOperatorRoleId)->delete();
        DB::table('role_permissions')->where('role_id', $platformOperatorRoleId)->delete();
        DB::table('roles')->where('id', $platformOperatorRoleId)->delete();
    }

    public function down(): void
    {
        //
    }
};
