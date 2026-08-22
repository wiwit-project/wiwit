<?php

use App\Enums\TransactionType;
use App\Filament\Imports\TransactionImporter;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Debts\DebtResource;
use App\Filament\Resources\Debts\Pages\ListDebts;
use App\Filament\Resources\Transactions\Pages\ManageTransactions;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Category;
use App\Models\Debt;
use App\Models\Transaction;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

it('scopes finance resources to the current user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $ownCategory = Category::create(['user_id' => $user->id, 'name' => 'Food']);
    $otherCategory = Category::create(['user_id' => $otherUser->id, 'name' => 'Travel']);
    $ownTransaction = Transaction::create([
        'user_id' => $user->id,
        'category_id' => $ownCategory->id,
        'title' => 'Lunch',
        'type' => TransactionType::Expense,
        'amount' => 12.30,
        'transaction_date' => today(),
    ]);
    $otherTransaction = Transaction::create([
        'user_id' => $otherUser->id,
        'category_id' => $otherCategory->id,
        'title' => 'Flight',
        'type' => TransactionType::Expense,
        'amount' => 67.80,
        'transaction_date' => today(),
    ]);

    $this->actingAs($user);

    expect(CategoryResource::getEloquentQuery()->pluck('id')->all())->toBe([$ownCategory->id])
        ->and(TransactionResource::getEloquentQuery()->pluck('id')->all())->toBe([$ownTransaction->id])
        ->and(CategoryResource::getRecordRouteBindingEloquentQuery()->find($otherCategory->id))->toBeNull()
        ->and(TransactionResource::getRecordRouteBindingEloquentQuery()->find($otherTransaction->id))->toBeNull();
});

it('imports categories per user and allows cents', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherCategory = Category::create(['user_id' => $otherUser->id, 'name' => 'Food']);

    Auth::login($user);

    $importer = new TransactionImporter(Import::create([
        'file_name' => 'transactions.csv',
        'file_path' => 'transactions.csv',
        'importer' => TransactionImporter::class,
        'total_rows' => 2,
        'user_id' => $user->id,
    ]), [
        'amount' => 'amount',
        'category' => 'category',
        'title' => 'title',
        'notes' => 'notes',
        'transaction_date' => 'transaction_date',
    ], []);

    $importer([
        'amount' => '-12.30',
        'category' => 'Food',
        'title' => 'Lunch',
        'notes' => 'Lunch',
        'transaction_date' => today()->toDateString(),
    ]);
    $importer([
        'amount' => '12.30',
        'category' => 'Food',
        'title' => 'Paycheck',
        'notes' => 'Paycheck',
        'transaction_date' => today()->toDateString(),
    ]);

    $expense = Transaction::where('user_id', $user->id)->where('title', 'Lunch')->sole();
    $income = Transaction::where('user_id', $user->id)->where('title', 'Paycheck')->sole();
    $category = $expense->category;

    expect($expense->amount)->toBe('12.30')
        ->and($expense->type)->toBe(TransactionType::Expense)
        ->and($income->amount)->toBe('12.30')
        ->and($income->type)->toBe(TransactionType::Income)
        ->and($category->user_id)->toBe($user->id)
        ->and($category->id)->not->toBe($otherCategory->id);
});

it('shows transaction amounts with type direction', function () {
    $user = User::factory()->create();

    Transaction::create([
        'user_id' => $user->id,
        'title' => 'Lunch',
        'type' => TransactionType::Expense,
        'amount' => 12.30,
        'transaction_date' => today(),
    ]);
    Transaction::create([
        'user_id' => $user->id,
        'title' => 'Paycheck',
        'type' => TransactionType::Income,
        'amount' => 12.30,
        'transaction_date' => today(),
    ]);

    $this->actingAs($user);

    Livewire::test(ManageTransactions::class)
        ->assertSee('-12.30')
        ->assertSee('+12.30');
});

it('creates, scopes, and settles debts for the current user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherDebt = Debt::create([
        'user_id' => $otherUser->id,
        'other_person' => 'Someone else',
        'direction' => 'lent',
        'amount' => 50,
        'borrowed_date' => today(),
    ]);

    $this->actingAs($user);

    Livewire::test(ListDebts::class)
        ->callAction(CreateAction::class, [
            'other_person' => 'Auntie',
            'direction' => 'borrowed',
            'amount' => 25.50,
            'borrowed_date' => today(),
        ])
        ->assertHasNoFormErrors();

    $debt = Debt::where('user_id', $user->id)->sole();

    $debt->transactions()->create([
        'amount' => 5.50,
        'paid_date' => today(),
    ]);

    expect($debt->balance())->toBe('20.00');

    Livewire::test(ListDebts::class)
        ->assertSee('Pending');

    $payment = $debt->settle();

    expect(DebtResource::getEloquentQuery()->pluck('id')->all())->toBe([$debt->id])
        ->and(DebtResource::getRecordRouteBindingEloquentQuery()->find($otherDebt->id))->toBeNull()
        ->and($payment?->amount)->toBe('20.00')
        ->and($debt->fresh()->balance())->toBe('0.00')
        ->and($debt->settle())->toBeNull();

    Livewire::test(ListDebts::class)
        ->assertSee('Settled');
});
