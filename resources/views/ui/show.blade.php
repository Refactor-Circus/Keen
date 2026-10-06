<x-atrium::layout :title="$entry->action">
    <x-atrium::page-header :title="$entry->action" :description="$entry->created_at->toDayDateTimeString()">
        <x-slot:actions>
            <x-atrium::icon-button icon="arrow-left" :label="__('keen::keen.audit_log')" variant="ghost" :href="route('atrium.keen.entries.index')" />
        </x-slot:actions>
    </x-atrium::page-header>

    <div class="mt-5 grid gap-4 lg:grid-cols-2">
        <x-atrium::card :title="__('keen::keen.details')">
            <x-atrium::description-list data-testid="audit-details">
                <x-atrium::description-list.item :term="__('keen::keen.source')">{{ $sources[$entry->source] ?? $entry->source }}</x-atrium::description-list.item>
                <x-atrium::description-list.item :term="__('keen::keen.subject')">
                    {{ $entry->subject_label ?? __('keen::keen.none') }}
                    @if ($entry->subject_type)
                        <span class="opacity-60">{{ $entry->subject_type }} {{ $entry->subject_id }}</span>
                    @endif
                </x-atrium::description-list.item>
                <x-atrium::description-list.item :term="__('keen::keen.actor')">{{ $entry->actor_label ?? __('keen::keen.system') }}</x-atrium::description-list.item>
                @if ($entry->scope_type)
                    <x-atrium::description-list.item :term="__('keen::keen.scope')">{{ $entry->scope_type }} {{ $entry->scope_id }}</x-atrium::description-list.item>
                @endif
                <x-atrium::description-list.item :term="__('keen::keen.surface')">{{ $entry->surface }}</x-atrium::description-list.item>
                @if ($personal)
                    <x-atrium::description-list.item :term="__('keen::keen.ip')">{{ $entry->ip ?? __('keen::keen.none') }}</x-atrium::description-list.item>
                    <x-atrium::description-list.item :term="__('keen::keen.user_agent')" class="break-all">{{ $entry->user_agent ?? __('keen::keen.none') }}</x-atrium::description-list.item>
                @endif
                <x-atrium::description-list.item :term="__('keen::keen.hash')" class="break-all font-mono text-xs">{{ $entry->hash }}</x-atrium::description-list.item>
            </x-atrium::description-list>
        </x-atrium::card>

        <x-atrium::card :title="__('keen::keen.changes')">
            <x-atrium::audit.changes :changes="$entry->changes ?? []" />

            @if (! empty($entry->context))
                <pre class="mt-4 overflow-x-auto text-xs" data-testid="audit-context">{{ json_encode($entry->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            @endif
        </x-atrium::card>
    </div>
</x-atrium::layout>
