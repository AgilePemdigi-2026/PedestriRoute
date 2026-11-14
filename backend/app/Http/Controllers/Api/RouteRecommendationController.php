<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Services\Routing\RouteRecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class RouteRecommendationController extends Controller
{
    public function __invoke(Request $request,RouteRecommendationService $service): JsonResponse
    {
        $input=$request->validate([
            'origin_lat'=>['required','numeric','between:-90,90'],'origin_lng'=>['required','numeric','between:-180,180'],
            'dest_lat'=>['required','numeric','between:-90,90'],'dest_lng'=>['required','numeric','between:-180,180'],
            'preference'=>['required','in:safest,fastest,accessible'],
        ]);
        return response()->json(['data'=>$service->recommend($input)]);
    }
}
