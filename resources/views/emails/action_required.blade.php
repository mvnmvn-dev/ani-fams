<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
        <h2 style="color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px;">{{ $title }}</h2>
        
        <p style="white-space: pre-line;">{{ $body }}</p>
        
        @if($actionUrl)
            <div style="margin-top: 30px; text-align: center;">
                <a href="{{ $actionUrl }}" style="background-color: #3498db; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 3px; font-weight: bold; display: inline-block;">
                    View Details / Take Action
                </a>
            </div>
        @endif
        
        <p style="margin-top: 40px; font-size: 12px; color: #7f8c8d; text-align: center;">
            This is an automated message from Archon Nell Incorporated — Facilities & Administrative Management System.
        </p>
    </div>
</body>
</html>
