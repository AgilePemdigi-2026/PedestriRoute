<?php
namespace App\Services\Routing;
use SplPriorityQueue;
class AStar
{
    public function cost(float $length, float $score, bool $obstructed, string $preference): float
    {
        if ($preference === 'fastest') return $length;
        $penalty = (1 - max(0, min(100, $score)) / 100) * (float) config('routing.condition_multiplier', 4);
        if ($preference === 'accessible' && $obstructed) $penalty += (float) config('routing.accessibility_penalty', 12);
        return $length * (1 + $penalty);
    }
    public function distance(array $a, array $b): float
    {
        $lat1=deg2rad($a[1]); $lat2=deg2rad($b[1]);
        $h=sin(($lat2-$lat1)/2)**2+cos($lat1)*cos($lat2)*sin(deg2rad($b[0]-$a[0])/2)**2;
        return 6371008.8 * 2 * asin(sqrt(min(1,$h)));
    }
    /** Each returned item has edge ID and travel direction; null means disconnected. */
    public function search(array $edges, array $vertices, int $start, int $goal, string $preference): ?array
    {
        if ($start === $goal) return [];
        $adj=[];
        foreach ($edges as $id=>$e) {
            $cost=$this->cost($e['length_m'],$e['score'],$e['obstructed'],$preference);
            $adj[$e['source']][]=[$e['target'],$id,$cost,true];
            $adj[$e['target']][]=[$e['source'],$id,$cost,false];
        }
        $queue=new SplPriorityQueue(); $queue->setExtractFlags(SplPriorityQueue::EXTR_DATA);
        $queue->insert([$start,0.0],0.0); $best=[$start=>0.0]; $previous=[];
        while (!$queue->isEmpty()) {
            [$node,$g]=$queue->extract();
            if ($g > $best[$node]+1e-8) continue;
            if ($node === $goal) {
                $path=[];
                while ($node !== $start) { [$parent,$edge,$forward]=$previous[$node]; $path[]=[$edge,$forward]; $node=$parent; }
                return array_reverse($path);
            }
            foreach ($adj[$node]??[] as [$next,$id,$cost,$forward]) {
                $candidate=$g+$cost;
                if ($candidate >= ($best[$next]??INF)) continue;
                $best[$next]=$candidate; $previous[$next]=[$node,$id,$forward];
                // Conservative spherical bound below WGS84 segment length; all penalties nonnegative.
                $heuristic=0.99*$this->distance($vertices[$next],$vertices[$goal]);
                $queue->insert([$next,$candidate],-($candidate+$heuristic));
            }
        }
        return null;
    }
}
