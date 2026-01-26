<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family:Arial;background:#f3f4f6;padding:30px;">
    <div style="max-width:600px;margin:auto;background:white;padding:30px;border-radius:8px;">
        
        <h2 style="color:#1E40AF;">Training Hub 🎓</h2>

        <p>Hello,</p>

        <p>Welcome to <strong>Training Hub</strong>. Click the button below to activate your account.</p>

        <a href="{{ $activationLink }}"
           style="display:inline-block;padding:12px 25px;background:#1E40AF;color:#fff;
           text-decoration:none;border-radius:6px;margin:20px 0;">
           Activate Account
        </a>

        <p style="font-size:14px;color:#555;">
            This link expires in 24 hours.
        </p>

        <hr>

        <p style="font-size:12px;color:#777;">
            © {{ date('Y') }} Training Hub. All rights reserved.
        </p>
    </div>
</body>
</html>
