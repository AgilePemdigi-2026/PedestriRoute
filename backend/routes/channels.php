<?php

use App\Models\Admin;
use App\Models\Warga;
use Illuminate\Support\Facades\Broadcast;

// Channel privat per warga: hanya warga yang bersangkutan.
Broadcast::channel('warga.{id}', function ($user, $id) {
    return $user instanceof Warga && (int) $user->warga_id === (int) $id;
}, ['guards' => ['sanctum']]);

// Channel privat untuk semua admin.
Broadcast::channel('admin.reports', function ($user) {
    return $user instanceof Admin;
}, ['guards' => ['sanctum']]);