<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Provider Suspended</title>
</head>
<body style="font-family: sans-serif; color: #333;">
    <h3 style="color: #d9534f;">Taarifa: Akaunti Yako Imesimamishwa</h3>

    <p>Habari {{ $name }},</p>
    <p>Tunakujulisha kuwa akaunti yako ya Provider imesimamishwa kwa muda kwa sababu ifuatayo:</p>

    <div style="background: #f8d7da; padding: 15px; border-left: 5px solid #d9534f; margin: 15px 0;">
        <strong>Sababu:</strong> {{ $reason }}
    </div>

    <p>Kama una maswali au umerekebisha mapungufu yaliyotajwa, tafadhali wasiliana na timu yetu ya usaidizi.</p>

    <p>Asante,<br>
       Uongozi wa {{ config('app.name') }}
    </p>
</body>
</html>
