<?php

namespace App\Http\Controllers;

use App\Models\Robbery;
use Illuminate\Http\Request;

class RobberyController extends Controller
{
    public function index()
    {
        return Robbery::with('author:id,username,IgName')
            ->orderBy('id', 'desc')
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'created_by' => 'required|exists:users,id',
            'participants_count' => 'required|integer|min:0',
            'applicants_count' => 'required|integer|min:0',
        ]);

        $robbery = Robbery::create([
            'created_by' => $validated['created_by'],
            'participants_count' => $validated['participants_count'],
            'applicants_count' => $validated['applicants_count'],
            'finished' => false,
        ]);

        return response()->json([
            'message' => 'Rablás sikeresen létrehozva.',
            'robbery' => $robbery->load('author:id,username,IgName'),
        ], 201);
    }

    public function updateFinished(Request $request, $id)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'finished' => 'required|boolean',
        ]);

        $robbery = Robbery::find($id);

        if (!$robbery) {
            return response()->json([
                'message' => 'Rablás nem található.',
            ], 404);
        }

        if ((int) $robbery->created_by !== (int) $validated['user_id']) {
            return response()->json([
                'message' => 'Ezt csak az tudja módosítani, aki létrehozta a rablást.',
            ], 403);
        }

        $robbery->finished = (bool) $validated['finished'];
        $robbery->save();

        return response()->json([
            'message' => 'Rablás állapota frissítve.',
            'robbery' => $robbery->load('author:id,username,IgName'),
        ], 200);
    }
}
