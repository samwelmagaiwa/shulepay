<script setup>
/**
 * Manage expense categories.
 *
 * Category is a required field on every expense, but nothing in the app ever
 * created one — the backend has had full CRUD since the feature shipped and only
 * the GET was wired up. The result was a required dropdown that could never be
 * filled, so no expense could be recorded at all. This is that missing screen.
 */
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useExpensesStore } from '@/stores/expenses'

const props = defineProps({ visible: Boolean })
const emit = defineEmits(['update:visible', 'changed'])

const { t } = useI18n()
const store = useExpensesStore()

// The four values the backend accepts; anything else is rejected at validation.
const TYPES = ['operational', 'capital', 'staff', 'other']

const form = ref({ id: null, name: '', type: 'operational', description: '' })
const busy = ref(false)
const error = ref('')
const deleteTarget = ref(null)

function reset() {
  form.value = { id: null, name: '', type: 'operational', description: '' }
  error.value = ''
  deleteTarget.value = null
}

function edit(category) {
  form.value = {
    id: category.id,
    name: category.name || '',
    type: category.type || 'operational',
    description: category.description || '',
  }
  error.value = ''
}

async function save() {
  if (!form.value.name.trim()) {
    error.value = t('expenseCategories.nameRequired')
    return
  }
  busy.value = true
  error.value = ''
  try {
    const payload = {
      name: form.value.name.trim(),
      type: form.value.type,
      description: form.value.description?.trim() || null,
    }
    if (form.value.id) await store.updateCategory(form.value.id, payload)
    else await store.createCategory(payload)
    reset()
    emit('changed')
  } catch (e) {
    error.value = e?.response?.data?.message
      || Object.values(e?.response?.data?.errors || {})[0]?.[0]
      || t('expenseCategories.saveFailed')
  } finally {
    busy.value = false
  }
}

async function remove() {
  busy.value = true
  error.value = ''
  try {
    await store.deleteCategory(deleteTarget.value.id)
    deleteTarget.value = null
    emit('changed')
  } catch (e) {
    // A category already used by an expense cannot be removed; say so plainly
    // rather than leaving the row looking as though the click did nothing.
    error.value = e?.response?.data?.message || t('expenseCategories.deleteFailed')
    deleteTarget.value = null
  } finally {
    busy.value = false
  }
}

watch(() => props.visible, (open) => {
  if (open) { reset(); store.fetchCategories() }
})
</script>

<template>
  <CModal :visible="visible" size="lg" alignment="center" @close="emit('update:visible', false)">
    <CModalHeader>
      <CModalTitle>{{ t('expenseCategories.title') }}</CModalTitle>
    </CModalHeader>

    <CModalBody>
      <CAlert v-if="error" color="danger" class="py-2 small">{{ error }}</CAlert>

      <!-- Add / edit form -->
      <CRow class="g-2 align-items-end mb-3">
        <CCol xs="12" md="4">
          <label class="form-label small fw-semibold mb-1">
            {{ t('expenseCategories.name') }} <span class="text-danger">*</span>
          </label>
          <CFormInput v-model="form.name" size="sm"
                      :placeholder="t('expenseCategories.namePlaceholder')"
                      @keyup.enter="save" />
        </CCol>
        <CCol xs="12" md="3">
          <label class="form-label small fw-semibold mb-1">{{ t('expenseCategories.type') }}</label>
          <!-- The option carries its meaning: these are accounting buckets whose
               names do not explain themselves to whoever is filing an expense. -->
          <CFormSelect v-model="form.type" size="sm">
            <option v-for="ty in TYPES" :key="ty" :value="ty">
              {{ t('expenseCategories.types.' + ty) }} — {{ t('expenseCategories.typeHints.' + ty) }}
            </option>
          </CFormSelect>
          <div class="form-text" style="font-size:.72rem; line-height:1.3;">
            {{ t('expenseCategories.typeHints.' + form.type) }}
          </div>
        </CCol>
        <CCol xs="12" md="3">
          <label class="form-label small fw-semibold mb-1">{{ t('expenseCategories.description') }}</label>
          <CFormInput v-model="form.description" size="sm"
                      :placeholder="t('common.optional')" @keyup.enter="save" />
        </CCol>
        <CCol xs="12" md="2" class="d-flex gap-1">
          <CButton color="primary" size="sm" class="flex-fill" :disabled="busy" @click="save">
            <CSpinner v-if="busy" size="sm" class="me-1" />
            {{ form.id ? t('common.save') : t('common.add') }}
          </CButton>
          <CButton v-if="form.id" color="secondary" variant="outline" size="sm" :disabled="busy" @click="reset">
            {{ t('common.cancel') }}
          </CButton>
        </CCol>
      </CRow>

      <div v-if="!store.categories.length" class="text-center text-muted py-4 border rounded">
        {{ t('expenseCategories.none') }}
      </div>

      <CTable v-else small hover responsive class="mb-0" style="font-size:.85rem;">
        <CTableHead class="table-light">
          <CTableRow>
            <CTableHeaderCell>{{ t('expenseCategories.name') }}</CTableHeaderCell>
            <CTableHeaderCell>{{ t('expenseCategories.type') }}</CTableHeaderCell>
            <CTableHeaderCell>{{ t('expenseCategories.description') }}</CTableHeaderCell>
            <CTableHeaderCell class="text-end" style="width:1%">{{ t('common.actions') }}</CTableHeaderCell>
          </CTableRow>
        </CTableHead>
        <CTableBody>
          <CTableRow v-for="c in store.categories" :key="c.id">
            <CTableDataCell class="fw-semibold">{{ c.name }}</CTableDataCell>
            <CTableDataCell>
              <CBadge color="secondary" shape="rounded-pill">
                {{ t('expenseCategories.types.' + (c.type || 'other')) }}
              </CBadge>
            </CTableDataCell>
            <CTableDataCell class="text-muted">{{ c.description || '—' }}</CTableDataCell>
            <CTableDataCell class="text-end text-nowrap">
              <CButton size="sm" color="secondary" variant="ghost" @click="edit(c)">✏️</CButton>
              <CButton size="sm" color="danger" variant="ghost" @click="deleteTarget = c">🗑️</CButton>
            </CTableDataCell>
          </CTableRow>
        </CTableBody>
      </CTable>
    </CModalBody>

    <CModalFooter>
      <CButton color="secondary" variant="ghost" @click="emit('update:visible', false)">
        {{ t('common.close') }}
      </CButton>
    </CModalFooter>
  </CModal>

  <CModal :visible="!!deleteTarget" size="sm" alignment="center" @close="deleteTarget = null">
    <CModalHeader><CModalTitle>{{ t('expenseCategories.deleteTitle') }}</CModalTitle></CModalHeader>
    <CModalBody class="small">
      {{ t('expenseCategories.deleteBody', { name: deleteTarget?.name }) }}
    </CModalBody>
    <CModalFooter class="gap-2">
      <CButton color="secondary" @click="deleteTarget = null">{{ t('common.cancel') }}</CButton>
      <CButton color="danger" :disabled="busy" @click="remove">
        <CSpinner v-if="busy" size="sm" class="me-1" />{{ t('common.delete') }}
      </CButton>
    </CModalFooter>
  </CModal>
</template>
