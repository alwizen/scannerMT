<?php

namespace App\Http\Controllers;

use App\Models\TankerCompartment;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TankerCompartmentQrController extends Controller
{
    public function download(Request $request, TankerCompartment $compartment): Response
    {
        if (empty($compartment->rfid_uid)) {
            abort(404, 'RFID/QR Code tidak ditemukan untuk kompartemen ini');
        }

        $format = strtolower($request->query('format', 'png'));
        $nopol = $compartment->tanker?->nopol ? str_replace(' ', '_', $compartment->tanker->nopol) : 'MT';
        $filename = "QR_{$nopol}_Comp{$compartment->compartment_no}.{$format}";

        if ($format === 'svg') {
            $options = new QROptions([
                'outputType' => QRCode::OUTPUT_MARKUP_SVG,
                'svgAddXmlHeader' => true,
                'imageBase64' => false,
            ]);
            $svgData = (new QRCode($options))->render($compartment->rfid_uid);

            return response($svgData, 200, [
                'Content-Type' => 'image/svg+xml',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        // Default PNG
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'scale' => 10,
            'imageBase64' => false,
        ]);
        $imageData = (new QRCode($options))->render($compartment->rfid_uid);

        return response($imageData, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
