<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

uses(TestCase::class);

// Models without a screen of their own, which nobody reaches directly.
const MODELS_WITHOUT_POLICIES = [
    App\Models\LoginEvent::class,
    App\Models\Permission::class,
];

it('has a policy for every model', function (): void {
    $models = collect(glob(app_path('Models/*.php')) ?: [])
        ->map(fn (string $path): string => 'App\\Models\\'.basename($path, '.php'))
        ->reject(fn (string $class): bool => in_array($class, MODELS_WITHOUT_POLICIES, true));

    foreach ($models as $model) {
        expect(Gate::getPolicyFor($model))->not->toBeNull("{$model} has no policy");
    }
});
