<?php

declare(strict_types=1);

// Rules every file in the app must follow. Generated modules are held to them too.

arch('the app uses strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debugging calls are left behind')
    ->preset()
    ->php();

arch('no insecure functions are used')
    ->preset()
    ->security();

arch('actions are invokable and final')
    ->expect('App\Actions')
    ->toBeFinal();

arch('enums are enums')
    ->expect('App\Enums')
    ->toBeEnums();

arch('API controllers only live under the versioned namespace')
    ->expect('App\Http\Controllers\Api')
    ->toHaveSuffix('Controller')
    ->ignoring('App\Http\Controllers\Api\V1\Concerns');
