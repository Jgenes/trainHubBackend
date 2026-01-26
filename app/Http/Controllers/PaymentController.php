<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PesapalService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    protected $pesapal;

    public function __construct(PesapalService $pesapal)
    {
        $this->pesapal = $pesapal;
    }

    public function pay(Request $request)
    {
        $payment = $this->pesapal->createOrder(
            auth()->id(),
            $request->course_id,
            $request->cohort_id,
            $request->amount,
            "Payment for course",
            route('pesapal.callback')
        );

        return redirect($payment->redirect_url);
    }

    public function redirect(Payment $payment)
    {
        // After payment, Pesapal can redirect here
        return view('payments.redirect', compact('payment'));
    }

    public function callback(Request $request)
    {
        $merchantRef = $request->merchant_reference;
        $payment = Payment::where('merchant_reference', $merchantRef)->firstOrFail();

        $status = $this->pesapal->checkPaymentStatus($payment);

        return redirect()->route('courses.show', $payment->course_id)
            ->with('status', "Payment status: $status");
    }
}
