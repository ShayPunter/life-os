<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDebtRequest;
use App\Http\Requests\UpdateDebtRequest;
use App\Models\Debt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DebtController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $debts = $request->user()
            ->debts()
            ->latest()
            ->get()
            ->map(fn (Debt $debt) => [
                'id' => $debt->id,
                'debtor_name' => $debt->debtor_name,
                'amount' => $debt->amount,
                'type' => $debt->type,
                'description' => $debt->description,
                'due_date' => $debt->due_date?->format('Y-m-d'),
                'is_paid' => $debt->is_paid,
                'created_at' => $debt->created_at->format('Y-m-d'),
            ]);

        $summary = [
            'total_owed_to_me' => $request->user()
                ->debts()
                ->where('type', 'owed_to_me')
                ->where('is_paid', false)
                ->sum('amount'),
            'total_i_owe' => $request->user()
                ->debts()
                ->where('type', 'i_owe')
                ->where('is_paid', false)
                ->sum('amount'),
        ];

        return Inertia::render('debts/Index', [
            'debts' => $debts,
            'summary' => $summary,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('debts/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDebtRequest $request): RedirectResponse
    {
        $request->user()->debts()->create($request->validated());

        return to_route('debts.index')->with('status', 'Debt created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Debt $debt): Response
    {
        $this->authorize('view', $debt);

        return Inertia::render('debts/Show', [
            'debt' => [
                'id' => $debt->id,
                'debtor_name' => $debt->debtor_name,
                'amount' => $debt->amount,
                'type' => $debt->type,
                'description' => $debt->description,
                'due_date' => $debt->due_date?->format('Y-m-d'),
                'is_paid' => $debt->is_paid,
                'created_at' => $debt->created_at->format('Y-m-d H:i'),
                'updated_at' => $debt->updated_at->format('Y-m-d H:i'),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Debt $debt): Response
    {
        $this->authorize('update', $debt);

        return Inertia::render('debts/Edit', [
            'debt' => [
                'id' => $debt->id,
                'debtor_name' => $debt->debtor_name,
                'amount' => $debt->amount,
                'type' => $debt->type,
                'description' => $debt->description,
                'due_date' => $debt->due_date?->format('Y-m-d'),
                'is_paid' => $debt->is_paid,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDebtRequest $request, Debt $debt): RedirectResponse
    {
        $this->authorize('update', $debt);

        $debt->update($request->validated());

        return to_route('debts.index')->with('status', 'Debt updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Debt $debt): RedirectResponse
    {
        $this->authorize('delete', $debt);

        $debt->delete();

        return to_route('debts.index')->with('status', 'Debt deleted successfully.');
    }
}
