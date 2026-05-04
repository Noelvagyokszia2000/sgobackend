<?php

namespace App\Http\Controllers;

use App\Models\Rankup;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class RankupController extends Controller
{
    public function index()
    {
        $items = Rankup::with([
            'user:id,username,IgName',
            'issuer:id,username,IgName',
            'previousRank:id,name',
            'nextRank:id,name'
        ])
        ->orderBy('issued_at_site', 'desc')
        ->get();

        return response()->json($items, 200);
    }

    

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'issued_by' => 'nullable|exists:users,id',
            'next_rank_id' => 'required|exists:ranks,id',
            'issued_at_site' => 'required|date',
            'issued_at_game' => 'nullable|date'
        ]);

        $user = User::find($request->user_id);

        if (!$user) {
            return response()->json([
                'message' => 'Felhasználó nem található'
            ], 404);
        }

        $rankup = Rankup::create([
            'user_id' => $user->id,
            'issued_by' => $request->issued_by,
            'previous_rank_id' => $user->rank_id,
            'next_rank_id' => $request->next_rank_id,
            'issued_at_site' => $request->issued_at_site,
            'issued_at_game' => $request->issued_at_game,
            'completed' => false
        ]);

        $user->lastRankup = Carbon::parse($request->issued_at_site);
        $user->save();

        return response()->json([
            'message' => 'Rankup létrehozva',
            'item' => $rankup->load([
                'user:id,username,IgName',
                'issuer:id,username,IgName',
                'previousRank:id,name',
                'nextRank:id,name'
            ])
        ], 201);
    }

    public function pending()
{
    return Rankup::with([
        'user:id,username,IgName',
        'issuer:id,username,IgName',
        'previousRank:id,name',
        'nextRank:id,name'
    ])
    ->where('completed', false)
    ->orderBy('issued_at_site', 'desc')
    ->get();
}

    public function markCompleted($id)
    {
        $item = Rankup::find($id);

        if (!$item) {
            return response()->json([
                'message' => 'Rankup nem található'
            ], 404);
        }

        if ($item->completed) {
            return response()->json([
                'message' => 'Ez a rankup már meg lett adva'
            ], 400);
        }

        $user = User::find($item->user_id);

        if (!$user) {
            return response()->json([
                'message' => 'Felhasználó nem található'
            ], 404);
        }

        $user->rank_id = $item->next_rank_id;
        $user->lastRankup = now();
        $user->save();

        $item->completed = true;
        $item->issued_at_game = now();
        $item->save();

        return response()->json([
            'message' => 'Rankup megadva',
            'item' => $item->load([
                'user:id,username,IgName',
                'issuer:id,username,IgName',
                'previousRank:id,name',
                'nextRank:id,name'
            ]),
            'user' => $user->load('rank:id,name,available')
        ], 200);
    }
}