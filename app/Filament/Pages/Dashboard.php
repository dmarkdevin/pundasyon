<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Shows every widget in app/Filament/Widgets the signed-in person may see.
 * A module adds to the dashboard by adding a widget there with its own canView().
 */
final class Dashboard extends BaseDashboard
{
    /**
     * @return array<string, ?int>
     */
    public function getColumns(): array
    {
        return ['md' => 2, 'xl' => 2];
    }
}
