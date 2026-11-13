<?php

namespace Tests\Unit;

use App\Services\TurnInstructionService;
use PHPUnit\Framework\TestCase;

class TurnInstructionServiceTest extends TestCase
{
    private TurnInstructionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TurnInstructionService();
    }

    public function test_it_builds_depart_turn_and_arrive_steps(): void
    {
        $legs = [
            $this->leg(['lat' => -7.2660, 'lng' => 112.7950], ['lat' => -7.2660, 'lng' => 112.7960], 111.0, 'Jl. Mulyosari'),
            $this->leg(['lat' => -7.2660, 'lng' => 112.7960], ['lat' => -7.2650, 'lng' => 112.7960], 111.0, 'Jl. Sutorejo'),
        ];

        $steps = $this->service->build($legs, $legs[0]['from'], $legs[1]['to']);

        $this->assertCount(3, $steps);
        $this->assertSame('depart', $steps[0]['type']);
        $this->assertStringContainsString('Jl. Mulyosari', $steps[0]['instruction']);
        $this->assertSame('left', $steps[1]['maneuver']);
        $this->assertStringContainsString('Jl. Sutorejo', $steps[1]['instruction']);
        $this->assertSame(111.0, $steps[1]['distance_from_start_m']);
        $this->assertSame('arrive', $steps[2]['type']);
        $this->assertSame(0.0, $steps[2]['remaining_distance_m']);
    }

    public function test_small_angle_with_same_street_does_not_create_a_turn(): void
    {
        $legs = [
            $this->leg(['lat' => -7.2660, 'lng' => 112.7950], ['lat' => -7.2659, 'lng' => 112.7960], 111.0, 'Jl. Mulyosari'),
            $this->leg(['lat' => -7.2659, 'lng' => 112.7960], ['lat' => -7.2658, 'lng' => 112.7970], 111.0, 'Jl. Mulyosari'),
        ];

        $steps = $this->service->build($legs, $legs[0]['from'], $legs[1]['to']);

        $this->assertCount(2, $steps);
        $this->assertSame('depart', $steps[0]['type']);
        $this->assertSame('arrive', $steps[1]['type']);
    }

    public function test_a_sharp_reversal_becomes_a_uturn_step(): void
    {
        $legs = [
            $this->leg(['lat' => -7.2660, 'lng' => 112.7950], ['lat' => -7.2660, 'lng' => 112.7960], 111.0, 'Jl. Mulyosari'),
            $this->leg(['lat' => -7.2660, 'lng' => 112.7960], ['lat' => -7.2660, 'lng' => 112.7950], 111.0, 'Jl. Mulyosari'),
        ];

        $steps = $this->service->build($legs, $legs[0]['from'], $legs[1]['to']);

        $this->assertSame('uturn', $steps[1]['maneuver']);
        $this->assertStringContainsString('Putar balik', $steps[1]['instruction']);
    }

    public function test_empty_legs_return_only_an_arrival_step(): void
    {
        $destination = ['lat' => -7.2660, 'lng' => 112.7960];

        $steps = $this->service->build([], ['lat' => -7.2660, 'lng' => 112.7950], $destination);

        $this->assertCount(1, $steps);
        $this->assertSame('arrive', $steps[0]['type']);
        $this->assertSame($destination, $steps[0]['location']);
    }

    /**
     * @param array{lat: float, lng: float} $from
     * @param array{lat: float, lng: float} $to
     * @return array<string, mixed>
     */
    private function leg(array $from, array $to, float $distance, ?string $street): array
    {
        return [
            'from' => $from,
            'to' => $to,
            'distance_m' => $distance,
            'street' => $street,
            'score' => 90.0,
            'segmen_id' => 1,
            'repair' => false,
            'connector' => false,
        ];
    }
}
