<!DOCTYPE html>
<html>
<head>
    <title>Malipo Yamepokelewa</title>
</head>
<body>
    <h2>Habari, {{ $payment->first_name }}!</h2>
    <p>Tumepokea malipo yako ya <strong>{{ number_format($payment->amount, 2) }} TZS</strong> kwa mafanikio.</p>
    <p>Tumekuambatanishia risiti ya malipo yako kwenye barua pepe hii (PDF).</p>
    <br>
    <p>Ahsante kwa kuchagua Training Hub!</p>
</body>
</html>