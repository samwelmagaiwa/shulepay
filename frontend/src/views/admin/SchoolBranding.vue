<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBrandingStore } from '@/stores/branding'

const { t } = useI18n()
const branding = useBrandingStore()

const appName    = ref(branding.appName)
const appTagline = ref(branding.appTagline)
const logoFile   = ref(null)
const logoPreview = ref(branding.logoUrl)
const saving     = ref(false)
const removing   = ref(false)
const success    = ref(false)
const error      = ref('')

function onLogoChange(e) {
  const file = e.target.files[0]
  if (!file) return
  logoFile.value = file
  logoPreview.value = URL.createObjectURL(file)
}

async function save() {
  saving.value = true
  error.value  = ''
  success.value = false
  try {
    const fd = new FormData()
    fd.append('app_name',    appName.value)
    fd.append('app_tagline', appTagline.value)
    if (logoFile.value) fd.append('logo', logoFile.value)
    await branding.updateBranding(fd)
    logoFile.value = null
    success.value  = true
    setTimeout(() => { success.value = false }, 3000)
  } catch (e) {
    error.value = e.response?.data?.message || 'Hitilafu ya kuhifadhi.'
  } finally {
    saving.value = false
  }
}

async function removeLogo() {
  removing.value = true
  try {
    await branding.deleteLogo()
    logoPreview.value = null
    logoFile.value    = null
  } catch {
    // silent
  } finally {
    removing.value = false
  }
}

const previewName    = computed(() => appName.value    || 'ShulePay')
const previewTagline = computed(() => appTagline.value || 'nexoryaTECH')
</script>

<template>
  <div class="container-lg py-4">
    <div class="row justify-content-center">
      <div class="col-lg-8">

        <!-- Page header -->
        <div class="mb-4">
          <h4 class="fw-bold mb-1">🎨 {{ t('nav.branding') }}</h4>
          <p class="text-muted small mb-0">
            Configure your app name, tagline and logo. Changes appear in the header,
            sidebar, footer and on all printed receipts.
          </p>
        </div>

        <!-- Live preview card -->
        <div class="card border-0 shadow-sm mb-4 overflow-hidden">
          <div class="card-header bg-white border-bottom fw-bold small text-muted text-uppercase" style="letter-spacing:.05em;">
            Live Preview
          </div>
          <div class="card-body d-flex align-items-center gap-3 py-3" style="background:#f8f9fa;">
            <!-- Logo preview -->
            <div
              v-if="logoPreview"
              style="width:52px;height:52px;border-radius:50%;overflow:hidden;border:2px solid #007f3e;flex-shrink:0;"
            >
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
              <div style="color:#003082;font-size:0.65rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;">
                {{ previewTagline }}
              </div>
            </div>
          </div>
        </div>

        <!-- Form -->
        <div class="card border-0 shadow-sm">
          <div class="card-body p-4">

            <div class="alert alert-success py-2 small" v-if="success">
              ✅ Branding saved successfully!
            </div>
            <div class="alert alert-danger py-2 small" v-if="error">
              ⚠️ {{ error }}
            </div>

            <!-- App Name -->
            <div class="mb-3">
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

            <!-- Tagline -->
            <div class="mb-3">
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

            <!-- Logo upload -->
            <div class="mb-4">
              <label class="form-label fw-semibold">Logo</label>
              <div class="d-flex align-items-start gap-3 flex-wrap">
                <div v-if="logoPreview" class="position-relative" style="width:80px;height:80px;">
                  <img
                    :src="logoPreview"
                    alt="Current logo"
                    style="width:80px;height:80px;object-fit:contain;border:1px solid #dee2e6;border-radius:8px;background:#fff;"
                  />
                  <button
                    type="button"
                    class="btn btn-sm btn-danger position-absolute top-0 end-0"
                    style="padding:1px 5px;font-size:11px;"
                    :disabled="removing"
                    @click="removeLogo"
                  >✕</button>
                </div>
                <div class="flex-grow-1">
                  <input
                    type="file"
                    class="form-control"
                    accept="image/png,image/jpeg,image/svg+xml,image/webp"
                    @change="onLogoChange"
                  />
                  <div class="form-text">PNG, JPG, SVG or WebP · max 2 MB. Recommended: square, at least 200×200 px.</div>
                </div>
              </div>
            </div>

            <div class="d-flex gap-2">
              <button
                class="btn btn-primary px-4"
                :disabled="saving"
                @click="save"
              >
                <span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>
                {{ saving ? 'Saving…' : 'Save Branding' }}
              </button>
            </div>
          </div>
        </div>

        <!-- Receipt preview note -->
        <div class="card border-0 shadow-sm mt-4">
          <div class="card-body py-3 px-4">
            <div class="d-flex align-items-start gap-3">
              <div style="font-size:1.8rem;line-height:1;">🧾</div>
              <div>
                <div class="fw-bold mb-1">Receipt Preview</div>
                <p class="text-muted small mb-0">
                  Your configured app name, tagline and logo will appear at the top
                  of every printed payment receipt. Download any receipt from a student's
                  profile to see the updated branding.
                </p>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>
