<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { width: 80%; margin: 20px auto; padding: 20px; border: 1px solid #eee; border-radius: 10px; }
        .header { background: #f8f9fa; padding: 10px; text-align: center; border-bottom: 3px solid #dc3545; }
        .content { padding: 20px; }
        .reason-box { background: #fff5f5; border-left: 5px solid #dc3545; padding: 15px; margin: 20px 0; font-style: italic; }
        .footer { font-size: 12px; color: #777; margin-top: 30px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2 style="color: #dc3545;">Maombi Yamekataliwa</h2>
        </div>
        <div class="content">
            <p>Habari <strong>{{ $contactPerson }}</strong>,</p>
            
            <p>Asante kwa nia yako ya kutaka kujiunga na jukwaa letu kama mtoa huduma kupitia <strong>{{ $providerName }}</strong>.</p>
            
            <p>Baada ya kupitia taarifa na vigezo vyako, tunasikitika kukufahamisha kuwa maombi yako <strong>hayajakubaliwa</strong> kwa sasa kwa sababu ifuatayo:</p>

            <div class="reason-box">
                "{{ $rejectionReason }}"
            </div>

            <p>Tafadhali rekebisha mapungufu yaliyotajwa hapo juu na unaweza kutuma tena maombi yako kupitia profile yako mara tu utakapokuwa tayari.</p>
            
            <p>Kama una swali lolote, tafadhali wasiliana na timu yetu ya usaidizi.</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. Hii ni email inayozalishwa na mfumo, usijibu.</p>
        </div>
    </div>
</body>
</html>