<script setup lang="ts">
import { integrationData, useAtriumIntegration, type AtriumDetailsItem } from '@refactor-circus/atrium'
import KeenChanges from '../../components/KeenChanges.vue'
import { KEEN_APP_SOURCE, type KeenAuditEntry } from '../../components/entries'

/**
 * The `keen.entry` screen: one audit entry in full (`keen.entries.show`),
 * with its changes as Keen's v1 `audit.changes` showed them. The IP address
 * and user agent show only when Keen sends them, to those who may read the
 * whole log.
 */
const route = useRoute()
const { t, locale } = useI18n()
const { client } = useAtriumIntegration('keen')

const id = computed(() => String(route.params.entry))
const entry = shallowRef<KeenAuditEntry | null>(null)
const error = shallowRef<unknown>(null)
const pending = ref(true)

const status = computed(() => atriumStatus(error.value))

async function load(): Promise<void> {
  if (!client) {
    return
  }

  pending.value = true
  error.value = null

  try {
    entry.value = integrationData(await client.GET('/entries/{entry}', { params: { path: { entry: id.value } } })).data
  } catch (e) {
    entry.value = null
    error.value = e
  } finally {
    pending.value = false
  }
}

watch(id, () => void load(), { immediate: true })

const title = computed(() => entry.value?.action ?? t('keen.entry.title'))
const createdAt = computed(() => entry.value ? new Date(entry.value.created_at).toLocaleString(locale.value) : null)
const personal = computed(() => entry.value !== null && ('ip' in entry.value || 'user_agent' in entry.value))
const context = computed(() => entry.value && Object.keys(entry.value.context ?? {}).length > 0 ? JSON.stringify(entry.value.context, null, 2) : null)

/** The entry's facts for <AtriumDetails>; each value keeps its `keen-entry-{key}` test id. */
const details = computed<AtriumDetailsItem[]>(() => {
  const value = entry.value

  if (!value) {
    return []
  }

  const items: AtriumDetailsItem[] = [
    { key: 'source', label: t('keen.entry.source'), value: value.source === KEEN_APP_SOURCE ? t('keen.filters.source_app') : value.source },
    { key: 'subject', label: t('keen.entry.subject'), value: [value.subject?.label ?? t('keen.entries.none'), value.subject?.type, value.subject?.id].filter(Boolean).join(' ') },
    { key: 'actor', label: t('keen.entry.actor'), value: value.actor?.label ?? t('keen.entries.system') }
  ]

  if (value.scope) {
    items.push({ key: 'scope', label: t('keen.entry.scope'), value: [value.scope.type, value.scope.id].filter(Boolean).join(' ') })
  }

  items.push({ key: 'surface', label: t('keen.entry.surface'), value: value.surface })

  if (personal.value) {
    items.push(
      { key: 'ip', label: t('keen.entry.ip'), value: value.ip ?? t('keen.entries.none'), mono: true },
      { key: 'user_agent', label: t('keen.entry.user_agent'), value: value.user_agent ?? t('keen.entries.none') }
    )
  }

  items.push({ key: 'hash', label: t('keen.entry.hash'), value: value.hash, mono: true })

  return items.map(item => ({ ...item, testid: `keen-entry-${item.key}` }))
})

useHead({ title: () => title.value })
</script>

<template>
  <UDashboardPanel>
    <template #header>
      <UDashboardNavbar :title="title">
        <template #leading>
          <UButton to="/keen/entries" icon="i-lucide-arrow-left" color="neutral" variant="ghost" :aria-label="t('keen.entry.back')" />
        </template>
      </UDashboardNavbar>
    </template>
    <template #body>
      <AtriumIntegrationScreen screen-key="keen.entry" :record="id">
        <AtriumNoAccess v-if="status === 403" />
        <UAlert
          v-else-if="status === 404"
          color="neutral"
          variant="subtle"
          icon="i-lucide-search-x"
          :title="t('keen.entry.not_found')"
          :description="t('keen.entry.not_found_description')"
          data-testid="keen-entry-not-found"
        />
        <UAlert v-else-if="error" color="error" :title="t('keen.entry.load_failed')" data-testid="keen-entry-failed" />
        <USkeleton v-else-if="pending && !entry" class="h-40" />
        <div v-else-if="entry" class="grid gap-4 lg:grid-cols-2" data-testid="keen-entry">
          <UCard>
            <template #header>
              <div class="flex min-w-0 flex-1 flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <h2 class="font-medium" data-testid="keen-entry-details-title">{{ t('keen.entry.details') }}</h2>
                <p v-if="createdAt" class="text-sm text-muted tabular-nums" data-testid="keen-entry-created-at">{{ createdAt }}</p>
              </div>
            </template>
            <AtriumDetails :items="details" data-testid="keen-entry-details" />
          </UCard>

          <UCard>
            <template #header>
              <h2 class="font-medium">{{ t('keen.entry.changes') }}</h2>
            </template>
            <KeenChanges :changes="entry.changes" />
            <pre v-if="context" class="mt-4 overflow-x-auto text-xs" data-testid="keen-entry-context">{{ context }}</pre>
          </UCard>
        </div>
      </AtriumIntegrationScreen>
    </template>
  </UDashboardPanel>
</template>
