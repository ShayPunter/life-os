<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use App\Services\CurrencyConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_asset_index(): void
    {
        $response = $this->get(route('assets.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_asset_index(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('assets.index'));
        $response->assertStatus(200);
    }

    public function test_users_can_create_asset(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $assetData = [
            'name' => 'Gaming Laptop',
            'description' => 'High-end gaming laptop',
            'cost' => 1500.00,
            'purchased_at' => now()->format('Y-m-d'),
        ];

        $response = $this->post(route('assets.store'), $assetData);
        $response->assertRedirect(route('assets.index'));

        $this->assertDatabaseHas('assets', [
            'user_id' => $user->id,
            'name' => 'Gaming Laptop',
            'description' => 'High-end gaming laptop',
            'cost' => 1500.00,
            'uses' => 0,
        ]);
    }

    public function test_users_can_create_asset_with_currency_conversion(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->mock(CurrencyConversionService::class, function ($mock) {
            $mock->shouldReceive('convertToEur')
                ->once()
                ->with(100.00, 'GBP')
                ->andReturn([
                    'amount_eur' => 117.00,
                    'original_amount' => 100.00,
                    'original_currency' => 'GBP',
                    'exchange_rate' => 1.17,
                ]);
        });

        $assetData = [
            'name' => 'Backpack',
            'description' => 'Travel backpack',
            'original_cost' => 100.00,
            'original_currency' => 'GBP',
            'purchased_at' => now()->format('Y-m-d'),
        ];

        $response = $this->post(route('assets.store'), $assetData);
        $response->assertRedirect(route('assets.index'));

        $this->assertDatabaseHas('assets', [
            'user_id' => $user->id,
            'name' => 'Backpack',
            'cost' => 117.00,
            'original_cost' => 100.00,
            'original_currency' => 'GBP',
            'exchange_rate' => 1.17,
        ]);
    }

    public function test_asset_creation_requires_valid_data(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post(route('assets.store'), []);
        $response->assertSessionHasErrors(['name', 'cost', 'purchased_at']);
    }

    public function test_asset_cost_must_be_positive(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post(route('assets.store'), [
            'name' => 'Test Asset',
            'cost' => -10,
            'purchased_at' => now()->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors(['cost']);
    }

    public function test_asset_currency_must_be_valid(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post(route('assets.store'), [
            'name' => 'Test Asset',
            'original_cost' => 100,
            'original_currency' => 'INVALID',
            'purchased_at' => now()->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors(['original_currency']);
    }

    public function test_users_can_update_their_own_asset(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        $updateData = [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'cost' => 2000.00,
            'purchased_at' => now()->format('Y-m-d'),
        ];

        $response = $this->put(route('assets.update', $asset), $updateData);
        $response->assertRedirect(route('assets.index'));

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'cost' => 2000.00,
        ]);
    }

    public function test_users_cannot_update_other_users_assets(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $asset = Asset::factory()->create(['user_id' => $user2->id]);

        $this->actingAs($user1);

        $response = $this->put(route('assets.update', $asset), [
            'name' => 'Hacked',
            'cost' => 999.99,
            'purchased_at' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(403);
    }

    public function test_users_can_delete_their_own_asset(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        $response = $this->delete(route('assets.destroy', $asset));
        $response->assertRedirect(route('assets.index'));

        $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
    }

    public function test_users_cannot_delete_other_users_assets(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $asset = Asset::factory()->create(['user_id' => $user2->id]);

        $this->actingAs($user1);

        $response = $this->delete(route('assets.destroy', $asset));
        $response->assertStatus(403);
    }

    public function test_users_can_increment_uses(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create([
            'user_id' => $user->id,
            'uses' => 5,
        ]);
        $this->actingAs($user);

        $response = $this->post(route('assets.increment-uses', $asset));
        $response->assertStatus(302);

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'uses' => 6,
        ]);
    }

    public function test_users_can_decrement_uses(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create([
            'user_id' => $user->id,
            'uses' => 5,
        ]);
        $this->actingAs($user);

        $response = $this->post(route('assets.decrement-uses', $asset));
        $response->assertStatus(302);

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'uses' => 4,
        ]);
    }

    public function test_users_cannot_decrement_uses_below_zero(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create([
            'user_id' => $user->id,
            'uses' => 0,
        ]);
        $this->actingAs($user);

        $response = $this->post(route('assets.decrement-uses', $asset));
        $response->assertStatus(302);

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'uses' => 0,
        ]);
    }

    public function test_users_cannot_increment_other_users_asset_uses(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $asset = Asset::factory()->create(['user_id' => $user2->id]);

        $this->actingAs($user1);

        $response = $this->post(route('assets.increment-uses', $asset));
        $response->assertStatus(403);
    }

    public function test_asset_index_shows_correct_summary(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Asset::factory()->create([
            'user_id' => $user->id,
            'cost' => 100,
            'uses' => 10,
        ]);

        Asset::factory()->create([
            'user_id' => $user->id,
            'cost' => 50,
            'uses' => 5,
        ]);

        $response = $this->get(route('assets.index'));
        $response->assertStatus(200);
    }

    public function test_users_only_see_their_own_assets(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $asset1 = Asset::factory()->create(['user_id' => $user1->id]);
        $asset2 = Asset::factory()->create(['user_id' => $user2->id]);

        $this->actingAs($user1);
        $response = $this->get(route('assets.index'));
        $response->assertStatus(200);
    }

    public function test_cost_per_use_calculation_is_correct(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create([
            'user_id' => $user->id,
            'cost' => 100.00,
            'uses' => 10,
        ]);

        $this->assertEquals(10.00, $asset->costPerUse());
    }

    public function test_cost_per_use_is_null_when_uses_is_zero(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create([
            'user_id' => $user->id,
            'cost' => 100.00,
            'uses' => 0,
        ]);

        $this->assertNull($asset->costPerUse());
    }

    public function test_assets_support_multiple_currencies(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $currencies = ['GBP', 'USD', 'CZK'];

        // Mock the currency service to expect 3 calls (one for each currency)
        $this->mock(CurrencyConversionService::class, function ($mock) use ($currencies) {
            foreach ($currencies as $currency) {
                $mock->shouldReceive('convertToEur')
                    ->once()
                    ->with(85.00, $currency)
                    ->andReturn([
                        'amount_eur' => 100.00,
                        'original_amount' => 85.00,
                        'original_currency' => $currency,
                        'exchange_rate' => 1.176,
                    ]);
            }
        });

        foreach ($currencies as $currency) {
            $response = $this->post(route('assets.store'), [
                'name' => "Asset in {$currency}",
                'original_cost' => 85.00,
                'original_currency' => $currency,
                'purchased_at' => now()->format('Y-m-d'),
            ]);

            $response->assertRedirect(route('assets.index'));
        }
    }

    public function test_users_can_increment_hours(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create([
            'user_id' => $user->id,
            'hours' => 5.0,
        ]);
        $this->actingAs($user);

        $response = $this->post(route('assets.increment-hours', $asset));
        $response->assertStatus(302);

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'hours' => 5.5,
        ]);
    }

    public function test_users_can_decrement_hours(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create([
            'user_id' => $user->id,
            'hours' => 5.0,
        ]);
        $this->actingAs($user);

        $response = $this->post(route('assets.decrement-hours', $asset));
        $response->assertStatus(302);

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'hours' => 4.5,
        ]);
    }

    public function test_users_cannot_decrement_hours_below_zero(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create([
            'user_id' => $user->id,
            'hours' => 0.0,
        ]);
        $this->actingAs($user);

        $response = $this->post(route('assets.decrement-hours', $asset));
        $response->assertStatus(302);

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'hours' => 0.0,
        ]);
    }

    public function test_users_cannot_increment_other_users_asset_hours(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $asset = Asset::factory()->create(['user_id' => $user2->id]);

        $this->actingAs($user1);

        $response = $this->post(route('assets.increment-hours', $asset));
        $response->assertStatus(403);
    }

    public function test_cost_per_hour_calculation_is_correct(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create([
            'user_id' => $user->id,
            'cost' => 60.00,
            'hours' => 10.0,
        ]);

        $this->assertEquals(6.00, $asset->costPerHour());
    }

    public function test_cost_per_hour_is_null_when_hours_is_zero(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create([
            'user_id' => $user->id,
            'cost' => 100.00,
            'hours' => 0.0,
        ]);

        $this->assertNull($asset->costPerHour());
    }
}
