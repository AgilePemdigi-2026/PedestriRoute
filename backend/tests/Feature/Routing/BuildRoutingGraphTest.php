<?php

namespace Tests\Feature\Routing;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BuildRoutingGraphTest extends TestCase
{
    use DatabaseTransactions;

    public function test_crossing_lines_are_noded_without_changing_original_segments(): void
    {
        $ids = [];
        foreach ([
            'LINESTRING(112.795 -7.2665,112.796 -7.2665)',
            'LINESTRING(112.7955 -7.266,112.7955 -7.267)',
        ] as $wkt) {
            $ids[] = DB::table('segmen_jalur')->insertGetId([
                'nama_jalan' => 'Topology test',
                'geom' => DB::raw("ST_GeogFromText('$wkt')"),
                'skor_kelayakan' => 100,
            ], 'segmen_id');
        }
        $before = DB::table('segmen_jalur')->whereIn('segmen_id', $ids)
            ->orderBy('segmen_id')->pluck('geom', 'segmen_id')->all();

        $this->artisan('routing:build')->assertSuccessful();

        $junction = DB::selectOne('SELECT id FROM routing_vertices
            WHERE ST_DWithin(geom::geography, ST_SetSRID(ST_Point(112.7955,-7.2665),4326)::geography,0.001)
            LIMIT 1');
        $this->assertNotNull($junction);
        $this->assertGreaterThanOrEqual(4, DB::table('routing_edges')
            ->whereIn('segmen_id', $ids)
            ->where(fn ($query) => $query->where('source', $junction->id)->orWhere('target', $junction->id))
            ->count());
        $this->assertSame($before, DB::table('segmen_jalur')->whereIn('segmen_id', $ids)
            ->orderBy('segmen_id')->pluck('geom', 'segmen_id')->all());
    }
}
