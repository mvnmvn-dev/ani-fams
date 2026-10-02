<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'otp_email')) {
            return;
        }

        $otpEmail = 'inigomerv@gmail.com';

        // Admin accounts must receive OTP (no role bypass).
        DB::table('users')
            ->whereNull('deleted_at')
            ->whereIn('role_id', function ($query) {
                $query->select('id')->from('roles')->where('code', 'admin');
            })
            ->update(['otp_email' => $otpEmail]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'otp_email')) {
            return;
        }

        DB::table('users')
            ->whereNull('deleted_at')
            ->where('otp_email', 'inigomerv@gmail.com')
            ->whereIn('role_id', function ($query) {
                $query->select('id')->from('roles')->where('code', 'admin');
            })
            ->update(['otp_email' => null]);
    }
};
