<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Transaction factory to create fake transaction details
 *
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement([
            TransactionType::Expense,
            TransactionType::Expense,
            TransactionType::Expense,
            TransactionType::Income,
        ]);

        return [
            'user_id' => User::factory(),
            'title' => fake()->randomElement($type === TransactionType::Income
                ? ['Salary', 'Freelance Payment', 'Bonus', 'Refund']
                : ['Lunch', 'Groceries', 'Petrol', 'Utilities', 'Lepak', 'Coffee', 'Badminton']),
            'type' => $type,
            'amount' => fake()->randomFloat(2, $type === TransactionType::Income ? 500 : 5, $type === TransactionType::Income ? 5000 : 300),
            'category_id' => null,
            'notes' => fake()->optional(0.25)->sentence(),
            'transaction_date' => fake()->dateTimeBetween(now()->startOfMonth(), now()),
        ];
    }

    public function income(): static
    {
        return $this->state(fn () => [
            'title' => fake()->randomElement(['Salary', 'Freelance Payment', 'Bonus', 'Refund']),
            'type' => TransactionType::Income,
            'amount' => fake()->randomFloat(2, 500, 5000),
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn () => [
            'title' => fake()->randomElement(['Lunch', 'Groceries', 'Petrol', 'Utilities', 'Lepak', 'Coffee', 'Badminton']),
            'type' => TransactionType::Expense,
            'amount' => fake()->randomFloat(2, 5, 300),
        ]);
    }
}
