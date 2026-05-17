<?php

namespace App\Http\Controllers;

use App\Models\Parking;
use App\Models\User;
use Illuminate\Http\Request;

class ParkingController extends Controller
{
    public function index()
    {
        $parkings = Parking::with('user:id,username,IgName')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json($parkings, 200);
    }

    public function claim(Request $request, $id)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $parking = Parking::find($id);

        if (!$parking) {
            return response()->json([
                'message' => 'Parkolóhely nem található'
            ], 404);
        }

        if ($parking->occupied) {
            return response()->json([
                'message' => 'Ez a parkolóhely már foglalt'
            ], 400);
        }

        $alreadyHasParking = Parking::where('user_id', $request->user_id)
            ->where('occupied', true)
            ->exists();

        if ($alreadyHasParking) {
            return response()->json([
                'message' => 'A felhasználónak már van parkolóhelye'
            ], 400);
        }

        $parking->user_id = $request->user_id;
        $parking->occupied = true;
        $parking->save();

        return response()->json([
            'message' => 'Parkolóhely sikeresen lefoglalva',
            'parking' => $parking
        ], 200);
    }

    public function release(Request $request, $id)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $parking = Parking::find($id);

        if (!$parking) {
            return response()->json([
                'message' => 'Parkolóhely nem található'
            ], 404);
        }

        if (!$parking->occupied) {
            return response()->json([
                'message' => 'Ez a parkolóhely nincs lefoglalva'
            ], 400);
        }

        $requestUser = User::find($request->user_id);

        if (!$requestUser || (!$requestUser->isAdmin && (int) $parking->user_id !== (int) $requestUser->id)) {
            return response()->json([
                'message' => 'Ezt a parkolĂłhelyet csak admin vagy a foglalĂł felhasznĂˇlĂł adhatja vissza'
            ], 403);
        }

        $parking->user_id = null;
        $parking->occupied = false;
        $parking->save();

        return response()->json([
            'message' => 'Parkolóhely felszabadítva',
            'parking' => $parking
        ], 200);
    }
}
