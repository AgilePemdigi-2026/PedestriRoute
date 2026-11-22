<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class PlaceSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['required', 'string', 'min:3', 'max:160']]);
        $query = trim($validated['q']);
        if (mb_strlen($query) < 3) {
            return response()->json(['message' => 'Masukkan minimal 3 karakter alamat.'], 422);
        }

        try {
            $places = Cache::remember('route-places:'.hash('sha256', mb_strtolower($query)), 600, function () use ($query): array {
                $response = Http::acceptJson()->withUserAgent('PedestriRoute/1.0')->connectTimeout(3)->timeout(8)
                    ->get(config('services.places.url'), [
                        'q' => $query, 'lat' => -7.2663, 'lon' => 112.7965,
                        'bbox' => '112.75,-7.31,112.85,-7.22', 'limit' => 8,
                    ])->throw();
                $places = [];
                foreach ($response->json('features', []) as $feature) {
                    $coordinates = $feature['geometry']['coordinates'] ?? [];
                    if (($feature['geometry']['type'] ?? '') !== 'Point' || ! is_array($coordinates) || count($coordinates) < 2
                        || ! is_numeric($coordinates[0]) || ! is_numeric($coordinates[1])
                        || abs($coordinates[0]) > 180 || abs($coordinates[1]) > 90) {
                        continue;
                    }
                    $properties = $feature['properties'] ?? [];
                    $street = trim(($properties['street'] ?? '').' '.($properties['housenumber'] ?? ''));
                    $name = $properties['name'] ?? ($street ?: 'Lokasi');
                    $parts = array_unique(array_filter([$name, $street, $properties['district'] ?? null, $properties['city'] ?? null]));
                    $places[] = [
                        'lat' => (float) $coordinates[1], 'lng' => (float) $coordinates[0],
                        'name' => $name, 'label' => implode(', ', $parts), 'type' => 'Alamat',
                    ];
                }

                return $places;
            });

            return response()->json(['places' => $places, 'attribution' => '© OpenStreetMap contributors · Photon']);
        } catch (Throwable) {
            return response()->json(['error' => ['code' => 'PLACE_SEARCH_UNAVAILABLE', 'message' => 'Pencarian alamat sedang tidak tersedia. Pilih rekomendasi lokasi di sekitar Mulyorejo.']], 503);
        }
    }
}
