<?php

namespace App\Http\Controllers;

use App\Models\CompletedResearchPoints;
use Illuminate\Http\Request;
use App\Traits\HttpResponses;

class CompletedResearchPointsController extends Controller
{
    use HttpResponses;

    public function index()
    {
        $points = CompletedResearchPoints::first();

        return $this->success($points);
    }
    public function update(Request $request)
    {
        $points = CompletedResearchPoints::first();

        $points->update(['points' => $request->points]);

        return $this->success('', 'Points updated successfully');
    }
}
