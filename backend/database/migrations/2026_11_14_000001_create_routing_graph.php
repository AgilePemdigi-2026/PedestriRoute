<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void
    {
        DB::unprepared('CREATE TABLE routing_vertices (id BIGSERIAL PRIMARY KEY, geom geometry(Point,4326) NOT NULL UNIQUE);
            CREATE INDEX routing_vertices_gix ON routing_vertices USING gist(geom);
            CREATE INDEX routing_vertices_geog_gix ON routing_vertices USING gist((geom::geography));
            CREATE TABLE routing_edges (id BIGSERIAL PRIMARY KEY, segmen_id INT NOT NULL REFERENCES segmen_jalur(segmen_id) ON DELETE CASCADE,
            source BIGINT NOT NULL REFERENCES routing_vertices(id), target BIGINT NOT NULL REFERENCES routing_vertices(id),
            geom geometry(LineString,4326) NOT NULL, length_m DOUBLE PRECISION NOT NULL CHECK(length_m > 0));
            CREATE INDEX routing_edges_source ON routing_edges(source);
            CREATE INDEX routing_edges_target ON routing_edges(target);
            CREATE INDEX routing_edges_segment ON routing_edges(segmen_id);
            CREATE INDEX routing_edges_gix ON routing_edges USING gist(geom);');
    }
    public function down(): void { DB::unprepared('DROP TABLE IF EXISTS routing_edges; DROP TABLE IF EXISTS routing_vertices;'); }
};
