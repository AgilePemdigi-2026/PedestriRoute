<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
class BuildRoutingGraph extends Command
{
    protected $signature = 'routing:build';
    protected $description = 'Build a noded pedestrian graph without changing report segment IDs';
    public function handle(): int
    {
        $area = json_decode(file_get_contents(resource_path('data/routing-mulyorejo.geojson')), true, 512, JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($area) {
            DB::statement('LOCK TABLE routing_edges, routing_vertices IN ACCESS EXCLUSIVE MODE');
            DB::statement('DELETE FROM routing_edges');
            DB::statement('DELETE FROM routing_vertices');
            // Keep the original segment identity when splitting at intersections.
            DB::statement('CREATE TEMP TABLE route_pieces ON COMMIT DROP AS
                WITH area AS (SELECT ST_SetSRID(ST_GeomFromGeoJSON(?),4326) g),
                roads AS (SELECT s.segmen_id,
                    (ST_Dump(ST_CollectionExtract(ST_Intersection(s.geom::geometry,area.g),2))).geom g
                    FROM segmen_jalur s,area WHERE ST_Intersects(s.geom::geometry,area.g)),
                network AS (SELECT ST_Node(ST_UnaryUnion(ST_Collect(g))) g FROM roads),
                lines AS (SELECT (ST_Dump(g)).geom g FROM network),
                endpoints AS (SELECT ST_StartPoint(g) p FROM lines UNION SELECT ST_EndPoint(g) FROM lines),
                junctions AS (SELECT ST_Collect(p) g FROM endpoints),
                split_roads AS (SELECT r.segmen_id,
                    (ST_Dump(ST_Split(ST_Snap(r.g,j.g,0.0000000001),j.g))).geom g
                    FROM roads r CROSS JOIN junctions j WHERE j.g IS NOT NULL),
                pieces AS (SELECT segmen_id,
                    (ST_DumpSegments(ST_Segmentize(g::geography,30)::geometry)).geom g FROM split_roads)
                SELECT segmen_id,g FROM pieces
                WHERE NOT ST_IsEmpty(g) AND ST_Length(g::geography)>0.001',
                [json_encode($area['geometry'], JSON_THROW_ON_ERROR)]);
            DB::statement('INSERT INTO routing_vertices(geom)
                SELECT ST_StartPoint(g) FROM route_pieces UNION SELECT ST_EndPoint(g) FROM route_pieces');
            DB::statement('INSERT INTO routing_edges(segmen_id,source,target,geom,length_m)
                SELECT p.segmen_id,a.id,b.id,p.g,ST_Length(p.g::geography)
                FROM route_pieces p
                JOIN routing_vertices a ON ST_Equals(a.geom,ST_StartPoint(p.g))
                JOIN routing_vertices b ON ST_Equals(b.geom,ST_EndPoint(p.g))');
        });
        DB::statement('ANALYZE routing_edges');
        DB::statement('ANALYZE routing_vertices');
        $count = DB::table('routing_edges')->count();
        $this->info("Routing graph: {$count} edges. Disconnected roads remain disconnected; no artificial connectors added.");
        return $count ? self::SUCCESS : self::FAILURE;
    }
}
