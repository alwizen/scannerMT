<div class="space-y-4 p-2">
    @foreach ($compartments as $comp)
        <div class="p-4 border rounded-xl bg-white dark:bg-gray-800 dark:border-gray-700 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="p-2 rounded-lg bg-gray-50 border dark:bg-gray-900 dark:border-gray-700 shrink-0 flex items-center justify-center" style="width: 110px; height: 110px;">
                    @if ($comp->rfid_uid)
                        <img src="{{ $comp->getQrCodeDataUrl() }}" alt="QR Code Comp {{ $comp->compartment_no }}" style="width: 90px; height: 90px; object-fit: contain;" />
                    @else
                        <span class="text-xs text-gray-400">Tidak ada kode</span>
                    @endif
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-base text-gray-900 dark:text-white">Kompartemen {{ $comp->compartment_no }}</span>
                        <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full uppercase {{ $comp->type === 'qrcode' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' }}">
                            {{ strtoupper($comp->type ?? 'rfid') }}
                        </span>
                    </div>
                    <p class="text-xs font-mono text-gray-600 dark:text-gray-400 mt-1">
                        UID/Code: <strong class="text-gray-900 dark:text-white">{{ $comp->rfid_uid }}</strong>
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Kapasitas: {{ $comp->capacity_kl }} KL
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('tanker-compartment.qr-code.download', ['compartment' => $comp->id, 'format' => 'png']) }}"
                   target="_blank"
                   style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; font-size: 13px; font-weight: 600; color: #ffffff; background-color: #4f46e5; border-radius: 8px; text-decoration: none;">
                    <svg style="width: 16px; height: 16px; min-width: 16px; min-height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    Download PNG
                </a>
                <a href="{{ route('tanker-compartment.qr-code.download', ['compartment' => $comp->id, 'format' => 'svg']) }}"
                   target="_blank"
                   style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; font-size: 13px; font-weight: 600; color: #374151; background-color: #ffffff; border: 1px solid #d1d5db; border-radius: 8px; text-decoration: none;">
                    <svg style="width: 16px; height: 16px; min-width: 16px; min-height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    Download SVG
                </a>
            </div>
        </div>
    @endforeach
</div>
