<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Knuckles\Scribe\Scribe;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        FilamentTimezone::set(config('app.timezone'));

        if (class_exists(Scribe::class)) {
            // Use the isolated SQLite database only while the Scribe command process is running.
            if (app()->runningInConsole() && in_array('scribe:generate', $_SERVER['argv'] ?? [], true)) {
                config([
                    'database.default' => 'sqlite',
                    'database.connections.sqlite.database' => database_path('scribe.sqlite'),
                    'scribe.database_connections_to_transact' => ['sqlite'],
                ]);
            }

            Scribe::beforeResponseCall(function (Request $request) {
                $abilities = ['view', 'create', 'update', 'delete'];

                // create temporary user and its data. This is used when scribe wants to generate docs
                // and response example. See:
                // https://scribe.knuckles.wtf/laravel/documenting/responses#response-calls
                $user = User::factory()->create();
                $token = $user->createToken('scribe_temporary', $abilities);
                $request->headers->add(['Authorization' => "Bearer {$token->plainTextToken}"]);

                $category = Category::create([
                    'user_id' => $user->id,
                    'name' => 'Food',
                    'description' => 'Everyday expenses',
                    'is_active' => true,
                ]);
                Transaction::factory()->expense()->for($user)->for($category)->create();
            });
        }
    }
}
