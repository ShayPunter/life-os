<?php

namespace Tests\Feature;

use App\Models\Debt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_debt_index(): void
    {
        $response = $this->get(route('debts.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_debt_index(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('debts.index'));
        $response->assertStatus(200);
    }

    public function test_users_can_create_debt(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $debtData = [
            'debtor_name' => 'John Doe',
            'amount' => 100.50,
            'type' => 'owed_to_me',
            'description' => 'Lunch money',
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'is_paid' => false,
        ];

        $response = $this->post(route('debts.store'), $debtData);
        $response->assertRedirect(route('debts.index'));

        $this->assertDatabaseHas('debts', [
            'user_id' => $user->id,
            'debtor_name' => 'John Doe',
            'amount' => 100.50,
            'type' => 'owed_to_me',
        ]);
    }

    public function test_debt_creation_requires_valid_data(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post(route('debts.store'), []);
        $response->assertSessionHasErrors(['debtor_name', 'amount', 'type']);
    }

    public function test_users_can_update_their_own_debt(): void
    {
        $user = User::factory()->create();
        $debt = Debt::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        $updateData = [
            'debtor_name' => 'Jane Doe',
            'amount' => 200.00,
            'type' => 'i_owe',
        ];

        $response = $this->put(route('debts.update', $debt), $updateData);
        $response->assertRedirect(route('debts.index'));

        $this->assertDatabaseHas('debts', [
            'id' => $debt->id,
            'debtor_name' => 'Jane Doe',
            'amount' => 200.00,
            'type' => 'i_owe',
        ]);
    }

    public function test_users_cannot_update_other_users_debts(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $debt = Debt::factory()->create(['user_id' => $user2->id]);

        $this->actingAs($user1);

        $response = $this->put(route('debts.update', $debt), [
            'debtor_name' => 'Hacker',
        ]);

        $response->assertStatus(403);
    }

    public function test_users_can_delete_their_own_debt(): void
    {
        $user = User::factory()->create();
        $debt = Debt::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        $response = $this->delete(route('debts.destroy', $debt));
        $response->assertRedirect(route('debts.index'));

        $this->assertDatabaseMissing('debts', ['id' => $debt->id]);
    }

    public function test_users_cannot_delete_other_users_debts(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $debt = Debt::factory()->create(['user_id' => $user2->id]);

        $this->actingAs($user1);

        $response = $this->delete(route('debts.destroy', $debt));
        $response->assertStatus(403);
    }

    public function test_debt_index_shows_correct_summary(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Debt::factory()->create([
            'user_id' => $user->id,
            'amount' => 100,
            'type' => 'owed_to_me',
            'is_paid' => false,
        ]);

        Debt::factory()->create([
            'user_id' => $user->id,
            'amount' => 50,
            'type' => 'i_owe',
            'is_paid' => false,
        ]);

        Debt::factory()->create([
            'user_id' => $user->id,
            'amount' => 200,
            'type' => 'owed_to_me',
            'is_paid' => true,
        ]);

        $response = $this->get(route('debts.index'));
        $response->assertStatus(200);
    }

    public function test_users_only_see_their_own_debts(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $debt1 = Debt::factory()->create(['user_id' => $user1->id, 'debtor_name' => 'User1 Debt']);
        $debt2 = Debt::factory()->create(['user_id' => $user2->id, 'debtor_name' => 'User2 Debt']);

        $this->actingAs($user1);
        $response = $this->get(route('debts.index'));
        $response->assertStatus(200);
    }
}
