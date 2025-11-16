<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAssetRequest;
use App\Http\Requests\UpdateAssetRequest;
use App\Models\Asset;
use App\Services\CurrencyConversionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected CurrencyConversionService $currencyService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $assets = $request->user()
            ->assets()
            ->latest('purchased_at')
            ->latest('created_at')
            ->get()
            ->map(fn (Asset $asset) => [
                'id' => $asset->id,
                'name' => $asset->name,
                'description' => $asset->description,
                'cost' => $asset->cost,
                'uses' => $asset->uses,
                'hours' => $asset->hours,
                'tracking_type' => $asset->tracking_type,
                'cost_per_use' => $asset->costPerUse(),
                'cost_per_hour' => $asset->costPerHour(),
                'purchased_at' => $asset->purchased_at->format('Y-m-d'),
                'created_at' => $asset->created_at->format('Y-m-d'),
            ]);

        $summary = [
            'total_cost' => $request->user()
                ->assets()
                ->sum('cost'),
            'count' => $request->user()
                ->assets()
                ->count(),
            'total_uses' => $request->user()
                ->assets()
                ->sum('uses'),
            'total_hours' => $request->user()
                ->assets()
                ->sum('hours'),
        ];

        return Inertia::render('assets/Index', [
            'assets' => $assets,
            'summary' => $summary,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAssetRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        // Handle currency conversion if original currency is provided
        if (! empty($data['original_currency']) && ! empty($data['original_cost'])) {
            $converted = $this->currencyService->convertToEur(
                $data['original_cost'],
                $data['original_currency']
            );

            $data['cost'] = $converted['amount_eur'];
            $data['original_cost'] = $converted['original_amount'];
            $data['original_currency'] = $converted['original_currency'];
            $data['exchange_rate'] = $converted['exchange_rate'];
        }

        $request->user()->assets()->create($data);

        return to_route('assets.index')->with('status', 'Asset created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAssetRequest $request, Asset $asset): RedirectResponse
    {
        $this->authorize('update', $asset);

        $data = $request->validated();

        // Handle currency conversion if original currency is provided
        if (! empty($data['original_currency']) && ! empty($data['original_cost'])) {
            $converted = $this->currencyService->convertToEur(
                $data['original_cost'],
                $data['original_currency']
            );

            $data['cost'] = $converted['amount_eur'];
            $data['original_cost'] = $converted['original_amount'];
            $data['original_currency'] = $converted['original_currency'];
            $data['exchange_rate'] = $converted['exchange_rate'];
        }

        $asset->update($data);

        return to_route('assets.index')->with('status', 'Asset updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Asset $asset): RedirectResponse
    {
        $this->authorize('delete', $asset);

        $asset->delete();

        return to_route('assets.index')->with('status', 'Asset deleted successfully.');
    }

    /**
     * Increment the uses count for an asset.
     */
    public function incrementUses(Asset $asset): RedirectResponse
    {
        $this->authorize('update', $asset);

        $asset->increment('uses');

        return back();
    }

    /**
     * Decrement the uses count for an asset.
     */
    public function decrementUses(Asset $asset): RedirectResponse
    {
        $this->authorize('update', $asset);

        if ($asset->uses > 0) {
            $asset->decrement('uses');
        }

        return back();
    }

    /**
     * Increment the hours for an asset.
     */
    public function incrementHours(Asset $asset): RedirectResponse
    {
        $this->authorize('update', $asset);

        $asset->increment('hours', 0.5);

        return back();
    }

    /**
     * Decrement the hours for an asset.
     */
    public function decrementHours(Asset $asset): RedirectResponse
    {
        $this->authorize('update', $asset);

        if ($asset->hours >= 0.5) {
            $asset->decrement('hours', 0.5);
        }

        return back();
    }
}
