<?php

declare(strict_types=1);

namespace Tests;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests don't need built CSS and JS, so CI can skip npm.
        $this->withoutVite();

        // Livewire tests of Filament pages need to know which panel they're in.
        Filament::setCurrentPanel(Filament::getPanel('app'));

        // No test may reach the internet. The breached-password check answers "not found".
        Http::preventStrayRequests();
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
    }
}
