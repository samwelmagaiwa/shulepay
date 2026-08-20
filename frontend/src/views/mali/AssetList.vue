<template>
  <CContainer fluid class="p-2 p-md-3">
    <!-- Filters & Action Toolbar -->
    <CCard class="mb-3">
      <CCardBody class="p-2">
        <CRow class="g-2 align-items-center">
          <CCol xs="12" sm="6" md="2">
            <CFormSelect v-model="filters.school_id" @update:modelValue="load" style="min-height:40px;">
              <option value="">{{ t('common.allSchools') }}</option>
              <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
            </CFormSelect>
          </CCol>
          <CCol xs="12" sm="6" md="2">
            <CFormSelect v-model="filters.category" @update:modelValue="load" style="min-height:40px;">
              <option value="">{{ t('assets.allTypes') }}</option>
              <option v-for="c in categoryOptions" :key="c.value" :value="c.value">{{ c.label }}</option>
            </CFormSelect>
          </CCol>
          <CCol xs="12" sm="6" md="2">
            <CFormSelect v-model="filters.condition" @update:modelValue="load" style="min-height:40px;">
              <option value="">{{ t('assets.allConditions') }}</option>
              <option v-for="c in conditionOptions" :key="c.value" :value="c.value">{{ c.label }}</option>
            </CFormSelect>
          </CCol>
          <CCol xs="12" sm="6" md="2">
            <CFormSelect v-model="filters.status" @update:modelValue="load" style="min-height:40px;">
              <option value="">{{ t('common.allStatuses') }}</option>
              <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
            </CFormSelect>
          </CCol>
          <CCol xs="12" md="2">
            <CFormInput v-model="filters.search" :placeholder="t('assets.searchPlaceholder')"
                        @input="debouncedLoad" style="min-height:40px;" />
          </CCol>
          <CCol xs="12" md="auto" class="ms-auto">
            <CButton color="success" @click="openAdd" style="min-height:40px; width:100%;">
              <CIcon icon="cilPlus" class="me-1" /> {{ t('assets.add') }}
            </CButton>
          </CCol>
        </CRow>
      </CCardBody>
    </CCard>

    <!-- Summary Cards -->
    <CRow class="g-2 mb-3">
      <CCol v-for="card in summaryCards" :key="card.label" xs="6" md="3">
        <CCard class="border-0 shadow-sm h-100">
          <CCardBody class="p-3">
            <div class="text-muted small mb-1">{{ card.label }}</div>
            <div class="fw-bold fs-5" :class="`text-${card.color}`">{{ card.value }}</div>
          </CCardBody>
        </CCard>
      </CCol>
    </CRow>

    <div v-if="loading" class="text-center py-5"><CSpinner color="primary" /></div>

    <div v-else>
      <!-- Mobile cards -->
      <div class="d-md-none">
        <div v-if="!assets.length" class="text-center text-muted py-5">{{ t('assets.noAssets') }}</div>
        <div v-for="a in assets" :key="a.id" class="mb-2 border rounded p-3">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div class="flex-grow-1">
              <div class="d-flex align-items-center gap-2 mb-1">
                <CBadge color="secondary" class="font-monospace small">{{ a.asset_tag || '—' }}</CBadge>
                <CBadge :color="conditionBadgeColor(a.condition)">{{ conditionLabel(a.condition) }}</CBadge>
              </div>
              <div class="fw-bold">{{ a.name }}</div>
              <div class="small text-muted">{{ categoryLabel(a.category) }} · {{ a.location || '—' }}</div>
            </div>
            <CBadge :color="statusBadgeColor(a.status)" class="ms-2">{{ statusLabel(a.status) }}</CBadge>
          </div>
          <div class="d-flex justify-content-between align-items-center">
            <div class="small">
              <div>{{ t('assets.cost') }}: <strong>{{ formatTZS(a.purchase_cost_cents) }}</strong></div>
              <div>{{ t('assets.currentValue') }}: <strong :class="valueColor(a)">{{ formatTZS(a.current_value_cents) }}</strong></div>
            </div>
            <div class="d-flex gap-1">
              <CButton size="sm" color="info" variant="outline" @click="openDetail(a)"
                       style="min-height:44px; min-width:44px;" title="Maelezo">
                <CIcon icon="cilEyedropper" />
              </CButton>
              <CButton size="sm" color="primary" variant="outline" @click="openEdit(a)"
                       style="min-height:44px; min-width:44px;" title="Hariri">
                <CIcon icon="cilPencil" />
              </CButton>
              <CButton v-if="canDispose(a)" size="sm" color="warning" variant="outline"
                       @click="openDispose(a)" style="min-height:44px; min-width:44px;" title="Tupa">
                <CIcon icon="cilXCircle" />
              </CButton>
              <CButton v-if="canDelete(a)" size="sm" color="danger" variant="ghost"
                       @click="confirmDelete(a)" style="min-height:44px; min-width:44px;" title="Futa">
                <CIcon icon="cilTrash" />
              </CButton>
            </div>
          </div>
        </div>
      </div>

      <!-- Desktop table -->
      <CCard class="d-none d-md-block">
        <CCardBody class="p-0">
          <CTable responsive hover class="mb-0">
            <CTableHead class="table-light">
              <CTableRow>
                <CTableHeaderCell>{{ t('assets.assetTag') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('assets.name') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('assets.category') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-lg-table-cell">{{ t('assets.quantity') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('assets.cost') }} (TZS)</CTableHeaderCell>
                <CTableHeaderCell>{{ t('assets.currentValue') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-lg-table-cell">{{ t('assets.condition') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('common.status') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('common.actions') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="a in assets" :key="a.id">
                <CTableDataCell>
                  <span class="font-monospace small text-muted">{{ a.asset_tag || '—' }}</span>
                </CTableDataCell>
                <CTableDataCell>
                  <div class="fw-semibold">{{ a.name }}</div>
                  <div v-if="a.serial_no || a.serial_number" class="small text-muted">
                    S/N: {{ a.serial_no || a.serial_number }}
                  </div>
                </CTableDataCell>
                <CTableDataCell>{{ categoryLabel(a.category) }}</CTableDataCell>
                <CTableDataCell class="d-none d-lg-table-cell">{{ a.quantity ?? 1 }}</CTableDataCell>
                <CTableDataCell>{{ formatTZS(a.purchase_cost_cents || a.cost_cents) }}</CTableDataCell>
                <CTableDataCell :class="valueColor(a)">{{ formatTZS(a.current_value_cents) }}</CTableDataCell>
                <CTableDataCell class="d-none d-lg-table-cell">
                  <CBadge :color="conditionBadgeColor(a.condition)">{{ conditionLabel(a.condition) }}</CBadge>
                </CTableDataCell>
                <CTableDataCell>
                  <CBadge :color="statusBadgeColor(a.status)">{{ statusLabel(a.status) }}</CBadge>
                </CTableDataCell>
                <CTableDataCell>
                  <CDropdown variant="btn-group" placement="bottom-end">
                    <CDropdownToggle color="primary" variant="outline" size="sm" class="py-1 px-2" :caret="false" title="Actions">
                      <CIcon icon="cilLowVision" />
                    </CDropdownToggle>
                    <CDropdownMenu>
                      <CDropdownItem @click="openDetail(a)" style="cursor:pointer;" class="d-flex align-items-center gap-2">
                        <CIcon icon="cilLowVision" class="text-info" /> {{ t('assets.detail.identity') }}
                      </CDropdownItem>
                      <CDropdownItem @click="openEdit(a)" style="cursor:pointer;" class="d-flex align-items-center gap-2">
                        <CIcon icon="cilPencil" class="text-primary" /> {{ t('common.edit') }}
                      </CDropdownItem>
                      <CDropdownItem v-if="canDispose(a)" @click="openDispose(a)" style="cursor:pointer;" class="d-flex align-items-center gap-2">
                        <CIcon icon="cilXCircle" class="text-warning" /> {{ t('assets.disposeModal.title') }}
                      </CDropdownItem>
                      <CDropdownDivider v-if="canDelete(a)" />
                      <CDropdownItem v-if="canDelete(a)" @click="confirmDelete(a)" style="cursor:pointer;" class="d-flex align-items-center gap-2 text-danger">
                        <CIcon icon="cilTrash" /> {{ t('common.delete') }}
                      </CDropdownItem>
                    </CDropdownMenu>
                  </CDropdown>
                </CTableDataCell>
              </CTableRow>
              <CTableRow v-if="!assets.length">
                <CTableDataCell colspan="9" class="text-center text-muted py-4">{{ t('assets.noAssets') }}</CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </CCardBody>
      </CCard>

      <!-- Pagination -->
      <div v-if="assetsStore.lastPage > 1" class="d-flex justify-content-center mt-3">
        <CPagination>
          <CPaginationItem :disabled="assetsStore.currentPage <= 1" @click="goPage(assetsStore.currentPage - 1)">
            &laquo;
          </CPaginationItem>
          <CPaginationItem
            v-for="p in assetsStore.lastPage" :key="p"
            :active="p === assetsStore.currentPage"
            @click="goPage(p)"
          >{{ p }}</CPaginationItem>
          <CPaginationItem :disabled="assetsStore.currentPage >= assetsStore.lastPage" @click="goPage(assetsStore.currentPage + 1)">
            &raquo;
          </CPaginationItem>
        </CPagination>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════
         DETAIL MODAL
    ════════════════════════════════════════════════ -->
    <CModal :visible="showDetail" @close="showDetail = false" size="xl" class="modal-fullscreen-sm-down">
      <CModalHeader>
        <CModalTitle>
          <span class="font-monospace me-2 text-muted small">{{ detailAsset?.asset_tag }}</span>
          {{ detailAsset?.name }}
        </CModalTitle>
      </CModalHeader>
      <CModalBody v-if="detailAsset">
        <!-- Photo -->
        <div v-if="detailAsset.photo_url" class="mb-3 text-center">
          <img :src="detailAsset.photo_url" class="img-fluid rounded" style="max-height:200px;" alt="Picha ya mali" />
        </div>

        <!-- 1. Identity -->
        <h6 class="text-primary fw-bold border-bottom pb-1 mb-2">{{ t('assets.detail.identity') }}</h6>
        <CRow class="g-2 mb-3">
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.assetTag') }}</small><div class="fw-semibold font-monospace">{{ detailAsset.asset_tag || '—' }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.name') }}</small><div class="fw-semibold">{{ detailAsset.name }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.category') }}</small><div>{{ categoryLabel(detailAsset.category) }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('common.school') }}</small><div>{{ detailAsset.school_name || detailAsset.school?.name || '—' }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.quantity') }}</small><div>{{ detailAsset.quantity ?? 1 }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.serialNo') }}</small><div>{{ detailAsset.serial_no || detailAsset.serial_number || '—' }}</div></CCol>
        </CRow>

        <!-- 2. Purchase -->
        <h6 class="text-primary fw-bold border-bottom pb-1 mb-2">{{ t('assets.detail.purchase') }}</h6>
        <CRow class="g-2 mb-3">
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.cost') }}</small><div class="fw-semibold">{{ formatTZS(detailAsset.purchase_cost_cents || detailAsset.cost_cents) }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.purchaseDate') }}</small><div>{{ detailAsset.purchase_date || '—' }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.supplier') }}</small><div>{{ detailAsset.supplier_name || '—' }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.invoiceNo') }}</small><div>{{ detailAsset.invoice_no || '—' }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.fundingSource') }}</small><div>{{ fundingLabel(detailAsset.funding_source) }}</div></CCol>
        </CRow>

        <!-- 3. Depreciation -->
        <h6 class="text-primary fw-bold border-bottom pb-1 mb-2">{{ t('assets.detail.depreciation') }}</h6>
        <CRow class="g-2 mb-3">
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.depMethod') }}</small><div>{{ depMethodLabel(detailAsset.depreciation_method) }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.usefulLife') }}</small><div>{{ detailAsset.useful_life_years ? detailAsset.useful_life_years + ' ' + t('assets.years') : '—' }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.depRate') }}</small><div>{{ detailAsset.depreciation_rate ? detailAsset.depreciation_rate + '%' : '—' }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.salvageValue') }}</small><div>{{ formatTZS(detailAsset.salvage_value_cents) }}</div></CCol>
          <CCol xs="12" md="4">
            <small class="text-muted">{{ t('assets.currentValue') }}</small>
            <div class="fw-bold fs-4" :class="valueColor(detailAsset)">{{ formatTZS(detailAsset.current_value_cents) }}</div>
          </CCol>
          <CCol xs="6" md="4"><small class="text-muted">{{ t('assets.accumulatedDep') }}</small><div>{{ formatTZS(detailAsset.accumulated_depreciation_cents) }}</div></CCol>
        </CRow>

        <!-- 4. Custody -->
        <h6 class="text-primary fw-bold border-bottom pb-1 mb-2">{{ t('assets.detail.custody') }}</h6>
        <CRow class="g-2 mb-3">
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.custodian') }}</small><div>{{ detailAsset.custodian || '—' }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.location') }}</small><div>{{ detailAsset.location || '—' }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.condition') }}</small><div><CBadge :color="conditionBadgeColor(detailAsset.condition)">{{ conditionLabel(detailAsset.condition) }}</CBadge></div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('common.status') }}</small><div><CBadge :color="statusBadgeColor(detailAsset.status)">{{ statusLabel(detailAsset.status) }}</CBadge></div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.warranty') }}</small><div>{{ detailAsset.warranty_expiry || '—' }}</div></CCol>
        </CRow>

        <!-- 5. Disposal (if disposed) -->
        <template v-if="detailAsset.disposal_date || detailAsset.status === 'disposed'">
          <h6 class="text-danger fw-bold border-bottom pb-1 mb-2">{{ t('assets.detail.disposal') }}</h6>
          <CRow class="g-2 mb-3">
            <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.disposeModal.disposalDate') }}</small><div>{{ detailAsset.disposal_date || '—' }}</div></CCol>
            <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.disposeModal.disposalValue') }}</small><div>{{ formatTZS(detailAsset.disposal_value_cents) }}</div></CCol>
            <CCol xs="12" md="6"><small class="text-muted">{{ t('assets.disposeModal.disposalReason') }}</small><div>{{ detailAsset.disposal_reason || '—' }}</div></CCol>
          </CRow>
        </template>

        <!-- 6. Audit -->
        <h6 class="text-secondary fw-bold border-bottom pb-1 mb-2">{{ t('assets.detail.audit') }}</h6>
        <CRow class="g-2 mb-2">
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.registeredBy') }}</small><div>{{ typeof detailAsset.registered_by === 'object' ? (detailAsset.registered_by?.name || '—') : (detailAsset.registered_by || '—') }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.registeredAt') }}</small><div>{{ detailAsset.registered_at ? detailAsset.registered_at.slice(0,16).replace('T',' ') : '—' }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('common.createdAt') }}</small><div>{{ detailAsset.created_at || '—' }}</div></CCol>
        </CRow>

        <!-- Notes -->
        <template v-if="detailAsset.notes">
          <div class="mt-2">
            <small class="text-muted">{{ t('common.notes') }}</small>
            <div class="border rounded p-2 bg-light small">{{ detailAsset.notes }}</div>
          </div>
        </template>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" @click="showDetail = false" style="min-height:44px;">{{ t('common.close') }}</CButton>
        <CButton color="primary" @click="openEdit(detailAsset); showDetail = false" style="min-height:44px;">
          <CIcon icon="cilPencil" class="me-1" /> {{ t('common.edit') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- ══════════════════════════════════════════════
         ADD / EDIT MODAL
    ════════════════════════════════════════════════ -->
    <CModal :visible="showModal" @close="showModal = false" size="xl" class="modal-fullscreen-sm-down">
      <CModalHeader>
        <CModalTitle>{{ editAsset ? t('assets.edit') : t('assets.add') }}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        <CAlert v-if="formError" color="danger" class="mb-3">{{ formError }}</CAlert>

        <!-- Section Tabs -->
        <CNav variant="tabs" class="mb-3">
          <CNavItem v-for="(tab, i) in formTabs" :key="i">
            <CNavLink :active="activeTab === i" @click="activeTab = i" style="cursor:pointer;">
              {{ tab }}
            </CNavLink>
          </CNavItem>
        </CNav>

        <!-- Tab 1: Identity -->
        <div v-show="activeTab === 0">
          <CRow class="g-3">
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('assets.assetTag') }} *</label>
              <div class="input-group">
                <CFormInput v-model="form.asset_tag" placeholder="mfano: MSG-AST-001" style="min-height:44px;" />
                <CButton color="outline-secondary" @click="autoFillTag" :disabled="loadingTag" style="min-height:44px;">
                  <CSpinner v-if="loadingTag" size="sm" />
                  <span v-else>{{ t('students.autoFill') }}</span>
                </CButton>
              </div>
            </CCol>
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('assets.name') }} *</label>
              <CFormInput v-model="form.name" :placeholder="t('assets.namePlaceholder')" style="min-height:44px;" />
            </CCol>
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('assets.category') }} *</label>
              <CFormSelect v-model="form.category" style="min-height:44px;">
                <option value="">{{ t('common.select') }}...</option>
                <option v-for="c in categoryOptions" :key="c.value" :value="c.value">{{ c.label }}</option>
              </CFormSelect>
            </CCol>
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('common.school') }} *</label>
              <CFormSelect v-model="form.school_id" style="min-height:44px;" @update:modelValue="form.asset_tag = ''">
                <option value="">{{ t('common.select') }}...</option>
                <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
              </CFormSelect>
            </CCol>
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('assets.quantity') }}</label>
              <CFormInput type="number" v-model.number="form.quantity" min="1" style="min-height:44px;" />
            </CCol>
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('assets.serialNo') }}</label>
              <CFormInput v-model="form.serial_no" style="min-height:44px;" />
            </CCol>
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('assets.location') }} *</label>
              <CFormInput v-model="form.location" :placeholder="t('assets.locationPlaceholder')" style="min-height:44px;" />
            </CCol>
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('assets.photo') }}</label>
              <input type="file" accept="image/*" class="form-control" style="min-height:44px;"
                     @change="handlePhotoChange" />
              <div v-if="photoPreview" class="mt-2">
                <img :src="photoPreview" class="img-thumbnail" style="max-height:120px;" alt="Preview" />
              </div>
              <div v-else-if="editAsset?.photo_url" class="mt-2">
                <img :src="editAsset.photo_url" class="img-thumbnail" style="max-height:120px;" :alt="t('assets.currentPhoto')" />
                <div class="small text-muted">{{ t('assets.currentPhoto') }}</div>
              </div>
            </CCol>
          </CRow>
        </div>

        <!-- Tab 2: Purchase -->
        <div v-show="activeTab === 1">
          <CRow class="g-3">
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('assets.cost') }} (TZS)</label>
              <CFormInput type="number" v-model.number="form.purchase_cost" min="0" style="min-height:44px;"
                          @input="updateDepPreview" />
            </CCol>
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('assets.purchaseDate') }}</label>
              <CFormInput type="date" v-model="form.purchase_date" style="min-height:44px;"
                          @change="updateDepPreview" />
            </CCol>
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('assets.supplier') }}</label>
              <CFormInput v-model="form.supplier_name" style="min-height:44px;" />
            </CCol>
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('assets.invoiceNo') }}</label>
              <CFormInput v-model="form.invoice_no" style="min-height:44px;" />
            </CCol>
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('assets.fundingSource') }}</label>
              <CFormSelect v-model="form.funding_source" style="min-height:44px;">
                <option value="">{{ t('common.select') }}...</option>
                <option value="fees">{{ t('assets.funding.fees') }}</option>
                <option value="donation">{{ t('assets.funding.donation') }}</option>
                <option value="grant">{{ t('assets.funding.grant') }}</option>
              </CFormSelect>
            </CCol>
          </CRow>
        </div>

        <!-- Tab 3: Depreciation -->
        <div v-show="activeTab === 2">
          <CRow class="g-3">
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('assets.depMethod') }}</label>
              <CFormSelect v-model="form.depreciation_method" style="min-height:44px;" @update:modelValue="updateDepPreview">
                <option value="">{{ t('common.none') }}</option>
                <option value="straight_line">{{ t('assets.depMethods.straight_line') }}</option>
                <option value="reducing_balance">{{ t('assets.depMethods.reducing_balance') }}</option>
              </CFormSelect>
            </CCol>
            <CCol v-if="form.depreciation_method === 'straight_line'" xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('assets.usefulLife') }}</label>
              <CFormInput type="number" v-model.number="form.useful_life_years" min="1" max="100"
                          style="min-height:44px;" @input="updateDepPreview" />
            </CCol>
            <CCol v-if="form.depreciation_method === 'reducing_balance'" xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('assets.depRate') }}</label>
              <CFormInput type="number" v-model.number="form.depreciation_rate" min="0.01" max="100" step="0.01"
                          style="min-height:44px;" @input="updateDepPreview" />
            </CCol>
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('assets.salvageValue') }} (TZS)</label>
              <CFormInput type="number" v-model.number="form.salvage_value" min="0" style="min-height:44px;"
                          @input="updateDepPreview" />
            </CCol>

            <!-- Live depreciation preview -->
            <CCol v-if="depPreview !== null" xs="12">
              <CAlert color="info" class="mb-0">
                <strong>{{ t('assets.currentValueEstimated') }}:</strong>
                <span class="fs-5 fw-bold ms-2">{{ formatTZS(depPreview) }}</span>
                <span class="text-muted small ms-2">{{ t('assets.calculatedFromInput') }}</span>
              </CAlert>
            </CCol>
          </CRow>
        </div>

        <!-- Tab 4: Condition -->
        <div v-show="activeTab === 3">
          <CRow class="g-3">
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('assets.custodian') }}</label>
              <CFormInput v-model="form.custodian" style="min-height:44px;" />
            </CCol>
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('assets.condition') }}</label>
              <CFormSelect v-model="form.condition" style="min-height:44px;">
                <option value="excellent">{{ t('assets.conditions.excellent') }}</option>
                <option value="good">{{ t('assets.conditions.good') }}</option>
                <option value="fair">{{ t('assets.conditions.fair') }}</option>
                <option value="poor">{{ t('assets.conditions.poor') }}</option>
              </CFormSelect>
            </CCol>
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('common.status') }}</label>
              <CFormSelect v-model="form.status" style="min-height:44px;">
                <option value="in_use">{{ t('assets.statuses.in_use') }}</option>
                <option value="under_repair">{{ t('assets.statuses.under_repair') }}</option>
                <option value="disposed">{{ t('assets.statuses.disposed') }}</option>
                <option value="lost">{{ t('assets.statuses.lost') }}</option>
                <option value="written_off">{{ t('assets.statuses.written_off') }}</option>
              </CFormSelect>
            </CCol>
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('assets.warranty') }}</label>
              <CFormInput type="date" v-model="form.warranty_expiry" style="min-height:44px;" />
            </CCol>
            <CCol xs="12">
              <label class="form-label fw-semibold">{{ t('common.notes') }}</label>
              <CFormTextarea v-model="form.notes" rows="3" />
            </CCol>
          </CRow>
        </div>
      </CModalBody>
      <CModalFooter class="flex-wrap gap-2">
        <CButton color="secondary" @click="showModal = false" style="min-height:44px;">{{ t('common.close') }}</CButton>
        <div class="ms-auto d-flex gap-2">
          <CButton v-if="activeTab > 0" color="outline-secondary" @click="activeTab--" style="min-height:44px;">
            &larr; {{ t('common.back') }}
          </CButton>
          <CButton v-if="activeTab < formTabs.length - 1" color="primary" @click="activeTab++" style="min-height:44px;">
            {{ t('common.next') }} &rarr;
          </CButton>
          <CButton v-if="activeTab === formTabs.length - 1" color="success"
                   :disabled="saving || !form.name || !form.asset_tag || !form.category || !form.school_id"
                   @click="submitAsset" style="min-height:44px;">
            <CSpinner v-if="saving" size="sm" class="me-1" />
            {{ editAsset ? t('common.save') : t('assets.add') }}
          </CButton>
        </div>
      </CModalFooter>
    </CModal>

    <!-- ══════════════════════════════════════════════
         DISPOSE MODAL
    ════════════════════════════════════════════════ -->
    <CModal :visible="showDisposeModal" @close="showDisposeModal = false" size="md" class="modal-fullscreen-sm-down">
      <CModalHeader><CModalTitle>{{ t('assets.disposeModal.dispose') }}: {{ disposeTarget?.name }}</CModalTitle></CModalHeader>
      <CModalBody>
        <CAlert v-if="disposeError" color="danger">{{ disposeError }}</CAlert>
        <CRow class="g-3">
          <CCol xs="12">
            <label class="form-label fw-semibold">{{ t('assets.disposeModal.disposalDate') }} *</label>
            <CFormInput type="date" v-model="disposeForm.disposal_date" style="min-height:44px;" />
          </CCol>
          <CCol xs="12">
            <label class="form-label fw-semibold">{{ t('assets.disposeModal.disposalValue') }} (TZS)</label>
            <CFormInput type="number" v-model.number="disposeForm.disposal_value" min="0" style="min-height:44px;" />
          </CCol>
          <CCol xs="12">
            <label class="form-label fw-semibold">{{ t('assets.disposeModal.disposalReason') }} *</label>
            <CFormTextarea v-model="disposeForm.disposal_reason" rows="3"
                           :placeholder="t('assets.disposeModal.reasonPlaceholder')" />
          </CCol>
        </CRow>
      </CModalBody>
      <CModalFooter class="gap-2">
        <CButton color="secondary" @click="showDisposeModal = false" style="min-height:44px;">{{ t('common.close') }}</CButton>
        <CButton color="warning" :disabled="disposing || !disposeForm.disposal_date || !disposeForm.disposal_reason"
                 @click="doDispose" style="min-height:44px;">
          <CSpinner v-if="disposing" size="sm" class="me-1" />{{ t('assets.disposeModal.confirmDisposal') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Delete Confirm -->
    <CModal :visible="showDeleteModal" @close="showDeleteModal = false" size="sm" class="modal-fullscreen-sm-down">
      <CModalHeader><CModalTitle>{{ t('common.confirmDelete') }}</CModalTitle></CModalHeader>
      <CModalBody>
        {{ t('assets.confirmDeleteMsg', { name: toDelete?.name }) }}
        <div class="text-danger small mt-1">{{ t('common.cannotUndo') }}</div>
      </CModalBody>
      <CModalFooter class="gap-2">
        <CButton color="secondary" @click="showDeleteModal = false" style="min-height:44px;">{{ t('common.close') }}</CButton>
        <CButton color="danger" :disabled="deleting" @click="doDelete" style="min-height:44px;">
          <CSpinner v-if="deleting" size="sm" class="me-1" />{{ t('common.delete') }}
        </CButton>
      </CModalFooter>
    </CModal>
  </CContainer>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAssetsStore }  from '@/stores/assets'
import { useSchoolsStore } from '@/stores/schools'
import { useSchoolStore }  from '@/stores/school'

const { t } = useI18n()

const assetsStore  = useAssetsStore()
const schoolStore  = useSchoolsStore()
const navSchool    = useSchoolStore()

const assets  = ref([])
const loading = ref(false)
const schools = computed(() => schoolStore.schools)

watch(() => navSchool.activeSchoolId, (id) => {
  filters.value.school_id = id || ''
  load(1)
})

const filters = ref({ school_id: navSchool.activeSchoolId || '', category: '', condition: '', status: '', search: '' })

// ── Options ───────────────────────────────────────────────────────────────────
const categoryOptions = computed(() => [
  { value: 'furniture',   label: t('assets.categories.furniture') },
  { value: 'technology',  label: t('assets.categories.technology') },
  { value: 'equipment',   label: t('assets.categories.equipment') },
  { value: 'vehicles',    label: t('assets.categories.vehicles') },
  { value: 'buildings',   label: t('assets.categories.buildings') },
  { value: 'sports',      label: t('assets.categories.sports') },
  { value: 'other',       label: t('assets.categories.other') },
])

const formTabs = computed(() => [t('assets.tabs.identity'), t('assets.tabs.purchase'), t('assets.tabs.depreciation'), t('assets.tabs.condition')])

// ── Modal state ───────────────────────────────────────────────────────────────
const showDetail      = ref(false)
const showModal       = ref(false)
const showDisposeModal= ref(false)
const showDeleteModal = ref(false)
const detailAsset     = ref(null)
const editAsset       = ref(null)
const disposeTarget   = ref(null)
const toDelete        = ref(null)
const activeTab       = ref(0)

const saving    = ref(false)
const disposing = ref(false)
const deleting  = ref(false)
const loadingTag= ref(false)
const formError = ref('')
const disposeError = ref('')

// Photo
const photoFile    = ref(null)
const photoPreview = ref('')
const depPreview   = ref(null)

const form = ref(defaultForm())
const disposeForm = ref({ disposal_date: '', disposal_value: '', disposal_reason: '' })

function defaultForm() {
  return {
    asset_tag: '', name: '', category: '', school_id: '', quantity: 1,
    serial_no: '', purchase_cost: '', purchase_date: '', supplier_name: '',
    invoice_no: '', funding_source: '', depreciation_method: '', useful_life_years: '',
    depreciation_rate: '', salvage_value: '', custodian: '', location: '',
    condition: 'good', status: 'in_use', warranty_expiry: '', notes: '',
  }
}

// ── Summary cards ─────────────────────────────────────────────────────────────
const summaryCards = computed(() => {
  const list = assets.value
  return [
    { label: t('assets.summary.totalAssets'),  value: list.length,                                                                                    color: 'primary' },
    { label: t('assets.summary.totalCost'),   value: formatTZS(list.reduce((s, a) => s + (a.purchase_cost_cents || a.cost_cents || 0), 0)),          color: 'info'    },
    { label: t('assets.summary.currentValue'),value: formatTZS(list.reduce((s, a) => s + (a.current_value_cents || 0), 0)),                          color: 'success' },
    { label: t('assets.summary.inUse'),       value: list.filter(a => a.status === 'in_use' || (!a.status && a.condition !== 'disposed')).length,    color: 'warning' },
  ]
})

// ── Helpers ───────────────────────────────────────────────────────────────────
function formatTZS(cents) {
  return 'TZS ' + Math.round((cents || 0) / 100).toLocaleString('sw-TZ', { maximumFractionDigits: 0 })
}

function valueColor(asset) {
  const cost = asset.purchase_cost_cents || asset.cost_cents || 0
  if (!cost) return 'text-muted'
  const ratio = (asset.current_value_cents || 0) / cost
  if (ratio > 0.5) return 'text-success fw-bold'
  if (ratio > 0.2) return 'text-warning fw-bold'
  return 'text-danger fw-bold'
}

function conditionBadgeColor(c) {
  return { excellent: 'success', good: 'info', fair: 'warning', poor: 'danger' }[c] || 'secondary'
}
function conditionLabel(c) {
  const map = { excellent: 'assets.conditions.excellent', good: 'assets.conditions.good', fair: 'assets.conditions.fair', poor: 'assets.conditions.poor' }
  return map[c] ? t(map[c]) : (c || '—')
}
function statusBadgeColor(s) {
  return { in_use: 'success', under_repair: 'warning', disposed: 'secondary', lost: 'danger', written_off: 'dark' }[s] || 'secondary'
}
function statusLabel(s) {
  const map = { in_use: 'assets.statuses.in_use', under_repair: 'assets.statuses.under_repair', disposed: 'assets.statuses.disposed', lost: 'assets.statuses.lost', written_off: 'assets.statuses.written_off' }
  return map[s] ? t(map[s]) : (s || '—')
}
function categoryLabel(c) {
  return categoryOptions.value.find(o => o.value === c)?.label || (c || '—')
}
function fundingLabel(f) {
  const map = { fees: 'assets.funding.fees', donation: 'assets.funding.donation', grant: 'assets.funding.grant' }
  return map[f] ? t(map[f]) : (f || '—')
}
function depMethodLabel(m) {
  const map = { straight_line: 'assets.depMethods.straight_line', reducing_balance: 'assets.depMethods.reducing_balance' }
  return map[m] ? t(map[m]) : (m || '—')
}
function canDispose(a) { return ['in_use', 'under_repair'].includes(a.status) }
function canDelete(a)  { return ['disposed', 'lost', 'written_off'].includes(a.status) }

// ── Debounce search ───────────────────────────────────────────────────────────
let searchTimer = null
function debouncedLoad() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(load, 400)
}

