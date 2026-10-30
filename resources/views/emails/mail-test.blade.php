<!DOCTYPE html>
<html>
<body style="font-family:Arial;background:#f9fafb;padding:30px;">
    <div style="max-width:600px;margin:auto;background:white;padding:30px;border-radius:8px;">
        <h2 style="color:#1E40AF;">Training Hub — mail delivery test</h2>

        <p>If you can read this, the API can deliver email.</p>

        <p style="font-size:13px;color:#555;">
            Sent {{ $timestamp }} using the <strong>{{ $mailer }}</strong> mailer.
        </p>

        <p style="font-size:12px;color:#777;">
            You can ignore this message. It is only produced by the mail diagnostics tool.
        </p>
    </div>
</body>
</html>
