<?php

use Illuminate\Support\Facades\Route;

// Redirect root to login
Route::get('/', function () {
    return redirect('/login');
});

// Auth views
Route::get('/login', function () {
    return view('auth.login');
})->name('login');

Route::get('/register', function () {
    return view('auth.register');
})->name('register');

// Warga views (unprivileged Blade shells, authenticated via localStorage Bearer token)
Route::get('/beranda-warga', function () {
    return view('warga.beranda');
})->name('warga.beranda');

Route::get('/lapor-kondisi', function () {
    return view('warga.lapor');
})->name('warga.lapor');

Route::get('/riwayat-laporan', function () {
    return view('warga.riwayat');
})->name('warga.riwayat');

// Admin views
Route::get('/admin', function () {
    return redirect('/admin/validasi');
});

Route::get('/admin/validasi', function () {
    return view('admin.validasi_queue');
})->name('admin.validasi.queue');

Route::get('/admin/validasi/{id}', function ($id) {
    return view('admin.validasi_detail', ['id' => $id]);
})->name('admin.validasi.detail');

Route::get('/admin/riwayat', function () {
    return view('admin.riwayat');
})->name('admin.riwayat');

Route::get('/admin/statistik', function () {
    return view('admin.statistik');
})->name('admin.statistik');

Route::view('/verify-otp', 'auth.verify-otp')->name('otp.verify.page');