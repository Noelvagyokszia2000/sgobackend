<?php

namespace App\Http\Controllers;

use App\Models\InactivityRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InactivityRequestController extends Controller
{
    public function byUser($userId)
    {
        try {
            $items = InactivityRequest::query()
                ->where('user_id', $userId)
                ->with('handler:id,username,IgName')
                ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
                ->orderBy('created_at', 'desc')
                ->get();
        } catch (QueryException $exception) {
            return $this->inactivityRequestTableError();
        }

        return response()->json($items, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|min:5|max:1000',
        ]);

        try {
            $hasOverlap = InactivityRequest::query()
                ->where('user_id', $validated['user_id'])
                ->whereIn('status', ['pending', 'approved'])
                ->whereDate('start_date', '<=', $validated['end_date'])
                ->whereDate('end_date', '>=', $validated['start_date'])
                ->exists();
        } catch (QueryException $exception) {
            return $this->inactivityRequestTableError();
        }

        if ($hasOverlap) {
            return response()->json([
                'message' => 'Erre az idoszakra mar van fuggoben vagy elfogadott inaktivitasod.',
            ], 422);
        }

        $item = InactivityRequest::create([
            'user_id' => $validated['user_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => trim($validated['reason']),
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Inaktivitas kerelem elkuldve.',
            'item' => $item,
        ], 201);
    }

    public function adminIndex()
    {
        try {
            return response()->json([
                'pending' => $this->pendingQuery()->get(),
                'active' => $this->activeQuery()->get(),
            ], 200);
        } catch (QueryException $exception) {
            return $this->inactivityRequestTableError();
        }
    }

    public function approve(Request $request, $id)
    {
        $request->validate([
            'admin_id' => 'nullable|exists:users,id',
        ]);

        $item = InactivityRequest::query()->find($id);

        if (!$item) {
            return response()->json([
                'message' => 'Inaktivitas kerelem nem talalhato.',
            ], 404);
        }

        if ($item->status !== 'pending') {
            return response()->json([
                'message' => 'Ez a kerelem mar kezelve lett.',
            ], 400);
        }

        $adminId = $this->validatedAdminId($request);

        DB::transaction(function () use ($item, $adminId): void {
            $item->status = 'approved';
            $item->handled_by = $adminId;
            $item->handled_at = Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s');
            $item->save();

            $user = User::query()->find($item->user_id);

            if (!$user || !$user->weeklyPay) {
                return;
            }

            $weeklyPay = Carbon::parse($user->weeklyPay)->startOfDay();
            $startDate = Carbon::parse($item->start_date)->startOfDay();
            $endDate = Carbon::parse($item->end_date)->startOfDay();

            if ($weeklyPay->between($startDate, $endDate, true)) {
                $user->weeklyPay = $endDate->toDateString();
                $user->weeklyPayReminderSentFor = null;
                $user->save();
            }
        });

        return response()->json([
            'message' => 'Inaktivitas elfogadva.',
            'item' => $item->fresh([
                'user:id,username,IgName,warn,rank_id,createdAt,weeklyPay,weeklyPaymentRequired,lastRankup,profileImage',
                'user.rank:id,name',
                'handler:id,username,IgName',
            ]),
        ], 200);
    }

    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'admin_id' => 'nullable|exists:users,id',
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        $item = InactivityRequest::query()->find($id);

        if (!$item) {
            return response()->json([
                'message' => 'Inaktivitas kerelem nem talalhato.',
            ], 404);
        }

        if ($item->status !== 'pending') {
            return response()->json([
                'message' => 'Ez a kerelem mar kezelve lett.',
            ], 400);
        }

        $item->status = 'rejected';
        $item->handled_by = $this->validatedAdminId($request);
        $item->handled_at = Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s');
        $item->rejection_reason = $validated['rejection_reason'] ?? null;
        $item->save();

        return response()->json([
            'message' => 'Inaktivitas elutasitva.',
            'item' => $item,
        ], 200);
    }

    private function pendingQuery()
    {
        return InactivityRequest::query()
            ->with([
                'user:id,username,IgName,warn,rank_id,createdAt,weeklyPay,weeklyPaymentRequired,lastRankup,profileImage',
                'user.rank:id,name',
            ])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc');
    }

    private function activeQuery()
    {
        return InactivityRequest::query()
            ->with([
                'user:id,username,IgName,warn,rank_id,createdAt,weeklyPay,weeklyPaymentRequired,lastRankup,profileImage',
                'user.rank:id,name',
                'handler:id,username,IgName',
            ])
            ->where('status', 'approved')
            ->whereDate('end_date', '>=', Carbon::now(config('app.timezone'))->toDateString())
            ->orderBy('start_date')
            ->orderBy('end_date');
    }

    private function validatedAdminId(Request $request): ?int
    {
        $adminId = $request->input('admin_id');

        if (!$adminId) {
            return null;
        }

        $admin = User::query()
            ->where('id', $adminId)
            ->where('isAdmin', true)
            ->first();

        return $admin ? (int) $admin->id : null;
    }

    private function inactivityRequestTableError()
    {
        return response()->json([
            'message' => 'Az inaktivitas adatbazis tabla hianyzik vagy nincs frissitve. Futtasd a backend migraciokat: php artisan migrate --force',
        ], 500);
    }
}
