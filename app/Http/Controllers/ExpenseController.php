<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index()
    {
        return Expense::query()
            ->with('addedBy:id,username,IgName')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'purpose' => 'required|string|max:255',
            'amount' => 'required|integer|min:1',
            'description' => 'nullable|string|max:2000',
            'added_by' => 'required|exists:users,id'
        ]);

        $expense = Expense::create([
            'purpose' => trim($validated['purpose']),
            'amount' => $validated['amount'],
            'description' => trim($validated['description'] ?? ''),
            'added_by' => $validated['added_by']
        ]);

        return response()->json([
            'message' => 'Kiadás sikeresen hozzáadva.',
            'expense' => $expense->load('addedBy:id,username,IgName')
        ], 201);
    }
}