// ── Load ──────────────────────────────────────────────────────────────────────
async function load(page = 1) {
  loading.value = true
  try {
    const params = { page }
    if (filters.value.school_id) params.school_id = filters.value.school_id
    if (filters.value.category)  params.category  = filters.value.category
    if (filters.value.condition) params.condition  = filters.value.condition
    if (filters.value.status)    params.status     = filters.value.status
    if (filters.value.search)    params.search     = filters.value.search
    await assetsStore.fetchAssets(params)
    assets.value = assetsStore.assets
  } catch {} finally { loading.value = false }
}

function goPage(p) { load(p) }

// ── Photo ─────────────────────────────────────────────────────────────────────
function handlePhotoChange(e) {
  const file = e.target.files[0]
  if (!file) return
  photoFile.value = file
  const reader = new FileReader()
  reader.onload = ev => { photoPreview.value = ev.target.result }
  reader.readAsDataURL(file)
}

// ── Live depreciation preview ─────────────────────────────────────────────────
function updateDepPreview() {
  const cost = (form.value.purchase_cost || 0) * 100
  if (!cost || !form.value.purchase_date) { depPreview.value = cost || null; return }
  const years   = (new Date() - new Date(form.value.purchase_date)) / (365.25 * 24 * 3600 * 1000)
  const salvage = (form.value.salvage_value || 0) * 100
  let dep = 0
  if (form.value.depreciation_method === 'straight_line' && form.value.useful_life_years > 0) {
    dep = Math.min(((cost - salvage) / form.value.useful_life_years) * years, cost - salvage)
  } else if (form.value.depreciation_method === 'reducing_balance' && form.value.depreciation_rate > 0) {
    let val  = cost
    const r  = form.value.depreciation_rate / 100
    const wy = Math.floor(years)
    for (let i = 0; i < wy; i++) { const d = val * r; dep += d; val -= d }
    dep += val * r * (years - wy)
  }
  depPreview.value = Math.max(salvage, cost - Math.round(dep))
}

