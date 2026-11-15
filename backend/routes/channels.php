<?php

use App\Models\Admin;
use App\Models\Warga;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel(
    'notifications.warga.{id}',
    function ($user, string $id): bool {
        return $user instanceof Warga
            && ctype_digit($id)
            && (int) $user->getAuthIdentifier() === (int) $id;
    }
);

Broadcast::channel(
    'notifications.admin.{id}',
    function ($user, string $id): bool {
        return $user instanceof Admin
            && ctype_digit($id)
            && (int) $user->getAuthIdentifier() === (int) $id;
    }
);