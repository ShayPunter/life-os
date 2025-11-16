<?php

namespace Tests\Unit;

use App\Models\Expense;
use App\Models\User;
use App\Policies\ExpensePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpensePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected ExpensePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ExpensePolicy;
    }

    public function test_any_authenticated_user_can_view_any_expenses(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_user_can_view_their_own_expense(): void
    {
        $user = User::factory()->create();
        $expense = Expense::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->view($user, $expense));
    }

    public function test_user_cannot_view_other_users_expense(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $expense = Expense::factory()->create(['user_id' => $user2->id]);

        $this->assertFalse($this->policy->view($user1, $expense));
    }

    public function test_any_authenticated_user_can_create_expense(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->policy->create($user));
    }

    public function test_user_can_update_their_own_expense(): void
    {
        $user = User::factory()->create();
        $expense = Expense::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->update($user, $expense));
    }

    public function test_user_cannot_update_other_users_expense(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $expense = Expense::factory()->create(['user_id' => $user2->id]);

        $this->assertFalse($this->policy->update($user1, $expense));
    }

    public function test_user_can_delete_their_own_expense(): void
    {
        $user = User::factory()->create();
        $expense = Expense::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->delete($user, $expense));
    }

    public function test_user_cannot_delete_other_users_expense(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $expense = Expense::factory()->create(['user_id' => $user2->id]);

        $this->assertFalse($this->policy->delete($user1, $expense));
    }

    public function test_user_can_restore_their_own_expense(): void
    {
        $user = User::factory()->create();
        $expense = Expense::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->restore($user, $expense));
    }

    public function test_user_cannot_restore_other_users_expense(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $expense = Expense::factory()->create(['user_id' => $user2->id]);

        $this->assertFalse($this->policy->restore($user1, $expense));
    }

    public function test_user_can_force_delete_their_own_expense(): void
    {
        $user = User::factory()->create();
        $expense = Expense::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->forceDelete($user, $expense));
    }

    public function test_user_cannot_force_delete_other_users_expense(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $expense = Expense::factory()->create(['user_id' => $user2->id]);

        $this->assertFalse($this->policy->forceDelete($user1, $expense));
    }
}