// ── Auto-fill tag ─────────────────────────────────────────────────────────────
async function autoFillTag() {
  if (!form.value.school_id) { alert('Chagua shule kwanza.'); return }
  loadingTag.value = true
  try {
    form.value.asset_tag = await assetsStore.nextTag(form.value.school_id)
  } catch { alert('Imeshindwa kupata nambari.') } finally { loadingTag.value = false }
}

// ── Open modals ───────────────────────────────────────────────────────────────
function openDetail(a) { detailAsset.value = a; showDetail.value = true }

function openAdd() {
  editAsset.value = null
  formError.value = ''
  photoFile.value = null
  photoPreview.value = ''
  depPreview.value = null
  activeTab.value = 0
  form.value = defaultForm()
  showModal.value = true
}

function openEdit(a) {
  editAsset.value = a
  formError.value = ''
  photoFile.value = null
  photoPreview.value = ''
  depPreview.value = null
  activeTab.value = 0
  form.value = {
    asset_tag:           a.asset_tag || '',
    name:                a.name || '',
    category:            a.category || '',
    school_id:           a.school_id || '',
    quantity:            a.quantity ?? 1,
    serial_no:           a.serial_no || a.serial_number || '',
    purchase_cost:       Math.round((a.purchase_cost_cents || a.cost_cents || 0) / 100),
    purchase_date:       a.purchase_date || '',
    supplier_name:       a.supplier_name || '',
    invoice_no:          a.invoice_no || '',
    funding_source:      a.funding_source || '',
    depreciation_method: a.depreciation_method || '',
    useful_life_years:   a.useful_life_years || '',
    depreciation_rate:   a.depreciation_rate || '',
    salvage_value:       Math.round((a.salvage_value_cents || 0) / 100),
    custodian:           a.custodian || '',
    location:            a.location || '',
    condition:           a.condition || 'good',
    status:              a.status || 'in_use',
    warranty_expiry:     a.warranty_expiry || '',
    notes:               a.notes || '',
  }
  updateDepPreview()
  showModal.value = true
}

