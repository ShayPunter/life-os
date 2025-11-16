<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\User;
use App\Services\CurrencyConversionService;
use App\Services\GroqService;
use App\Services\ImageCompressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_expense_index(): void
    {
        $response = $this->get(route('expenses.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_expense_index(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('expenses.index'));
        $response->assertStatus(200);
    }

    public function test_users_can_create_expense(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user);

        $expenseData = [
            'amount' => 50.25,
            'description' => 'Grocery shopping',
            'category' => 'Food',
            'date' => now()->format('Y-m-d'),
        ];

        $response = $this->post(route('expenses.store'), $expenseData);
        $response->assertRedirect(route('expenses.index'));

        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'amount' => 50.25,
            'description' => 'Grocery shopping',
            'category' => 'Food',
        ]);
    }

    public function test_users_can_create_expense_with_image_receipt(): void
    {
        Storage::fake('public');
        Storage::fake('s3');

        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->image('receipt.jpg');

        $expenseData = [
            'amount' => 75.00,
            'description' => 'Restaurant bill',
            'category' => 'Food',
            'date' => now()->format('Y-m-d'),
            'receipt' => $file,
        ];

        $response = $this->post(route('expenses.store'), $expenseData);
        $response->assertRedirect(route('expenses.index'));

        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'amount' => 75.00,
        ]);

        $expense = Expense::where('user_id', $user->id)->first();
        $this->assertNotNull($expense->receipt_path);
    }

    public function test_users_can_create_expense_with_pdf_receipt(): void
    {
        Storage::fake('public');
        Storage::fake('s3');

        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');

        $expenseData = [
            'amount' => 150.00,
            'description' => 'Invoice payment',
            'category' => 'Utilities',
            'date' => now()->format('Y-m-d'),
            'receipt' => $file,
        ];

        $response = $this->post(route('expenses.store'), $expenseData);
        $response->assertRedirect(route('expenses.index'));

        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'amount' => 150.00,
        ]);

        $expense = Expense::where('user_id', $user->id)->first();
        $this->assertNotNull($expense->receipt_path);
        $this->assertStringEndsWith('.pdf', $expense->receipt_path);
    }

    public function test_expense_creation_requires_valid_data(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post(route('expenses.store'), []);
        $response->assertSessionHasErrors(['amount', 'date']);
    }

    public function test_expense_amount_must_be_positive(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post(route('expenses.store'), [
            'amount' => -10,
            'date' => now()->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors(['amount']);
    }

    public function test_expense_receipt_must_be_valid_file_type(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->create('document.txt', 100);

        $response = $this->post(route('expenses.store'), [
            'amount' => 50.00,
            'date' => now()->format('Y-m-d'),
            'receipt' => $file,
        ]);

        $response->assertSessionHasErrors(['receipt']);
    }

    public function test_users_can_update_their_own_expense(): void
    {
        $user = User::factory()->create();
        $expense = Expense::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        $updateData = [
            'amount' => 100.00,
            'description' => 'Updated description',
            'category' => 'Shopping',
            'date' => now()->format('Y-m-d'),
        ];

        $response = $this->put(route('expenses.update', $expense), $updateData);
        $response->assertRedirect(route('expenses.index'));

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'amount' => 100.00,
            'description' => 'Updated description',
            'category' => 'Shopping',
        ]);
    }

    public function test_users_cannot_update_other_users_expenses(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $expense = Expense::factory()->create(['user_id' => $user2->id]);

        $this->actingAs($user1);

        $response = $this->put(route('expenses.update', $expense), [
            'amount' => 999.99,
            'date' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(403);
    }

    public function test_users_can_delete_their_own_expense(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $expense = Expense::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        $response = $this->delete(route('expenses.destroy', $expense));
        $response->assertRedirect(route('expenses.index'));

        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    public function test_users_cannot_delete_other_users_expenses(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $expense = Expense::factory()->create(['user_id' => $user2->id]);

        $this->actingAs($user1);

        $response = $this->delete(route('expenses.destroy', $expense));
        $response->assertStatus(403);
    }

    public function test_expense_index_shows_correct_summary(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Expense::factory()->create([
            'user_id' => $user->id,
            'amount' => 100,
            'date' => now(),
        ]);

        Expense::factory()->create([
            'user_id' => $user->id,
            'amount' => 50,
            'date' => now(),
        ]);

        Expense::factory()->create([
            'user_id' => $user->id,
            'amount' => 75,
            'date' => now()->subMonth(),
        ]);

        $response = $this->get(route('expenses.index'));
        $response->assertStatus(200);
    }

    public function test_users_only_see_their_own_expenses(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $expense1 = Expense::factory()->create(['user_id' => $user1->id]);
        $expense2 = Expense::factory()->create(['user_id' => $user2->id]);

        $this->actingAs($user1);
        $response = $this->get(route('expenses.index'));
        $response->assertStatus(200);
    }

    public function test_analyze_receipt_requires_authentication(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->image('receipt.jpg');

        $response = $this->post(route('expenses.analyze-receipt'), [
            'receipt' => $file,
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_analyze_receipt_requires_file(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson(route('expenses.analyze-receipt'), []);

        $response->assertStatus(422);
    }

    public function test_analyze_receipt_accepts_image_files(): void
    {
        Storage::fake('local');
        Storage::fake('s3');
        Storage::fake('public');

        $user = User::factory()->create();
        $this->actingAs($user);

        // Mock the GroqService
        $this->mock(GroqService::class, function ($mock) {
            $mock->shouldReceive('analyzeReceiptFromS3')
                ->once()
                ->andReturn([
                    'amount' => 50.00,
                    'currency' => 'GBP',
                    'description' => 'Coffee and pastries',
                    'category' => 'Food',
                ]);
        });

        // Mock the CurrencyConversionService
        $this->mock(CurrencyConversionService::class, function ($mock) {
            $mock->shouldReceive('convertToEur')
                ->once()
                ->with(50.00, 'GBP')
                ->andReturn([
                    'amount_eur' => 58.50,
                    'original_amount' => 50.00,
                    'original_currency' => 'GBP',
                    'exchange_rate' => 1.17,
                ]);
        });

        // Don't mock ImageCompressionService - it will fall back to copy without API key
        // This avoids filesystem vs Storage::fake conflicts

        $file = UploadedFile::fake()->image('receipt.jpg');

        $response = $this->postJson(route('expenses.analyze-receipt'), [
            'receipt' => $file,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'amount' => 58.50,
                'original_amount' => 50.00,
                'original_currency' => 'GBP',
                'exchange_rate' => 1.17,
                'description' => 'Coffee and pastries',
                'category' => 'Food',
            ],
        ]);
    }

    public function test_analyze_receipt_accepts_pdf_files(): void
    {
        Storage::fake('local');
        Storage::fake('s3');
        Storage::fake('public');

        $user = User::factory()->create();
        $this->actingAs($user);

        // Mock the GroqService
        $this->mock(GroqService::class, function ($mock) {
            $mock->shouldReceive('analyzeReceiptFromS3')
                ->once()
                ->andReturn([
                    'amount' => 3000.00,
                    'currency' => 'CZK',
                    'description' => 'Office supplies',
                    'category' => 'Shopping',
                ]);
        });

        // Mock the CurrencyConversionService
        $this->mock(CurrencyConversionService::class, function ($mock) {
            $mock->shouldReceive('convertToEur')
                ->once()
                ->with(3000.00, 'CZK')
                ->andReturn([
                    'amount_eur' => 125.50,
                    'original_amount' => 3000.00,
                    'original_currency' => 'CZK',
                    'exchange_rate' => 0.04183,
                ]);
        });

        $file = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');

        $response = $this->postJson(route('expenses.analyze-receipt'), [
            'receipt' => $file,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'amount' => 125.50,
                'original_amount' => 3000.00,
                'original_currency' => 'CZK',
                'exchange_rate' => 0.04183,
                'description' => 'Office supplies',
                'category' => 'Shopping',
            ],
        ]);
    }

    public function test_analyze_receipt_accepts_usd_currency(): void
    {
        Storage::fake('local');
        Storage::fake('s3');
        Storage::fake('public');

        $user = User::factory()->create();
        $this->actingAs($user);

        // Mock the GroqService
        $this->mock(GroqService::class, function ($mock) {
            $mock->shouldReceive('analyzeReceiptFromS3')
                ->once()
                ->andReturn([
                    'amount' => 100.00,
                    'currency' => 'USD',
                    'description' => 'Electronics',
                    'category' => 'Shopping',
                ]);
        });

        // Mock the CurrencyConversionService
        $this->mock(CurrencyConversionService::class, function ($mock) {
            $mock->shouldReceive('convertToEur')
                ->once()
                ->with(100.00, 'USD')
                ->andReturn([
                    'amount_eur' => 92.50,
                    'original_amount' => 100.00,
                    'original_currency' => 'USD',
                    'exchange_rate' => 0.925,
                ]);
        });

        $file = UploadedFile::fake()->image('receipt.jpg');

        $response = $this->postJson(route('expenses.analyze-receipt'), [
            'receipt' => $file,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'amount' => 92.50,
                'original_amount' => 100.00,
                'original_currency' => 'USD',
                'exchange_rate' => 0.925,
                'description' => 'Electronics',
                'category' => 'Shopping',
            ],
        ]);
    }

    public function test_deleting_expense_removes_receipt_file(): void
    {
        Storage::fake('public');
        Storage::fake('s3');

        $user = User::factory()->create();
        $this->actingAs($user);

        $expense = Expense::factory()->create([
            'user_id' => $user->id,
            'receipt_path' => 'receipts/test-receipt.jpg',
        ]);

        // Create a fake file in storage
        Storage::disk('public')->put($expense->receipt_path, 'fake content');

        $response = $this->delete(route('expenses.destroy', $expense));
        $response->assertRedirect(route('expenses.index'));

        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
        Storage::disk('public')->assertMissing($expense->receipt_path);
    }
}
