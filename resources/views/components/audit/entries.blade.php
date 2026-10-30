@props([
    // list<\RefactorCircus\Keystone\Audit\Data\AuditEntry>
    'entries' => [],
    // Hide the subject column, for a single record's history.
    'withoutSubject' => false,
])

@php
    // Keen's dashboard section shows an entry in full; link to it while it
    // is served.
    $link = \Illuminate\Support\Facades\Route::has('atrium.keen.entries.show');
@endphp

@if ($entries === [] || (is_countable($entries) && count($entries) === 0))
    <x-atrium::empty-state :title="__('keen::keen.empty')" data-testid="audit-empty" />
@else
    <x-atrium::table striped {{ $attributes->merge(['data-testid' => 'audit-entries']) }}>
        <x-slot:head>
            <x-atrium::table.row>
                <x-atrium::table.cell heading>{{ __('keen::keen.when') }}</x-atrium::table.cell>
                <x-atrium::table.cell heading>{{ __('keen::keen.action') }}</x-atrium::table.cell>
                @unless ($withoutSubject)
                    <x-atrium::table.cell heading>{{ __('keen::keen.subject') }}</x-atrium::table.cell>
                @endunless
                <x-atrium::table.cell heading>{{ __('keen::keen.actor') }}</x-atrium::table.cell>
                <x-atrium::table.cell heading>{{ __('keen::keen.surface') }}</x-atrium::table.cell>
            </x-atrium::table.row>
        </x-slot:head>

        @foreach ($entries as $entry)
            <x-atrium::table.row>
                <x-atrium::table.cell>
                    @if ($link)
                        <a class="underline-offset-2 hover:underline" href="{{ route('atrium.keen.entries.show', $entry->id) }}" title="{{ $entry->createdAt->toIso8601String() }}">{{ $entry->createdAt->diffForHumans() }}</a>
                    @else
                        <span title="{{ $entry->createdAt->toIso8601String() }}">{{ $entry->createdAt->diffForHumans() }}</span>
                    @endif
                </x-atrium::table.cell>
                <x-atrium::table.cell>
                    <code>{{ $entry->action }}</code>
                    @if ($entry->source === 'app')
                        <x-atrium::badge variant="info">{{ __('keen::keen.app_badge') }}</x-atrium::badge>
                    @endif
                </x-atrium::table.cell>
                @unless ($withoutSubject)
                    <x-atrium::table.cell>{{ $entry->subjectLabel ?? __('keen::keen.none') }}</x-atrium::table.cell>
                @endunless
                <x-atrium::table.cell>{{ $entry->actorLabel ?? __('keen::keen.system') }}</x-atrium::table.cell>
                <x-atrium::table.cell><x-atrium::badge>{{ $entry->surface }}</x-atrium::badge></x-atrium::table.cell>
            </x-atrium::table.row>
        @endforeach
    </x-atrium::table>
@endif
