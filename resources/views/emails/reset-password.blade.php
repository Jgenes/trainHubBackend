<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset your password</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1c2536; line-height: 1.6;">
    <div style="max-width: 560px; margin: 0 auto; padding: 32px; border: 1px solid #e2e8f0; border-radius: 12px;">
        <h2 style="margin-top: 0;">Reset your password</h2>
        <p>Hello {{ $user->name }},</p>
        <p>We received a request to reset your TrainingHub password. Use the button below to choose a new one.</p>
        <p style="text-align: center; margin: 32px 0;">
            <a href="{{ $link }}" style="background: #0a2e67; color: #fff; padding: 12px 24px; border-radius: 8px; text-decoration: none;">
                Reset password
            </a>
        </p>
        <p>This link expires in 60 minutes. If you did not request a password reset, you can ignore this email.</p>
        <p style="color: #64748b; font-size: 12px;">If the button does not work, paste this link into your browser:<br>{{ $link }}</p>
    </div>
</body>
</html>
