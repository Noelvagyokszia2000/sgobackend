<?php

namespace App\Http\Controllers;

use App\Models\Robbery;
use App\Models\RobberyIncomeImage;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
        ]);

        $robbery = Robbery::create([
            'created_by' => $validated['created_by'],
            'participants_count' => 0,
            'applicants_count' => 0,
            'finished' => false,
        ]);

        return response()->json([
            'message' => 'Rablás sikeresen létrehozva.',
            'robbery' => $this->formatRobbery($this->loadRobbery($robbery->id)),
        ], 201);
    }

    public function join($id)
    {
        $robbery = Robbery::find($id);

        if (!$robbery) {
            return response()->json([
                'message' => 'Rablás nem található.',
            ], 404);
        }

        $robbery->increment('participants_count');

        return response()->json([
            'message' => 'Jelentkezés sikeresen mentve.',
            'robbery' => $this->formatRobbery($this->loadRobbery($robbery->id)),
        ]);
    }

    public function requestPayout($id)
    {
        $robbery = Robbery::find($id);

        if (!$robbery) {
            return response()->json([
                'message' => 'Rablás nem található.',
            ], 404);
        }

        $robbery->increment('applicants_count');

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
            'imageFile' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        RobberyIncomeImage::create([
            'robbery_id' => $robbery->id,
            'submitted_by' => $validated['submitted_by'],
            'submitted_at' => Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s'),
            'amount' => $validated['amount'],
            'image' => $this->storeImageFile($request, 'imageFile', 'robbery-income-images'),
        ]);

        return response()->json([
            'message' => 'Pénz sikeresen hozzáadva.',
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

    private function loadRobbery(int $id): Robbery
    {
        return Robbery::query()
            ->with([
                'author:id,username,IgName,profileImage',
                'incomeImages.submitter:id,username,IgName,profileImage',
            ])
            ->findOrFail($id);
    }

    private function formatRobbery(Robbery $robbery): array
    {
        $totalIncome = (int) $robbery->incomeImages->sum('amount');
        $applicantsCount = (int) $robbery->applicants_count;

        return [
            'id' => $robbery->id,
            'created_by' => $robbery->created_by,
            'participants_count' => (int) $robbery->participants_count,
            'applicants_count' => $applicantsCount,
            'finished' => (bool) $robbery->finished,
            'author' => $robbery->author,
            'income_images' => $robbery->incomeImages,
            'total_income' => $totalIncome,
            'payout_share' => $applicantsCount > 0 ? intdiv($totalIncome, $applicantsCount) : 0,
        ];
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
