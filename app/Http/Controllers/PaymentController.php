<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Models\Debt;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Debt $debt): Response
    {
        $this->authorize('view', $debt);

        $payments = $debt->payments()
            ->latest('payment_date')
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'payment_date' => $payment->payment_date->format('Y-m-d'),
                'notes' => $payment->notes,
                'created_at' => $payment->created_at->format('Y-m-d H:i'),
            ]);

        $totalPaid = $debt->payments()->sum('amount');
        $remainingBalance = $debt->amount - $totalPaid;

        return Inertia::render('payments/Index', [
            'debt' => [
                'id' => $debt->id,
                'debtor_name' => $debt->debtor_name,
                'amount' => $debt->amount,
                'type' => $debt->type,
            ],
            'payments' => $payments,
            'summary' => [
                'total_paid' => $totalPaid,
                'remaining_balance' => $remainingBalance,
                'payment_count' => $payments->count(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Debt $debt): Response
    {
        $this->authorize('view', $debt);

        return Inertia::render('payments/Create', [
            'debt' => [
                'id' => $debt->id,
                'debtor_name' => $debt->debtor_name,
                'amount' => $debt->amount,
                'type' => $debt->type,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePaymentRequest $request, Debt $debt): RedirectResponse
    {
        $this->authorize('view', $debt);

        $debt->payments()->create($request->validated());

        return to_route('debts.show', $debt)->with('status', 'Payment recorded successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Debt $debt, Payment $payment): Response
    {
        $this->authorize('view', $debt);

        abort_if($payment->debt_id !== $debt->id, 404);

        return Inertia::render('payments/Show', [
            'debt' => [
                'id' => $debt->id,
                'debtor_name' => $debt->debtor_name,
                'amount' => $debt->amount,
                'type' => $debt->type,
            ],
            'payment' => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'payment_date' => $payment->payment_date->format('Y-m-d'),
                'notes' => $payment->notes,
                'created_at' => $payment->created_at->format('Y-m-d H:i'),
                'updated_at' => $payment->updated_at->format('Y-m-d H:i'),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Debt $debt, Payment $payment): Response
    {
        $this->authorize('view', $debt);

        abort_if($payment->debt_id !== $debt->id, 404);

        return Inertia::render('payments/Edit', [
            'debt' => [
                'id' => $debt->id,
                'debtor_name' => $debt->debtor_name,
                'amount' => $debt->amount,
                'type' => $debt->type,
            ],
            'payment' => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'payment_date' => $payment->payment_date->format('Y-m-d'),
                'notes' => $payment->notes,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePaymentRequest $request, Debt $debt, Payment $payment): RedirectResponse
    {
        $this->authorize('view', $debt);

        abort_if($payment->debt_id !== $debt->id, 404);

        $payment->update($request->validated());

        return to_route('debts.show', $debt)->with('status', 'Payment updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Debt $debt, Payment $payment): RedirectResponse
    {
        $this->authorize('view', $debt);

        abort_if($payment->debt_id !== $debt->id, 404);

        $payment->delete();

        return to_route('debts.show', $debt)->with('status', 'Payment deleted successfully.');
    }
}
