<?php

namespace Tests\Unit;

use App\Services\OsmPedestrianNetworkParser;
use PHPUnit\Framework\TestCase;

class OsmPedestrianNetworkParserTest extends TestCase
{
    private function node(int $id, float $lng, float $lat): array
    {
        return ['type' => 'node', 'id' => $id, 'lat' => $lat, 'lon' => $lng];
    }

    private function way(int $id, array $nodes, array $tags): array
    {
        return ['type' => 'way', 'id' => $id, 'nodes' => $nodes, 'tags' => $tags];
    }

    public function test_ways_are_split_at_shared_nodes_into_graph_edges(): void
    {
        $edges = (new OsmPedestrianNetworkParser)->parse(['elements' => [
            $this->node(1, 112.800, -7.270), $this->node(2, 112.801, -7.270), $this->node(3, 112.802, -7.270),
            $this->node(4, 112.801, -7.271), $this->node(5, 112.801, -7.269),
            // Main road 1-2-3 is crossed at node 2 by a footway 4-2-5.
            $this->way(10, [1, 2, 3], ['highway' => 'residential', 'name' => 'Jl. Contoh']),
            $this->way(11, [4, 2, 5], ['highway' => 'footway']),
        ]]);

        $keys = array_map(fn (array $edge): string => $edge['osm_way_id'].':'.$edge['u_node'].'-'.$edge['v_node'], $edges);
        sort($keys);
        $this->assertSame(['10:1-2', '10:2-3', '11:2-5', '11:4-2'], $keys);
        $this->assertSame('Jl. Contoh', $edges[0]['nama_jalan']);
        $this->assertEqualsWithDelta(110.3, $edges[0]['panjang_m'], 0.5);
        $footway = array_values(array_filter($edges, fn (array $edge): bool => $edge['osm_way_id'] === 11))[0];
        $this->assertSame('Jalur pejalan kaki', $footway['nama_jalan']);
    }

    public function test_intermediate_nodes_stay_in_the_edge_geometry(): void
    {
        $edges = (new OsmPedestrianNetworkParser)->parse(['elements' => [
            $this->node(1, 112.800, -7.270), $this->node(2, 112.8005, -7.2702), $this->node(3, 112.801, -7.270),
            $this->way(10, [1, 2, 3], ['highway' => 'path']),
        ]]);

        $this->assertCount(1, $edges);
        $this->assertSame([1, 3], [$edges[0]['u_node'], $edges[0]['v_node']]);
        $this->assertCount(3, $edges[0]['coordinates']);
    }

    public function test_only_ways_a_pedestrian_may_use_are_imported(): void
    {
        $parser = new OsmPedestrianNetworkParser;

        $this->assertTrue($parser->isWalkable(['highway' => 'primary']));
        $this->assertTrue($parser->isWalkable(['highway' => 'service', 'access' => 'private', 'foot' => 'yes']));
        $this->assertTrue($parser->isWalkable(['highway' => 'cycleway', 'foot' => 'designated']));
        $this->assertFalse($parser->isWalkable(['highway' => 'motorway']));
        $this->assertFalse($parser->isWalkable(['highway' => 'trunk']));
        $this->assertFalse($parser->isWalkable(['highway' => 'cycleway']));
        $this->assertFalse($parser->isWalkable(['highway' => 'footway', 'foot' => 'no']));
        $this->assertFalse($parser->isWalkable(['highway' => 'residential', 'access' => 'private']));
        $this->assertFalse($parser->isWalkable(['highway' => 'pedestrian', 'area' => 'yes']));
        $this->assertFalse($parser->isWalkable(['highway' => 'construction']));
        $this->assertFalse($parser->isWalkable(['building' => 'yes']));
    }
}
