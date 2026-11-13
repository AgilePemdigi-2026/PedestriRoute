<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RouteRecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class RouteNavigationController extends Controller
{
    public function __construct(private readonly RouteRecommendationService $routes)
    {
    }

    /**
     * Recommend a walking route and return turn-by-turn instructions.
     *
     * Endpoint: POST /api/v1/routes/recommend
     */
    public function recommend(Request $request): JsonResponse
    {
        $payload = $request->all();

        // Accept both nested (origin.lat) and flattened (origin_lat) input.
        if (!isset($payload['origin']) && isset($payload['origin_lat'], $payload['origin_lng'])) {
            $payload['origin'] = [
                'lat' => $payload['origin_lat'],
                'lng' => $payload['origin_lng'],
            ];
        }

        if (!isset($payload['destination']) && isset($payload['dest_lat'], $payload['dest_lng'])) {
            $payload['destination'] = [
                'lat' => $payload['dest_lat'],
                'lng' => $payload['dest_lng'],
            ];
        }

        if (!isset($payload['destination']) && isset($payload['destination_lat'], $payload['destination_lng'])) {
            $payload['destination'] = [
                'lat' => $payload['destination_lat'],
                'lng' => $payload['destination_lng'],
            ];
        }

        $validator = Validator::make($payload, [
            'origin' => ['required', 'array'],
            'origin.lat' => ['required', 'numeric', 'between:-90,90'],
            'origin.lng' => ['required', 'numeric', 'between:-180,180'],
            'destination' => ['required', 'array'],
            'destination.lat' => ['required', 'numeric', 'between:-90,90'],
            'destination.lng' => ['required', 'numeric', 'between:-180,180'],
            'preference' => ['nullable', 'string', 'in:safest,fastest'],
        ], [
            'origin.required' => 'Titik awal wajib diisi.',
            'destination.required' => 'Titik tujuan wajib diisi.',
            'origin.lat.*' => 'Koordinat lintang titik awal tidak valid.',
            'origin.lng.*' => 'Koordinat bujur titik awal tidak valid.',
            'destination.lat.*' => 'Koordinat lintang titik tujuan tidak valid.',
            'destination.lng.*' => 'Koordinat bujur titik tujuan tidak valid.',
            'preference.in' => 'Preferensi rute harus "safest" atau "fastest".',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_ROUTE_PARAMETERS',
                    'message' => 'Koordinat atau preferensi rute tidak valid.',
                    'fields' => $validator->errors()->toArray(),
                ],
            ], 422);
        }

        try {
            $route = $this->routes->recommend(
                [
                    'lat' => (float) $payload['origin']['lat'],
                    'lng' => (float) $payload['origin']['lng'],
                ],
                [
                    'lat' => (float) $payload['destination']['lat'],
                    'lng' => (float) $payload['destination']['lng'],
                ],
                (string) ($payload['preference'] ?? 'safest'),
            );

            return response()->json(['success' => true] + $route);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'error' => [
                    'code' => 'ROUTE_ENGINE_ERROR',
                    'message' => 'Rute tidak dapat dihitung saat ini. Silakan coba lagi.',
                ],
            ], 500);
        }
    }
}
