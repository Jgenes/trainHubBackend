<!DOCTYPE html>
<html>
<head>
    <title>{{ $reportTitle }}</title>
    <style>
        body { font-family: DejaVu Sans; font-size: 12px; }
        table { width:100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border:1px solid #ccc; padding:6px; }
        th { background: #000; color: #fff; }
    </style>
</head>
<body>

<h2>{{ $reportTitle }}</h2>

<p><strong>Provider:</strong> {{ $user->name }}</p>
<p><strong>Period:</strong> {{ ucfirst($period) }}</p>
<p><strong>Total Revenue:</strong> TZS {{ number_format($total,2) }}</p>

<table>
    <thead>
        <tr>
            <th>Student</th>
            <th>Course</th>
            <th>Amount</th>
            <th>Date</th>
        </tr>
    </thead>
    <tbody>
        @foreach($list as $payment)
        <tr>
            <td>{{ $payment->user->name ?? '' }}</td>
            <td>{{ $payment->course->title ?? '' }}</td>
            <td>{{ number_format($payment->amount,2) }}</td>
            <td>{{ $payment->created_at->format('d M Y') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

</body>
</html>