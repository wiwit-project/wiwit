<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    /**
     * Set the page grid
     */
    public function getColumns(): int|array
    {
        return 12;
    }
}
