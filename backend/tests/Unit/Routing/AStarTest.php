<?php
namespace Tests\Unit\Routing;
use App\Services\Routing\AStar;
use Tests\TestCase;
class AStarTest extends TestCase
{
    public function test_costs_and_accessibility_penalty(): void
    {
        $a=new AStar;
        $this->assertEquals(100,$a->cost(100,0,true,'fastest'));
        $this->assertGreaterThan($a->cost(100,100,false,'safest'),$a->cost(100,0,false,'safest'));
        $this->assertGreaterThan($a->cost(100,100,false,'accessible'),$a->cost(100,100,true,'accessible'));
    }
    public function test_astar_selects_safer_detour_and_handles_disconnected_nodes(): void
    {
        $vertices=[1=>[112.795,-7.266],2=>[112.796,-7.266],3=>[112.797,-7.266],4=>[112.796,-7.267],5=>[112.80,-7.27]];
        $edges=[];
        foreach([[1,2,111,0],[2,3,111,0],[1,4,157,100],[4,3,157,100]] as $i=>[$s,$t,$length,$score]) {
            $edges[$i+1]=['source'=>$s,'target'=>$t,'length_m'=>$length,'score'=>$score,'obstructed'=>false];
        }
        $a=new AStar;
        $this->assertSame([[1,true],[2,true]],$a->search($edges,$vertices,1,3,'fastest'));
        $this->assertSame([[3,true],[4,true]],$a->search($edges,$vertices,1,3,'safest'));
        $this->assertSame([[2,false],[1,false]],$a->search($edges,$vertices,3,1,'fastest'));
        $this->assertNull($a->search($edges,$vertices,1,5,'fastest'));
        $this->assertSame([],$a->search($edges,$vertices,1,1,'fastest'));
    }
}
