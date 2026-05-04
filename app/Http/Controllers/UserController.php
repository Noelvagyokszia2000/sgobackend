<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index($id)
    {
        $user = User::with('rank:id,name,available')->find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Felhasználó nem található'
            ], 404);
        }

        return response()->json($user, 200);
    }

    public function addUser(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255|unique:users,username',
            'password' => 'required|string|min:4',
            'IgName' => 'required|string|max:255',
            'rank_id' => 'required|exists:ranks,id'
        ]);

        $user = User::create([
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'IgName' => $request->IgName,
            'createdAt' => now()->toDateString(),
            'warn' => 0,
            'weeklyPay' => now()->toDateString(),
            'isAdmin' => false,
            'rank_id' => $request->rank_id
        ]);

        return response()->json([
            'message' => 'Felhasználó létrehozva',
            'user' => $user->load('rank:id,name,available')
        ], 201);
    }

    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Felhasználó nem található'
            ], 404);
        }

        $user->delete();

        return response()->json([
            'message' => 'Felhasználó törölve'
        ], 200);
    }

    public function isAdmin($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Felhasználó nem található'
            ], 404);
        }

        return response()->json([
            'isAdmin' => (bool) $user->isAdmin
        ], 200);
    }

    public function login(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'username' => 'required|string',
                'password' => 'required|string'
            ]
        );

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $user = User::with('rank:id,name,available')
            ->where('username', $request->username)
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Wrong password'
            ], 401);
        }

        return response()->json([
            'message' => 'Login successful',
            'user' => $user
        ], 200);
    }

    public function getAllUsers()
{
    $users = User::with('rank:id,name,available')
        ->select('id', 'username', 'IgName', 'warn', 'isAdmin', 'rank_id', 'lastRankup', 'createdAt')
        ->orderBy('id', 'asc')
        ->get();

    return response()->json($users, 200);
}

    public function changeUsername(Request $request)
    {
        $request->validate([
            'userId' => 'required|exists:users,id',
            'username' => 'required|string|max:255|unique:users,username'
        ]);

        $user = User::find($request->userId);

        if (!$user) {
            return response()->json([
                'message' => 'Felhasználó nem található'
            ], 404);
        }

        $user->username = $request->username;
        $user->save();

        return response()->json([
            'message' => 'Felhasználónév frissítve',
            'user' => $user->load('rank:id,name,available')
        ], 200);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'userId' => 'required|exists:users,id',
            'oldPassword' => 'required|string',
            'newPassword' => 'required|string|min:4'
        ]);

        $user = User::find($request->userId);

        if (!$user) {
            return response()->json([
                'message' => 'Felhasználó nem található'
            ], 404);
        }

        if (!Hash::check($request->oldPassword, $user->password)) {
            return response()->json([
                'message' => 'A régi jelszó hibás'
            ], 401);
        }

        $user->password = Hash::make($request->newPassword);
        $user->save();

        return response()->json([
            'message' => 'Jelszó sikeresen frissítve',
            'user' => $user->load('rank:id,name,available')
        ], 200);
    }

    public function changeName(Request $request)
    {
        $request->validate([
            'userId' => 'required|exists:users,id',
            'IgName' => 'required|string|max:255'
        ]);

        $user = User::find($request->userId);

        if (!$user) {
            return response()->json([
                'message' => 'Felhasználó nem található'
            ], 404);
        }

        $user->IgName = $request->IgName;
        $user->save();

        return response()->json([
            'message' => 'Név frissítve',
            'user' => $user->load('rank:id,name,available')
        ], 200);
    }

    public function changeRank(Request $request)
    {
        $request->validate([
            'userId' => 'required|exists:users,id',
            'rank_id' => 'required|exists:ranks,id'
        ]);

        $user = User::find($request->userId);

        if (!$user) {
            return response()->json([
                'message' => 'Felhasználó nem található'
            ], 404);
        }

        $user->rank_id = $request->rank_id;
        $user->save();

        return response()->json([
            'message' => 'Rang frissítve',
            'user' => $user->load('rank:id,name,available')
        ], 200);
    }
}