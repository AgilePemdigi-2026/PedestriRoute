<?php

namespace App\Console\Commands;

use App\Services\OsmPedestrianNetworkParser;
use App\Services\SegmentScoreService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

#[Signature('app:import-pedestrian-network
    {--bbox=-7.296,112.755,-7.238,112.830 : south,west,north,east (default: Kecamatan Mulyorejo + margin)}
    {--file= : Read a saved Overpass JSON file instead of downloading}
    {--keep-stale : Keep segments in the bbox that are not part of the imported OSM network}
    {--dry-run : Parse and report counts without writing to the database}')]
#[Description('Import the walkable OSM network into segmen_jalur as graph edges for A* routing')]
class ImportPedestrianNetwork extends Command
{
    /** Reports on a removed segment move to the nearest imported edge within this distance. */
    private const REASSIGN_RADIUS_M = 30;

    public function handle(OsmPedestrianNetworkParser $parser, SegmentScoreService $scores): int
    {
        $bbox = array_map('floatval', explode(',', (string) $this->option('bbox')));
        if (count($bbox) !== 4 || $bbox[0] >= $bbox[2] || $bbox[1] >= $bbox[3]) {
            $this->error('Format --bbox harus south,west,north,east.');

            return self::INVALID;
        }

        try {
            $payload = $this->loadPayload($bbox);
        } catch (Throwable $exception) {
            $this->error('Gagal mengambil data OSM: '.$exception->getMessage());

            return self::FAILURE;
        }

        $edges = $parser->parse($payload);
        $this->info(sprintf('%d ruas pejalan kaki terbaca dari OSM.', count($edges)));
        if ($edges === []) {
            $this->warn('Tidak ada ruas yang bisa diimpor; database tidak diubah.');

            return self::FAILURE;
        }
        if ($this->option('dry-run')) {
            $this->line(sprintf('Total panjang: %.1f km (dry run, database tidak diubah).', array_sum(array_column($edges, 'panjang_m')) / 1000));

            return self::SUCCESS;
        }

        $importedIds = [];
        $bar = $this->output->createProgressBar(count($edges));
        DB::transaction(function () use ($edges, &$importedIds, $bar): void {
            foreach ($edges as $edge) {
                $wkt = 'SRID=4326;LINESTRING('.implode(', ', array_map(fn (array $c): string => sprintf('%.7F %.7F', $c[0], $c[1]), $edge['coordinates'])).')';
                // Existing WSM scores are preserved: only geometry, name and length are refreshed.
                $row = DB::selectOne(
                    'INSERT INTO segmen_jalur (osm_way_id, u_node, v_node, nama_jalan, geom, panjang_m, skor_kelayakan, tanggal_update)
                     VALUES (?, ?, ?, ?, ST_GeogFromText(?), ?, 100, now())
                     ON CONFLICT (osm_way_id, u_node, v_node) DO UPDATE
                     SET nama_jalan = EXCLUDED.nama_jalan, geom = EXCLUDED.geom, panjang_m = EXCLUDED.panjang_m
                     RETURNING segmen_id',
                    [$edge['osm_way_id'], $edge['u_node'], $edge['v_node'], $edge['nama_jalan'], $wkt, $edge['panjang_m']],
                );
                $importedIds[] = (int) $row->segmen_id;
                $bar->advance();
            }
        });
        $bar->finish();
        $this->newLine();
        $this->info(sprintf('%d ruas disimpan/diperbarui.', count($importedIds)));

        if (! $this->option('keep-stale')) {
            $this->replaceStaleSegments($bbox, $importedIds, $scores);
        }

        return self::SUCCESS;
    }

    /** @param array<int, float> $bbox */
    private function loadPayload(array $bbox): array
    {
        if ($file = $this->option('file')) {
            $json = json_decode((string) file_get_contents($file), true);
            if (! is_array($json)) {
                throw new \RuntimeException('File bukan JSON Overpass yang valid.');
            }

            return $json;
        }

        [$south, $west, $north, $east] = $bbox;
        $query = sprintf('[out:json][timeout:180];way["highway"](%F,%F,%F,%F);(._;>;);out body;', $south, $west, $north, $east);
        $this->line('Mengunduh jaringan jalan dari Overpass API...');

        return Http::asForm()->withUserAgent('PedestriRoute/1.0')->connectTimeout(10)->timeout(200)
            ->post((string) config('services.osm.overpass_url'), ['data' => $query])
            ->throw()->json();
    }

    /**
     * Segments in the bbox that are not part of the imported network (e.g. the
     * hand-made seeder segments) would duplicate imported edges. Their reports
     * move to the nearest imported edge, scores are recomputed with WSM, and
     * unreferenced stale rows are removed.
     *
     * @param  array<int, float>  $bbox
     * @param  array<int, int>  $importedIds
     */
    private function replaceStaleSegments(array $bbox, array $importedIds, SegmentScoreService $scores): void
    {
        [$south, $west, $north, $east] = $bbox;
        $imported = '{'.implode(',', $importedIds).'}';
        $stale = array_map(fn ($row): int => (int) $row->segmen_id, DB::select(
            'SELECT segmen_id FROM segmen_jalur
             WHERE ST_Intersects(geom, ST_MakeEnvelope(?, ?, ?, ?, 4326)::geography) AND NOT (segmen_id = ANY(?::int[]))',
            [$west, $south, $east, $north, $imported],
        ));
        if ($stale === []) {
            return;
        }

        $touched = [];
        $moved = 0;
        $locations = DB::table('lokasi')->whereIn('segmen_id', $stale)->whereNotNull('geom')->get(['lokasi_id', 'segmen_id']);
        foreach ($locations as $location) {
            $nearest = DB::selectOne(
                'SELECT s.segmen_id FROM segmen_jalur s, lokasi l
                 WHERE l.lokasi_id = ? AND s.segmen_id = ANY(?::int[]) AND ST_DWithin(s.geom, l.geom::geography, ?)
                 ORDER BY ST_Distance(s.geom, l.geom::geography) LIMIT 1',
                [$location->lokasi_id, $imported, self::REASSIGN_RADIUS_M],
            );
            if ($nearest === null) {
                continue;
            }
            DB::table('lokasi')->where('lokasi_id', $location->lokasi_id)->update(['segmen_id' => $nearest->segmen_id]);
            $touched[(int) $nearest->segmen_id] = true;
            $moved++;
        }
        foreach (array_keys($touched) as $segmentId) {
            $scores->recalculate($segmentId);
        }

        $deleted = DB::table('segmen_jalur')->whereIn('segmen_id', $stale)
            ->whereNotIn('segmen_id', DB::table('lokasi')->whereNotNull('segmen_id')->select('segmen_id'))
            ->delete();
        $this->info(sprintf('%d lokasi laporan dipindah ke ruas OSM terdekat; %d ruas lama dihapus; %d ruas lama dipertahankan karena laporannya tidak punya ruas OSM dalam %d m.',
            $moved, $deleted, count($stale) - $deleted, self::REASSIGN_RADIUS_M));
    }
}
