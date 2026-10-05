<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TmsService
{
    public function postCekCompartment(string $nopol, Carbon|string $jamCekComp): bool
    {
        $url = $this->endpointUrl('set_cek_compartment');

        if (! $url) {
            return false;
        }

        $jamCekComp = $jamCekComp instanceof Carbon
            ? $jamCekComp->format('Y-m-d H:i:s')
            : Carbon::parse($jamCekComp)->format('Y-m-d H:i:s');

        try {
            $response = Http::acceptJson()
                ->timeout($this->timeout())
                ->post($url, [
                    'nopol' => $nopol,
                    'jam_cek_comp' => $jamCekComp,
                ]);

            if (! $response->successful()) {
                Log::warning('TMS set_cek_compartment gagal', [
                    'nopol' => $nopol,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            $result = $response->json('nopol');

            if (! is_string($result) || $result === 'False' || trim($result) === '') {
                Log::warning('TMS set_cek_compartment ditolak', [
                    'nopol' => $nopol,
                    'response' => $response->json(),
                ]);

                return false;
            }

            Log::info('TMS set_cek_compartment berhasil', [
                'nopol' => $nopol,
                'jam_cek_comp' => $jamCekComp,
                'response_nopol' => $result,
            ]);

            return true;
        } catch (Throwable $e) {
            Log::warning('TMS set_cek_compartment error', [
                'nopol' => $nopol,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getGateInStatus(string $nopol): ?array
    {
        $url = $this->endpointUrl('get_gatein_status');

        if (! $url) {
            return null;
        }

        try {
            $response = Http::acceptJson()
                ->timeout($this->timeout())
                ->post($url, ['nopol' => $nopol]);

            if (! $response->successful()) {
                Log::warning('TMS get_gatein_status gagal', [
                    'nopol' => $nopol,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();

            if (! is_array($data)) {
                Log::warning('TMS get_gatein_status response tidak valid', [
                    'nopol' => $nopol,
                    'body' => $response->body(),
                ]);

                return null;
            }

            $shipmentId = $data['shipment_id'] ?? null;

            if (! is_string($shipmentId) && ! is_numeric($shipmentId)) {
                Log::warning('TMS get_gatein_status tanpa shipment_id', [
                    'nopol' => $nopol,
                    'response' => $data,
                ]);

                return null;
            }

            if ((string) $shipmentId === 'False') {
                Log::info('TMS get_gatein_status: truk belum gate-in', [
                    'nopol' => $nopol,
                ]);

                return [
                    'gate_in' => false,
                    'raw' => $data,
                ];
            }

            Log::info('TMS get_gatein_status berhasil', [
                'nopol' => $nopol,
                'shipment_id' => (string) $shipmentId,
                'gate_in_time' => $data['gate_in_time'] ?? null,
            ]);

            return [
                'gate_in' => true,
                'shipment_id' => (string) $shipmentId,
                'gate_in_time' => $data['gate_in_time'] ?? null,
                'nopol' => $data['nopol'] ?? $nopol,
                'status' => $data['status'] ?? null,
                'nip_supir' => $data['nip_supir'] ?? null,
                'nama_supir' => $data['nama_supir'] ?? null,
                'nip_kernet' => $data['nip_kernet'] ?? null,
                'nama_kernet' => $data['nama_kernet'] ?? null,
                'raw' => $data,
            ];
        } catch (Throwable $e) {
            Log::warning('TMS get_gatein_status error', [
                'nopol' => $nopol,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function endpointUrl(string $endpoint): ?string
    {
        $baseUrl = rtrim((string) config('services.tms.base_url', ''), '/');

        if ($baseUrl === '') {
            Log::warning('TMS_BASE_URL belum dikonfigurasi, request TMS dilewati', [
                'endpoint' => $endpoint,
            ]);

            return null;
        }

        $path = config("services.tms.endpoints.{$endpoint}");

        if (! $path) {
            return null;
        }

        return $baseUrl.$path;
    }

    private function timeout(): int
    {
        $timeout = (int) config('services.tms.timeout', 5);

        return $timeout > 0 ? $timeout : 5;
    }
}
