@php
    $heading = $heading ?? 'Navigasi pejalan kaki yang nyaman & aman.';
    $subheading = $subheading ?? 'Temukan rute teraman, laporkan hambatan, dan berkontribusi untuk kota yang lebih ramah pejalan kaki.';
@endphp

<aside class="hidden lg:flex lg:w-1/2 xl:w-[55%] relative overflow-hidden bg-gradient-to-br from-brand-700 via-brand-600 to-teal-500 text-white p-12 xl:p-16 flex-col justify-between">
    <div class="pointer-events-none absolute -top-24 -right-24 w-72 h-72 rounded-full bg-white/10"></div>
    <div class="pointer-events-none absolute -bottom-32 -left-20 w-96 h-96 rounded-full bg-black/10"></div>

    <div class="relative">
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-2xl bg-white/15 backdrop-blur flex items-center justify-center">
                <svg class="w-7 h-7 text-white" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M13.5 5.5c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zM9.8 8.9L7 23h2.1l1.8-8 2.1 2v6h2v-7.5l-2.1-2 .6-3C14.8 12 16.8 13 19 13v-2c-1.9 0-3.5-1-4.3-2.4l-1-1.6c-.4-.6-1-1-1.7-1-.3 0-.5.1-.8.1L6 8.3V13h2V9.6l1.8-.7"/>
                </svg>
            </div>
            <span class="text-xl font-extrabold tracking-tight">PedestriRoute</span>
        </div>

        <h2 class="mt-16 text-4xl xl:text-5xl font-extrabold leading-tight tracking-tight">
            {{ $heading }}
        </h2>
        <p class="mt-4 text-base xl:text-lg text-white/80 max-w-md leading-relaxed">
            {{ $subheading }}
        </p>

        <ul class="mt-10 space-y-4 text-sm font-medium text-white/90">
            <li class="flex items-center gap-3">
                <span class="w-6 h-6 rounded-full bg-white/15 flex items-center justify-center shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </span>
                Rute aman &amp; ramah pejalan kaki
            </li>
            <li class="flex items-center gap-3">
                <span class="w-6 h-6 rounded-full bg-white/15 flex items-center justify-center shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </span>
                Laporan hambatan secara real-time
            </li>
            <li class="flex items-center gap-3">
                <span class="w-6 h-6 rounded-full bg-white/15 flex items-center justify-center shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </span>
                Data kota yang inklusif untuk semua
            </li>
        </ul>
    </div>

    <p class="relative text-xs text-white/60">&copy; {{ date('Y') }} PedestriRoute. Semua hak dilindungi.</p>
</aside>
