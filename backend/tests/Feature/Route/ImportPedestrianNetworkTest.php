<?php

namespace Tests\Feature\Route;

use App\Models\Warga;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportPedestrianNetworkTest extends TestCase
{
    use DatabaseTransactions;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->file = tempnam(sys_get_temp_dir(), 'overpass');
        file_put_contents($this->file, json_encode(['elements' => [
            ['type' => 'node', 'id' => 1, 'lat' => -7.500, 'lon' => 113.000],
            ['type' => 'node', 'id' => 2, 'lat' => -7.500, 'lon' => 113.001],
            ['type' => 'node', 'id' => 3, 'lat' => -7.500, 'lon' => 113.002],
            ['type' => 'node', 'id' => 4, 'lat' => -7.501, 'lon' => 113.001],
            ['type' => 'way', 'id' => 501, 'nodes' => [1, 2, 3], 'tags' => ['highway' => 'residential', 'name' => 'Jl. Impor']],
            ['type' => 'way', 'id' => 502, 'nodes' => [2, 4], 'tags' => ['highway' => 'footway']],
            ['type' => 'way', 'id' => 503, 'nodes' => [1, 4], 'tags' => ['highway' => 'motorway']],
        ]]));
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    private function import(): void
    {
        $this->artisan('app:import-pedestrian-network', ['--file' => $this->file, '--bbox' => '-7.502,112.999,-7.499,113.003'])->assertSuccessful();
    }

    public function test_import_creates_graph_edges_and_keeps_existing_wsm_scores_on_reimport(): void
    {
        $this->import();
        $this->assertSame(3, DB::table('segmen_jalur')->whereIn('osm_way_id', [501, 502, 503])->count());
        $this->assertSame(0, DB::table('segmen_jalur')->where('osm_way_id', 503)->count());

        DB::table('segmen_jalur')->where(['osm_way_id' => 501, 'u_node' => 1, 'v_node' => 2])->update(['skor_kelayakan' => 42]);
        $this->import();

        $this->assertSame(3, DB::table('segmen_jalur')->whereIn('osm_way_id', [501, 502])->count());
        $this->assertEquals(42, DB::table('segmen_jalur')->where(['osm_way_id' => 501, 'u_node' => 1, 'v_node' => 2])->value('skor_kelayakan'));
    }

    public function test_stale_hand_made_segments_hand_their_reports_to_the_nearest_osm_edge(): void
    {
        $legacy = DB::table('segmen_jalur')->insertGetId([
            'osm_way_id' => 1001, 'u_node' => 11, 'v_node' => 12, 'nama_jalan' => 'Seeder lama',
            'geom' => DB::raw("ST_GeogFromText('SRID=4326;LINESTRING(113.0000 -7.50005, 113.0010 -7.50005)')"),
            'panjang_m' => 110, 'skor_kelayakan' => 100,
        ], 'segmen_id');
        $location = DB::table('lokasi')->insertGetId([
            'latitude' => -7.50005, 'longitude' => 113.0005, 'segmen_id' => $legacy,
            'geom' => DB::raw('ST_SetSRID(ST_Point(113.0005, -7.50005), 4326)'), 'alamat' => 'Laporan lama',
        ], 'lokasi_id');
        $warga = Warga::create(['nama' => 'Import', 'email' => uniqid().'@import.test', 'no_hp' => '628'.random_int(100000000, 999999999),
            'password' => 'password123', 'tanggal_daftar' => now(), 'gagal_login' => 0]);
        DB::table('laporan')->insert(['warga_id' => $warga->warga_id, 'kategori_id' => 1, 'lokasi_id' => $location,
            'deskripsi' => 'Rusak', 'status' => 'TERVALIDASI', 'tanggal_lapor' => now()]);

        $this->import();

        $target = DB::table('segmen_jalur')->where(['osm_way_id' => 501, 'u_node' => 1, 'v_node' => 2])->value('segmen_id');
        $this->assertSame($target, DB::table('lokasi')->where('lokasi_id', $location)->value('segmen_id'));
        $this->assertNull(DB::table('segmen_jalur')->where('segmen_id', $legacy)->first());
        $this->assertLessThan(100, (float) DB::table('segmen_jalur')->where('segmen_id', $target)->value('skor_kelayakan'));
    }
}
