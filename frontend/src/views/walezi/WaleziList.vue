<template>
  <CContainer fluid class="xg-page">
    <XStats :cards="cards" />

    <XBar :meta="meta" v-model:per-page="perPage" :filters-active="!!search" @page="goPage" @update:per-page="goPage(1)">
      <template #actions>
        <CButton color="success" size="sm" @click="openAdd"><CIcon icon="cilPlus" class="me-1" /> {{ t('guardians.add') }}</CButton>
      </template>
      <template #toolbar>
        <button class="xg-tb-btn" :disabled="!sel" @click="sel && openView(sel)">🔍 {{ t('common.view') }}</button>
        <button class="xg-tb-btn" :disabled="!sel" @click="sel && openEdit(sel)">✏️ {{ t('common.edit') }}</button>
        <button class="xg-tb-btn is-danger" :disabled="!sel" @click="sel && remove(sel)">🗑️ {{ t('common.delete') }}</button>
      </template>
      <template #search>
        <CFormInput v-model="search" :placeholder="t('common.search') + '...'" @input="debouncedLoad" />
        <CButton color="secondary" variant="outline" size="sm" @click="search = ''; goPage(1)">{{ t('common.reset') }}</CButton>
      </template>
    </XBar>

    <XGrid v-model:selected-id="selId" :columns="columns" :rows="store.guardians" :loading="store.loading" :empty="t('guardians.noGuardians')"
           :min-width="980" :menu="menuFor" @open="openView">
      <template #cell-name="{ row }"><span class="xg-name">{{ row.full_name || row.user?.name || '—' }}</span></template>
      <template #cell-email="{ row }"><span class="xg-sub" style="font-size:12px">{{ row.login_email || row.email || row.user?.email || '—' }}</span></template>
      <template #cell-phone="{ row }">{{ row.phone || row.user?.phone || '—' }}</template>
      <template #cell-role><span class="xg-pill xg-pill--gray">👨‍👩‍👧 {{ t('guardians.parentRole') }}</span></template>
      <template #cell-children="{ row }">
        <template v-if="!row.students?.length">—</template>
        <template v-else>
          <span class="xg-pill xg-pill--blue" :title="row.students.map(s => s.full_name).join(', ')">{{ row.students[0].full_name }}</span>
          <span v-if="row.students.length > 1" class="xg-pill xg-pill--gray ms-1" :title="row.students.map(s => s.full_name).join(', ')">+{{ row.students.length - 1 }}</span>
        </template>
      </template>
    </XGrid>

    <!-- View Guardian Modal -->
    <CModal :visible="showViewModal" @close="showViewModal = false" size="lg" class="modal-fullscreen-sm-down">
      <CModalHeader><CModalTitle>👨‍👩‍👧 {{ t('guardians.viewTitle') }}</CModalTitle></CModalHeader>
      <CModalBody v-if="viewTarget" class="p-3">
        <div class="d-flex align-items-center gap-3 mb-4 p-3 bg-light rounded">
          <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center fw-bold"
               style="width:56px;height:56px;font-size:1.4rem;flex-shrink:0;">
            {{ (viewTarget.full_name || viewTarget.user?.name || '?').charAt(0).toUpperCase() }}
          </div>
          <div>
            <div class="fw-bold fs-5">{{ viewTarget.full_name || viewTarget.user?.name || '—' }}</div>
            <div class="text-muted small">{{ viewTarget.relation || '—' }}</div>
          </div>
        </div>
        <CRow class="g-3">
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('guardians.phone') }}</div>
            <div>{{ viewTarget.phone || viewTarget.user?.phone || '—' }}</div>
          </CCol>
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('guardians.relation') }}</div>
            <div>{{ viewTarget.relation || '—' }}</div>
          </CCol>
          <CCol xs="12">
            <div class="text-muted small fw-semibold mb-2">{{ t('guardians.children') }}</div>
            <div v-if="viewTarget.students?.length" class="d-flex flex-wrap gap-2">
              <CBadge v-for="s in viewTarget.students" :key="s.id" color="info" shape="rounded-pill" class="px-3 py-2">
                {{ s.full_name }} <span class="ms-1 opacity-75">{{ s.school_class?.name }}</span>
              </CBadge>
            </div>
            <div v-else class="text-muted">—</div>
          </CCol>

          <!-- Login credentials -->
          <CCol xs="12">
            <div class="border rounded-3 p-3 bg-light">
              <div class="fw-semibold mb-2">🔑 {{ t('guardians.loginCredentials') }}</div>
              <div class="d-flex flex-wrap gap-3">
                <div>
                  <div class="text-muted small fw-semibold mb-1">{{ t('common.email') }} ({{ t('guardians.username') }})</div>
                  <div class="font-monospace small">{{ viewTarget.login_email || viewTarget.user?.email || '—' }}</div>
                </div>
                <div>
                  <div class="text-muted small fw-semibold mb-1">{{ t('staff.role') }}</div>
                  <CBadge color="secondary" class="px-2 py-1">👨‍👩‍👧 {{ t('guardians.parentRole') }}</CBadge>
                </div>
                <div>
                  <div class="text-muted small fw-semibold mb-1">{{ t('staff.password') }}</div>
                  <div class="font-monospace small">
                    <span v-if="viewTarget.user?.must_change_password" class="text-warning">SCHOOL <span class="text-muted">({{ t('guardians.mustChange') }})</span></span>
                    <span v-else class="text-success">{{ t('guardians.passwordSet') }}</span>
                  </div>
                </div>
              </div>
            </div>
          </CCol>
        </CRow>
      </CModalBody>
      <CModalFooter class="gap-2">
        <CButton color="secondary" @click="showViewModal = false" style="min-height:44px;">{{ t('common.close') }}</CButton>
        <CButton color="primary" @click="openEdit(viewTarget); showViewModal = false" style="min-height:44px;">✏️ {{ t('common.edit') }}</CButton>
      </CModalFooter>
    </CModal>

    <!-- Add/Edit Modal -->
    <CModal :visible="showModal" @close="closeModal" size="lg">
      <CModalHeader>
        <CModalTitle>{{ editing ? t('common.edit') : t('guardians.add') }}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        <CRow class="g-3">
          <CCol sm="6">
            <label class="form-label">{{ t('common.name') }} *</label>
            <CFormInput v-model="form.name" />
          </CCol>
          <CCol sm="6">
            <label class="form-label">{{ t('guardians.phone') }}</label>
            <CFormInput v-model="form.phone" type="tel" />
          </CCol>
          <CCol sm="6">
            <label class="form-label">{{ t('common.email') }}</label>
            <CFormInput v-model="form.email" type="email" />
          </CCol>
          <CCol sm="6">
            <label class="form-label">{{ t('guardians.relation') }}</label>
            <CFormSelect v-model="form.relation">
              <option value="">{{ t('common.select') }}</option>
              <option value="baba">{{ t('guardians.father') }}</option>
              <option value="mama">{{ t('guardians.mother') }}</option>
              <option value="mlezi">{{ t('guardians.guardian') }}</option>
              <option value="ndugu">{{ t('guardians.relative') }}</option>
            </CFormSelect>
          </CCol>
          <CCol sm="12">
            <label class="form-label">{{ t('guardians.children') }} *</label>
            <div class="border rounded p-2" style="max-height:180px;overflow-y:auto">
              <div v-if="!students.length" class="text-muted small">{{ t('common.loading') }}</div>
              <CFormCheck
                v-for="s in students" :key="s.id"
                :value="s.id"
                :label="`${s.full_name} (${s.admission_number || '—'})`"
                v-model="form.student_ids"
              />
            </div>
          </CCol>
        </CRow>
        <CAlert v-if="modalError" color="danger" class="mt-3">{{ modalError }}</CAlert>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" @click="closeModal">{{ t('common.cancel') }}</CButton>
        <CButton color="primary" :disabled="saving" @click="save">
          <CSpinner v-if="saving" size="sm" class="me-1" />{{ t('common.save') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Delete Confirm Modal -->
    <CModal :visible="showDeleteModal" @close="showDeleteModal = false" size="sm" class="modal-fullscreen-sm-down">
      <CModalHeader><CModalTitle>🗑️ {{ t('common.delete') }}</CModalTitle></CModalHeader>
      <CModalBody>
        <p class="mb-1 text-muted small">{{ t('common.confirmDelete') || t('ui.muundoWaAda.areYouSureYouWant') }}</p>
        <p class="fw-bold mb-0">{{ deleteTarget?.full_name }}</p>
      </CModalBody>
      <CModalFooter class="gap-2">
        <CButton color="secondary" @click="showDeleteModal = false" style="min-height:44px;">{{ t('common.cancel') }}</CButton>
        <CButton color="danger" :disabled="deleting" @click="doDelete" style="min-height:44px;">
          <CSpinner v-if="deleting" size="sm" class="me-1" />{{ t('common.delete') }}
        </CButton>
      </CModalFooter>
    </CModal>
  </CContainer>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useGuardiansStore } from '@/stores/guardians'
import { useStudentsStore }  from '@/stores/students'
import XStats from '@/components/XStats.vue'
import XBar from '@/components/XBar.vue'
import XGrid from '@/components/XGrid.vue'

const { t } = useI18n()
const store        = useGuardiansStore()
const studentsStore = useStudentsStore()

const search         = ref('')
const perPage = ref('20')
const selId = ref(null)
const sel = computed(() => store.guardians.find(g => g.id === selId.value) || null)
const cards = computed(() => [{ label: t('guardians.title', 'Guardians'), value: (meta.value.total || 0).toLocaleString(), tone: 'blue' }])
const columns = computed(() => [
  { key: 'name', label: t('common.name'), w: 'minmax(170px, 2fr)' },
  { key: 'email', label: t('common.email'), w: 'minmax(170px, 1.8fr)' },
  { key: 'phone', label: t('guardians.phone'), w: 'minmax(130px, 1.2fr)' },
  { key: 'role', label: t('staff.role'), w: 'minmax(140px, 1.2fr)' },
  { key: 'relation', label: t('guardians.relation'), w: 'minmax(90px, .8fr)' },
  { key: 'children', label: t('guardians.children'), w: 'minmax(200px, 2fr)' },
])
const menuFor = () => [
  { label: '🔍 ' + t('common.view'), run: openView },
  { label: '✏️ ' + t('common.edit'), run: openEdit },
  { label: '🗑️ ' + t('common.delete'), run: remove, danger: true },
]
function goPage(p) { page.value = typeof p === 'number' ? p : 1; loadData() }
const showViewModal   = ref(false)
const viewTarget      = ref(null)
const showDeleteModal = ref(false)
const deleteTarget    = ref(null)
const deleting        = ref(false)
const showModal       = ref(false)
const editing     = ref(null)
const saving      = ref(false)
const modalError  = ref('')
const students    = ref([])
const page        = ref(1)
const meta        = ref({ total: 0, last_page: 1, per_page: 20, current_page: 1 })
let   debouncer   = null

const emptyForm = () => ({ name:'', phone:'', email:'', relation:'', student_ids:[] })
const form = ref(emptyForm())

function openView(guardian) { viewTarget.value = guardian; showViewModal.value = true }

async function loadData() {
  const params = { page: page.value, per_page: perPage.value }
  if (search.value) params.search = search.value
  try {
    await store.fetchGuardians(params)
    meta.value = store.pagination || meta.value
  } catch {}
}

function debouncedLoad() {
  clearTimeout(debouncer)
  debouncer = setTimeout(() => { page.value = 1; loadData() }, 350)
}

function openAdd() {
  editing.value   = null
  form.value      = emptyForm()
  modalError.value = ''
  showModal.value = true
}

function openEdit(g) {
  editing.value   = g
  modalError.value = ''
  form.value = {
    name:        g.user?.name || '',
    phone:       g.user?.phone || '',
    email:       g.user?.email || '',
    relation:    g.relation || '',
    student_ids: g.students?.map(s => s.id) || [],
  }
  showModal.value = true
}

function closeModal() { showModal.value = false }

async function save() {
  modalError.value = ''
  saving.value = true
  try {
    if (editing.value) {
      await store.updateGuardian(editing.value.id, form.value)
    } else {
      await store.createGuardian(form.value)
    }
    closeModal()
    await loadData()
  } catch (e) {
    modalError.value = e?.response?.data?.message || t('common.error')
  } finally {
    saving.value = false
  }
}

function remove(g) {
  deleteTarget.value = { id: g.id, full_name: g.full_name || g.user?.name }
  showDeleteModal.value = true
}

async function doDelete() {
  deleting.value = true
  try {
    await store.deleteGuardian(deleteTarget.value.id)
    showDeleteModal.value = false
    await loadData()
  } catch (e) {
    alert(e?.response?.data?.message || t('common.error'))
  } finally {
    deleting.value = false
  }
}

// The Children checklist (Edit/Add modal) must offer every student in the
// school, not just one page — /students is paginated (max 100/page), so a
// single fetchStudents() call left this list silently truncated to the
// first 20/100 students while Guardian Details (which reads the guardian's
// own `students` relation, not this list) showed the guardian's real,
// complete set of children. Loop every page to build the full roster.
async function loadAllStudents() {
  const all = []
  let page = 1
  let lastPage = 1
  do {
    await studentsStore.fetchStudents({ page, per_page: 100 })
    all.push(...studentsStore.students)
    lastPage = studentsStore.pagination?.last_page || 1
    page++
  } while (page <= lastPage)
  students.value = all
}

onMounted(async () => {
  await loadData()
  try {
    await loadAllStudents()
  } catch {}
})

onUnmounted(() => clearTimeout(debouncer))
</script>
