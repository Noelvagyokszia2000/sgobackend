<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Warn;
use App\Models\User;
use Carbon\Carbon;

class WarnController extends Controller
{
    public function addWarn(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'issued_by' => 'required|exists:users,id',
            'reason' => 'nullable|string'
        ]);

        Warn::create([
            'user_id' => $request->user_id,
            'issued_by' => $request->issued_by,
            'reason' => $request->reason,
            'created_at' => Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s')
        ]);

        $user = User::find($request->user_id);
        $user->increment('warn');

        return response()->json([
            'message' => 'Warn hozzáadva'
        ], 201);
    }

    public function removeWarn($id)
    {
        $warn = Warn::find($id);

        if (!$warn) {
            return response()->json([
                'message' => 'Warn nem található'
            ], 404);
        }

        $user = User::find($warn->user_id);

        $warn->delete();

        if ($user && $user->warn > 0) {
            $user->decrement('warn');
        }

        return response()->json([
            'message' => 'Warn törölve'
        ], 200);
    }

    public function getUserWarns($userId)
    {
        $warns = Warn::with(['issuer:id,username,IgName'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($warns, 200);
    }

    public function getAllWarns()
    {
        $warns = Warn::with([
            'user:id,username,IgName',
            'issuer:id,username,IgName'
        ])
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json($warns, 200);
    }
}
