<?php

namespace App\Services;

/**
 * Builds human readable turn-by-turn instructions from a list of route legs.
 *
 * A leg is the smallest navigable piece of a route and has the following shape:
 * [
 *   'from' => ['lat' => -7.26, 'lng' => 112.79],
 *   'to'   => ['lat' => -7.27, 'lng' => 112.80],
 *   'distance_m' => 42.5,
 *   'street' => 'Jl. Raya Mulyosari',
 *   'connector' => false,
 * ]
 */
class TurnInstructionService
{
    public const WALK_SPEED_MPS = 1.25;

    /**
     * @param array<int, array<string, mixed>> $legs
     * @param array{lat: float, lng: float} $origin
     * @param array{lat: float, lng: float} $destination
     * @return array<int, array<string, mixed>>
     */
    public function build(array $legs, array $origin, array $destination): array
    {
        $legs = array_values(array_filter($legs, function (array $leg): bool {
            return ($leg['distance_m'] ?? 0) > 0.1;
        }));

        if (empty($legs)) {
            return [[
                'index' => 0,
                'type' => 'arrive',
                'maneuver' => 'arrive',
                'modifier' => 'straight',
                'instruction' => 'Anda telah tiba di tujuan.',
                'street' => null,
                'distance_m' => 0.0,
                'distance_from_start_m' => 0.0,
                'remaining_distance_m' => 0.0,
                'duration_s' => 0,
                'location' => $destination,
                'bearing_before' => 0,
                'bearing_after' => 0,
            ]];
        }

        $total = array_reduce($legs, fn (float $carry, array $leg): float => $carry + (float) $leg['distance_m'], 0.0);

        $firstStreet = $this->cleanStreet($legs[0]['street'] ?? null);
        $startBearing = $this->bearing($legs[0]['from'], $legs[0]['to']);

        $steps = [[
            'index' => 0,
            'type' => 'depart',
            'maneuver' => 'depart',
            'modifier' => 'straight',
            'instruction' => $firstStreet
                ? sprintf('Mulai perjalanan ke arah %s di %s.', $this->compassLabel($startBearing), $firstStreet)
                : sprintf('Mulai perjalanan ke arah %s.', $this->compassLabel($startBearing)),
            'street' => $firstStreet,
            'distance_m' => 0.0,
            'distance_from_start_m' => 0.0,
            'remaining_distance_m' => round($total, 1),
            'duration_s' => 0,
            'location' => $legs[0]['from'],
            'bearing_before' => $startBearing,
            'bearing_after' => $startBearing,
        ]];

        $cumulative = 0.0;
        $lastManeuverAt = 0.0;
        $lastIndex = count($legs) - 1;

        foreach ($legs as $i => $leg) {
            $cumulative += (float) $leg['distance_m'];

            if ($i >= $lastIndex) {
                break;
            }

            $next = $legs[$i + 1];
            $bearingBefore = $this->bearing($leg['from'], $leg['to']);
            $bearingAfter = $this->bearing($next['from'], $next['to']);
            $angle = $this->normalizeAngle($bearingAfter - $bearingBefore);

            $streetChanged = !($next['connector'] ?? false)
                && !($leg['connector'] ?? false)
                && $this->cleanStreet($next['street'] ?? null) !== $this->cleanStreet($leg['street'] ?? null);

            // Small GPS/OSM jitter should not produce a turn instruction.
            if (abs($angle) < 30 && !$streetChanged) {
                continue;
            }

            $nextStreet = $this->cleanStreet($next['street'] ?? null);
            $maneuver = $this->classifyManeuver($angle, $streetChanged);
            $distance = $cumulative - $lastManeuverAt;

            $steps[] = [
                'index' => count($steps),
                'type' => $maneuver === 'straight' ? 'continue' : 'turn',
                'maneuver' => $maneuver,
                'modifier' => $this->modifier($maneuver),
                'instruction' => $this->instruction($maneuver, $nextStreet),
                'street' => $nextStreet,
                'distance_m' => round($distance, 1),
                'distance_from_start_m' => round($cumulative, 1),
                'remaining_distance_m' => round(max(0, $total - $cumulative), 1),
                'duration_s' => (int) round($distance / self::WALK_SPEED_MPS),
                'location' => $leg['to'],
                'bearing_before' => $bearingBefore,
                'bearing_after' => $bearingAfter,
            ];

            $lastManeuverAt = $cumulative;
        }

        $finalDistance = $total - $lastManeuverAt;
        $steps[] = [
            'index' => count($steps),
            'type' => 'arrive',
            'maneuver' => 'arrive',
            'modifier' => 'straight',
            'instruction' => 'Anda telah tiba di tujuan.',
            'street' => null,
            'distance_m' => round($finalDistance, 1),
            'distance_from_start_m' => round($total, 1),
            'remaining_distance_m' => 0.0,
            'duration_s' => (int) round($finalDistance / self::WALK_SPEED_MPS),
            'location' => $destination,
            'bearing_before' => $this->bearing($legs[$lastIndex]['from'], $legs[$lastIndex]['to']),
            'bearing_after' => $this->bearing($legs[$lastIndex]['from'], $legs[$lastIndex]['to']),
        ];

        return $steps;
    }

