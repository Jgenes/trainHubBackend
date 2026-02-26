<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Provider Approved</title>
</head>
<body style="font-family: sans-serif; color: #333;">
    <h2 style="color: #28a745;">Hongera Sana! 🎉</h2>

    <p>Habari <strong>{{ $name }}</strong>,</p>
    <p>Tunayo furaha kukujulisha kuwa akaunti yako ya Provider imethibitishwa rasmi na Admin.</p>

    <p>Sasa unaweza kuingia kwenye dashboard yako na kuanza kuongeza kozi na mafunzo yako.</p>

    <div style="text-align: center; margin-top: 30px;">
        <a href="{{ $login_url }}" style="background-color: #007bff; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px;">
            Ingia Kwenye Dashboard
        </a>
    </div>

    <p style="margin-top: 40px; font-size: 0.9em; color: #555;">
        Asante kwa kujiunga nasi.<br>
        <strong>Timu ya {{ config('app.name') }}</strong>
    </p>
</body>
</html>
