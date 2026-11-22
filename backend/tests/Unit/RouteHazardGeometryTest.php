<?php

namespace Tests\Unit;

use App\Services\RouteHazardGeometry;
use PHPUnit\Framework\TestCase;

class RouteHazardGeometryTest extends TestCase
{
    public function test_report_between_sparse_vertices_clips_only_fifty_metres_of_route(): void
    {
        $assessment = (new RouteHazardGeometry)->assess([[-0.01, 0], [0.01, 0]], [
            ['laporan_id' => 1, 'lat' => 0, 'lng' => 0, 'repair' => false],
            ['laporan_id' => 2, 'lat' => 0.001, 'lng' => 0, 'repair' => false],
        ], 25);

        $this->assertSame([1], array_column($assessment['points'], 'laporan_id'));
        $this->assertCount(1, $assessment['sections']);
        $this->assertEqualsWithDelta(50, $assessment['sections'][0]['distance_m'], 0.1);
        $this->assertEqualsWithDelta(-0.00022483, $assessment['sections'][0]['polyline'][0][1], 0.0000001);
        $this->assertFalse($assessment['sections'][0]['repair']);
    }

    public function test_overlapping_report_zones_do_not_double_count_distance(): void
    {
        $assessment = (new RouteHazardGeometry)->assess([[-0.01, 0], [0.01, 0]], [
            ['laporan_id' => 1, 'lat' => 0, 'lng' => 0, 'repair' => false],
            ['laporan_id' => 2, 'lat' => 0, 'lng' => 0.0001, 'repair' => true],
        ], 25);

        $this->assertSame([1, 2], array_column($assessment['points'], 'laporan_id'));
        $this->assertCount(3, $assessment['sections']);
        $this->assertEqualsWithDelta(61.12, array_sum(array_column($assessment['sections'], 'distance_m')), 0.1);
        $repairSections = array_filter($assessment['sections'], fn (array $section): bool => $section['repair']);
        $this->assertEqualsWithDelta(50, array_sum(array_column($repairSections, 'distance_m')), 0.1);
    }

    public function test_reports_behind_an_endpoint_and_outside_radius_are_not_encountered(): void
    {
        $assessment = (new RouteHazardGeometry)->assess([[0, 0], [0.01, 0]], [
            ['laporan_id' => 1, 'lat' => 0, 'lng' => -0.001, 'repair' => false],
        ], 25);

        $this->assertSame(['points' => [], 'sections' => []], $assessment);
    }

    public function test_departure_and_arrival_exposure_are_allowed_but_midroute_reentry_is_blocked(): void
    {
        $geometry = new RouteHazardGeometry;
        $reports = [
            ['laporan_id' => 1, 'lat' => 0, 'lng' => 0, 'repair' => false],
            ['laporan_id' => 2, 'lat' => 0, 'lng' => 0.01, 'repair' => false],
        ];
        $direct = $geometry->blockingAssessment([[0, 0], [0.01, 0]], $reports, 25);
        $this->assertSame([], $direct['blocking_points']);
        $this->assertSame([true, true], array_column($direct['points'], 'endpoint_unavoidable'));

        $loop = $geometry->blockingAssessment([[0, 0], [0.002, 0], [0, 0], [0.002, 0], [0.01, 0]], $reports, 25);
        $this->assertSame([1], array_column($loop['blocking_points'], 'laporan_id'));
    }
}
