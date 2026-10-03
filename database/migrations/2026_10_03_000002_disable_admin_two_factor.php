<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'two_factor_enabled')) {
            return;
        }

        // Temporarily clear authenticator 2FA so admins can log in via email OTP
        // after a username/profile change that invalidated the phone authenticator.
        DB::table('users')
            ->whereNull('deleted_at')
            ->where('two_factor_enabled', true)
            ->whereIn('role_id', function ($query) {
                $query->select('id')->from('roles')->where('code', 'admin');
            })
            ->update([
                'two_factor_enabled' => false,
                'two_factor_secret' => null,
            ]);
    }

    public function down(): void
    {
        // Intentionally left blank — secrets cannot be restored after clearing.
    }
};