    private function classifyManeuver(float $angle, bool $streetChanged): string
    {
        $absolute = abs($angle);

        if ($absolute < 30) {
            return 'straight';
        }

        if ($absolute >= 150) {
            return 'uturn';
        }

        $side = $angle > 0 ? 'right' : 'left';

        if ($absolute < 60) {
            return 'slight_' . $side;
        }

        if ($absolute < 120) {
            return $side;
        }

        return 'sharp_' . $side;
    }

    private function modifier(string $maneuver): string
    {
        return match ($maneuver) {
            'slight_left' => 'slight left',
            'slight_right' => 'slight right',
            'sharp_left' => 'sharp left',
            'sharp_right' => 'sharp right',
            'left' => 'left',
            'right' => 'right',
            'uturn' => 'uturn',
            default => 'straight',
        };
    }

    private function instruction(string $maneuver, ?string $street): string
    {
        $target = $street ? ' ke ' . $street : '';

        return match ($maneuver) {
            'slight_left' => 'Belok sedikit ke kiri' . $target . '.',
            'slight_right' => 'Belok sedikit ke kanan' . $target . '.',
            'sharp_left' => 'Belok tajam ke kiri' . $target . '.',
            'sharp_right' => 'Belok tajam ke kanan' . $target . '.',
            'left' => 'Belok kiri' . $target . '.',
            'right' => 'Belok kanan' . $target . '.',
            'uturn' => 'Putar balik' . $target . '.',
            default => 'Lanjutkan lurus' . $target . '.',
        };
    }

    private function cleanStreet(?string $street): ?string
    {
        if ($street === null) {
            return null;
        }

        $street = trim($street);
        if ($street === '' || strcasecmp($street, 'Jalur penghubung') === 0) {
            return null;
        }

        return $street;
    }

    private function bearing(array $from, array $to): float
    {
        $lat1 = deg2rad((float) $from['lat']);
        $lat2 = deg2rad((float) $to['lat']);
        $deltaLng = deg2rad((float) $to['lng'] - (float) $from['lng']);

        $y = sin($deltaLng) * cos($lat2);
        $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($deltaLng);

        return fmod(rad2deg(atan2($y, $x)) + 360, 360);
    }

    private function normalizeAngle(float $angle): float
    {
        while ($angle > 180) {
            $angle -= 360;
        }
        while ($angle < -180) {
            $angle += 360;
        }
        return $angle;
    }

    private function compassLabel(float $bearing): string
    {
        $directions = ['Utara', 'Timur Laut', 'Timur', 'Tenggara', 'Selatan', 'Barat Daya', 'Barat', 'Barat Laut'];
        $index = (int) round(fmod($bearing + 360, 360) / 45) % 8;

        return $directions[$index];
    }
}
