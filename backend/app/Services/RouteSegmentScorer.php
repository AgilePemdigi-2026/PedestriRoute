<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Matches route geometry to scored `segmen_jalur` rows and prices it with the
 * WSM feasibility score. Segments are loaded once per request into a grid so
 * every OSRM candidate can be scored without repeated database queries.
 */
class RouteSegmentScorer
{
    private const MATCH_RADIUS_M = 20.0;

    private const CELL_DEGREES = 0.0005;

    /** @var array<string, array<int, array{id: int, score: ?float, a: array{0: float, 1: float}, b: array{0: float, 1: float}}>> */
    private array $grid = [];

    private bool $loaded = false;

    /** @var array<string, float> */
    private array $costCache = [];

    /**
     * Load every scored segment intersecting the bounding box (lng/lat degrees).
     */
    public function load(float $minLng, float $minLat, float $maxLng, float $maxLat): void
    {
        $this->grid = [];
        $this->costCache = [];
        $this->loaded = true;
        try {
            $rows = DB::select(
                'SELECT segmen_id, skor_kelayakan, ST_AsGeoJSON(geom::geometry) AS geojson FROM segmen_jalur WHERE ST_Intersects(geom, ST_MakeEnvelope(?, ?, ?, ?, 4326)::geography)',
                [$minLng - 0.001, $minLat - 0.001, $maxLng + 0.001, $maxLat + 0.001],
            );
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        foreach ($rows as $row) {
            $geometry = json_decode($row->geojson, true);
            $lines = ($geometry['type'] ?? '') === 'MultiLineString' ? $geometry['coordinates'] : [$geometry['coordinates'] ?? []];
            $score = $row->skor_kelayakan === null ? null : max(0.0, min(100.0, (float) $row->skor_kelayakan));
            foreach ($lines as $line) {
                for ($i = 1; $i < count($line); $i++) {
                    $edge = ['id' => (int) $row->segmen_id, 'score' => $score, 'a' => $line[$i - 1], 'b' => $line[$i]];
                    foreach ($this->cellsForEdge($line[$i - 1], $line[$i]) as $cell) {
                        $this->grid[$cell][] = $edge;
                    }
                }
            }
        }
    }

    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    /**
     * Nearest scored segment within 20 m of a point, or null.
     *
     * @param  array{0: float, 1: float}  $point  [lng, lat]
     * @return array{id: int, score: ?float}|null
     */
    public function match(array $point): ?array
    {
        [$cx, $cy] = $this->cell($point);
        $best = null;
        $nearest = self::MATCH_RADIUS_M;
        $seen = [];
        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                foreach ($this->grid[($cx + $dx).':'.($cy + $dy)] ?? [] as $index => $edge) {
                    $key = $edge['id'].'|'.implode(',', $edge['a']).'|'.implode(',', $edge['b']);
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $distance = $this->pointToEdge($point, $edge['a'], $edge['b']);
                    if ($distance <= $nearest) {
                        $nearest = $distance;
                        $best = ['id' => $edge['id'], 'score' => $edge['score']];
                    }
                }
            }
        }

        return $best;
    }

    /**
     * WSM-weighted walking cost in metres:
     * length × (1 + ((100 − score) / 100) × penalty). Unscored edges use the
     * configured neutral score so unknown paths are neither free nor forbidden.
     *
     * @param  array<int, array{0: float, 1: float}>  $coordinates  OSRM [lng, lat]
     */
    public function cost(array $coordinates, float $penalty, float $unscoredScore): float
    {
        $key = md5(json_encode($coordinates)).'|'.$penalty.'|'.$unscoredScore;
        if (isset($this->costCache[$key])) {
            return $this->costCache[$key];
        }

        $cost = 0.0;
        for ($i = 1; $i < count($coordinates); $i++) {
            $a = $coordinates[$i - 1];
            $b = $coordinates[$i];
            $length = $this->metres($a, $b);
            if ($length <= 0) {
                continue;
            }
            $matched = $this->match([($a[0] + $b[0]) / 2, ($a[1] + $b[1]) / 2]);
            $score = $matched['score'] ?? $unscoredScore;
            $cost += $length * (1 + ((100 - $score) / 100) * $penalty);
        }

        return $this->costCache[$key] = $cost;
    }

    /** @return array{0: int, 1: int} */
    private function cell(array $point): array
    {
        return [(int) floor($point[0] / self::CELL_DEGREES), (int) floor($point[1] / self::CELL_DEGREES)];
    }

    /** @return array<int, string> */
    private function cellsForEdge(array $a, array $b): array
    {
        [$ax, $ay] = $this->cell($a);
        [$bx, $by] = $this->cell($b);
        $cells = [];
        for ($x = min($ax, $bx); $x <= max($ax, $bx); $x++) {
            for ($y = min($ay, $by); $y <= max($ay, $by); $y++) {
                $cells[] = $x.':'.$y;
            }
        }

        return $cells;
    }

    private function pointToEdge(array $point, array $a, array $b): float
    {
        $scale = 111195 * cos(deg2rad($point[1]));
        $ax = ($a[0] - $point[0]) * $scale;
        $ay = ($a[1] - $point[1]) * 111195;
        $dx = ($b[0] - $a[0]) * $scale;
        $dy = ($b[1] - $a[1]) * 111195;
        $lengthSquared = $dx * $dx + $dy * $dy;
        $t = $lengthSquared > 0 ? max(0, min(1, -($ax * $dx + $ay * $dy) / $lengthSquared)) : 0;

        return hypot($ax + $t * $dx, $ay + $t * $dy);
    }

    private function metres(array $a, array $b): float
    {
        $scale = 111195 * cos(deg2rad(($a[1] + $b[1]) / 2));

        return hypot(($b[0] - $a[0]) * $scale, ($b[1] - $a[1]) * 111195);
    }
}
