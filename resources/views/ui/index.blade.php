{{-- The `keen.entries` screen: every package's entries. Header actions always
     render; an open registered tab's panel replaces the log, and `before`,
     `after` and each entry row's `row.actions` render only around it. A row
     is the entry's AuditEntryModel, so row sections receive it as `$record`. --}}
<x-atrium::layout :title="__('keen::keen.audit_log')">
    <x-atrium::page-header :title="__('keen::keen.audit_log')">
        @if ($resolved->sections('header.actions') !== [])
            <x-slot:actions>
                <x-atrium::screen-sections :screen="$resolved" slot="header.actions" />
            </x-slot:actions>
        @endif
    </x-atrium::page-header>

    <x-atrium::screen-tabs :screen="$resolved" class="mt-4" />

    @if ($resolved->openRegisteredTab !== null)
        <x-atrium::screen-tab-panel :screen="$resolved" class="mt-5" />
    @else
        <x-atrium::screen-sections :screen="$resolved" slot="before" class="mt-5" />

        <div class="mt-5 flex flex-col gap-4">
            <x-atrium::flash />

            <x-atrium::card>
                <form method="GET" action="{{ route('atrium.keen.entries.index') }}" class="flex flex-wrap items-start gap-3" data-testid="audit-filters">
                    <x-atrium::form.select
                        name="source"
                        :label="__('keen::keen.source')"
                        :placeholder="__('keen::keen.all_sources')"
                        :options="$sources"
                        :selected="$filters['source'] ?? null"
                        wrapper="w-44" />
                    <x-atrium::form.input name="action" :label="__('keen::keen.action')" :value="$filters['action'] ?? null" :hint="__('keen::keen.action_hint')" wrapper="w-56" />
                    <x-atrium::form.input name="subject_type" :label="__('keen::keen.subject_type')" :value="$filters['subject_type'] ?? null" wrapper="w-48" />
                    <x-atrium::form.input name="subject_id" :label="__('keen::keen.subject_id')" :value="$filters['subject_id'] ?? null" wrapper="w-40" />
                    <x-atrium::form.actions>
                        <x-atrium::icon-button icon="funnel" :label="__('keen::keen.filter')" variant="primary" type="submit" data-testid="filter-audit" />
                        <x-atrium::icon-button icon="x-mark" :label="__('keen::keen.clear')" variant="ghost" :href="route('atrium.keen.entries.index')" />
                    </x-atrium::form.actions>
                </form>
            </x-atrium::card>

            <x-keen::audit.entries
                :entries="collect($entries->items())->map->toAuditEntry()->all()"
                :screen="$resolved"
                :rows="$rows" />

            <x-atrium::pagination :paginator="$entries" />

            @if ($mayRecord)
                <x-atrium::card :title="__('keen::keen.record_note')" data-testid="record-note-card">
                    <form method="POST" action="{{ route('atrium.keen.entries.store') }}" class="flex flex-wrap items-start gap-3">
                        @csrf
                        <x-atrium::form.input name="action" :label="__('keen::keen.action')" :hint="__('keen::keen.record_hint')" wrapper="w-56" required />
                        <x-atrium::form.input name="subject_label" :label="__('keen::keen.subject')" wrapper="w-56" />
                        <x-atrium::form.input name="context[note]" id="audit-note" :label="__('keen::keen.note')" wrapper="w-80" />
                        <x-atrium::form.actions>
                            <x-atrium::icon-button icon="pencil-square" :label="__('keen::keen.record')" variant="primary" type="submit" data-testid="record-audit" />
                        </x-atrium::form.actions>
                    </form>
                </x-atrium::card>
            @endif
        </div>

        <x-atrium::screen-sections :screen="$resolved" slot="after" class="mt-5" />
    @endif
</x-atrium::layout>
