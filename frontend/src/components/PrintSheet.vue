<template>
  <Teleport to="body">
    <!-- A full-page print view: the school's header, the title and only the data, as the table shows it. -->
    <div v-if="modelValue" class="print-root">
      <div class="print-toolbar no-print">
        <div class="fw-semibold">{{ t('printing.preview') }}</div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <div class="btn-group" role="group" :aria-label="t('printing.orientation')">
            <button type="button" class="btn btn-sm" :class="mode === 'portrait' ? 'btn-secondary' : 'btn-outline-secondary'" @click="mode = 'portrait'">▯ {{ t('printing.portrait') }}</button>
            <button type="button" class="btn btn-sm" :class="mode === 'landscape' ? 'btn-secondary' : 'btn-outline-secondary'" @click="mode = 'landscape'">▭ {{ t('printing.landscape') }}</button>
          </div>
          <button type="button" class="btn btn-primary" @click="print">🖨 {{ t('printing.print') }}</button>
          <button type="button" class="btn btn-outline-secondary" @click="$emit('update:modelValue', false)">{{ t('printing.close') }}</button>
        </div>
      </div>

      <component :is="'style'" v-if="mode === 'landscape'">@media print { @page { size: A4 landscape; margin: 10mm; } }</component>
      <component :is="'style'" v-else>@media print { @page { size: A4 portrait; margin: 10mm; } }</component>

      <div class="print-paper" :class="[`is-${mode}`, { 'is-compact': mode === 'portrait' && columns.length > 8 }]">
        <header class="print-header">
          <img v-if="branding.logoUrl" :src="branding.logoUrl" alt="" class="print-logo" />
          <div class="flex-grow-1">
            <div class="print-school">{{ branding.appName }}</div>
            <div v-if="branding.appTagline" class="print-tag">{{ branding.appTagline }}</div>
          </div>
          <div class="print-when">{{ t('printing.printedOn', { date: today }) }}</div>
        </header>
        <h1 class="print-title">{{ title }}</h1>
        <div v-for="(line, i) in lines" :key="i" class="print-line">{{ line }}</div>

        <table class="print-table">
          <thead>
            <tr>
              <th v-for="c in columns" :key="c.label" :class="`t-${c.align || 'start'}`">{{ c.label }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, i) in rows" :key="i">
              <td v-for="c in columns" :key="c.label" :class="[`t-${c.align || 'start'}`, c.bold ? 'fw-bold' : '']">{{ cell(c, row, i) }}</td>
            </tr>
            <tr v-if="!rows.length"><td :colspan="columns.length" class="t-center">{{ t('printing.noRows') }}</td></tr>
          </tbody>
          <tfoot v-if="footer.length">
            <tr v-for="(f, i) in footer" :key="i">
              <td v-for="(v, j) in f" :key="j" :class="j === 0 ? 't-start fw-bold' : `t-${columns[j]?.align || 'start'} fw-bold`">{{ v }}</td>
            </tr>
          </tfoot>
        </table>
        <div class="print-foot">{{ t('printing.rowsCount', { count: rows.length }) }}</div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBrandingStore } from '@/stores/branding'

// Shared by the exam and supplier pages. The caller passes what the table shows (columns + rows);
// nothing else of the page (cards, tabs, buttons, action menus) is printed.
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, required: true },
  /** Lines under the title: the filters, the class, the period... */
  lines: { type: Array, default: () => [] },
  /** [{ label, value: (row, index) => any, align?: 'start'|'center'|'end', bold? }] */
  columns: { type: Array, required: true },
  rows: { type: Array, default: () => [] },
  /** Totals rows: arrays of cells, one per column */
  footer: { type: Array, default: () => [] },
  landscape: { type: Boolean, default: false },
})
defineEmits(['update:modelValue'])

const { t } = useI18n()
const branding = useBrandingStore()
// Landscape suits wide tables and portrait suits lists; the user can switch, and a wide table in portrait is set smaller.
const mode = ref(props.landscape ? 'landscape' : 'portrait')
const today = computed(() => new Date().toLocaleString())

const cell = (c, row, i) => {
  const v = c.value(row, i)
  return v === null || v === undefined || v === '' ? '—' : v
}

function print() {
  window.print()
}

// While the sheet is open the page behind it must not scroll.
watch(() => props.modelValue, (open) => {
  document.body.classList.toggle('print-open', open)
  if (open) mode.value = props.landscape ? 'landscape' : 'portrait'
})
onBeforeUnmount(() => document.body.classList.remove('print-open'))
</script>
