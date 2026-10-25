<template>
  <div class="xg-sticky">
    <div class="xg-topbar">
      <div class="xg-topbar-left">
        <span class="xg-count">{{ t('common.showing', { from: showFrom, to: showTo, total: m.total }) }}</span>
        <select v-if="!noPerPage" :value="perPage" class="xg-perpage" @change="$emit('update:perPage', $event.target.value)">
          <option v-for="n in [10, 20, 50, 100]" :key="n" :value="String(n)">{{ n }}</option>
        </select>
        <span v-if="!noPerPage" class="xg-count">{{ t('common.perPage') }}</span>
        <button v-if="$slots.filters" class="xg-filter-btn" :class="{ active: open || filtersActive }" @click="open = !open">
          {{ t('expensex.filter') }}<span v-if="filtersActive" class="xg-filter-dot"></span>
        </button>
      </div>
      <div class="xg-topbar-right">
        <slot name="actions" />
        <div v-if="m.last_page > 1" class="xg-pager">
          <button class="xg-page-btn" :disabled="m.current_page <= 1" @click="$emit('page', m.current_page - 1)">{{ t('common.prev') }}</button>
          <button v-for="p in pages" :key="p" class="xg-page-btn" :class="{ active: p === m.current_page }" @click="$emit('page', p)">{{ p }}</button>
          <button class="xg-page-btn" :disabled="m.current_page >= m.last_page" @click="$emit('page', m.current_page + 1)">{{ t('common.next') }}</button>
        </div>
      </div>
    </div>

    <div v-if="$slots.filters && open" class="xg-filters"><slot name="filters" /></div>

    <div class="xg-toolbar">
      <div class="xg-toolbar-actions"><slot name="toolbar" /></div>
      <div v-if="$slots.search" class="xg-toolbar-search"><slot name="search" /></div>
      <span v-else class="xg-toolbar-hint">{{ t('expensex.hint') }}</span>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'

// The sticky top of a list page: "showing x-y of n", rows per page, the filter panel, pager and the
// toolbar of the selected row. Pagination is the Laravel `meta` of the list (or {} for an unpaged list).
const props = defineProps({
  meta: { type: Object, default: () => ({}) },
  perPage: { type: [String, Number], default: '20' },
  noPerPage: { type: Boolean, default: false },
  filtersActive: { type: Boolean, default: false },
  total: { type: Number, default: null },
})
defineEmits(['update:perPage', 'page'])

const { t } = useI18n()
const open = ref(false)
const m = computed(() => {
  const base = { total: 0, last_page: 1, per_page: Number(props.perPage) || 20, current_page: 1, ...(props.meta || {}) }
  if (props.total !== null) base.total = props.total
  return base
})
const showFrom = computed(() => (m.value.total ? (m.value.current_page - 1) * m.value.per_page + 1 : 0))
const showTo = computed(() => (props.meta?.last_page ? Math.min(m.value.current_page * m.value.per_page, m.value.total) : m.value.total))
const pages = computed(() => {
  const start = Math.max(1, m.value.current_page - 2)
  const end = Math.min(m.value.last_page, m.value.current_page + 2)
  return Array.from({ length: end - start + 1 }, (_, i) => start + i)
})
</script>
