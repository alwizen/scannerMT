<?php

namespace Tests\Feature;

use App\Models\Tanker;
use App\Models\TankerCompartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TankerCompartmentQrCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_compartment_with_qr_code_type(): void
    {
        $tanker = Tanker::create([
            'nopol' => 'G 8888 QR',
            'capacity_kl' => 24,
            'status' => 'available',
        ]);

        $comp = TankerCompartment::create([
            'tanker_id' => $tanker->id,
            'compartment_no' => 1,
            'type' => 'qrcode',
            'capacity_kl' => 8.00,
            'rfid_uid' => 'QR-C1-TEST1234',
        ]);

        $this->assertDatabaseHas('tanker_compartments', [
            'id' => $comp->id,
            'tanker_id' => $tanker->id,
            'compartment_no' => 1,
            'type' => 'qrcode',
            'rfid_uid' => 'QR-C1-TEST1234',
        ]);

        $this->assertTrue($comp->isQrCode());
        $this->assertFalse($comp->isRfid());
        $this->assertNotEmpty($comp->generateQrSvg());
        $this->assertStringContainsString('data:image/svg+xml;base64,', $comp->getQrCodeDataUrl());
    }

    public function test_can_download_qr_code_as_png_and_svg(): void
    {
        $tanker = Tanker::create([
            'nopol' => 'G 9999 QR',
            'capacity_kl' => 16,
            'status' => 'available',
        ]);

        $comp = TankerCompartment::create([
            'tanker_id' => $tanker->id,
            'compartment_no' => 1,
            'type' => 'qrcode',
            'capacity_kl' => 8.00,
            'rfid_uid' => 'QR-C1-DOWNLOAD',
        ]);

        // Test PNG download
        $responsePng = $this->get(route('tanker-compartment.qr-code.download', [
            'compartment' => $comp->id,
            'format' => 'png',
        ]));

        $responsePng->assertStatus(200);
        $responsePng->assertHeader('Content-Type', 'image/png');
        $responsePng->assertHeader('Content-Disposition', 'attachment; filename="QR_G_9999_QR_Comp1.png"');
        $this->assertStringStartsWith("\x89PNG", $responsePng->getContent());

        // Test SVG download
        $responseSvg = $this->get(route('tanker-compartment.qr-code.download', [
            'compartment' => $comp->id,
            'format' => 'svg',
        ]));

        $responseSvg->assertStatus(200);
        $responseSvg->assertHeader('Content-Type', 'image/svg+xml');
        $responseSvg->assertHeader('Content-Disposition', 'attachment; filename="QR_G_9999_QR_Comp1.svg"');
        $this->assertStringContainsString('<svg', $responseSvg->getContent());
    }
}
