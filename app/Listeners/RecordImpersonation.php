<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Database\Eloquent\Model;
use STS\FilamentImpersonate\Events\EnterImpersonation;
use STS\FilamentImpersonate\Events\LeaveImpersonation;

/**
 * Every impersonation goes in the audit log, with who did it to whom.
 */
final class RecordImpersonation
{
    public function handleEnter(EnterImpersonation $event): void
    {
        $this->log($event->impersonator, $event->impersonated, 'impersonation-started', 'Started impersonating');
    }

    public function handleLeave(LeaveImpersonation $event): void
    {
        $this->log($event->impersonator, $event->impersonated, 'impersonation-ended', 'Stopped impersonating');
    }

    private function log(mixed $impersonator, mixed $impersonated, string $event, string $description): void
    {
        $entry = activity('auth')->event($event);

        if ($impersonator instanceof Model) {
            $entry->causedBy($impersonator);
        }

        if ($impersonated instanceof Model) {
            $entry->performedOn($impersonated);
        }

        $entry->log($description);
    }
}
