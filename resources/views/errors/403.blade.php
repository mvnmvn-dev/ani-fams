<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access denied</title>
    <style>
        body { margin: 0; font-family: Inter, system-ui, sans-serif; background: #F8F8F8; color: #1E293B; }
        .wrap { max-width: 28rem; margin: 6rem auto; text-align: center; padding: 0 1rem; }
        a { color: #142E4C; }
    </style>
</head>
<body>
    <div class="wrap">
        <p style="font-size:1.75rem;font-weight:700;margin:0 0 .5rem">403 — Access denied</p>
        <p style="color:#64748B;font-size:.9rem">Your role doesn't include the permission needed for this page.</p>
        <a href="{{ url('/dashboard') }}">Back to dashboard</a>
    </div>
</body>
</html>
