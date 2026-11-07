<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Everything happens in the Filament panel. Point this at a public page if your app needs one.
Route::redirect('/', '/app');
