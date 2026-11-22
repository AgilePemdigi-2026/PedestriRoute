<?php

namespace App\Services;

class RouteHazardGeometry
{
    public function distance(array $a, array $b): float
    {
        $lat1 = deg2rad($a['lat']);
        $lat2 = deg2rad($b['lat']);
        $value = sin(($lat2 - $lat1) / 2) ** 2
            + cos($lat1) * cos($lat2) * sin(deg2rad($b['lng'] - $a['lng']) / 2) ** 2;

        return 12742000 * asin(min(1, sqrt($value)));
    }

    /**
     * Clip every route edge against fixed report circles. Splitting at all circle
     * boundaries produces non-overlapping dashed sections, even for sparse routes.
     *
     * @param  array<int, array{0: float, 1: float}>  $coordinates  OSRM [lng, lat]
     * @return array{points: array, sections: array}
     */
    public function assess(array $coordinates, array $reports, float $radius): array
    {
        $hits = [];
        $sections = [];
        $travelled = 0.0;

        for ($i = 1; $i < count($coordinates); $i++) {
            $a = $coordinates[$i - 1];
            $b = $coordinates[$i];
            $edgeLength = $this->distance(['lat' => $a[1], 'lng' => $a[0]], ['lat' => $b[1], 'lng' => $b[0]]);
            $intervals = [];
            $boundaries = [0.0, 1.0];

            foreach ($reports as $report) {
                $scale = 111195 * cos(deg2rad($report['lat']));
                $ax = ($a[0] - $report['lng']) * $scale;
                $ay = ($a[1] - $report['lat']) * 111195;
                $dx = ($b[0] - $a[0]) * $scale;
                $dy = ($b[1] - $a[1]) * 111195;
                $lengthSquared = $dx * $dx + $dy * $dy;
                $t = $lengthSquared > 0 ? max(0, min(1, -($ax * $dx + $ay * $dy) / $lengthSquared)) : 0;
                $distance = hypot($ax + $t * $dx, $ay + $t * $dy);

                if ($distance > $radius + 0.001) {
                    continue;
                }

                $id = $report['laporan_id'];
                $closest = min($distance, $hits[$id]['distance_to_route_m'] ?? INF);
                $hits[$id] = $report + ['distance_to_route_m' => round($closest, 1)];
                if ($lengthSquared < 0.000001) {
                    continue;
                }

                $dot = $ax * $dx + $ay * $dy;
                $discriminant = max(0, $dot * $dot - $lengthSquared * ($ax * $ax + $ay * $ay - $radius * $radius));
                $start = max(0, (-$dot - sqrt($discriminant)) / $lengthSquared);
                $end = min(1, (-$dot + sqrt($discriminant)) / $lengthSquared);
                if ($end > $start) {
                    $intervals[] = ['start' => $start, 'end' => $end, 'report' => $report];
                    $boundaries[] = $start;
                    $boundaries[] = $end;
                }
            }

            sort($boundaries, SORT_NUMERIC);
            $boundaries = array_values(array_unique($boundaries, SORT_REGULAR));
            for ($j = 1; $j < count($boundaries); $j++) {
                $start = $boundaries[$j - 1];
                $end = $boundaries[$j];
                $mid = ($start + $end) / 2;
                $active = array_filter($intervals, fn (array $interval): bool => $mid > $interval['start'] && $mid < $interval['end']);
                if ($active === []) {
                    continue;
                }

                $from = [$a[1] + ($b[1] - $a[1]) * $start, $a[0] + ($b[0] - $a[0]) * $start];
                $to = [$a[1] + ($b[1] - $a[1]) * $end, $a[0] + ($b[0] - $a[0]) * $end];
                $ids = array_values(array_map(fn (array $interval): int => $interval['report']['laporan_id'], $active));
                sort($ids);
                $repair = count(array_filter($active, fn (array $interval): bool => $interval['report']['repair'])) > 0;
                $distance = $this->distance(['lat' => $from[0], 'lng' => $from[1]], ['lat' => $to[0], 'lng' => $to[1]]);
                $last = count($sections) - 1;
                if ($last >= 0 && $sections[$last]['report_ids'] === $ids && end($sections[$last]['polyline']) === $from
                    && abs($sections[$last]['end_distance_m'] - ($travelled + $start * $edgeLength)) < 0.01) {
                    $sections[$last]['polyline'][] = $to;
                    $sections[$last]['distance_m'] += $distance;
                    $sections[$last]['end_distance_m'] = $travelled + $end * $edgeLength;
                } else {
                    $sections[] = ['polyline' => [$from, $to], 'distance_m' => $distance, 'report_ids' => $ids, 'repair' => $repair,
                        'start_distance_m' => $travelled + $start * $edgeLength, 'end_distance_m' => $travelled + $end * $edgeLength];
                }
            }
            $travelled += $edgeLength;
        }

        return ['points' => array_values($hits), 'sections' => $sections];
    }

    /** Only contiguous departure/arrival exposure may be unavoidable; re-entry is blocked. */
    public function blockingAssessment(array $coordinates, array $reports, float $radius): array
    {
        $assessment = $this->assess($coordinates, $reports, $radius);
        $first = ['lng' => $coordinates[0][0], 'lat' => $coordinates[0][1]];
        $lastCoordinate = $coordinates[count($coordinates) - 1];
        $last = ['lng' => $lastCoordinate[0], 'lat' => $lastCoordinate[1]];
        $length = 0.0;
        for ($i = 1; $i < count($coordinates); $i++) {
            $length += $this->distance(['lng' => $coordinates[$i - 1][0], 'lat' => $coordinates[$i - 1][1]], ['lng' => $coordinates[$i][0], 'lat' => $coordinates[$i][1]]);
        }
        $blocking = [];
        foreach ($assessment['points'] as &$report) {
            $intervals = [];
            foreach ($assessment['sections'] as $section) {
                if (! in_array($report['laporan_id'], $section['report_ids'], true)) {
                    continue;
                }
                $index = count($intervals) - 1;
                if ($index >= 0 && $section['start_distance_m'] <= $intervals[$index][1] + 0.01) {
                    $intervals[$index][1] = $section['end_distance_m'];
                } else {
                    $intervals[] = [$section['start_distance_m'], $section['end_distance_m']];
                }
            }
            $allowed = $intervals !== [];
            foreach ($intervals as [$start, $end]) {
                $departure = $start <= 0.01 && $this->distance($first, $report) <= $radius + 0.01;
                $arrival = $end >= $length - 0.01 && $this->distance($last, $report) <= $radius + 0.01;
                if (! $departure && ! $arrival) {
                    $allowed = false;
                }
            }
            $report['endpoint_unavoidable'] = $allowed;
            if (! $allowed) {
                $blocking[] = $report;
            }
        }
        unset($report);
        $assessment['blocking_points'] = $blocking;

        return $assessment;
    }

    public function offset(array $point, float $bearing, float $distance): array
    {
        $angle = deg2rad($bearing);

        return [
            'lat' => $point['lat'] + $distance * cos($angle) / 111195,
            'lng' => $point['lng'] + $distance * sin($angle) / (111195 * cos(deg2rad($point['lat']))),
        ];
    }
}
