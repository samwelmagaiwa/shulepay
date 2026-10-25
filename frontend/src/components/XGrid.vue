<template>
  <div class="xg-wrap">
    <div v-if="loading" class="text-center py-5"><CSpinner color="primary" /></div>
    <div v-else class="xg-scroll">
      <div class="xg-grid" :style="{ minWidth: minWidth + 'px' }">
        <div class="xg-head" :style="{ gridTemplateColumns: template }">
          <div v-for="c in columns" :key="c.key" class="xg-cell" :class="alignClass(c)">{{ c.label }}</div>
          <div v-if="hasMenu" class="xg-cell xg-center">{{ t('common.actions') }}</div>
        </div>

        <div
          v-for="(row, idx) in rows" :key="row[rowKey]"
          class="xg-row"
          :style="{ gridTemplateColumns: template }"
          :class="[rowClass ? rowClass(row) : '', { 'xg-row--selected': selectedId === row[rowKey], 'xg-row--alt': idx % 2 === 1 }]"
          @click="$emit('update:selectedId', row[rowKey])"
          @dblclick="$emit('open', row)"
          @contextmenu.prevent="hasMenu && openMenu(row, $event)"
        >
          <div v-for="c in columns" :key="c.key" class="xg-cell" :class="[alignClass(c), c.cls]">
            <slot :name="`cell-${c.key}`" :row="row" :index="idx">{{ plain(row, c) }}</slot>
          </div>
          <div v-if="hasMenu" class="xg-cell xg-actions-cell">
            <button class="xg-dots-btn" @click.stop="openMenu(row, $event)">⋮</button>
          </div>
        </div>

        <div v-if="!rows.length" class="xg-empty">{{ empty || t('expensex.none') }}</div>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="pop.show" class="xg-ctx-menu" :style="{ top: pop.y + 'px', left: pop.x + 'px' }" @click.stop>
        <button v-for="(it, i) in pop.items" :key="i" class="xg-ctx-item" :class="{ 'is-danger': it.danger }" @click="run(it)">{{ it.label }}</button>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'

// The data grid of a list page (header, zebra rows, selection, double-click, right-click / ⋮ menu).
// columns: [{ key, label, w: '1fr', align?: 'end'|'center', cls?, value?: row => text }]; fill a cell with the
// slot `cell-<key>`. `menu(row)` returns the ⋮ / right-click items [{ label, run(row), danger? }] (empty = no menu).
const props = defineProps({
  columns: { type: Array, required: true },
  rows: { type: Array, default: () => [] },
  rowKey: { type: String, default: 'id' },
  selectedId: { type: [Number, String], default: null },
  loading: { type: Boolean, default: false },
  empty: { type: String, default: '' },
  rowClass: { type: Function, default: null },
  menu: { type: Function, default: null },
  minWidth: { type: Number, default: 900 },
})
defineEmits(['update:selectedId', 'open'])

const { t } = useI18n()
const hasMenu = computed(() => !!props.menu)
const template = computed(() => props.columns.map(c => c.w || 'minmax(90px, 1fr)').concat(hasMenu.value ? ['84px'] : []).join(' '))
const alignClass = (c) => (c.align === 'end' ? 'xg-num' : c.align === 'center' ? 'xg-center' : '')
const plain = (row, c) => {
  const v = c.value ? c.value(row) : row[c.key]
  return v === null || v === undefined || v === '' ? '—' : v
}

const pop = ref({ show: false, x: 0, y: 0, items: [] })
function openMenu(row, ev) {
  const items = (props.menu(row) || []).map(it => ({ ...it, row }))
  if (!items.length) return
  const w = 200
  const h = 12 + 34 * items.length
  pop.value = {
    show: true,
    x: Math.max(8, Math.min(ev.clientX || 0, window.innerWidth - w - 8)),
    y: Math.max(8, Math.min(ev.clientY || 0, window.innerHeight - h - 8)),
    items,
  }
}
function run(it) { pop.value.show = false; it.run(it.row) }
const close = () => { pop.value.show = false }
onMounted(() => { document.addEventListener('click', close); window.addEventListener('scroll', close, true) })
onBeforeUnmount(() => { document.removeEventListener('click', close); window.removeEventListener('scroll', close, true) })
</script>
