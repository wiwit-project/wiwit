<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\QueryException;

/**
 * The Registration control class.
 */
class Registration
{
    public static function enabled(): bool
    {
        if (config('app.enable_registration')) {
            return true;
        }

        // Database may not exist yet (e.g. during `artisan migrate`), so ignore
        // query exception and let the command finish
        try {
            return User::count() === 0;
        } catch (QueryException) {
            return false;
        }
    }
}
