<?php
namespace App\Services\Routing;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
class RouteRecommendationService
{
    public function __construct(private AStar $algorithm) {}
    public function recommend(array $input): array
    {
        $started=hrtime(true);
        $area=json_decode(file_get_contents(resource_path('data/routing-mulyorejo.geojson')),true,512,JSON_THROW_ON_ERROR);
        $snaps=[];
        foreach (['origin','dest'] as $prefix) {
            $lat=$input[$prefix.'_lat']; $lng=$input[$prefix.'_lng'];
            $covered=DB::selectOne('SELECT CASE WHEN ST_Covers(ST_SetSRID(ST_GeomFromGeoJSON(?),4326),ST_SetSRID(ST_Point(?,?),4326)) THEN 1 ELSE 0 END ok', [json_encode($area['geometry']),$lng,$lat]);
            if (!(int)$covered->ok) throw ValidationException::withMessages([$prefix.'_lat'=>'Titik berada di luar cakupan Mulyorejo.']);
            $snaps[$prefix]=DB::selectOne('SELECT id, ST_X(geom) lng,ST_Y(geom) lat,
                ST_Distance(geom::geography,ST_SetSRID(ST_Point(?,?),4326)::geography) distance_m
                FROM routing_vertices WHERE ST_DWithin(geom::geography,ST_SetSRID(ST_Point(?,?),4326)::geography,?)
                AND EXISTS (SELECT 1 FROM routing_edges e WHERE e.source=routing_vertices.id OR e.target=routing_vertices.id)
                ORDER BY distance_m,id LIMIT 1',[$lng,$lat,$lng,$lat,config('routing.snap_radius_m',50)]);
            if (!$snaps[$prefix]) throw ValidationException::withMessages([$prefix.'_lat'=>'Tidak ada jaringan pejalan kaki terdata dalam radius snapping.']);
        }
        return DB::transaction(function () use ($input,$snaps,$started) {
            // Match the same active-report definition used by SegmentScoreService.
            $cats=array_map('intval',config('routing.accessibility_categories',[3,4,5]));
            $categorySql=$cats ? implode(',',$cats) : 'NULL';
            $rows=DB::select("SELECT e.id,e.source,e.target,e.segmen_id,e.length_m,ST_AsGeoJSON(e.geom) geometry,
                COALESCE(s.skor_kelayakan,0) score,
                CASE WHEN EXISTS(SELECT 1 FROM laporan l JOIN lokasi x ON x.lokasi_id=l.lokasi_id
                    WHERE x.segmen_id=s.segmen_id AND l.status='TERVALIDASI'
                    AND (l.status_perbaikan IS NULL OR l.status_perbaikan='SEDANG_DIPERBAIKI')
                    AND l.kategori_id IN ({$categorySql})) THEN 1 ELSE 0 END obstructed
                FROM routing_edges e JOIN segmen_jalur s ON s.segmen_id=e.segmen_id");
            $edges=[];$vertices=[];
            foreach ($rows as $row) {
                $coordinates=json_decode($row->geometry,true)['coordinates'];
                $vertices[(int)$row->source]=$coordinates[0]; $vertices[(int)$row->target]=$coordinates[count($coordinates)-1];
                $edges[(int)$row->id]=['source'=>(int)$row->source,'target'=>(int)$row->target,'segmen_id'=>(int)$row->segmen_id,
                    'length_m'=>(float)$row->length_m,'score'=>(float)$row->score,'obstructed'=>(bool)$row->obstructed,'coordinates'=>$coordinates];
            }
            $start=(int)$snaps['origin']->id;$goal=(int)$snaps['dest']->id;
            if (!isset($vertices[$start],$vertices[$goal])) throw new HttpException(503,'Graph berubah. Silakan ulangi permintaan rute.');
            $path=$this->algorithm->search($edges,$vertices,$start,$goal,$input['preference']);
            if ($path === null) throw ValidationException::withMessages(['route'=>'Tidak ada jalur yang terhubung antara kedua titik.']);
            $coords=[];$distance=0.0;$weighted=0.0;$cost=0.0;$segments=[];$obstructions=0;
            foreach ($path as [$id,$forward]) {
                $e=$edges[$id];$part=$forward?$e['coordinates']:array_reverse($e['coordinates']);
                if ($coords) array_shift($part);
                array_push($coords,...$part);
                $distance+=$e['length_m'];$weighted+=$e['score']*$e['length_m'];
                $cost+=$this->algorithm->cost($e['length_m'],$e['score'],$e['obstructed'],$input['preference']);
                $segments[]=$e['segmen_id'];if($e['obstructed']) $obstructions++;
            }
            if (!$coords) $coords=[$vertices[$start],$vertices[$start]];
            $snapData=[];foreach($snaps as $key=>$p) $snapData[$key]=['latitude'=>(float)$p->lat,'longitude'=>(float)$p->lng,'distance_m'=>round($p->distance_m,2)];
            return ['type'=>'Feature','geometry'=>['type'=>'LineString','coordinates'=>$coords],
                'properties'=>['preference'=>$input['preference'],'distance_m'=>round($distance,2),
                    'duration_minutes'=>round($distance/max(1,(float)config('routing.walking_speed_m_per_min',75)),2),
                    'average_score'=>$distance>0?round($weighted/$distance,2):null,'weighted_cost'=>round($cost,2),
                    'segment_ids'=>array_values(array_unique($segments)),'snapped'=>$snapData,
                    'has_accessibility_obstructions'=>$obstructions>0,
                    'computation_ms'=>round((hrtime(true)-$started)/1e6,2),
                    'notice'=>'Rute mengikuti jaringan terdata antara titik snapping; akses dari koordinat asli ke jaringan tidak dihitung. Mode accessible adalah preferensi bobot, bukan sertifikasi akses kursi roda.']];
        });
    }
}
