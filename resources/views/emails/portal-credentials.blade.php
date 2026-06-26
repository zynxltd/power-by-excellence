<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Portal login</title></head>
<body style="font-family: sans-serif; line-height: 1.5; color: #1e293b;">
    <h2>Your {{ $platformName }} account</h2>
    <p>Hello {{ $user->name }},</p>
    <p>An administrator has created an account for you. Use the credentials below to sign in:</p>
    <ul>
        <li><strong>Portal URL:</strong> <a href="{{ $portalUrl }}">{{ $portalUrl }}</a></li>
        <li><strong>Email:</strong> {{ $user->email }}</li>
        <li><strong>Password:</strong> {{ $password }}</li>
    </ul>
    <p>Please change your password after your first login.</p>
    <p style="color:#64748b;font-size:12px;">If you did not expect this email, contact your platform administrator.</p>
</body>
</html>
