<template>
  <CContainer fluid class="p-2 p-md-4" style="min-height:100vh; background:#f0faf4;">
    <div v-if="loading" class="text-center py-5"><CSpinner color="primary" /></div>

    <div v-else-if="student">
      <!-- Back button -->
      <CButton color="secondary" variant="ghost" size="sm" @click="$router.back()" class="mb-2">
        &larr; {{ t('common.back') }}
      </CButton>

      <!-- Summary cards (top row) -->
      <CRow class="g-2 mb-2">
        <CCol xs="4">
          <CCard class="border-0 bg-body-secondary h-100">
            <CCardBody class="p-2">
              <div class="small text-muted" style="font-size:.72rem;">{{ t('students.totalInvoiced') }}</div>
              <div class="fw-bold" style="font-size:.95rem;">{{ formatMoney(totalInvoiced) }}</div>
            </CCardBody>
          </CCard>
        </CCol>
        <CCol xs="4">
          <CCard class="border-0 h-100" style="background:rgba(25,135,84,0.08);">
            <CCardBody class="p-2">
              <div class="small text-muted" style="font-size:.72rem;">{{ t('students.totalPaid') }}</div>
              <div class="fw-bold text-success" style="font-size:.95rem;">{{ formatMoney(totalPaid) }}</div>
            </CCardBody>
          </CCard>
        </CCol>
        <CCol xs="4">
          <CCard class="border-0 h-100" style="background:rgba(220,53,69,0.08);">
            <CCardBody class="p-2">
              <div class="small text-muted" style="font-size:.72rem;">{{ t('invoices.debt') }}</div>
              <div class="fw-bold text-danger" style="font-size:.95rem;">{{ formatMoney(totalOutstanding) }}</div>
            </CCardBody>
          </CCard>
        </CCol>
      </CRow>

      <!-- Student identity (below cards) -->
      <CRow class="align-items-center mb-3 g-2">
        <CCol>
          <h4 class="fw-bold mb-0 fs-5">{{ student.full_name }}</h4>
          <div class="text-muted small">
            {{ student.admission_number }}
            <span v-if="student.school_class"> &middot; {{ student.school_class?.name }}</span>
            <span v-if="student.school"> &middot; {{ student.school?.name }}</span>
          </div>
        </CCol>
        <CCol xs="auto">
          <CBadge
            :color="student.status === 'active' ? 'success' : student.status === 'graduated' ? 'info' : 'secondary'"
            class="p-2 fs-6">
            {{ statusLabel(student.status) }}
          </CBadge>
        </CCol>
      </CRow>

      <!-- Tabs -->
      <CNav variant="tabs" class="mb-0 flex-nowrap overflow-auto">
        <CNavItem v-for="tab in tabs" :key="tab.key">
          <CNavLink
            :active="activeTab === tab.key"
            @click="activeTab = tab.key"
            style="cursor:pointer; white-space:nowrap; min-height:44px; display:flex; align-items:center;">
            {{ tab.label }}
          </CNavLink>
        </CNavItem>
      </CNav>

      <CCard class="border-top-0 rounded-0 rounded-bottom">
        <CCardBody class="p-2 p-md-3">

          <!-- Tab: Muhtasari -->
          <div v-if="activeTab === 'muhtasari'">
            <!-- Photo + quick profile header -->
            <div class="d-flex align-items-center gap-3 mb-3 p-3 rounded" style="background:#f8fffe;">
              <div style="flex-shrink:0;">
                <img v-if="student.photo" :src="student.photo"
                     class="rounded-circle border shadow-sm"
                     style="width:80px;height:80px;object-fit:cover;" alt="Picha" />
                <div v-else class="rounded-circle border d-flex align-items-center justify-content-center bg-light"
                     style="width:80px;height:80px;font-size:2.2rem;color:#adb5bd;">👤</div>
              </div>
              <div>
                <div class="fw-bold fs-5">{{ student.full_name }}</div>
                <div class="text-muted small">{{ student.admission_number }}</div>
                <div class="d-flex gap-2 flex-wrap mt-1">
                  <CBadge v-if="student.blood_group" color="danger" style="font-size:.72rem;">
                    🩸 {{ student.blood_group }}
                  </CBadge>
                  <CBadge v-if="student.religion" color="light" style="font-size:.72rem;color:#555;">
                    🕌 {{ student.religion }}
                  </CBadge>
                  <CBadge v-if="student.nationality" color="light" style="font-size:.72rem;color:#555;">
                    🌍 {{ student.nationality }}
                  </CBadge>
                </div>
              </div>
            </div>

            <CRow class="g-3">
              <!-- Personal info -->
              <CCol xs="12" md="4">
                <CCard class="h-100">
                  <CCardHeader class="fw-semibold small py-2" style="background:#f8fffe;">
                    👤 Taarifa za Mwanafunzi
                  </CCardHeader>
                  <CCardBody class="p-2">
                    <InfoRow :label="t('students.gender')" :value="(student.gender === 'me' || student.gender === 'male') ? t('students.male') : (student.gender === 'ke' || student.gender === 'female') ? t('students.female') : '—'" />
                    <InfoRow :label="t('students.dob')" :value="student.date_of_birth || '—'" />
                    <InfoRow :label="t('students.admitDate')" :value="student.admitted_at || student.admission_date || '—'" />
                    <InfoRow :label="t('common.status')" :value="statusLabel(student.status)" />
                    <InfoRow v-if="student.birth_certificate_no" label="Cheti cha Kuzaliwa" :value="student.birth_certificate_no" />
                    <InfoRow v-if="student.notes" :label="t('common.notes')" :value="student.notes" />
                  </CCardBody>
                </CCard>
              </CCol>

              <!-- Health info -->
              <CCol xs="12" md="4">
                <CCard class="h-100">
                  <CCardHeader class="fw-semibold small py-2" style="background:#fff8f8;">
                    🩺 Taarifa za Kiafya
                  </CCardHeader>
                  <CCardBody class="p-2">
                    <InfoRow label="Kundi la Damu" :value="student.blood_group || 'Haijulikani'" />
                    <InfoRow label="Mzio (Allergies)" :value="student.allergies || 'Hakuna'" />
                    <InfoRow label="Matatizo ya Kiafya" :value="student.medical_conditions || 'Hakuna'" />
                  </CCardBody>
                </CCard>
              </CCol>

              <!-- Address info -->
              <CCol xs="12" md="4">
                <CCard class="h-100">
                  <CCardHeader class="fw-semibold small py-2" style="background:#f8f8ff;">
                    📍 Makazi & Anwani
                  </CCardHeader>
                  <CCardBody class="p-2">
                    <InfoRow v-if="student.address" label="Anwani" :value="student.address" />
                    <InfoRow v-if="student.region" label="Mkoa" :value="student.region" />
                    <InfoRow v-if="student.district" label="Wilaya" :value="student.district" />
                    <InfoRow v-if="student.ward" label="Kata" :value="student.ward" />
                    <InfoRow v-if="student.street" label="Mtaa/Kijiji" :value="student.street" />
                    <div v-if="!student.address && !student.region && !student.district"
                         class="text-muted small text-center py-2">Hakuna taarifa za makazi</div>
                  </CCardBody>
                </CCard>
              </CCol>

              <!-- Quick actions -->
              <CCol xs="12">
                <CCard>
                  <CCardHeader class="fw-semibold small py-2">{{ t('students.summary.quickActions') }}</CCardHeader>
                  <CCardBody class="d-flex gap-2 flex-wrap p-2">
                    <CButton color="primary" size="sm" @click="activeTab = 'ankara'" style="min-height:40px;">
                      📄 {{ t('students.summary.viewInvoices') }}
                    </CButton>
                    <CButton color="success" variant="outline" size="sm" @click="activeTab = 'malipo'" style="min-height:40px;">
                      💰 {{ t('students.summary.paymentHistory') }}
                    </CButton>
                    <CButton color="warning" size="sm" @click="activeTab = 'ahadi'; $nextTick(() => scrollToPromiseForm())" style="min-height:40px;">
                      🤝 {{ t('students.summary.recordPromise') }}
                    </CButton>
                    <RouterLink :to="`/wanafunzi/clearance?student_id=${student.id}`"
                                class="btn btn-info btn-sm" style="min-height:40px;display:flex;align-items:center;">
                      ✅ {{ t('clearance.title') }}
                    </RouterLink>
                  </CCardBody>
                </CCard>
              </CCol>
            </CRow>
          </div>

          <!-- Tab: Ankara -->
          <div v-if="activeTab === 'ankara'">
            <!-- Mobile cards -->
            <div class="d-md-none">
              <div v-if="!student.invoices?.length" class="text-center text-muted py-4">{{ t('invoices.noInvoices') }}</div>
              <div v-for="inv in student.invoices" :key="inv.id"
                   class="mb-2 p-3 rounded border"
                   :class="inv.status === 'paid' ? 'border-success' : inv.status === 'partial' ? 'border-warning' : 'border-danger'">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <div class="fw-semibold small">{{ inv.invoice_number }}</div>
                    <div class="text-muted small">{{ inv.term?.name }}</div>
                  </div>
                  <StatusBadge :status="inv.status" />
                </div>
                <div class="d-flex justify-content-between mt-2">
                  <span class="small">{{ t('invoices.debt') }}: <strong class="text-danger">{{ formatMoney(inv.balance_due_cents) }}</strong></span>
                  <CButton v-if="inv.status !== 'paid'" size="sm" color="primary"
                           @click="openPayment(inv)" style="min-height:44px;">{{ t('payments.pay') }}</CButton>
                </div>
              </div>
            </div>
            <!-- Desktop table -->
            <CTable responsive hover small class="mb-0 d-none d-md-table">
              <CTableHead class="table-light">
                <CTableRow>
                  <CTableHeaderCell>{{ t('invoices.invoiceNo') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('common.term') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('common.total') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('invoices.amountPaid') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('invoices.debt') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('common.status') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('common.actions') }}</CTableHeaderCell>
                </CTableRow>
              </CTableHead>
              <CTableBody>
                <CTableRow v-for="inv in student.invoices" :key="inv.id">
                  <CTableDataCell>{{ inv.invoice_number }}</CTableDataCell>
                  <CTableDataCell>{{ inv.term?.name }}</CTableDataCell>
                  <CTableDataCell>{{ formatMoney(inv.total_amount_cents) }}</CTableDataCell>
                  <CTableDataCell>{{ formatMoney(inv.paid_cents) }}</CTableDataCell>
                  <CTableDataCell class="text-danger fw-semibold">{{ formatMoney(inv.balance_due_cents) }}</CTableDataCell>
                  <CTableDataCell><StatusBadge :status="inv.status" /></CTableDataCell>
                  <CTableDataCell class="text-nowrap">
                    <CButton size="sm" color="warning" variant="outline" class="me-1"
                             @click="openEditInvoice(inv)" style="min-height:36px;">
                      ✏️ {{ t('common.edit') }}
                    </CButton>
                    <CButton v-if="inv.status !== 'paid'" size="sm" color="primary"
                             @click="openPayment(inv)" style="min-height:36px;">{{ t('payments.pay') }}</CButton>
                  </CTableDataCell>
                </CTableRow>
                <CTableRow v-if="!student.invoices?.length">
                  <CTableDataCell colspan="7" class="text-center text-muted py-3">{{ t('invoices.noInvoices') }}</CTableDataCell>
                </CTableRow>
              </CTableBody>
            </CTable>
          </div>

          <!-- Tab: Malipo -->
          <div v-if="activeTab === 'malipo'">
            <div v-if="paymentsLoading" class="text-center py-4"><CSpinner size="sm" /></div>
            <div v-else>
              <!-- Mobile -->
              <div class="d-md-none">
                <div v-if="!payments.length" class="text-center text-muted py-4">{{ t('payments.noPayments') }}</div>
                <div v-for="p in payments" :key="p.id" class="mb-2 p-3 border rounded">
                  <div class="d-flex justify-content-between">
                    <div>
                      <div class="fw-semibold text-success mb-1">{{ formatMoney(p.amount_cents) }}</div>
                      <div class="small fw-bold text-primary" v-if="p.invoice">
                        {{ p.invoice.invoice_number }} 
                        <span class="text-body-secondary fw-normal">· {{ p.invoice.term }}</span>
                      </div>
                      <div class="small text-muted">{{ p.paid_at }} &middot; {{ p.method }}</div>
                      <div v-if="p.reference_number" class="small text-muted">Ref: {{ p.reference_number }}</div>
                    </div>
                    <CButton v-if="p.receipt_id" size="sm" color="secondary" variant="outline"
                             @click="downloadReceipt(p.receipt_id)" style="min-height:44px;">
                      <CIcon icon="cilPrint" />
                    </CButton>
                  </div>
                </div>
              </div>
              <!-- Desktop -->
              <CTable responsive hover small class="mb-0 d-none d-md-table">
                <CTableHead class="table-light">
                  <CTableRow>
                    <CTableHeaderCell>{{ t('common.date') }}</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('payments.invoice', 'Invoice Details') }}</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('payments.amount') }}</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('payments.method') }}</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('payments.reference') }}</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('common.actions') }}</CTableHeaderCell>
                  </CTableRow>
                </CTableHead>
                <CTableBody>
                  <CTableRow v-for="p in payments" :key="p.id">
                    <CTableDataCell>{{ p.paid_at }}</CTableDataCell>
                    <CTableDataCell>
                      <div v-if="p.invoice">
                        <div class="fw-semibold text-primary">{{ p.invoice.invoice_number }}</div>
                        <div class="small text-muted">{{ p.invoice.term || '—' }} ({{ p.invoice.academic_year || '' }})</div>
                      </div>
                      <span v-else class="text-muted">—</span>
                    </CTableDataCell>
                    <CTableDataCell class="fw-semibold text-success">{{ formatMoney(p.amount_cents) }}</CTableDataCell>
                    <CTableDataCell>{{ p.method }}</CTableDataCell>
                    <CTableDataCell>{{ p.reference_number || '—' }}</CTableDataCell>
                    <CTableDataCell class="text-nowrap">
                      <CButton v-if="p.receipt_id" size="sm" color="secondary" variant="outline"
                               @click="downloadReceipt(p.receipt_id)" style="min-height:36px;">
                        <CIcon icon="cilPrint" />
                      </CButton>
                      <CButton size="sm" color="warning" variant="outline" class="ms-1"
                               @click="openEditPayment(p)" style="min-height:36px;">✏️</CButton>
                      <!-- Reversal reduces recorded collections and there is a
                           receipt in someone's hands, so it is superadmin-only. -->
                      <CButton v-if="auth.isSuperAdmin" size="sm" color="danger" variant="outline" class="ms-1"
                               @click="reverseTarget = p" style="min-height:36px;">🗑️</CButton>
                    </CTableDataCell>
                  </CTableRow>
                  <CTableRow v-if="!payments.length">
                    <CTableDataCell colspan="5" class="text-center text-muted py-3">{{ t('payments.noPayments') }}</CTableDataCell>
                  </CTableRow>
                </CTableBody>
              </CTable>
            </div>
          </div>

          <!-- Tab: Walezi -->
          <div v-if="activeTab === 'walezi'">
            <div v-if="!student.guardians?.length" class="text-center text-muted py-4">{{ t('students.noGuardians') }}</div>
            <CRow class="g-3">
              <CCol xs="12" sm="6" v-for="g in student.guardians" :key="g.id">
                <CCard class="h-100">
                  <CCardBody>
                    <div class="d-flex justify-content-between align-items-start">
                      <div class="fw-bold">{{ g.full_name }}</div>
                      <CBadge v-if="g.is_primary" color="primary">{{ t('students.primaryGuardian') }}</CBadge>
                    </div>
                    <div class="small text-muted mt-1">
                      <div>{{ t('common.phone') }}: {{ g.phone || '—' }}</div>
                      <div>{{ t('guardians.relation') }}: {{ g.relation || '—' }}</div>
                      <div v-if="g.email">{{ t('common.email') }}: {{ g.email }}</div>
                    </div>
                  </CCardBody>
                </CCard>
              </CCol>
            </CRow>
          </div>

          <!-- Tab: Installments -->
          <div v-if="activeTab === 'installments'">
            <div v-if="installmentsLoading" class="text-center py-4"><CSpinner size="sm" /></div>
            <div v-else>

              <!-- Plan groups -->
              <div v-if="installments.length">
                <div v-for="plan in installmentGroups" :key="plan.invoice_id" class="mb-4">

                  <!-- Group header card -->
                  <div class="p-3 rounded-top d-flex justify-content-between align-items-start flex-wrap gap-2"
                       style="background:linear-gradient(135deg,#f8fffe 0%,#e8f5ee 100%); border:1px solid #c3e6cb; border-bottom:none;">
                    <div>
                      <div class="fw-bold" style="color:#007f3e;">{{ plan.invoice_number }}</div>
                      <div class="small text-muted mt-1">
                        {{ plan.total }} awamu ·
                        {{ formatMoney(plan.amount_each) }} kila moja ·
                        Malipo {{ plan.paid_count }}/{{ plan.total }}
                      </div>
                    </div>
                    <CBadge :color="plan.all_paid ? 'success' : 'warning'" class="px-2 py-1">
                      {{ plan.all_paid ? '✓ Imekamilika' : '⏳ Inaendelea' }}
                    </CBadge>
                  </div>

                  <!-- Progress bar -->
                  <div style="border-left:1px solid #c3e6cb; border-right:1px solid #c3e6cb; padding:8px 12px; background:#f8fffe;">
                    <div class="d-flex justify-content-between mb-1" style="font-size:.72rem; color:#6c757d;">
                      <span>Maendeleo ya Malipo</span>
                      <span class="fw-semibold">{{ Math.round(plan.paid_count / plan.total * 100) }}%</span>
                    </div>
                    <CProgress :value="Math.round(plan.paid_count / plan.total * 100)"
                               :color="plan.all_paid ? 'success' : 'warning'"
                               style="height:10px;" />
                    <div class="d-flex justify-content-between mt-1" style="font-size:.68rem; color:#6c757d;">
                      <span>Kimelipwa: {{ formatMoney(plan.total_paid_cents) }}</span>
                      <span class="text-danger">Kinabaki: {{ formatMoney(plan.total_remaining_cents) }}</span>
                    </div>
                  </div>

                  <!-- Individual installment rows -->
                  <div style="border:1px solid #c3e6cb; border-top:none; border-radius:0 0 8px 8px; overflow:hidden;">
                    <!-- Header row -->
                    <div class="d-flex align-items-center px-3 py-1"
                         style="background:#e8f5ee; font-size:.68rem; font-weight:600; color:#495057; text-transform:uppercase; letter-spacing:.04em; border-bottom:1px solid #c3e6cb;">
                      <div style="width:60px;">#</div>
                      <div style="flex:1;">Tarehe</div>
                      <div style="width:130px; text-align:right;">Kiasi Kinachohitajika</div>
                      <div style="width:130px; text-align:right;">Kilicholipwa</div>
                      <div style="width:80px; text-align:center;">Hali</div>
                      <div style="width:64px;"></div>
                    </div>
                    <div v-for="item in plan.items" :key="item.id"
                         class="d-flex align-items-center px-3 py-2"
                         :style="installmentRowStyle(item) + '; border-top:1px solid rgba(0,0,0,.04);'">
                      <!-- Number -->
                      <div style="width:60px;" class="fw-semibold small">#{{ item.installment_number }}</div>
                      <!-- Due date -->
                      <div style="flex:1; font-size:.78rem;">
                        <span>{{ item.due_date }}</span>
                        <span v-if="isOverdue(item.due_date) && item.status !== 'paid'"
                              class="ms-1 badge bg-danger" style="font-size:.62rem;">Imechelewa</span>
                      </div>
                      <!-- Expected amount -->
                      <div style="width:130px; text-align:right; font-size:.78rem;">
                        {{ formatMoney(item.installment_amount_cents) }}
                      </div>
                      <!-- Paid amount -->
                      <div style="width:130px; text-align:right; font-size:.78rem;">
                        <span v-if="item.status === 'paid'" class="text-success fw-semibold">
                          ✓ {{ formatMoney(item.paid_amount_cents) }}
                        </span>
                        <span v-else-if="item.status === 'partial'" class="text-warning fw-semibold">
                          {{ formatMoney(item.paid_amount_cents) }}
                          <br><span class="text-danger" style="font-size:.68rem;">
                            –{{ formatMoney(item.installment_amount_cents - item.paid_amount_cents) }}
                          </span>
                        </span>
                        <span v-else class="text-muted">—</span>
                      </div>
                      <!-- Status badge -->
                      <div style="width:80px; text-align:center;">
                        <span class="badge" style="font-size:.65rem;"
                              :class="item.status === 'paid' ? 'bg-success'
                                    : item.status === 'partial' ? 'bg-warning text-dark'
                                    : isOverdue(item.due_date) ? 'bg-danger' : 'bg-secondary'">
                          {{ item.status === 'paid' ? 'Imelipwa'
                           : item.status === 'partial' ? 'Sehemu'
                           : isOverdue(item.due_date) ? 'Imechelewa' : 'Inasubiri' }}
                        </span>
                      </div>
                      <!-- Pay button -->
                      <div style="width:64px; text-align:right;">
                        <CButton v-if="item.status !== 'paid'" size="sm" color="success" variant="outline"
                                 style="font-size:.68rem; padding:2px 8px; min-height:28px;"
                                 @click="openInstallmentPay(item)">
                          Lipa
                        </CButton>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Empty state — compact, no gap before form -->
              <div v-if="!installments.length" class="text-muted small mb-2 d-flex align-items-center gap-2">
                <span style="font-size:1rem;">📋</span> Hakuna mipango ya awamu.
              </div>

              <!-- Create new plan (only shows when invoice has balance and no existing plan) -->
              <div v-if="unpaidInvoices.length" class="rounded overflow-hidden"
                   style="border:1px solid #c3e6cb;">
                <div class="px-3 py-2" style="background:linear-gradient(135deg,#f8fffe,#e8f5ee); border-bottom:1px solid #c3e6cb;">
                  <span class="fw-bold small" style="color:#007f3e;">➕ Unda Mpango Mpya wa Awamu</span>
                </div>
                <div class="p-3" style="background:#fff;">
                  <div class="mb-2">
                    <label class="form-label small fw-semibold">Chagua Ankara</label>
                    <select class="form-select form-select-sm" v-model="newPlan.invoice_id">
                      <option value="">-- Chagua Ankara --</option>
                      <option v-for="inv in unpaidInvoices" :key="inv.id" :value="inv.id">
                        {{ inv.invoice_number }} · Deni: {{ formatMoney(inv.balance_due_cents) }}
                      </option>
                    </select>
                  </div>
                  <!-- Auto-preview when invoice selected -->
                  <div v-if="newPlan.invoice_id" class="mb-3 p-2 rounded" style="background:#f8fffe; border:1px solid #e0f2ec;">
                    <div class="small text-muted mb-1">Deni linalobaki:</div>
                    <div class="fw-bold" style="color:#007f3e;">{{ formatMoney(getInvoiceBalance(newPlan.invoice_id)) }}</div>
                    <div v-if="newPlan.total_installments >= 2" class="small text-muted mt-1">
                      → Kila awamu ≈
                      <strong>{{ formatMoney(Math.ceil(getInvoiceBalance(newPlan.invoice_id) / newPlan.total_installments)) }}</strong>
                    </div>
                  </div>
                  <div class="row g-2 mb-3">
                    <div class="col-4">
                      <label class="form-label small fw-semibold">Idadi ya Awamu</label>
                      <input type="number" class="form-control form-control-sm" v-model.number="newPlan.total_installments" min="2" max="12" />
                      <div class="text-muted" style="font-size:.65rem;">Min 2, Max 12</div>
                    </div>
                    <div class="col-4">
                      <label class="form-label small fw-semibold">Siku Kati</label>
                      <input type="number" class="form-control form-control-sm" v-model.number="newPlan.interval_days" min="7" max="90" />
                      <div class="text-muted" style="font-size:.65rem;">7–90 siku</div>
                    </div>
                    <div class="col-4">
                      <label class="form-label small fw-semibold">Tarehe ya Kwanza</label>
                      <input type="date" class="form-control form-control-sm" v-model="newPlan.start_date" />
                    </div>
                  </div>
                  <CAlert v-if="createPlanError" color="danger" class="py-2 small mb-2">{{ createPlanError }}</CAlert>
                  <CButton color="success" size="sm"
                           :disabled="creatingPlan || !newPlan.invoice_id || newPlan.total_installments < 2 || !newPlan.start_date"
                           @click="createInstallmentPlan"
                           style="min-height:36px; background:#007f3e; border-color:#007f3e;">
                    <CSpinner v-if="creatingPlan" size="sm" class="me-1" />
                    Unda Mpango
                  </CButton>
                </div>
              </div>

            </div>
          </div>
          <!-- ── Tab: Ahadi za Malipo ────────────────────────────────────────── -->
          <div v-if="activeTab === 'ahadi'">

            <!-- Existing promises list -->
            <div v-if="promisesLoading" class="text-center py-4"><CSpinner size="sm" /></div>
            <div v-else>
              <div v-if="promises.length" class="mb-3">
                <div v-for="p in promises" :key="p.id"
                     class="d-flex align-items-start gap-2 p-2 mb-2 rounded border"
                     :style="promiseRowStyle(p)">
                  <div style="flex:1; min-width:0;">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                      <span class="fw-semibold small">{{ p.invoice?.invoice_number || '—' }}</span>
                      <CBadge :color="p.status === 'kept' ? 'success' : p.status === 'broken' ? 'danger' : isPromiseOverdue(p) ? 'warning' : 'info'" style="font-size:.65rem;">
                        {{ p.status === 'kept' ? t('students.promises.statusKept') : p.status === 'broken' ? t('students.promises.statusBroken') : isPromiseOverdue(p) ? t('students.promises.statusOverdue') : t('students.promises.statusPending') }}
                      </CBadge>
                    </div>
                    <div class="small text-muted mt-1">
                      📅 {{ p.promised_date }} &nbsp;·&nbsp; 💰 {{ formatMoney(p.amount_cents) }}
                      <span v-if="p.guardian"> &nbsp;·&nbsp; 👤 {{ p.guardian.first_name }}</span>
                    </div>
                    <div v-if="p.notes" class="small text-muted fst-italic mt-1">{{ p.notes }}</div>
                  </div>
                  <div class="d-flex gap-1 flex-shrink-0" v-if="p.status === 'pending'">
                    <CButton size="sm" color="success" variant="outline" style="font-size:.7rem; padding:2px 6px;"
                             @click="markPromise(p.id, 'kept')">{{ t('students.promises.markKept') }}</CButton>
                    <CButton size="sm" color="danger"  variant="outline" style="font-size:.7rem; padding:2px 6px;"
                             @click="markPromise(p.id, 'broken')">{{ t('students.promises.markBroken') }}</CButton>
                  </div>
                </div>
              </div>
              <div v-else class="text-muted small mb-2 d-flex align-items-center gap-2">
                <span>🤝</span> {{ t('students.promises.noPromises') }}
              </div>
            </div>

            <!-- Record new promise form -->
            <div class="rounded overflow-hidden" style="border:1px solid #c3e6cb;">
              <div class="px-3 py-2" style="background:linear-gradient(135deg,#f8fffe,#e8f5ee); border-bottom:1px solid #c3e6cb;">
                <span class="fw-bold small" style="color:#007f3e;">{{ t('students.promises.newTitle') }}</span>
              </div>
              <div class="p-3" style="background:#fff;" ref="promiseFormRef">
                <CAlert v-if="promiseError" color="danger" class="py-2 mb-2 small">{{ promiseError }}</CAlert>
                <div class="row g-2 mb-2">
                  <!-- Invoice — full width -->
                  <div class="col-12">
                    <label class="form-label small fw-semibold mb-1">{{ t('students.promises.selectInvoice') }}</label>
                    <select class="form-select form-select-sm" v-model="newPromise.invoice_id">
                      <option value="">{{ t('students.promises.invoicePlaceholder') }}</option>
                      <option v-for="inv in overdueInvoices" :key="inv.id" :value="inv.id">
                        {{ inv.invoice_number }} · {{ t('invoices.debt') }}: {{ formatMoney(inv.balance_due_cents) }}
                      </option>
                    </select>
                  </div>
                  <!-- 3-column row: Guardian | Amount | Date -->
                  <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold mb-1">{{ t('students.promises.selectGuardian') }}</label>
                    <select class="form-select form-select-sm" v-model="newPromise.guardian_id">
                      <option value="">{{ t('students.promises.guardianPlaceholder') }}</option>
                      <option v-for="g in studentGuardians" :key="g.id" :value="g.id">
                        {{ g.full_name }} {{ g.phone ? `(${g.phone})` : '' }}
                      </option>
                    </select>
                  </div>
                  <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold mb-1">{{ t('students.promises.amount') }}</label>
                    <input type="number" class="form-control form-control-sm" v-model.number="newPromise.amount"
                           min="1" placeholder="0" />
                  </div>
                  <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold mb-1">{{ t('students.promises.date') }}</label>
                    <input type="date" class="form-control form-control-sm" v-model="newPromise.promised_date"
                           :min="today" />
                  </div>
                  <!-- Notes — full width -->
                  <div class="col-12">
                    <label class="form-label small fw-semibold mb-1">{{ t('students.promises.notes') }}</label>
                    <input type="text" class="form-control form-control-sm" v-model="newPromise.notes"
                           :placeholder="t('students.promises.notesPlaceholder')" />
                  </div>
                </div>
                <!-- SMS notice -->
                <div class="small text-muted mb-2" style="font-size:.72rem;">
                  {{ t('students.promises.smsHint') }}
                </div>
                <CButton color="success" size="sm"
                         :disabled="savingPromise || !newPromise.invoice_id || !newPromise.amount || !newPromise.promised_date"
                         @click="savePromise"
                         style="background:#007f3e; border-color:#007f3e; min-height:36px;">
                  <CSpinner v-if="savingPromise" size="sm" class="me-1" />
                  {{ t('students.promises.save') }}
                </CButton>
              </div>
            </div>
          </div>

        </CCardBody>
      </CCard>
    </div>

    <div v-else class="text-center py-5 text-muted">{{ t('students.notFound') }}</div>

    <!-- Invoice Payment Modal -->
    <LipiaModal
      :visible="showLipiaModal"
      :invoice="selectedInvoice"
      @close="showLipiaModal = false; selectedInvoice = null"
      @paid="onPaid"
    />

    <!-- Installment Payment Modal -->
    <CModal :visible="showInstPayModal" @close="closeInstPay" backdrop="static" class="modal-fullscreen-sm-down">
      <CModalHeader style="border-bottom:2px solid #007f3e;">
        <CModalTitle class="fw-bold" style="font-size:1rem;">
          {{ t('installments.payInstallmentTitle', { n: selectedInstallment?.installment_number }) }}
        </CModalTitle>
      </CModalHeader>
      <CModalBody v-if="selectedInstallment" class="p-3 p-md-4">
        <CAlert v-if="instPayError" color="danger" class="py-2 mb-3">{{ instPayError }}</CAlert>

        <!-- Info card -->
        <div class="rounded p-3 mb-3" style="background:#f8fffe; border:1px solid #e0f2ec;">
          <div class="d-flex justify-content-between mb-1 small">
            <span class="text-muted">{{ t('installments.installmentLabel') }}</span>
            <strong>#{{ selectedInstallment.installment_number }} / {{ selectedInstallment.total_installments }}</strong>
          </div>
          <div class="d-flex justify-content-between mb-1 small">
            <span class="text-muted">{{ t('installments.paymentDueDate') }}</span>
            <span>{{ selectedInstallment.due_date }}</span>
          </div>
          <div class="d-flex justify-content-between mb-1 small">
            <span class="text-muted">{{ t('installments.amountRequired') }}</span>
            <span class="fw-semibold">{{ formatMoney(selectedInstallment.installment_amount_cents) }}</span>
          </div>
          <div v-if="selectedInstallment.paid_amount_cents > 0" class="d-flex justify-content-between mb-1 small">
            <span class="text-muted">{{ t('installments.amountAlreadyPaid') }}</span>
            <span class="text-success">{{ formatMoney(selectedInstallment.paid_amount_cents) }}</span>
          </div>
          <hr class="my-2">
          <div class="d-flex justify-content-between">
            <span class="fw-bold">{{ t('installments.balanceRemaining') }}</span>
            <span class="fw-bold fs-6 text-danger">
              {{ formatMoney(selectedInstallment.installment_amount_cents - selectedInstallment.paid_amount_cents) }}
            </span>
          </div>
        </div>

        <!-- Custom amount toggle -->
        <div class="mb-2 d-flex justify-content-between align-items-center">
          <label class="form-label small fw-semibold mb-0">{{ t('installments.payCustomAmount') }}</label>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" role="switch" v-model="instCustomize"
                   style="width:2em; height:1.2em; cursor:pointer;" @change="instCustomAmount = ''" />
          </div>
        </div>
        <Transition name="slide-fade">
          <div v-if="instCustomize" class="mb-2">
            <CFormInput type="number" v-model.number="instCustomAmount"
                        :min="1"
                        :max="Math.round((selectedInstallment.installment_amount_cents - selectedInstallment.paid_amount_cents) / 100)"
                        :placeholder="`Max: ${formatMoney(selectedInstallment.installment_amount_cents - selectedInstallment.paid_amount_cents)}`"
                        style="font-size:1rem;" />
            <div v-if="instCustomAmount > 0" class="small text-success mt-1">
              ✔ {{ t('installments.confirmAmountBtn', { amount: Number(instCustomAmount).toLocaleString() }) }}
            </div>
          </div>
        </Transition>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" @click="closeInstPay" :disabled="instPaying">{{ t('common.cancel') }}</CButton>
        <CButton color="success" :disabled="instPaying || (instCustomize && !instCustomAmount)"
                 @click="confirmInstPay"
                 style="min-width:120px; background:#007f3e; border-color:#007f3e;">
          <CSpinner v-if="instPaying" size="sm" class="me-1" />
          <span v-else>{{ instCustomize && instCustomAmount ? t('installments.confirmAmountBtn', { amount: Number(instCustomAmount).toLocaleString() }) : t('installments.confirmPayBtn') }}</span>
        </CButton>
      </CModalFooter>
    </CModal>
    <!-- Payment correction. Amount, method, reference and date are the fields
         that get mis-keyed; the invoice it belongs to is not editable, since
         moving a payment between invoices is a transfer, not a correction. -->
    <CModal :visible="!!editPayment" size="sm" alignment="center" backdrop="static"
            @close="editPayment = null">
      <CModalHeader><CModalTitle>{{ t('payments.editTitle') }}</CModalTitle></CModalHeader>
      <CModalBody>
        <div class="small text-muted mb-2">{{ editPayment?.invoice?.invoice_number }}</div>

        <label class="form-label fw-semibold small mb-1">{{ t('payments.amount') }} *</label>
        <CFormInput v-model="payAmountDisplay" type="text" inputmode="numeric"
                    autocomplete="off" class="mb-2" @keyup.enter="savePayment" />

        <label class="form-label fw-semibold small mb-1">{{ t('payments.method') }}</label>
        <CFormSelect v-model="payForm.method" class="mb-2">
          <option value="cash">Cash</option>
          <option value="mpesa">M-Pesa</option>
          <option value="bank">Bank</option>
          <option value="cheque">Cheque</option>
        </CFormSelect>

        <label class="form-label fw-semibold small mb-1">{{ t('payments.reference') }}</label>
        <CFormInput v-model="payForm.reference_number" class="mb-2" />

        <label class="form-label fw-semibold small mb-1">{{ t('common.date') }}</label>
        <CFormInput v-model="payForm.paid_at" type="date" />

        <CAlert v-if="payError" color="danger" class="py-2 mt-2 mb-0 small">{{ payError }}</CAlert>
      </CModalBody>
      <CModalFooter class="gap-2">
        <CButton color="secondary" variant="outline" :disabled="paySaving" @click="editPayment = null">
          {{ t('common.cancel') }}
        </CButton>
        <CButton color="primary" :disabled="paySaving" @click="savePayment">
          <CSpinner v-if="paySaving" size="sm" class="me-1" />{{ t('common.save') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <CModal :visible="!!reverseTarget" size="sm" alignment="center" @close="reverseTarget = null">
      <CModalHeader><CModalTitle>{{ t('payments.reverseTitle') }}</CModalTitle></CModalHeader>
      <CModalBody class="small">
        {{ t('payments.reverseBody', { amount: formatMoney(reverseTarget?.amount_cents) }) }}
      </CModalBody>
      <CModalFooter class="gap-2">
        <CButton color="secondary" @click="reverseTarget = null">{{ t('common.cancel') }}</CButton>
        <CButton color="danger" :disabled="paySaving" @click="reversePayment">
          <CSpinner v-if="paySaving" size="sm" class="me-1" />{{ t('payments.reverseAction') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Invoice amount correction. Deliberately amount-only: the term, student
         and number identify the invoice and must not drift. -->
    <CModal :visible="!!editInvoice" size="sm" alignment="center" backdrop="static"
            @close="editInvoice = null">
      <CModalHeader><CModalTitle>{{ t('invoices.editTitle') }}</CModalTitle></CModalHeader>
      <CModalBody>
        <div class="small text-muted mb-2">
          {{ editInvoice?.invoice_number }} · {{ editInvoice?.term?.name }}
        </div>

        <label class="form-label fw-semibold small mb-1">
          {{ t('invoices.totalAmount') }} <span class="text-danger">*</span>
        </label>
        <CFormInput v-model="editAmountDisplay" type="text" inputmode="numeric"
                    autocomplete="off" placeholder="e.g. 200,000" @keyup.enter="saveInvoice" />

        <div v-if="editInvoice && editInvoice.paid_cents > 0" class="form-text small">
          {{ t('invoices.alreadyPaidHint', { paid: formatMoney(editInvoice.paid_cents) }) }}
        </div>

        <CAlert v-if="editError" color="danger" class="py-2 mt-2 mb-0 small">{{ editError }}</CAlert>
      </CModalBody>
      <CModalFooter class="gap-2">
        <CButton color="secondary" variant="outline" :disabled="editSaving" @click="editInvoice = null">
          {{ t('common.cancel') }}
        </CButton>
        <CButton color="primary" :disabled="editSaving" @click="saveInvoice">
          <CSpinner v-if="editSaving" size="sm" class="me-1" />{{ t('common.save') }}
        </CButton>
      </CModalFooter>
    </CModal>
  </CContainer>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useStudentsStore } from '@/stores/students'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'
import { usePaymentsStore } from '@/stores/payments'
import { useInstallmentsStore } from '@/stores/installments'
import StatusBadge from '@/components/StatusBadge.vue'
import InfoRow from '@/components/InfoRow.vue'
import { downloadReceipt as downloadReceiptPdf } from '@/utils/receipt'
import LipiaModal from '@/components/LipiaModal.vue'

const { t } = useI18n()
const route   = useRoute()
const store   = useStudentsStore()
const payStore = usePaymentsStore()
const installStore = useInstallmentsStore()

const auth = useAuthStore()
const student  = computed(() => store.student)
const loading  = computed(() => store.loading)

const activeTab = ref('muhtasari')
const tabs = computed(() => [
  { key: 'muhtasari',    label: t('students.tabs.summary') },
  { key: 'ankara',       label: t('students.tabs.invoices') },
  { key: 'malipo',       label: t('students.tabs.payments') },
  { key: 'walezi',       label: t('students.tabs.guardians') },
  { key: 'installments', label: t('students.tabs.installments') },
  { key: 'ahadi',        label: t('students.promises.tabLabel'), badge: pendingPromisesCount.value || null },
])

const payments            = ref([])
const paymentsLoading     = ref(false)
const installments        = ref([])
const installmentsLoading = ref(false)
const selectedInvoice     = ref(null)
const showLipiaModal      = ref(false)

// Installment payment modal state
const showInstPayModal  = ref(false)
const selectedInstallment = ref(null)
const instPaying        = ref(false)
const instPayError      = ref('')
const instCustomize     = ref(false)
const instCustomAmount  = ref('')

// Installment plan creation
const today = new Date().toISOString().slice(0, 10)

// ── Payment Promises ─────────────────────────────────────────────────────────
const promises          = ref([])
const promisesLoading   = ref(false)
const promiseError      = ref('')
const savingPromise     = ref(false)
const newPromise        = ref({ invoice_id: '', guardian_id: '', amount: '', promised_date: '', notes: '' })
const promiseFormRef    = ref(null)

function scrollToPromiseForm() {
  promiseFormRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const pendingPromisesCount = computed(() => promises.value.filter(p => p.status === 'pending').length)
const overdueInvoices = computed(() => (student.value?.invoices || []).filter(inv => (inv.balance_due_cents || 0) > 0))
const studentGuardians = computed(() => student.value?.guardians || [])

function isPromiseOverdue(p) {
  return p.status === 'pending' && new Date(p.promised_date) < new Date(today)
}
function promiseRowStyle(p) {
  if (p.status === 'kept')    return 'background:rgba(25,135,84,.05); border-color:#c3e6cb!important;'
  if (p.status === 'broken')  return 'background:rgba(220,53,69,.05); border-color:#f5c2c7!important;'
  if (isPromiseOverdue(p))    return 'background:rgba(255,193,7,.08); border-color:#ffc107!important;'
  return 'border-color:#e0f2ec!important;'
}

async function loadPromises() {
  if (!student.value?.id) return
  promisesLoading.value = true
  try {
    const { data } = await api.get('/payment-promises', { params: { student_id: student.value.id, per_page: 100 } })
    promises.value = data.data || data
  } catch { promises.value = [] }
  finally { promisesLoading.value = false }
}

async function savePromise() {
  promiseError.value = ''
  if (!newPromise.value.invoice_id || !newPromise.value.amount || !newPromise.value.promised_date) {
    promiseError.value = t('students.promises.validationMsg')
    return
  }
  savingPromise.value = true
  try {
    await api.post('/payment-promises', {
      student_id:    student.value.id,
      invoice_id:    newPromise.value.invoice_id,
      guardian_id:   newPromise.value.guardian_id || null,
      promised_date: newPromise.value.promised_date,
      amount_cents:  Math.round(newPromise.value.amount * 100),
      notes:         newPromise.value.notes || null,
    })
    newPromise.value = { invoice_id: '', guardian_id: '', amount: '', promised_date: '', notes: '' }
    await loadPromises()
  } catch (e) {
    promiseError.value = e?.response?.data?.message || t('students.promises.saveError')
  } finally { savingPromise.value = false }
}

async function markPromise(id, status) {
  try {
    await api.put(`/payment-promises/${id}`, { status })
    await loadPromises()
  } catch {}
}

const newPlan = ref({ invoice_id: '', total_installments: 3, interval_days: 30, start_date: today })
const creatingPlan  = ref(false)
const createPlanError = ref('')

// Unpaid invoices the student has (for installment plan creation)
const unpaidInvoices = computed(() =>
  (student.value?.invoices || []).filter(inv => (inv.balance_due_cents || 0) > 0)
)

// Group individual installment records by invoice
const installmentGroups = computed(() => {
  const groups = {}
  installments.value.forEach(item => {
    const key = item.invoice_id
    if (!groups[key]) {
      groups[key] = {
        invoice_id: key,
        invoice_number: item.invoice_number || item.invoice?.invoice_number || '—',
        total: 0,
        amount_each: item.installment_amount_cents || 0,
        next_due_date: item.next_due_date || null,
        paid_count: 0,
        total_paid_cents: 0,
        total_remaining_cents: 0,
        all_paid: false,
        items: [],
      }
    }
    groups[key].items.push(item)
    groups[key].total++
    groups[key].total_paid_cents += (item.paid_amount_cents || 0)
    if (item.status === 'paid') groups[key].paid_count++
  })
  Object.values(groups).forEach(g => {
    g.items.sort((a, b) => a.installment_number - b.installment_number)
    g.all_paid = g.paid_count >= g.total && g.total > 0
    const gross = g.items.reduce((s, i) => s + (i.installment_amount_cents || 0), 0)
    g.total_remaining_cents = Math.max(0, gross - g.total_paid_cents)
  })
  return Object.values(groups)
})

const totalInvoiced = computed(() =>
  (student.value?.invoices || []).reduce((s, i) => s + (i.total_amount_cents || 0), 0)
)
const totalPaid = computed(() =>
  (student.value?.invoices || []).reduce((s, i) => s + (i.paid_cents || 0), 0)
)
const totalOutstanding = computed(() =>
  (student.value?.invoices || []).reduce((s, i) => s + (i.balance_due_cents || 0), 0)
)

function formatMoney(cents) {
  return 'TZS ' + Number((cents || 0) / 100).toLocaleString('sw-TZ', { minimumFractionDigits: 0 })
}

function statusLabel(s) {
  const map = {
    active: 'statusBadge.active', transferred: 'statusBadge.transferred',
    graduated: 'statusBadge.graduated', dropped: 'statusBadge.dropped',
  }
  return map[s] ? t(map[s]) : (s || '—')
}

function progressPct(plan) {
  if (!plan.total_installments) return 0
  return Math.round(((plan.installments_paid || 0) / plan.total_installments) * 100)
}

function getInvoiceBalance(invoiceId) {
  const inv = (student.value?.invoices || []).find(i => i.id === invoiceId)
  return inv?.balance_due_cents || 0
}

function isOverdue(dueDateStr) {
  if (!dueDateStr) return false
  return new Date(dueDateStr) < new Date()
}

function installmentRowStyle(item) {
  if (item.status === 'paid')    return 'background:rgba(25,135,84,.05);'
  if (item.status === 'partial') return 'background:rgba(255,193,7,.08);'
  if (isOverdue(item.due_date))  return 'background:rgba(220,53,69,.05);'
  return ''
}

// ── Payment correction ─────────────────────────────────────────────────────
const editPayment = ref(null)
const reverseTarget = ref(null)
const payForm = ref({ amount: '', method: 'cash', reference_number: '', paid_at: '' })
const paySaving = ref(false)
const payError = ref('')

const payAmountDisplay = computed({
  get: () => (payForm.value.amount === '' ? '' : Number(payForm.value.amount).toLocaleString('en-US')),
  set: (raw) => {
    const digits = String(raw ?? '').replace(/[^0-9]/g, '')
    payForm.value.amount = digits === '' ? '' : Number(digits)
  },
})

function openEditPayment(p) {
  editPayment.value = p
  payForm.value = {
    amount: Math.round((p.amount_cents || 0) / 100),
    method: p.method || 'cash',
    reference_number: p.reference_number || '',
    // The list shows a date-time; the input needs a plain date.
    paid_at: String(p.paid_at || '').slice(0, 10),
  }
  payError.value = ''
}

async function savePayment() {
  if (!payForm.value.amount) { payError.value = t('invoices.amountRequired'); return }
  paySaving.value = true
  payError.value = ''
  try {
    await api.put(`/payments/${editPayment.value.id}`, {
      amount_cents: Math.round(payForm.value.amount * 100),
      method: payForm.value.method,
      reference_number: payForm.value.reference_number || null,
      paid_at: payForm.value.paid_at,
    })
    editPayment.value = null
    await refreshAfterMoneyChange()
  } catch (e) {
    payError.value = e?.response?.data?.message
      || Object.values(e?.response?.data?.errors || {})[0]?.[0]
      || t('common.saveFailed')
  } finally {
    paySaving.value = false
  }
}

async function reversePayment() {
  paySaving.value = true
  try {
    await api.delete(`/payments/${reverseTarget.value.id}`)
    reverseTarget.value = null
    await refreshAfterMoneyChange()
  } catch (e) {
    payError.value = e?.response?.data?.message || t('common.saveFailed')
    reverseTarget.value = null
  } finally {
    paySaving.value = false
  }
}

// A payment change moves the invoice balance and its status, so both tabs and
// the header totals are reloaded rather than only the row that was edited.
async function refreshAfterMoneyChange() {
  await store.fetchStudent(route.params.id)
  if (typeof loadPayments === 'function') await loadPayments()
}

// ── Invoice amount correction ──────────────────────────────────────────────
const editInvoice = ref(null)
const editAmount = ref('')
const editSaving = ref(false)
const editError = ref('')

// Grouped while typing, same as the expense amount field: 200000 and 20000 are
// hard to tell apart at a glance, 200,000 and 20,000 are not.
const editAmountDisplay = computed({
  get: () => (editAmount.value === '' ? '' : Number(editAmount.value).toLocaleString('en-US')),
  set: (raw) => {
    const digits = String(raw ?? '').replace(/[^0-9]/g, '')
    editAmount.value = digits === '' ? '' : Number(digits)
  },
})

function openEditInvoice(inv) {
  editInvoice.value = inv
  editAmount.value = Math.round((inv.total_amount_cents || 0) / 100)
  editError.value = ''
}

async function saveInvoice() {
  if (!editAmount.value && editAmount.value !== 0) {
    editError.value = t('invoices.amountRequired')
    return
  }
  editSaving.value = true
  editError.value = ''
  try {
    await api.put(`/invoices/${editInvoice.value.id}`, {
      total_amount_cents: Math.round(editAmount.value * 100),
    })
    editInvoice.value = null
    // Reload the student so the row, its status badge and the summary totals
    // above all reflect the new figure rather than only the cell that changed.
    await store.fetchStudent(route.params.id)
  } catch (e) {
    editError.value = e?.response?.data?.errors?.total_amount_cents?.[0]
      || e?.response?.data?.message
      || t('common.saveFailed')
  } finally {
    editSaving.value = false
  }
}

function openPayment(inv) {
  selectedInvoice.value = { ...inv, student: inv.student || student.value }
  showLipiaModal.value = true
}

function openInstallmentPay(item) {
  selectedInstallment.value = item
  instPayError.value = ''
  instCustomize.value = false
  instCustomAmount.value = ''
  showInstPayModal.value = true
}

function closeInstPay() {
  showInstPayModal.value = false
  instPayError.value = ''
  instCustomize.value = false
  instCustomAmount.value = ''
}

async function confirmInstPay() {
  if (!selectedInstallment.value) return
  instPaying.value = true
  instPayError.value = ''
  try {
    const customCents = instCustomize.value && instCustomAmount.value
      ? Math.round(Number(instCustomAmount.value) * 100) : null
    await installStore.markPaid(
      selectedInstallment.value.id,
      selectedInstallment.value.installment_number,
      customCents
    )
    closeInstPay()
    await loadInstallments()
    await store.fetchStudent(route.params.id)
  } catch (e) {
    instPayError.value = e?.response?.data?.message || 'Imeshindwa kulipa awamu'
  } finally {
    instPaying.value = false
  }
}

function onPaid() {
  showLipiaModal.value = false
  selectedInvoice.value = null
  store.fetchStudent(route.params.id)
  loadPayments()
  loadInstallments()
}

async function downloadReceipt(receiptId) {
  // Opening the URL directly sends no auth token — see utils/receipt.js
  try {
    await downloadReceiptPdf(receiptId)
  } catch (e) {
    console.error('Receipt download failed:', e?.response?.data?.message || e.message)
  }
}

async function loadPayments() {
  paymentsLoading.value = true
  try {
    await payStore.fetchPayments({ student_id: route.params.id })
    payments.value = payStore.payments
  } catch {}
  paymentsLoading.value = false
}

async function loadInstallments() {
  installmentsLoading.value = true
  try {
    await installStore.fetchInstallments({ student_id: route.params.id })
    installments.value = installStore.installments
  } catch {}
  installmentsLoading.value = false
}


async function createInstallmentPlan() {
  createPlanError.value = ''
  creatingPlan.value = true
  try {
    await installStore.createInstallment({
      invoice_id:         newPlan.value.invoice_id,
      total_installments: newPlan.value.total_installments,
      interval_days:      newPlan.value.interval_days,
      start_date:         newPlan.value.start_date,
    })
    newPlan.value = { invoice_id: '', total_installments: 3, interval_days: 30, start_date: today }
    await loadInstallments()
  } catch (e) {
    createPlanError.value = e?.response?.data?.message || 'Imeshindwa kuunda mpango'
  } finally {
    creatingPlan.value = false
  }
}

onMounted(async () => {
  try {
    await store.fetchStudent(route.params.id)
    await loadPayments()
    await loadInstallments()
    await loadPromises()
    // Support ?tab=ahadi (or any valid tab key) from router navigation
    const tabParam = route.query.tab
    if (tabParam) {
      activeTab.value = tabParam
      if (tabParam === 'ahadi') {
        setTimeout(() => scrollToPromiseForm(), 300)
      }
    }
  } catch (e) {
    console.error('MwanafunziDetail error', e)
  }
})
</script>

<style scoped>
.slide-fade-enter-active { transition: all .2s ease-out; }
.slide-fade-leave-active { transition: all .15s ease-in; }
.slide-fade-enter-from, .slide-fade-leave-to { opacity: 0; transform: translateY(-6px); }
</style>
