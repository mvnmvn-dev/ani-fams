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
            ?: env('ADMIN_OTP_EMAIL', 'inigomerv@gmail.com')
        ));

        if ($otpEmail === '' || ! filter_var($otpEmail, FILTER_VALIDATE_EMAIL) || str_ends_with(strtolower($otpEmail), '.local')) {
            $otpEmail = 'inigomerv@gmail.com';
        }

        // Re-apply whenever admin OTP is missing (e.g. cleared after a username/profile change).
        DB::table('users')
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $query->whereNull('otp_email')->orWhere('otp_email', '');
            })
            ->whereIn('role_id', function ($query) {
                $query->select('id')->from('roles')->where('code', 'admin');
            })
            ->update(['otp_email' => $otpEmail]);
    }

    public function down(): void
    {
        // Intentionally left blank — do not clear OTP destinations on rollback.
    }
};
