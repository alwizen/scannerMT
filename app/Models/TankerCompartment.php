<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TankerCompartment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tanker_id',
        'compartment_no',
        'type',
        'capacity_kl',
        'rfid_uid',
    ];

    protected $attributes = [
        'type' => 'rfid',
    ];

    protected $casts = [
        'tanker_id' => 'integer',
        'compartment_no' => 'integer',
        'capacity_kl' => 'decimal:2',
    ];

    public function isQrCode(): bool
    {
        return $this->type === 'qrcode';
    }

    public function isRfid(): bool
    {
        return $this->type === 'rfid' || empty($this->type);
    }

    public function generateQrSvg(): string
    {
        if (empty($this->rfid_uid)) {
            return '';
        }

        $options = new \chillerlan\QRCode\QROptions([
            'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_MARKUP_SVG,
            'svgAddXmlHeader' => false,
        ]);

        return (new \chillerlan\QRCode\QRCode($options))->render($this->rfid_uid);
    }

    public function getQrCodeDataUrl(): string
    {
        if (empty($this->rfid_uid)) {
            return '';
        }

        return (new \chillerlan\QRCode\QRCode())->render($this->rfid_uid);
    }

    public function tanker(): BelongsTo
    {
        return $this->belongsTo(Tanker::class);
    }
}
