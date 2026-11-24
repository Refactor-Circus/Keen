<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import { KEEN_APP_SOURCE, type KeenAuditEntry } from './entries'

/**
 * Audit entries as Keen's v1 `x-keen::audit.entries` listed them: when (a
 * link to the entry), the action with an "App" badge for the application's
 * own, the record, who acted and through what. Shared by Keen's pages and
 * its hooks (`withoutSubject` hides the record column for one record's
 * history).
 */
const props = withDefaults(defineProps<{ entries: readonly KeenAuditEntry[], withoutSubject?: boolean }>(), { withoutSubject: false })

const { t, locale } = useI18n()

function when(entry: KeenAuditEntry): string {
  const date = new Date(entry.created_at)

  return Number.isNaN(date.getTime()) ? entry.created_at : date.toLocaleString(locale.value)
}

interface KeenEntryRow {
  entry: KeenAuditEntry
  when: string
  subject: string
  actor: string
}

const rows = computed<KeenEntryRow[]>(() => props.entries.map(entry => ({
  entry,
  when: when(entry),
  subject: entry.subject?.label ?? t('keen.entries.none'),
  actor: entry.actor?.label ?? t('keen.entries.system')
})))

const columns = computed<TableColumn<KeenEntryRow>[]>(() => [
  { id: 'when', header: t('keen.entries.when'), meta: { class: { td: 'whitespace-nowrap' } } },
  { id: 'action', header: t('keen.entries.action') },
  ...(props.withoutSubject ? [] : [{ accessorKey: 'subject', header: t('keen.entries.subject') } satisfies TableColumn<KeenEntryRow>]),
  { accessorKey: 'actor', header: t('keen.entries.actor') },
  { id: 'surface', header: t('keen.entries.surface') }
])
</script>

<template>
  <UEmpty v-if="rows.length === 0" icon="i-lucide-scroll-text" :title="t('keen.entries.empty')" size="xs" variant="naked" :ui="{ root: 'py-6' }" role="status" data-testid="keen-entries-empty" />
  <UTable v-else :data="rows" :columns="columns" data-testid="keen-entries">
    <template #when-cell="{ row }">
      <ULink :to="`/keen/entries/${row.original.entry.id}`" :title="row.original.entry.created_at" class="hover:underline">
        {{ row.original.when }}
      </ULink>
    </template>
    <template #action-cell="{ row }">
      <code>{{ row.original.entry.action }}</code>
      <UBadge v-if="row.original.entry.source === KEEN_APP_SOURCE" color="info" variant="subtle" size="sm" class="ms-2">
        {{ t('keen.entries.app_badge') }}
      </UBadge>
    </template>
    <template #surface-cell="{ row }">
      <UBadge color="neutral" variant="subtle" size="sm">{{ row.original.entry.surface }}</UBadge>
    </template>
  </UTable>
</template>
