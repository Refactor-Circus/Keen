<script setup lang="ts">
import type { TableColumn } from '@refactor-circus/atrium/ui'
import { changeRows, type KeenChangeRow } from './entries'

/**
 * An audit entry's field changes, as Keen's v1 `x-keen::audit.changes` showed
 * them: one row per field with its value before and after (PHP's
 * `var_export()` for scalars and null, JSON for the rest), or a line saying
 * nothing changed. Shared by the entry page and the Keen hooks.
 */
const props = defineProps<{ changes: Record<string, unknown> | null | undefined }>()

const { t } = useI18n()

const rows = computed(() => changeRows(props.changes))

const columns = computed<TableColumn<KeenChangeRow>[]>(() => [
  { accessorKey: 'field', header: t('keen.changes.field') },
  { accessorKey: 'before', header: t('keen.changes.before'), meta: { class: { td: 'break-all whitespace-normal' } } },
  { accessorKey: 'after', header: t('keen.changes.after'), meta: { class: { td: 'break-all whitespace-normal' } } }
])
</script>

<template>
  <p v-if="rows.length === 0" class="text-sm text-muted" data-testid="keen-no-changes">
    {{ t('keen.changes.none') }}
  </p>
  <UTable v-else :data="rows" :columns="columns" data-testid="keen-changes">
    <template #field-cell="{ row }">
      <code>{{ row.original.field }}</code>
    </template>
    <template #before-cell="{ row }">
      <span data-testid="keen-change-before">{{ row.original.before }}</span>
    </template>
    <template #after-cell="{ row }">
      <span data-testid="keen-change-after">{{ row.original.after }}</span>
    </template>
  </UTable>
</template>
