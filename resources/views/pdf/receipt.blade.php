<!doctype html>
<html>
  <head>
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
      rel="stylesheet"
      integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
      crossorigin="anonymous"
    />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net" />

    <style>
      body {
        font-family: "arial", sans-serif;
      }
      .receipt-card {
        border: 1px solid #eee;
        padding: 20px;
        width: 300px;
        margin: auto;
      }
      .text-center {
        text-align: center;
      }
      .success-icon {
        color: #28a745;
        font-size: 40px;
      }
      .amount {
        font-size: 25px;
        font-weight: bold;
        margin: 10px 0;
      }
      table {
        width: 100%;
        font-size: 12px;
        margin-top: 20px;
        border-collapse: collapse;
      }
      table tr td {
        padding: 5px 0;
        font-size: 13px;
      }
      .label {
        color: #666;
        font-size: 13px;
      }
      .value {
        text-align: right;
        font-weight: bold;
      }
      .container {
        position: relative;
        top: 30px;
      }
    </style>
  </head>
  <body>
    <div class="container mt-5">
      <div class="row justify-content-center">
        <div class="col-md-4">
          <div class="receipt-card">
            <div class="text-center">
              <div class="success-icon">
@php
$path = public_path('images/logo1.png');
$type = pathinfo($path, PATHINFO_EXTENSION);
$data = file_get_contents($path);
$base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
@endphp

<img src="{{ $base64 }}" width="150" height="50">              </div>
              <hr />
              <div class="amount">
                <i class="bi bi-check-circle"></i>
{{ $payment->currency }} {{ number_format($payment->amount, 2) }}              </div>
              <hr />
            </div>

            <table>
              <tr>
                <td class="label" style="font-size: 13px">From</td>
                <td class="value">{{ $payment->first_name }} {{ $payment->last_name }}</td>
              </tr>
              <tr>
                <td class="label">Training</td>
                <td class="value"> @if($payment->course)
            {{ $payment->course->title }} 
        @else
            N/A
        @endif</td>
              </tr>
              <tr>
                <td class="label">Reference</td>
                <td class="value">{{ $payment->reference }}</td>
              </tr>
              <tr>
                <td class="label">Date</td>
                <td class="value">{{ $payment->created_at->format('d/m/Y g:i A') }} </td>
              </tr>
              <tr>
                <td class="label">Status</td>
                <td class="value" style="color: #28a745">Completed</td>
              </tr>
            </table>
            <hr />

            <div class="text-center" style="margin-top: 20px">
              <p style="font-size: 9px; color: #888">
                Scan for receipt verification
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>
</html>
