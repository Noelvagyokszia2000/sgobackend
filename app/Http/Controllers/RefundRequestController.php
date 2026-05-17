<?php

namespace App\Http\Controllers;

use App\Models\RefundRequest;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RefundRequestController extends Controller
{
    private const REASONS = [
        'Autószerelés',
        'IC jail (Bank)',
        'IC jail (ATM)',
    ];

    public function index()
    {
        try {
            $items = RefundRequest::with([
                'user:id,username,IgName,warn,rank_id,createdAt,weeklyPay,weeklyPaymentRequired,lastRankup,profileImage',
                'user.rank:id,name',
                'handler:id,username,IgName',
            ])
                ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
                ->orderBy('created_at', 'desc')
                ->get();
        } catch (QueryException $exception) {
            return $this->refundRequestTableError();
        }

        return response()->json($items, 200);
    }

    public function pending()
    {
        try {
            $items = RefundRequest::with([
                'user:id,username,IgName,warn,rank_id,createdAt,weeklyPay,weeklyPaymentRequired,lastRankup,profileImage',
                'user.rank:id,name',
            ])
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->get();
        } catch (QueryException $exception) {
            return $this->refundRequestTableError();
        }

        return response()->json($items, 200);
    }

    public function byUser($userId)
    {
        try {
            $items = RefundRequest::where('user_id', $userId)
                ->with('handler:id,username,IgName')
                ->orderBy('created_at', 'desc')
                ->get();
        } catch (QueryException $exception) {
            return $this->refundRequestTableError();
        }

        return response()->json($items, 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'reason' => ['required', 'string', Rule::in(self::REASONS)],
            'amount' => 'required|integer|min:1',
            'proof_link' => 'required|url|max:2048',
            'note' => 'nullable|string|max:1000',
        ]);

        $item = RefundRequest::create([
            'user_id' => $request->user_id,
            'reason' => $request->reason,
            'amount' => $request->amount,
            'proof_link' => $request->proof_link,
            'note' => $request->note,
            'status' => 'pending',
            'refunded' => false,
        ]);

        return response()->json([
            'message' => 'Visszatérítési igény elküldve',
            'item' => $item,
        ], 201);
    }

    public function approve(Request $request, $id)
    {
        $request->validate([
            'admin_id' => 'nullable|exists:users,id',
        ]);

        $item = RefundRequest::find($id);

        if (!$item) {
            return response()->json([
                'message' => 'Visszatérítési igény nem található',
            ], 404);
        }

        if ($item->status !== 'pending') {
            return response()->json([
                'message' => 'Ez az igény már kezelve lett',
            ], 400);
        }

        $item->status = 'approved';
        $item->refunded = true;
        $item->handled_by = $request->admin_id;
        $item->handled_at = Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s');
        $item->save();

        return response()->json([
            'message' => 'Visszatérítés elfogadva',
            'item' => $item,
        ], 200);
    }

    public function reject(Request $request, $id)
    {
        $request->validate([
            'admin_id' => 'nullable|exists:users,id',
        ]);

        $item = RefundRequest::find($id);

        if (!$item) {
            return response()->json([
                'message' => 'Visszatérítési igény nem található',
            ], 404);
        }

        if ($item->status !== 'pending') {
            return response()->json([
                'message' => 'Ez az igény már kezelve lett',
            ], 400);
        }

        $item->status = 'rejected';
        $item->refunded = false;
        $item->handled_by = $request->admin_id;
        $item->handled_at = Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s');
        $item->save();

        return response()->json([
            'message' => 'Visszatérítés elutasítva',
            'item' => $item,
        ], 200);
    }

    private function refundRequestTableError()
    {
        return response()->json([
            'message' => 'A visszatérítési adatbázis tábla hiányzik vagy nincs frissítve. Futtasd a backend migrációkat: php artisan migrate --force',
        ], 500);
    }
}
