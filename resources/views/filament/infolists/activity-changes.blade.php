@php
    $changes = $getState();
    $new = collect($changes['attributes'] ?? []);
    $old = collect($changes['old'] ?? []);
@endphp
<table class="w-full text-sm">
    <thead>
        <tr class="text-start text-gray-500 dark:text-gray-400">
            <th class="py-2 pe-4 text-start font-medium">Field</th>
            <th class="py-2 pe-4 text-start font-medium">Before</th>
            <th class="py-2 text-start font-medium">After</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
        @foreach ($new as $field => $value)
            <tr>
                <td class="py-2 pe-4 font-medium text-gray-950 dark:text-white">{{ \Illuminate\Support\Str::headline($field) }}</td>
                <td class="py-2 pe-4 text-gray-500 dark:text-gray-400">{{ is_scalar($old->get($field)) ? $old->get($field) : ($old->has($field) ? json_encode($old->get($field)) : '-') }}</td>
                <td class="py-2 text-gray-950 dark:text-white">{{ is_scalar($value) ? $value : json_encode($value) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
