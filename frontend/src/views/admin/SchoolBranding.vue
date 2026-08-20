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
const appName     = ref(branding.appName)
const appTagline  = ref(branding.appTagline)
const logoFile    = ref(null)
const logoPreview = ref(branding.logoUrl)
const saving      = ref(false)
const removing    = ref(false)
const success     = ref('')
const error       = ref('')

// Fetch branding when superadmin picks a school
watch(selectedSchoolId, async (id) => {
  error.value = ''
  try {
    const params = id ? { school_id: id } : {}
    const res = await api.get('/branding', { params })
    appName.value     = res.data.app_name    || ''
    appTagline.value  = res.data.app_tagline || ''
    logoPreview.value = res.data.logo_url    || null
    logoFile.value    = null
  } catch {
    error.value = 'Failed to load branding.'
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
    logoFile.value    = null
    logoPreview.value = res.logo_url || logoPreview.value
    success.value     = res.message || 'Branding saved!'
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
</script>

<template>
  <div class="container-fluid px-4 py-4">

    <!-- Page title -->
    <h4 class="fw-bold mb-3">🎨 {{ t('nav.branding') }}</h4>

    <!-- Alerts -->
    <div class="alert alert-success py-2 small mb-3" v-if="success">✅ {{ success }}</div>
    <div class="alert alert-danger py-2 small mb-3" v-if="error">⚠️ {{ error }}</div>

    <!-- Superadmin: school selector (inline, compact) -->
    <div v-if="auth.isSuperAdmin" class="d-flex align-items-center gap-3 mb-3 flex-wrap">
      <span class="fw-semibold text-muted small text-uppercase" style="letter-spacing:.05em;">Configure for</span>
      <select
        v-model="selectedSchoolId"
        class="form-select form-select-sm"
        style="max-width:280px;"
        :disabled="loadingSchools"
      >
        <option :value="null">🌐 System Default (all schools)</option>
        <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
      </select>
    </div>

    <!-- Main card: form left, preview right -->
    <div class="card border-0 shadow-sm">
      <div class="card-body p-0">
        <div class="row g-0">

          <!-- LEFT: Form fields -->
          <div class="col-12 col-lg-8 p-4 border-end">

            <div class="row g-3">

              <!-- App Name -->
              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold mb-1">App Name <span class="text-danger">*</span></label>
                <input
                  v-model="appName"
                  type="text"
                  class="form-control"
                  placeholder="ShulePay"
                  maxlength="80"
                />
                <div class="form-text">Shown in the header, footer and receipts.</div>
              </div>

              <!-- Tagline -->
              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold mb-1">Tagline / Developer Name</label>
                <input
                  v-model="appTagline"
                  type="text"
                  class="form-control"
                  placeholder="nexoryaTECH"
                  maxlength="80"
                />
                <div class="form-text">Shown below the app name.</div>
              </div>

              <!-- Logo -->
              <div class="col-12">
                <label class="form-label fw-semibold mb-1">Logo</label>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                  <!-- Current logo preview + remove -->
                  <div v-if="logoPreview" class="d-flex align-items-center gap-2">
                    <img
                      :src="logoPreview"
                      alt="logo"
                      style="width:56px;height:56px;object-fit:contain;border:1px solid #dee2e6;border-radius:8px;background:#fff;"
                    />
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-danger"
                      :disabled="removing"
                      @click="removeLogo"
                    >
                      <span v-if="removing" class="spinner-border spinner-border-sm me-1"></span>
                      {{ removing ? 'Removing…' : '🗑 Remove' }}
                    </button>
                  </div>
                  <!-- File picker -->
                  <div class="flex-grow-1" style="min-width:200px;">
                    <input
                      type="file"
                      class="form-control form-control-sm"
                      accept="image/png,image/jpeg,image/svg+xml,image/webp"
                      @change="onLogoChange"
                    />
                    <div class="form-text">PNG, JPG, SVG or WebP · max 2 MB · square min 200×200 px</div>
                  </div>
                </div>
              </div>

            </div>

            <!-- Save -->
            <div class="mt-4 pt-3 border-top">
              <button class="btn btn-primary px-4" :disabled="saving" @click="save">
                <span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>
                {{ saving ? 'Saving…' : 'Save Branding' }}
              </button>
            </div>

          </div>

          <!-- RIGHT: Live Preview -->
          <div class="col-12 col-lg-4 p-4 d-flex flex-column" style="background:#f8f9fa;">
            <div class="small fw-bold text-muted text-uppercase mb-3" style="letter-spacing:.06em;">Live Preview</div>

            <!-- Header simulation -->
            <div class="rounded-3 border bg-white p-3 shadow-sm d-flex align-items-center gap-3 mb-3">
              <div v-if="logoPreview" style="width:44px;height:44px;border-radius:50%;overflow:hidden;border:2px solid #007f3e;flex-shrink:0;">
                <img :src="logoPreview" style="width:100%;height:100%;object-fit:contain;" alt="logo" />
              </div>
              <div v-else style="width:44px;height:44px;flex-shrink:0;">
                <svg viewBox="0 0 40 40" width="44" height="44">
                  <circle cx="20" cy="20" r="19" fill="#007f3e"/>
                  <rect x="8"  y="13" width="11" height="16" rx="2" fill="white" opacity=".95"/>
                  <rect x="21" y="13" width="11" height="16" rx="2" fill="white" opacity=".95"/>
                  <rect x="18" y="11" width="4"  height="20" rx="1" fill="#fcd116"/>
                  <circle cx="29" cy="11" r="5" fill="#fcd116"/>
                  <text x="29" y="14.5" text-anchor="middle" font-size="6" font-weight="bold" fill="#007f3e">$</text>
                </svg>
              </div>
              <div class="lh-1">
                <div class="fw-bold" style="color:#007f3e;font-size:1rem;">{{ previewName }}</div>
                <div style="color:#003082;font-size:0.6rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;">{{ previewTagline }}</div>
              </div>
            </div>

            <!-- Receipt simulation -->
            <div class="rounded-3 border bg-white p-3 shadow-sm text-center" style="font-family:monospace;font-size:0.72rem;">
              <div class="fw-bold mb-1" style="color:#007f3e;font-size:0.85rem;">{{ previewName }}</div>
              <div style="color:#003082;font-size:0.6rem;letter-spacing:1px;text-transform:uppercase;">{{ previewTagline }}</div>
              <hr class="my-2" style="border-style:dashed;" />
              <div class="text-muted">RECEIPT #0001</div>
              <div class="d-flex justify-content-between mt-1"><span>Student:</span><span>J. Doe</span></div>
              <div class="d-flex justify-content-between"><span>Amount:</span><span>TZS 50,000</span></div>
              <div class="d-flex justify-content-between"><span>Method:</span><span>CASH</span></div>
              <hr class="my-2" style="border-style:dashed;" />
              <div class="text-muted" style="font-size:0.65rem;">Thank you!</div>
            </div>

            <div class="mt-auto pt-3">
              <p class="text-muted small mb-0">
                Changes appear in the header and on all printed receipts after saving.
              </p>
            </div>
          </div>

        </div>
      </div>
    </div>

  </div>
</template>
