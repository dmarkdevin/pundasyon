<x-filament-panels::page>
    @if ($newToken)
        <x-filament::section icon="heroicon-o-key" icon-color="success">
            <x-slot name="heading">Copy your new token now</x-slot>
            <x-slot name="description">It won't be shown again. Send it as <code>Authorization: Bearer &lt;token&gt;</code>.</x-slot>
            <div class="flex flex-wrap items-center gap-3">
                <code class="break-all rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-950 dark:bg-white/5 dark:text-white" x-ref="token">{{ $newToken }}</code>
                <x-filament::button size="sm" color="gray" x-on:click="navigator.clipboard.writeText($refs.token.innerText); $tooltip('Copied')">Copy</x-filament::button>
                <x-filament::button size="sm" color="gray" wire:click="forgetNewToken">I've saved it</x-filament::button>
            </div>
        </x-filament::section>
    @endif

    <x-filament::section>
        <x-slot name="heading">Your tokens</x-slot>
        <x-slot name="description">
            Tokens let other programs use the API as you.
            {{-- The docs are open to everyone in local development, and to admins elsewhere. --}}
            @if (app()->environment('local') || Gate::allows('viewApiDocs'))
                API docs: <a class="text-primary-600 underline dark:text-primary-400" href="{{ url('/docs/api') }}">/docs/api</a>.
            @endif
        </x-slot>
        <x-slot name="afterHeader">{{ $this->createTokenAction }}</x-slot>

        @php($tokens = $this->getTokens())
        @if ($tokens->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No tokens yet.</p>
        @else
            <ul class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($tokens as $token)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $token->name }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ count($token->abilities) }} {{ \Illuminate\Support\Str::plural('permission', count($token->abilities)) }},
                                {{ $token->last_used_at ? 'last used '.$token->last_used_at->diffForHumans() : 'never used' }},
                                {{ $token->expires_at ? ($token->expires_at->isPast() ? 'expired '.$token->expires_at->diffForHumans() : 'expires '.$token->expires_at->diffForHumans()) : 'never expires' }}
                            </p>
                        </div>
                        {{ ($this->revokeTokenAction)(['token' => $token->getKey()]) }}
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-panels::page>
