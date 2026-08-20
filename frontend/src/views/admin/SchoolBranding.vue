<script setup>
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBrandingStore } from '@/stores/branding'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const { t } = useI18n()
const branding = useBrandingStore()
const auth = useAuthStore()

// ── Schools list (superadmin only) ──────────────────────────────────────────
const schools = ref([])
const selectedSchoolId = ref(null)
const loadingSchools = ref(false)

if (auth.isSuperAdmin) {
  loadingSchools.value = true
  api.get('/schools', { params: { all: true } })
    .then(r => { schools.value = r.data.data ?? r.data })
    .finally(() => { loadingSchools.value = false })
}

// ── Form state ───────────────────────────────────────────────────────────────
const appName    = ref(branding.appName)
const appTagline = ref(branding.appTagline)
const logoFile   = ref(null)
const logoPreview = ref(branding.logoUrl)
const saving     = ref(false)
const removing   = ref(false)
const success    = ref('')
const error      = ref('')

// When superadmin picks a school, fetch that school's branding
watch(selectedSchoolId, async (id) => {
  if (!id) return
  error.value = ''
  try {
    const res = await api.get('/branding', { params: { school_id: id } })
    appName.value    = res.data.app_name    || ''
    appTagline.value = res.data.app_tagline || ''
    logoPreview.value = res.data.logo_url   || null
    logoFile.value   = null
  } catch {
    error.value = 'Failed to load school branding.'
  }
})

async function save() {
  saving.value  = true
  error.value   = ''
  success.value = ''
  try {
    const fd = new FormData()
    fd.append('app_name', appName.value)
    fd.append('app_tagline', appTagline.value)
    if (logoFile.value) fd.append('logo', logoFile.value)
    if (auth.isSuperAdmin && selectedSchoolId.value) {
      fd.append('school_id', selectedSchoolId.value)
    }
    const res = await branding.updateBranding(fd)
    logoFile.value = null
    logoPreview.value = res.logo_url || logoPreview.value
    success.value = res.message || 'Branding saved successfully!'
    setTimeout(() => { success.value = '' }, 3000)
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to save.'
  } finally {
    saving.value = false
  }
}

async function removeLogo() {
  removing.value = true
  error.value    = ''
  try {
    const params = auth.isSuperAdmin && selectedSchoolId.value
      ? { school_id: selectedSchoolId.value }
      : {}
    await api.delete('/branding/logo', { params })
    logoPreview.value = null
    logoFile.value    = null
    branding.logoUrl  = null
    localStorage.removeItem('branding_logo')
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to remove logo.'
  } finally {
    removing.value = false
  }
}

function onLogoChange(e) {
  const file = e.target.files[0]
  if (!file) return
  logoFile.value    = file
  logoPreview.value = URL.createObjectURL(file)
}

const previewName    = computed(() => appName.value    || 'ShulePay')
const previewTagline = computed(() => appTagline.value || 'nexoryaTECH')

const schoolLabel = computed(() => {
  if (!auth.isSuperAdmin) return null
  if (!selectedSchoolId.value) return 'System Default'
  return schools.value.find(s => s.id == selectedSchoolId.value)?.name || 'Selected School'
})
</script>

