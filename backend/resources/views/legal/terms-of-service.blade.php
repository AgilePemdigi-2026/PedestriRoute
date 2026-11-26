@extends('layouts.app')

@section('title', 'Ketentuan Layanan - PedestriRoute')

@section('content')
<div class="min-h-screen bg-slate-50 py-10 px-4 sm:px-6 lg:px-8">
    <div class="max-w-3xl mx-auto bg-white rounded-3xl shadow-sm border border-slate-200 p-6 sm:p-10">
        <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-6 mb-8">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-brand-600 rounded-xl flex items-center justify-center text-white font-extrabold text-lg">P</div>
                <div>
                    <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Ketentuan Layanan</h1>
                    <p class="text-xs text-slate-500 font-medium">PedestriRoute &bull; Terakhir diperbarui: 26 November 2026</p>
                </div>
            </div>
            <a href="/login" class="text-sm font-semibold text-brand-600 hover:text-brand-700 shrink-0">&larr; Kembali</a>
        </div>

        <div class="space-y-6 text-sm text-slate-700 leading-relaxed">
            <section>
                <h2 class="text-base font-bold text-slate-900 mb-2">1. Penerimaan Ketentuan</h2>
                <p>Dengan mengakses atau menggunakan layanan PedestriRoute, Anda menyetujui untuk terikat oleh Ketentuan Layanan ini serta <a href="{{ route('legal.privacy') }}" class="text-brand-600 hover:underline">Kebijakan Privasi</a> kami.</p>
            </section>

            <section>
                <h2 class="text-base font-bold text-slate-900 mb-2">2. Penggunaan Layanan</h2>
                <p>PedestriRoute menyediakan informasi rute pejalan kaki dan sarana pelaporan kondisi fasilitas pejalan kaki. Anda setuju untuk:</p>
                <ul class="list-disc pl-5 mt-2 space-y-1">
                    <li>Mengirimkan laporan kondisi trotoar yang akurat, relevan, dan tidak mengandung konten ilegal, menyesatkan, atau melanggar privasi pihak lain.</li>
                    <li>Tidak menyalahgunakan sistem, melakukan akses tidak sah, atau mengganggu ketersediaan layanan.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-base font-bold text-slate-900 mb-2">3. Akun Pengguna</h2>
                <p>Anda dapat mendaftar dan masuk menggunakan email atau akun Google Anda. Anda bertanggung jawab menjaga kerahasiaan akses akun Anda dan segala aktivitas yang terjadi di dalamnya.</p>
            </section>

            <section>
                <h2 class="text-base font-bold text-slate-900 mb-2">4. Batasan Tanggung Jawab</h2>
                <p>Rekomendasi rute dan informasi kondisi trotoar disediakan berdasarkan data laporan pengguna dan sumber peta yang tersedia. Pengguna tetap bertanggung jawab penuh atas keselamatan pribadi saat berjalan kaki di lapangan.</p>
            </section>

            <section>
                <h2 class="text-base font-bold text-slate-900 mb-2">5. Perubahan Ketentuan</h2>
                <p>Kami dapat memperbarui Ketentuan Layanan ini sewaktu-waktu. Versi terbaru akan selalu dipublikasikan pada halaman ini.</p>
            </section>
        </div>
    </div>
</div>
@endsection
