<?php
namespace Tests\Feature\Routing;
use App\Models\Warga;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class RouteRecommendationTest extends TestCase
{
    use DatabaseTransactions;
    private array $segments=[];
    private Warga $warga;
    protected function setUp(): void
    {
        parent::setUp();
        DB::table('routing_edges')->delete(); DB::table('routing_vertices')->delete();
        $this->warga=Warga::create(['nama'=>'Routing test','email'=>uniqid('route').'@test.com','no_hp'=>'628'.random_int(100000000,999999999),
            'password'=>bcrypt('password123'),'tanggal_daftar'=>now(),'gagal_login'=>0]);
        Sanctum::actingAs($this->warga,['role:warga']);
        $vertices=[];
        foreach([[112.795,-7.266],[112.796,-7.266],[112.797,-7.266],[112.796,-7.267],[112.8,-7.269],[112.801,-7.269]] as $i=>[$x,$y]) {
            $vertices[$i+1]=DB::table('routing_vertices')->insertGetId(['geom'=>DB::raw("ST_SetSRID(ST_Point($x,$y),4326)")]);
        }
        $points=[1=>[112.795,-7.266],2=>[112.796,-7.266],3=>[112.797,-7.266],4=>[112.796,-7.267],5=>[112.8,-7.269],6=>[112.801,-7.269]];
        foreach([[1,2,0],[2,3,0],[1,4,100],[4,3,100],[5,6,100]] as $i=>[$a,$b,$score]) {
            [$x1,$y1]=$points[$a];[$x2,$y2]=$points[$b];
            $wkt="ST_GeomFromText('LINESTRING($x1 $y1,$x2 $y2)',4326)";
            $id=DB::table('segmen_jalur')->insertGetId(['osm_way_id'=>random_int(100000000,999999999),'u_node'=>$vertices[$a],
                'v_node'=>$vertices[$b],'nama_jalan'=>'Routing fixture','geom'=>DB::raw("$wkt::geography"),
                'panjang_m'=>DB::raw("ST_Length($wkt::geography)"),'skor_kelayakan'=>$score,'tanggal_update'=>now()],'segmen_id');
            $this->segments[]=$id;
            DB::table('routing_edges')->insert(['segmen_id'=>$id,'source'=>$vertices[$a],'target'=>$vertices[$b],
                'geom'=>DB::raw($wkt),'length_m'=>DB::raw("ST_Length($wkt::geography)")]);
        }
    }
    private function input(string $preference='fastest'): array
    { return ['origin_lat'=>-7.266,'origin_lng'=>112.795,'dest_lat'=>-7.266,'dest_lng'=>112.797,'preference'=>$preference]; }
    public function test_continuous_geojson_and_metrics(): void
    {
        $r=$this->postJson('/api/v1/routes/recommend',$this->input())->assertOk()->assertJsonPath('data.geometry.type','LineString');
        $coords=$r->json('data.geometry.coordinates');
        $this->assertEquals([112.795,-7.266],$coords[0]);$this->assertEquals([112.797,-7.266],end($coords));
        $this->assertGreaterThan(200,$r->json('data.properties.distance_m'));
        $this->assertEqualsWithDelta($r->json('data.properties.distance_m')/75,$r->json('data.properties.duration_minutes'),.02);
    }
    public function test_safest_avoids_low_score_and_reads_new_scores(): void
    {
        $fast=$this->postJson('/api/v1/routes/recommend',$this->input())->assertOk()->json('data.properties');
        $safe=$this->postJson('/api/v1/routes/recommend',$this->input('safest'))->assertOk()->json('data.properties');
        $this->assertGreaterThan($fast['distance_m'],$safe['distance_m']);
        $this->assertEquals(100,$safe['average_score']);
        DB::table('segmen_jalur')->whereIn('segmen_id',array_slice($this->segments,0,2))->update(['skor_kelayakan'=>100]);
        $this->postJson('/api/v1/routes/recommend',$this->input('safest'))->assertOk()->assertJsonPath('data.properties.segment_ids',array_slice($this->segments,0,2));
    }
    public function test_accessible_avoids_active_obstruction_and_completed_repairs_remove_penalty(): void
    {
        DB::table('segmen_jalur')->whereIn('segmen_id',$this->segments)->update(['skor_kelayakan'=>100]);
        $category=DB::table('kategori_masalah')->insertGetId(['nama_kategori'=>'Routing obstacle','bobot'=>.15],'kategori_id');
        config(['routing.accessibility_categories'=>[$category]]);
        $location=DB::table('lokasi')->insertGetId(['latitude'=>-7.266,'longitude'=>112.7955,
            'geom'=>DB::raw('ST_SetSRID(ST_Point(112.7955,-7.266),4326)::geography'),'segmen_id'=>$this->segments[0]],'lokasi_id');
        $report=DB::table('laporan')->insertGetId(['warga_id'=>$this->warga->warga_id,'kategori_id'=>$category,'lokasi_id'=>$location,
            'deskripsi'=>'Fixture obstruction','status'=>'TERVALIDASI','tanggal_lapor'=>now()],'laporan_id');
        $this->postJson('/api/v1/routes/recommend',$this->input('accessible'))->assertOk()->assertJsonPath('data.properties.segment_ids',array_slice($this->segments,2,2));
        DB::table('laporan')->where('laporan_id',$report)->update(['status_perbaikan'=>'SUDAH_DIPERBAIKI']);
        $this->postJson('/api/v1/routes/recommend',$this->input('accessible'))->assertOk()->assertJsonPath('data.properties.segment_ids',array_slice($this->segments,0,2));
    }
    public function test_invalid_input_outside_area_and_missing_network_are_rejected(): void
    {
        $this->postJson('/api/v1/routes/recommend',array_merge($this->input(),['preference'=>'flying']))->assertUnprocessable();
        $this->postJson('/api/v1/routes/recommend',array_merge($this->input(),['origin_lat'=>-7.4]))->assertUnprocessable();
        $this->postJson('/api/v1/routes/recommend',array_merge($this->input(),['origin_lng'=>112.79]))->assertUnprocessable();
    }
    public function test_disconnected_graph_returns_no_route(): void
    {
        $this->postJson('/api/v1/routes/recommend',array_merge($this->input(),['dest_lat'=>-7.269,'dest_lng'=>112.8]))->assertUnprocessable();
    }
    public function test_reverse_same_point_and_get_requests(): void
    {
        $input=$this->input();$input['origin_lng']=112.797;$input['dest_lng']=112.795;
        $this->getJson('/api/v1/routes/recommend?'.http_build_query($input))->assertOk();
        $input['dest_lng']=$input['origin_lng'];
        $this->postJson('/api/v1/routes/recommend',$input)->assertOk()->assertJsonPath('data.properties.distance_m',0);
    }
}
