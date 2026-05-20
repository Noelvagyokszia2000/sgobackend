<?php

namespace App\Http\Controllers;

use App\Models\Robbery;
use App\Models\RobberyIncomeImage;
use App\Services\DiscordNotifier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RobberyController extends Controller
{
    public function index()
    {
        return Robbery::query()
            ->with([
                'author:id,username,IgName,profileImage',
                'incomeImages.submitter:id,username,IgName,profileImage',
            ])
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn (Robbery $robbery) => $this->formatRobbery($robbery));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'created_by' => 'required|exists:users,id',
            'name' => 'required|string|max:120',
            'type' => 'required|string|in:ATM,BANK',
        ]);

        $robbery = Robbery::create([
            'created_by' => $validated['created_by'],
            'name' => trim($validated['name']),
            'type' => $validated['type'],
            'participants_count' => 0,
            'applicants_count' => 0,
            'finished' => false,
        ]);

        app(DiscordNotifier::class)->sendRobbery(
            $robbery->load('author:id,username,IgName,profileImage')
        );

        return response()->json([
            'message' => 'Rablás sikeresen létrehozva.',
            'robbery' => $this->formatRobbery($this->loadRobbery($robbery->id)),
        ], 201);
    }

    public function join(Request $request, $id)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $robbery = Robbery::find($id);

        if (!$robbery) {
            return response()->json([
                'message' => 'Rablás nem található.',
            ], 404);
        }

        if ($robbery->finished) {
            return response()->json([
                'message' => 'Ez a rablás már le van zárva.',
            ], 422);
        }

        $alreadyJoined = DB::table('robbery_participants')
            ->where('robbery_id', $robbery->id)
            ->where('user_id', $validated['user_id'])
            ->exists();

        if ($alreadyJoined) {
            return response()->json([
                'message' => 'Erre a rablásra már jelentkeztél.',
            ], 422);
        }

        DB::table('robbery_participants')->insert([
            'robbery_id' => $robbery->id,
            'user_id' => $validated['user_id'],
            'created_at' => Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s'),
        ]);

        $this->syncCounts($robbery);

        return response()->json([
            'message' => 'Jelentkezés sikeresen mentve.',
            'robbery' => $this->formatRobbery($this->loadRobbery($robbery->id)),
        ]);
    }

    public function requestPayout(Request $request, $id)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $robbery = Robbery::find($id);

        if (!$robbery) {
            return response()->json([
                'message' => 'Rablás nem található.',
            ], 404);
        }

        if ($robbery->finished) {
            return response()->json([
                'message' => 'Ez a rablás már le van zárva.',
            ], 422);
        }

        $hasJoined = DB::table('robbery_participants')
            ->where('robbery_id', $robbery->id)
            ->where('user_id', $validated['user_id'])
            ->exists();

        if (!$hasJoined) {
            return response()->json([
                'message' => 'Pénzosztásra csak akkor jelentkezhetsz, ha előtte jelentkeztél a rablásra.',
            ], 422);
        }

        $alreadyRequested = DB::table('robbery_payout_requests')
            ->where('robbery_id', $robbery->id)
            ->where('user_id', $validated['user_id'])
            ->exists();

        if ($alreadyRequested) {
            return response()->json([
                'message' => 'Erre a rablásra már kértél pénzosztást.',
            ], 422);
        }

        DB::table('robbery_payout_requests')->insert([
            'robbery_id' => $robbery->id,
            'user_id' => $validated['user_id'],
            'created_at' => Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s'),
        ]);

        $this->syncCounts($robbery);

        return response()->json([
            'message' => 'Pénzosztásra jelentkezés sikeresen mentve.',
            'robbery' => $this->formatRobbery($this->loadRobbery($robbery->id)),
        ]);
    }

    public function storeIncome(Request $request, $id)
    {
        $robbery = Robbery::find($id);

        if (!$robbery) {
            return response()->json([
                'message' => 'Rablás nem található.',
            ], 404);
        }

        $validated = $request->validate([
            'submitted_by' => 'required|exists:users,id',
            'amount' => 'required|integer|min:1',
            'drilled_count' => 'required|integer|min:0|max:999',
            'imageFile' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $feeUnits = $robbery->type === 'BANK'
            ? intdiv((int) $validated['drilled_count'], 3)
            : (int) $validated['drilled_count'];
        $feeAmount = $feeUnits * 50000;
        $netAmount = max((int) $validated['amount'] - $feeAmount, 0);

        RobberyIncomeImage::create([
            'robbery_id' => $robbery->id,
            'submitted_by' => $validated['submitted_by'],
            'submitted_at' => Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s'),
            'amount' => $validated['amount'],
            'drilled_count' => $validated['drilled_count'],
            'fee_amount' => $feeAmount,
            'net_amount' => $netAmount,
            'image' => $this->storeImageFile($request, 'imageFile', 'robbery-income-images'),
        ]);

        return response()->json([
            'message' => 'Pénz sikeresen hozzáadva.',
            'net_amount' => $netAmount,
            'robbery' => $this->formatRobbery($this->loadRobbery($robbery->id)),
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
            'robbery' => $this->formatRobbery($this->loadRobbery($robbery->id)),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $robbery = Robbery::find($id);

        if (!$robbery) {
            return response()->json([
                'message' => 'Rablás nem található.',
            ], 404);
        }

        $actor = DB::table('users')
            ->select('id', 'isAdmin')
            ->where('id', $validated['user_id'])
            ->first();

        $isCreator = (int) $robbery->created_by === (int) $validated['user_id'];
        $isAdmin = (bool) ($actor->isAdmin ?? false);

        if (!$isCreator && !$isAdmin) {
            return response()->json([
                'message' => 'Ezt csak admin vagy a rablás létrehozója törölheti.',
            ], 403);
        }

        $robbery->delete();

        return response()->json([
            'message' => 'Rablás törölve.',
        ]);
    }

    private function loadRobbery(int $id): Robbery
    {
        return Robbery::query()
            ->with([
                'author:id,username,IgName,profileImage',
                'incomeImages.submitter:id,username,IgName,profileImage',
            ])
            ->findOrFail($id);
    }

    private function syncCounts(Robbery $robbery): void
    {
        $robbery->participants_count = DB::table('robbery_participants')
            ->where('robbery_id', $robbery->id)
            ->count();

        $robbery->applicants_count = DB::table('robbery_payout_requests')
            ->where('robbery_id', $robbery->id)
            ->count();

        $robbery->save();
    }

    private function formatRobbery(Robbery $robbery): array
    {
        $totalIncome = (int) $robbery->incomeImages->sum(function ($image) {
            return $image->net_amount > 0 ? $image->net_amount : $image->amount;
        });

        return [
            'id' => $robbery->id,
            'created_by' => $robbery->created_by,
            'name' => $robbery->name,
            'type' => $robbery->type,
            'participants_count' => (int) $robbery->participants_count,
            'applicants_count' => (int) $robbery->applicants_count,
            'finished' => (bool) $robbery->finished,
            'author' => $robbery->author,
            'income_images' => $robbery->incomeImages,
            'participants' => $this->getApplicationUsers('robbery_participants', $robbery->id),
            'payout_applicants' => $this->getApplicationUsers('robbery_payout_requests', $robbery->id),
            'total_income' => $totalIncome,
            'payout_share' => 0,
        ];
    }

    private function getApplicationUsers(string $table, int $robberyId)
    {
        return DB::table($table)
            ->join('users', "{$table}.user_id", '=', 'users.id')
            ->where("{$table}.robbery_id", $robberyId)
            ->orderBy("{$table}.created_at")
            ->get([
                'users.id',
                'users.username',
                'users.IgName',
                'users.profileImage',
                "{$table}.created_at as joined_at",
            ]);
    }

    private function storeImageFile(Request $request, string $field, string $directory): string
    {
        $disk = $this->imageStorageDisk();

        try {
            $path = Storage::disk($disk)->putFile($directory, $request->file($field));
        } catch (\Throwable $exception) {
            Log::warning('Robbery income image upload failed', [
                'field' => $field,
                'directory' => $directory,
                'disk' => $disk,
                'error' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                $field => 'A kép feltöltése nem sikerült.'
            ]);
        }

        if (!$path) {
            throw ValidationException::withMessages([
                $field => 'A kép feltöltése nem sikerült.'
            ]);
        }

        return $this->imageStorageUrl($path);
    }

    private function imageStorageUrl(string $path): string
    {
        $disk = $this->imageStorageDisk();
        $diskUrl = config("filesystems.disks.{$disk}.url");

        if ($diskUrl) {
            return rtrim($diskUrl, '/') . '/' . ltrim($path, '/');
        }

        return Storage::disk($disk)->url($path);
    }

    private function imageStorageDisk(): string
    {
        return config('filesystems.image_disk', 'public');
    }
}