<template>
  <div class="container-fluid px-4 py-4">

    <!-- Page title -->
    <h4 class="fw-bold mb-4">🎨 {{ t('nav.branding') }}</h4>

    <!-- Alerts -->
    <div class="alert alert-success py-2 small" v-if="success">✅ {{ success }}</div>
    <div class="alert alert-danger py-2 small" v-if="error">⚠️ {{ error }}</div>

    <!-- Superadmin: school selector -->
    <div v-if="auth.isSuperAdmin" class="card border-0 shadow-sm mb-4">
      <div class="card-body py-3 px-4">
        <div class="row align-items-center g-3">
          <div class="col-auto">
            <label class="form-label fw-semibold mb-0">Configure branding for</label>
          </div>
          <div class="col-12 col-md-4">
            <select
              v-model="selectedSchoolId"
              class="form-select"
              :disabled="loadingSchools"
            >
              <option :value="null">🌐 System Default (all schools)</option>
              <option v-for="s in schools" :key="s.id" :value="s.id">
                {{ s.name }}
              </option>
            </select>
          </div>
          <div class="col-auto">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
              {{ schoolLabel }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Live Preview -->
    <div class="card border-0 shadow-sm mb-4 overflow-hidden">
      <div class="card-header bg-white border-bottom fw-bold small text-muted text-uppercase" style="letter-spacing:.05em;">
        Live Preview
      </div>
      <div class="card-body d-flex align-items-center gap-3 py-3" style="background:#f8f9fa;">
        <div v-if="logoPreview" style="width:52px;height:52px;border-radius:50%;overflow:hidden;border:2px solid #007f3e;flex-shrink:0;">
          <img :src="logoPreview" alt="logo" style="width:100%;height:100%;object-fit:contain;" />
        </div>
        <div v-else style="width:52px;height:52px;flex-shrink:0;">
          <svg viewBox="0 0 40 40" width="52" height="52">
            <circle cx="20" cy="20" r="19" fill="#007f3e"/>
            <rect x="8"  y="13" width="11" height="16" rx="2" fill="white" opacity=".95"/>
            <rect x="21" y="13" width="11" height="16" rx="2" fill="white" opacity=".95"/>
            <rect x="18" y="11" width="4"  height="20" rx="1" fill="#fcd116"/>
            <circle cx="29" cy="11" r="5" fill="#fcd116"/>
            <text x="29" y="14.5" text-anchor="middle" font-size="6" font-weight="bold" fill="#007f3e">$</text>
          </svg>
        </div>
        <div class="lh-1">
          <div class="fw-bold" style="color:#007f3e;font-size:1.1rem;">{{ previewName }}</div>
          <div style="color:#003082;font-size:0.65rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;">{{ previewTagline }}</div>
        </div>
      </div>
    </div>

    <!-- Form — 3 columns -->
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <div class="row g-4">

          <!-- Col 1: App Name -->
          <div class="col-12 col-md-4">
            <label class="form-label fw-semibold">App Name <span class="text-danger">*</span></label>
            <input
              v-model="appName"
              type="text"
              class="form-control"
              placeholder="ShulePay"
              maxlength="80"
            />
            <div class="form-text">Shown in the header, sidebar, footer and receipts.</div>
          </div>

          <!-- Col 2: Tagline -->
          <div class="col-12 col-md-4">
            <label class="form-label fw-semibold">Tagline / Developer Name</label>
            <input
              v-model="appTagline"
              type="text"
              class="form-control"
              placeholder="nexoryaTECH"
              maxlength="80"
            />
            <div class="form-text">Shown below the app name in the header and on receipts.</div>
          </div>

          <!-- Col 3: Logo -->
          <div class="col-12 col-md-4">
            <label class="form-label fw-semibold">Logo</label>

            <!-- Current logo thumbnail + delete -->
            <div v-if="logoPreview" class="d-flex align-items-center gap-2 mb-2">
              <img
                :src="logoPreview"
                alt="Current logo"
                style="width:64px;height:64px;object-fit:contain;border:1px solid #dee2e6;border-radius:8px;background:#fff;"
              />
              <button
                type="button"
                class="btn btn-sm btn-outline-danger"
                :disabled="removing"
                @click="removeLogo"
              >
                <span v-if="removing" class="spinner-border spinner-border-sm me-1"></span>
                {{ removing ? 'Removing…' : '🗑 Remove logo' }}
              </button>
            </div>

            <input
              type="file"
              class="form-control"
              accept="image/png,image/jpeg,image/svg+xml,image/webp"
              @change="onLogoChange"
            />
            <div class="form-text">PNG, JPG, SVG or WebP · max 2 MB. Square, min 200×200 px.</div>
          </div>

        </div>

        <!-- Save button -->
        <div class="mt-4">
          <button class="btn btn-primary px-4" :disabled="saving" @click="save">
            <span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>
            {{ saving ? 'Saving…' : 'Save Branding' }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>
