<template>
  <CContainer fluid class="p-2 p-md-3">
    <div class="mb-3">
      <h5 class="fw-bold mb-1">{{ t('primaryFees.title') }}</h5>
      <div class="text-muted small">{{ t('primaryFees.subtitle') }}</div>
    </div>

    <div v-if="loading" class="text-center py-5"><CSpinner color="primary" /></div>

    <template v-else>
      <CAlert v-if="saved" color="success" class="py-2 small" dismissible @close="saved = false">
        {{ t('primaryFees.saved') }}
      </CAlert>

      <div v-for="tier in TIERS" :key="tier" class="mb-4">
        <h6 class="fw-bold text-muted small text-uppercase mb-2">{{ t(TIER_LABEL_KEYS[tier]) }}</h6>
        <CRow class="g-3">
          <CCol md="6" lg="3" v-for="c in tiers[tier]" :key="c.category">
            <CCard class="h-100 border-0 shadow-sm" :style="`border-left:4px solid ${categoryColor(c.category)} !important`">
              <CCardBody>
                <div class="fw-semibold small mb-1">{{ categoryLabel(c.category) }}</div>
                <div class="text-muted mb-2" style="font-size:.72rem;">{{ categoryHint(c.category) }}</div>
                <CInputGroup size="sm">
                  <CInputGroupText>TZS</CInputGroupText>
                  <CFormInput
                    type="text"
                    inputmode="numeric"
                    :value="formatAmount(c.amount)"
                    @input="c.amount = parseAmount($event.target.value)"
                  />
                </CInputGroup>
              </CCardBody>
            </CCard>
          </CCol>
        </CRow>
      </div>

      <div class="mt-3">
        <CButton color="primary" :disabled="saving" @click="save">
          <CSpinner v-if="saving" size="sm" class="me-1" />
          {{ t('common.save') }}
        </CButton>
      </div>
    </template>
  </CContainer>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'

const { t } = useI18n()

const loading = ref(true)
const saving = ref(false)
const saved = ref(false)
const TIERS = ['std_4_6', 'std_1_3']
const tiers = ref({ std_4_6: [], std_1_3: [] })

const TIER_LABEL_KEYS = {
  std_4_6: 'primaryFees.tierStd46',
  std_1_3: 'primaryFees.tierStd13',
}

const CATEGORY_META = {
  day_transport_food: { label: 'primaryFees.dayTransportFood', hint: 'primaryFees.dayTransportFoodHint', color: '#0ea5e9' },
  hostel: { label: 'primaryFees.hostel', hint: 'primaryFees.hostelHint', color: '#8b5cf6' },
  day_food_only: { label: 'primaryFees.dayFoodOnly', hint: 'primaryFees.dayFoodOnlyHint', color: '#10b981' },
  day_none: { label: 'primaryFees.dayNone', hint: 'primaryFees.dayNoneHint', color: '#f59e0b' },
}

const categoryLabel = (category) => t(CATEGORY_META[category]?.label || category)
const categoryHint = (category) => t(CATEGORY_META[category]?.hint || '')
const categoryColor = (category) => CATEGORY_META[category]?.color || '#6b7280'

function formatAmount(value) {
  return (value || 0).toLocaleString('sw-TZ')
}
function parseAmount(str) {
  return parseInt(String(str).replace(/[^0-9]/g, ''), 10) || 0
}

function mapTiers(rawTiers) {
  const mapped = {}
  for (const tier of TIERS) {
    mapped[tier] = (rawTiers[tier] || []).map(c => ({
      category: c.category,
      amount: Math.round((c.amount_cents || 0) / 100),
    }))
  }
  return mapped
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/primary-fee-categories')
    tiers.value = mapTiers(data.tiers || {})
  } catch {} finally { loading.value = false }
}

async function save() {
  saving.value = true
  saved.value = false
  try {
    const payload = {
      tiers: Object.fromEntries(TIERS.map(tier => [
        tier,
        (tiers.value[tier] || []).map(c => ({
          category: c.category,
          amount_cents: Math.round((c.amount || 0) * 100),
        })),
      ])),
    }
    const { data } = await api.put('/primary-fee-categories', payload)
    tiers.value = mapTiers(data.tiers || {})
    saved.value = true
  } catch {} finally { saving.value = false }
}

onMounted(load)
</script>
