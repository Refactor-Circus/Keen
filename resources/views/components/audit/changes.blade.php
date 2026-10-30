@props([
    // array<string, array{0: mixed, 1: mixed}>: field => [old, new]
    'changes' => [],
])

@php
    $show = fn (mixed $value): string => is_scalar($value) || $value === null ? var_export($value, true) : (string) json_encode($value, JSON_UNESCAPED_SLASHES);
@endphp

@if ($changes === [])
    <p {{ $attributes->class('text-sm') }}>{{ __('keen::keen.no_changes') }}</p>
@else
    <x-atrium::table {{ $attributes->merge(['data-testid' => 'audit-changes']) }}>
        <x-slot:head>
            <x-atrium::table.row>
                <x-atrium::table.cell heading>{{ __('keen::keen.field') }}</x-atrium::table.cell>
                <x-atrium::table.cell heading>{{ __('keen::keen.before') }}</x-atrium::table.cell>
                <x-atrium::table.cell heading>{{ __('keen::keen.after') }}</x-atrium::table.cell>
            </x-atrium::table.row>
        </x-slot:head>
        @foreach ($changes as $field => $change)
            <x-atrium::table.row>
                <x-atrium::table.cell><code>{{ $field }}</code></x-atrium::table.cell>
                <x-atrium::table.cell class="break-all">{{ $show($change[0] ?? null) }}</x-atrium::table.cell>
                <x-atrium::table.cell class="break-all">{{ $show($change[1] ?? null) }}</x-atrium::table.cell>
            </x-atrium::table.row>
        @endforeach
    </x-atrium::table>
@endif
