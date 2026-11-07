<?php

declare(strict_types=1);

use App\Models\LoginEvent;
use Illuminate\Support\Facades\Schedule;

// Deletes sign-in history older than SECURITY_LOGIN_HISTORY_DAYS.
Schedule::command('model:prune', ['--model' => [LoginEvent::class]])->daily();

// Deletes activity log entries older than ACTIVITYLOG_CLEAN_AFTER_DAYS (365 by default).
Schedule::command('activitylog:clean')->daily();

// Deletes API tokens that expired more than a day ago.
Schedule::command('sanctum:prune-expired', ['--hours' => 24])->daily();
