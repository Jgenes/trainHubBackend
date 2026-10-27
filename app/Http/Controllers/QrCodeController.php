<?php

namespace App\Http\Controllers;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeController extends Controller
{
    public function generateQrCode($qrCodeData)
    {
        $png = (string) QrCode::format('png')->size(200)->generate(urldecode((string) $qrCodeData));

        return response()->json([
            'qr_code' => 'data:image/png;base64,'.base64_encode($png),
        ]);
    }
}
