<?php
namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Mail\PaymentReceiptMail;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class EnrollmentController extends Controller
{
    /**
     * Handle the Payment Callback/Webhook
     */
    public function handlePaymentCallback(Request $request)
    {
        // 1. Validate the incoming request
        $reference = $request->input('reference');
        $status = strtoupper($request->input('status', 'PENDING'));

        // 2. Find the payment or fail
$payment = Payment::with(['course', 'cohort', 'user'])->where('reference', $reference)->first();        
        if (!$payment) {
            return response()->json(['status' => 'error', 'message' => 'Payment reference not found'], 404);
        }

        try {
            // 3. Prepare data for the update
            $updateData = [
                'status' => $status,
                'user_name' => $request->input('user_name', $payment->user_name),
                'payment_method' => $request->input('payment_method', 'Mobile Money / Card'),
            ];

            // If it's a new invoice (Pending), set an expiry date (e.g., 7 days)
            if ($status === 'PENDING' && !$payment->expires_at) {
                $updateData['expires_at'] = Carbon::now()->addDays(7);
            }

            $payment->update($updateData);

            // 4. Generate the correct PDF type
            // If status is PENDING, we use invoice. If COMPLETED/PAID, we use receipt.
            $isPaid = in_array($status, ['COMPLETED', 'PAID', 'SUCCESS']);
            $view = $isPaid ? 'pdf.receipt' : 'pdf.invoice';
            $mailType = $isPaid ? 'receipt' : 'invoice';

            $pdf = Pdf::loadView($view, compact('payment'));

            // 5. Send Email with Attachment
            Mail::to($payment->email)->send(new PaymentReceiptMail($payment, $pdf, $mailType));

            return response()->json([
                'status' => 'success',
                'message' => "Payment updated to $status and $mailType sent.",
                'data' => [
                    'reference' => $payment->reference,
                    'expiry' => $payment->expires_at
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'System error during processing',
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manual Download Link for Users
     */
    /**
 * Manual Download Link for Users
 * Tumeshajiridhisha kuwa Route inaita downloadDoc
 */
public function downloadDoc($reference)
{
    // 1. Vuta data pamoja na mahusiano yake yote
    $payment = Payment::with(['course', 'cohort', 'user'])->where('reference', $reference)->firstOrFail();
    
    // 2. Angalia kama ameshalipa au bado
    $isPaid = in_array($payment->status, ['COMPLETED', 'PAID', 'SUCCESS']);
    
    // 3. Chagua View na Jina la faili
    $view = $isPaid ? 'pdf.receipt' : 'pdf.invoice';
    $filename = $isPaid ? "Receipt-{$reference}.pdf" : "Invoice-{$reference}.pdf";

    // 4. Tengeneza PDF
    $pdf = Pdf::loadView($view, compact('payment'));
    
    // 5. Download
    return $pdf->download($filename);
}
    /**
 * Get the authenticated user's payment history
 */
public function myPayments()
{
    $userId = auth()->id();

    // Jaribu kuvuta data bila 'with' kwanza kuona kama itatoka
    $payments = Payment::where('user_id', $userId)->get();

    return response()->json($payments);
}
/**
 * Admin: View all payments from all users
 */
public function allPayments()
{
    // Tunavuta malipo yote na taarifa za User, Course, na Cohort
    $payments = Payment::with(['user', 'course', 'cohort'])
                ->orderBy('created_at', 'desc')
                ->get();

    return response()->json($payments);
}

/**
 * Admin: Delete a payment record
 */
public function deletePayment($id)
{
    try {
        $payment = Payment::findOrFail($id);
        $payment->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Payment record deleted successfully'
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to delete record'
        ], 500);
    }
}

/**
 * Provider: View payments for their own courses only
 */
public function providerPayments()
{
    $providerId = auth()->id();

    // Tunapata malipo yote ambapo kozi husika inamilikiwa na huyu Provider
   $payments = Payment::with(['user', 'course', 'cohort'])->get();

    return response()->json($payments);
}
/**
 * Provider: View all student enrollments for their courses
 */
/**
 * Provider: View all student enrollments for their courses
 */

public function providerEnrollments()
{
    try {
        $providerId = auth()->id(); // Hii ndiyo ID ya User aliyelogin (Provider)

        $enrollments = Payment::with(['user', 'course', 'cohort'])
            ->whereHas('course', function ($query) use ($providerId) {
                // BADILISHA HAPA: Tumia jina halisi la column iliyopo kwenye table ya 'courses'
                // Kama kwenye database inaitwa 'provider_id', basi iwe hivi:
                $query->where('provider_id', $providerId); 
                
                // AU kama inaitwa 'created_by', badilisha iwe:
                // $query->where('created_by', $providerId);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($enrollments);

    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

}