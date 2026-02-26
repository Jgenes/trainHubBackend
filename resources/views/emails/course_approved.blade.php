<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <div style="font-family: Arial, sans-serif; padding: 20px; color: #333;">
    <h2 style="color: #28a745;">Hongera sana!</h2>
    <p>Habari {{ $course->provider->contact_name }},</p>
    <p>Tunayo furaha kukufahamisha kuwa kozi yako: <strong>"{{ $course->title }}"</strong> imekaguliwa na imepitishwa rasmi.</p>
    
    <div style="background: #f4f4f4; padding: 15px; border-radius: 5px; margin: 20px 0;">
        <p style="margin: 0;"><strong>Status:</strong> PUBLISHED</p>
        <p style="margin: 0;"><strong>Category:</strong> {{ $course->category }}</p>
    </div>

    <p>Sasa wanafunzi wanaweza kuona na kuanza kujiandikisha kwenye kozi hii kupitia jukwaa letu.</p>
    
    <p>Asante kwa kuchagua kutoa elimu nasi!</p>
    <br>
    <p>Timu ya Usimamizi.</p>
</div>
</body>
</html>