<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Helvetica', sans-serif; color: #333; line-height: 1.5; }
        .header { border-bottom: 3px solid #0a2e67; padding-bottom: 15px; margin-bottom: 30px; }
        .report-title { color: #0a2e67; text-transform: uppercase; font-size: 24px; margin: 0; }
        .summary-card { background: #f4f7fa; border-left: 5px solid #0a2e67; padding: 20px; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; }
        th { background-color: #0a2e67; color: white; text-align: left; padding: 12px; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        .total-row { background-color: #eee; font-weight: bold; font-size: 16px; }
        .text-right { text-align: right; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 10px; color: #999; }
    </style>
</head>
<body>
    <div class="header">
        <table style="border: none;">
            <tr style="border: none;">
                <td style="border: none; width: 70%;">
                    <h1 class="report-title">Revenue Report</h1>
                    <p>Period: <strong>{{ strtoupper($period) }}</strong></p>
                </td>
                <td style="border: none; width: 30%; text-align: right;">
                    <strong>{{ Auth::user()->name }}</strong><br>
                    Generated: {{ date('d M, Y') }}
                </td>
            </tr>
        </table>
    </div>

    <div class="summary-card">
        <span style="font-size: 14px; color: #666;">Total Revenue for this Period:</span><br>
        <span style="font-size: 28px; font-weight: bold; color: #0a2e67;">TZS {{ number_format($total, 2) }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Course Name</th>
                <th>Student</th>
                <th class="text-right">Amount (TZS)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($list as $item)
            <tr>
                <td>{{ $item->created_at->format('d/m/Y') }}</td>
                <td>{{ $item->course->title }}</td>
                <td>{{ $item->user->name ?? 'N/A' }}</td>
                <td class="text-right">{{ number_format($item->amount, 2) }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="3" class="text-right">GRAND TOTAL</td>
                <td class="text-right">TZS {{ number_format($total, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        This is a system-generated financial report.
    </div>
</body>
</html>