function openDispose(a) {
  disposeTarget.value  = a
  disposeError.value   = ''
  disposeForm.value    = { disposal_date: new Date().toISOString().slice(0, 10), disposal_value: '', disposal_reason: '' }
  showDisposeModal.value = true
}

function confirmDelete(a) { toDelete.value = a; showDeleteModal.value = true }

// ── Submit asset ──────────────────────────────────────────────────────────────
async function submitAsset() {
  formError.value = ''
  saving.value = true
  try {
    let payload
    if (photoFile.value) {
      payload = new FormData()
      Object.entries(form.value).forEach(([k, v]) => {
        if (v !== '' && v !== null && v !== undefined) payload.append(k, v)
      })
      payload.append('photo', photoFile.value)
    } else {
      payload = { ...form.value }
      Object.keys(payload).forEach(k => {
        if (payload[k] === '' || payload[k] === undefined) delete payload[k]
      })
    }

    if (editAsset.value) {
      await assetsStore.updateAsset(editAsset.value.id, payload)
    } else {
      await assetsStore.createAsset(payload)
    }
    showModal.value = false
    await load()
  } catch (e) {
    const errors = e?.response?.data?.errors
    if (errors) {
      formError.value = Object.values(errors).flat().join(' ')
    } else {
      formError.value = e?.response?.data?.message || 'Imeshindwa. Jaribu tena.'
    }
  } finally {
    saving.value = false
  }
}

