<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Signed-in devices</x-slot>
        <x-slot name="description">Don't recognise one? Log out other devices, then change your password.</x-slot>
        <x-slot name="afterHeader">{{ $this->logoutOtherDevicesAction }}</x-slot>

        @php($sessions = $this->getSessions())
        @if ($sessions->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">This list needs the database session driver (SESSION_DRIVER=database).</p>
        @else
            <ul class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($sessions as $session)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                        <div>
                            <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $session['device'] }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $session['ip'] ?? 'Unknown address' }}, active {{ $session['last_active']->diffForHumans() }}</p>
                        </div>
                        @if ($session['current'])
                            <x-filament::badge color="success">This device</x-filament::badge>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Recent sign-ins</x-slot>
        <x-slot name="description">The last 20 attempts on your account, including failed ones.</x-slot>

        @php($events = $this->getLoginEvents())
        @if ($events->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No sign-ins recorded yet.</p>
        @else
            <ul class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($events as $event)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                        <div>
                            <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $this->deviceName($event->user_agent) }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $event->ip_address ?? 'Unknown address' }}, {{ $event->created_at->diffForHumans() }}</p>
                        </div>
                        <x-filament::badge :color="$event->type->color()">{{ $event->type->label() }}</x-filament::badge>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-panels::page>
