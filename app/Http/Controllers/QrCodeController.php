<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeController extends Controller
{
    public function generateQrCode($qrCodeData)
    {
        $qrCodeImage = QrCode::format('png')->size(200)->generate($qrCodeData);
        $type = 'png';
        $data = $qrCodeImage;
        $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);    
        return response()->json(['qr_code' => $base64]);
    }
}
