<?php

namespace App\Http\Controllers;

use App\Models\Rank;

class RankController extends Controller
{
    public function index()
    {
        $ranks = Rank::select('id', 'name', 'available')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json($ranks, 200);
    }

    public function available()
    {
        $ranks = Rank::select('id', 'name', 'available')
            ->where('available', true)
            ->orderBy('id', 'asc')
            ->get();

        return response()->json($ranks, 200);
    }
}