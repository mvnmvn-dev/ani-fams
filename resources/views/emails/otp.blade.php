<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Verification code</title>
</head>
<body style="font-family: system-ui, sans-serif; color: #1a1a1a; line-height: 1.5;">
    <p>Hello {{ $fullName }},</p>
    <p>Use the verification code below to sign in to {{ $appName }}. This code expires in {{ $expiresMinutes }} minutes.</p>
    <p style="margin: 24px 0; font-size: 32px; font-weight: 700; letter-spacing: 0.25em; color: #142E4C;">{{ $otp }}</p>
    <p style="font-size: 13px; color: #666;">If you did not attempt to sign in, you can ignore this email.</p>
</body>
</html>
