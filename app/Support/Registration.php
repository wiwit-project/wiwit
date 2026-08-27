<?php

namespace App\Support;

use App\Models\User;

/**
 * Controls whether self-registration is currently available.
 */
class Registration
{
    public static function enabled(): bool
    {
        return config('app.enable_registration') || User::count() === 0;
    }
}
