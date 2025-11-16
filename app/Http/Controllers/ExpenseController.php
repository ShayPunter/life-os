<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use App\Services\GroqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected GroqService $groqService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $expenses = $request->user()
            ->expenses()
            ->latest('date')
            ->latest('created_at')
            ->get()
            ->map(fn (Expense $expense) => [
                'id' => $expense->id,
                'amount' => $expense->amount,
                'description' => $expense->description,
                'category' => $expense->category,
                'date' => $expense->date->format('Y-m-d'),
                'receipt_path' => $expense->receipt_path,
                'created_at' => $expense->created_at->format('Y-m-d'),
            ]);

        $summary = [
            'total' => $request->user()
                ->expenses()
                ->sum('amount'),
            'count' => $request->user()
                ->expenses()
                ->count(),
            'this_month' => $request->user()
                ->expenses()
                ->whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->sum('amount'),
        ];

        return Inertia::render('expenses/Index', [
            'expenses' => $expenses,
            'summary' => $summary,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store('receipts', 'public');
            $data['receipt_path'] = $receiptPath;
        }

        $request->user()->expenses()->create($data);

        return to_route('expenses.index')->with('status', 'Expense created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);

        $data = $request->validated();

        if ($request->hasFile('receipt')) {
            // Delete old receipt if it exists
            if ($expense->receipt_path) {
                Storage::disk('public')->delete($expense->receipt_path);
            }

            $receiptPath = $request->file('receipt')->store('receipts', 'public');
            $data['receipt_path'] = $receiptPath;
        }

        $expense->update($data);

        return to_route('expenses.index')->with('status', 'Expense updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);

        // Delete receipt file if it exists
        if ($expense->receipt_path) {
            Storage::disk('public')->delete($expense->receipt_path);
        }

        $expense->delete();

        return to_route('expenses.index')->with('status', 'Expense deleted successfully.');
    }

    /**
     * Analyze a receipt image using Groq AI.
     */
    public function analyzeReceipt(Request $request): JsonResponse
    {
        $request->validate([
            'receipt' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);

        try {
            $file = $request->file('receipt');
            $tempPath = $file->store('temp', 'local');
            $fullPath = Storage::disk('local')->path($tempPath);

            $result = $this->groqService->analyzeReceipt($fullPath);

            // Clean up temp file
            Storage::disk('local')->delete($tempPath);

            return response()->json([
                'success' => true,
                'data' => [
                    'amount' => $result['amount'],
                    'description' => $result['description'],
                    'category' => $result['category'],
                    'date' => now()->format('Y-m-d'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
