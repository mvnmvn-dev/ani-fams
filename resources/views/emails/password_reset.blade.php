<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Password reset</title>
</head>
<body style="font-family: system-ui, sans-serif; color: #1a1a1a; line-height: 1.5;">
    <p>Hello {{ $fullName }},</p>
    <p>We received a request to reset your password. Click the button below to choose a new one. This link expires in 60 minutes.</p>
    <p style="margin: 24px 0;">
        <a href="{{ $resetUrl }}"
           style="display:inline-block;background:#142E4C;color:#fff;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:600;">
            Reset password
        </a>
    </p>
    <p style="font-size: 13px; color: #666;">If you did not request this, you can ignore this email.</p>
    <p style="font-size: 12px; color: #999; word-break: break-all;">{{ $resetUrl }}</p>
</body>
</html>
