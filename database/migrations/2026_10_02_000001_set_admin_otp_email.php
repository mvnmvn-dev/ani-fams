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

        $otpEmail = trim((string) (
            config('phpmailer.admin_otp_email')
            ?: config('phpmailer.from_address')
            ?: config('phpmailer.username')
            ?: 'inigomerv@gmail.com'
        ));

        if ($otpEmail === '' || ! filter_var($otpEmail, FILTER_VALIDATE_EMAIL) || str_ends_with(strtolower($otpEmail), '.local')) {
            $otpEmail = 'inigomerv@gmail.com';
        }

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
