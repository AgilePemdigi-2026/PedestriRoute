@extends('layouts.app')

@section('title', 'Kebijakan Privasi - PedestriRoute')

@section('content')
<div class="min-h-screen bg-slate-50 py-10 px-4 sm:px-6 lg:px-8">
    <div class="max-w-3xl mx-auto bg-white rounded-3xl shadow-sm border border-slate-200 p-6 sm:p-10">
        <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-6 mb-8">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-brand-600 rounded-xl flex items-center justify-center text-white font-extrabold text-lg">P</div>
                <div>
                    <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Kebijakan Privasi</h1>
                    <p class="text-xs text-slate-500 font-medium">PedestriRoute &bull; Terakhir diperbarui: 26 November 2026</p>
                </div>
            </div>
            <a href="/login" class="text-sm font-semibold text-brand-600 hover:text-brand-700 shrink-0">&larr; Kembali</a>
        </div>

        <div class="space-y-6 text-sm text-slate-700 leading-relaxed">
            <section>
                <h2 class="text-base font-bold text-slate-900 mb-2">1. Informasi yang Kami Kumpulkan</h2>
                <p>PedestriRoute mengumpulkan informasi berikut untuk menyediakan layanan navigasi pejalan kaki dan pelaporan kondisi trotoar:</p>
                <ul class="list-disc pl-5 mt-2 space-y-1">
                    <li><strong>Informasi Akun &amp; Google OAuth:</strong> Nama, alamat email, ID akun Google, dan URL foto profil saat Anda masuk menggunakan Google.</li>
                    <li><strong>Informasi Kontak Opsional:</strong> Nomor telepon jika Anda menambahkannya pada profil Anda.</li>
                    <li><strong>Data Laporan &amp; Lokasi:</strong> Foto kondisi trotoar, deskripsi laporan, serta koordinat lokasi yang Anda kirimkan secara sukarela untuk pelaporan infrastruktur pejalan kaki atau pencarian rute.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-base font-bold text-slate-900 mb-2">2. Penggunaan Informasi</h2>
                <p>Data yang dikumpulkan digunakan secara eksklusif untuk:</p>
                <ul class="list-disc pl-5 mt-2 space-y-1">
                    <li>Mengautentikasi akun Anda dan menjaga keamanan sesi login.</li>
                    <li>Menampilkan riwayat laporan serta mengirim notifikasi terkait status validasi dan perbaikan laporan Anda.</li>
                    <li>Menghitung rekomendasi rute pejalan kaki yang aman dan nyaman berdasarkan kondisi trotoar terkini.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-base font-bold text-slate-900 mb-2">3. Berbagi &amp; Keamanan Data</h2>
                <p>Kami tidak menjual, menyewakan, atau memperdagangkan data pribadi Anda kepada pihak ketiga. Foto dan lokasi laporan kondisi trotoar yang telah divalidasi dapat ditampilkan secara agregat pada peta kondisi pejalan kaki untuk kepentingan publik.</p>
            </section>

            <section>
                <h2 class="text-base font-bold text-slate-900 mb-2">4. Penyimpanan &amp; Penghapusan Data</h2>
                <p>Anda dapat berhenti menggunakan layanan kapan saja atau menghubungi administrator PedestriRoute untuk meminta pembaruan maupun penghapusan data akun Anda.</p>
            </section>

            <section>
                <h2 class="text-base font-bold text-slate-900 mb-2">5. Kontak</h2>
                <p>Jika Anda memiliki pertanyaan mengenai Kebijakan Privasi ini, silakan hubungi tim pengelola PedestriRoute melalui halaman utama layanan di <a href="https://pedestri-route.caiis.ai.id" class="text-brand-600 hover:underline">pedestri-route.caiis.ai.id</a>.</p>
            </section>
        </div>
    </div>
</div>
@endsection
