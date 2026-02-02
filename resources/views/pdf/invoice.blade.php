<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; font-size: 14px; }
        .invoice-box { border: 1px solid #eee; padding: 30px; }
        .row { display: table; width: 100%; }
        .col { display: table-cell; }
        .status-red { color: #d9534f; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #f8f9fa; padding: 10px; border-bottom: 2px solid #ddd; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="invoice-box">
        <div class="row">
            <div class="col">
                <h2 style="color: #333;">INVOICE</h2>
                <strong>To:</strong> {{ $payment->user->name ?? 'Student' }}<br>
                <strong>Email:</strong> {{ $payment->email }}
            </div>
            <div class="col" style="text-align: right;">
                <strong>Ref:</strong> {{ $payment->reference }}<br>
                <strong>Date:</strong> {{ date('d M Y') }}<br>
                <span class="status-red">Expires: {{ \Carbon\Carbon::parse($payment->expires_at)->format('d M Y') }}</span>
            </div>
        </div>

        <hr>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Payment Method</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>Course:</strong> {{ $payment->course->title ?? 'N/A' }}<br>
                        <small><strong>Cohort:</strong> {{ $payment->cohort->intake_name ?? 'N/A' }}</small>
                    </td>
                    <td>{{ $payment->payment_method ?? 'Bank Transfer/Mobile Money' }}</td>
                    <td style="text-align: right;">Tsh {{ number_format($payment->amount, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div style="margin-top: 50px; text-align: center; color: #777;">
            <p>Please pay before the expiry date to secure your slot.</p>
        </div>
    </div>
</body>
</html>