// ── Dispose ───────────────────────────────────────────────────────────────────
async function doDispose() {
  disposeError.value = ''
  disposing.value = true
  try {
    await assetsStore.disposeAsset(disposeTarget.value.id, {
      disposal_date:   disposeForm.value.disposal_date,
      disposal_value:  disposeForm.value.disposal_value || 0,
      disposal_reason: disposeForm.value.disposal_reason,
    })
    showDisposeModal.value = false
    await load()
  } catch (e) {
    disposeError.value = e?.response?.data?.message || 'Imeshindwa. Jaribu tena.'
  } finally {
    disposing.value = false
  }
}

// ── Delete ────────────────────────────────────────────────────────────────────
async function doDelete() {
  deleting.value = true
  try {
    await assetsStore.deleteAsset(toDelete.value.id)
    showDeleteModal.value = false
    await load()
  } catch (e) {
    alert(e?.response?.data?.message || 'Imeshindwa kufuta.')
  } finally {
    deleting.value = false
  }
}

onMounted(async () => {
  try {
    await schoolStore.fetchSchools()
  } catch {}
})
</script>

<style scoped>
:deep(.table-responsive) {
  overflow: visible !important;
}
:deep(.card) {
  overflow: visible !important;
}
:deep(.dropdown-menu) {
  z-index: 1050 !important;
}
</style>
