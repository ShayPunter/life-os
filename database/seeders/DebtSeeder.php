<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DebtSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = \App\Models\User::all();

        if ($users->isEmpty()) {
            return;
        }

        foreach ($users as $user) {
            \App\Models\Debt::factory()
                ->count(5)
                ->create(['user_id' => $user->id]);
        }
    }
}
