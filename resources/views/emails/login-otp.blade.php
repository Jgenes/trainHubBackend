<!DOCTYPE html>
<html>
<body style="font-family:Arial;background:#f9fafb;padding:30px;">
    <div style="max-width:600px;margin:auto;background:white;padding:30px;border-radius:8px;">
        
        @php $purpose = $purpose ?? 'login'; @endphp

        <h2 style="color:#1E40AF;">Training Hub {{ $purpose === 'payment' ? 'Payment' : 'Login' }}</h2>

        <p>
            @if($purpose === 'payment')
                Your payment verification One-Time Password (OTP):
            @else
                Your One-Time Password (OTP):
            @endif
        </p>

        <h1 style="letter-spacing:6px;color:#1E40AF;">{{ $otp }}</h1>

        <p>This OTP expires in 5 minutes.</p>

        <p style="font-size:12px;color:#777;">
            If you did not request this, ignore this email.
        </p>
    </div>
</body>
</html>
