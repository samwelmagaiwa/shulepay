<template>
  <CContainer fluid class="p-2 p-md-3">

    <!-- ── Search / filter bar ─────────────────────────────────────────────── -->
    <CRow class="g-2 mb-3 align-items-center">
      <CCol xs="12" md="5">
        <CInputGroup>
          <CInputGroupText><CIcon icon="cilSearch" /></CInputGroupText>
          <CFormInput v-model="search" :placeholder="t('schools.searchPlaceholder')" clearable />
        </CInputGroup>
      </CCol>
      <CCol xs="6" md="auto">
        <CFormSelect v-model="filterLevel" style="min-height:38px;">
          <option value="">{{ t('schools.allLevels') }}</option>
          <option value="primary">{{ t('schools.primary') }}</option>
          <option value="secondary">{{ t('schools.secondary') }}</option>
        </CFormSelect>
      </CCol>
      <CCol xs="6" md="auto">
        <CFormSelect v-model="filterStatus" style="min-height:38px;">
          <option value="">{{ t('common.allStatuses') }}</option>
          <option value="active">{{ t('schools.active') }}</option>
          <option value="inactive">{{ t('schools.inactive') }}</option>
        </CFormSelect>
      </CCol>
      <CCol xs="12" md="auto" class="ms-md-auto">
        <CButton color="success" @click="openCreate" class="w-100 w-md-auto" style="min-height:38px;">
          <CIcon icon="cilPlus" class="me-1" /> {{ t('schools.add') }}
        </CButton>
      </CCol>
    </CRow>

    <!-- ── Loading / error ─────────────────────────────────────────────────── -->
    <div v-if="store.loading" class="text-center py-5"><CSpinner color="primary" /></div>
    <CAlert v-else-if="store.error" color="danger">{{ store.error }}</CAlert>

    <template v-else>
      <!-- ── Summary cards ──────────────────────────────────────────────────── -->
      <CRow class="g-3 mb-3">
        <CCol xs="6" md="3">
          <CCard class="stat-card border-0 shadow-sm h-100" style="border-left:4px solid #198754 !important">
            <CCardBody class="p-3">
              <div class="stat-label">{{ t('schools.summary.total') }}</div>
              <div class="stat-value">{{ store.schools.length }}</div>
            </CCardBody>
          </CCard>
        </CCol>
        <CCol xs="6" md="3">
          <CCard class="stat-card border-0 shadow-sm h-100" style="border-left:4px solid #0d6efd !important">
            <CCardBody class="p-3">
              <div class="stat-label">{{ t('schools.active') }}</div>
              <div class="stat-value text-success">{{ store.schools.filter(s => s.is_active).length }}</div>
            </CCardBody>
          </CCard>
        </CCol>
        <CCol xs="6" md="3">
          <CCard class="stat-card border-0 shadow-sm h-100" style="border-left:4px solid #ffc107 !important">
            <CCardBody class="p-3">
              <div class="stat-label">{{ t('schools.primary') }}</div>
              <div class="stat-value">{{ store.schools.filter(s => s.level === 'primary').length }}</div>
            </CCardBody>
          </CCard>
        </CCol>
        <CCol xs="6" md="3">
          <CCard class="stat-card border-0 shadow-sm h-100" style="border-left:4px solid #6f42c1 !important">
            <CCardBody class="p-3">
              <div class="stat-label">{{ t('schools.secondary') }}</div>
              <div class="stat-value">{{ store.schools.filter(s => s.level === 'secondary').length }}</div>
            </CCardBody>
          </CCard>
        </CCol>
      </CRow>

      <!-- ── Desktop table ──────────────────────────────────────────────────── -->
      <CCard class="d-none d-md-block shadow-sm border-0">
        <CCardBody class="p-0">
          <CTable hover responsive class="mb-0 align-middle schools-table">
            <CTableHead class="table-light">
              <CTableRow>
                <CTableHeaderCell style="width:52px;"></CTableHeaderCell>
                <CTableHeaderCell>{{ t('schools.schoolName') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('schools.code') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('schools.level') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('schools.region') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('schools.students') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('common.status') }}</CTableHeaderCell>
                <CTableHeaderCell class="text-center" style="min-width:220px;">{{ t('common.actions') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-if="!filtered.length">
                <CTableDataCell colspan="8" class="text-center text-muted py-5">
                  {{ t('schools.noMatch') }}
                </CTableDataCell>
              </CTableRow>
              <CTableRow v-for="school in filtered" :key="school.id" class="school-row">
                <CTableDataCell>
                  <SchoolLogo :school="school" size="sm" />
                </CTableDataCell>
                <CTableDataCell>
                  <div class="fw-semibold">{{ school.name }}</div>
                  <div class="text-muted small">{{ school.registration_number || '—' }}</div>
                </CTableDataCell>
                <CTableDataCell>
                  <CBadge color="secondary" shape="rounded-pill" class="fw-bold px-2">{{ school.code }}</CBadge>
                </CTableDataCell>
                <CTableDataCell>
                  <CBadge :color="school.level === 'primary' ? 'info' : 'warning'" shape="rounded-pill">
                    {{ school.level === 'primary' ? t('schools.primary') : t('schools.secondary') }}
                  </CBadge>
                </CTableDataCell>
                <CTableDataCell class="small text-muted">{{ school.region || '—' }}</CTableDataCell>
                <CTableDataCell class="fw-semibold">{{ school.students_count ?? '—' }}</CTableDataCell>
                <CTableDataCell>
                  <CBadge :color="school.is_active ? 'success' : 'danger'" shape="rounded-pill">
                    {{ school.is_active ? t('schools.active') : t('schools.inactive') }}
                  </CBadge>
                </CTableDataCell>

                <!-- ── Action buttons ───────────────────────────────────────── -->
                <CTableDataCell class="text-center">
                  <CDropdown placement="bottom-end">
                    <CDropdownToggle color="info" variant="outline" size="sm" :caret="false" style="min-width:36px;">
                      <CIcon icon="cilLowVision" />
                    </CDropdownToggle>
                    <CDropdownMenu>
                      <CDropdownItem @click="openDetail(school)" style="cursor:pointer;" class="d-flex align-items-center gap-2">
                        <CIcon icon="cilLowVision" class="text-info" /> {{ t('common.view') }}
                      </CDropdownItem>
                      <CDropdownItem @click="openEdit(school)" style="cursor:pointer;" class="d-flex align-items-center gap-2">
                        <CIcon icon="cilPencil" class="text-primary" /> {{ t('common.edit') }}
                      </CDropdownItem>
                      <CDropdownItem @click="openManage(school)" style="cursor:pointer;" class="d-flex align-items-center gap-2">
                        <CIcon icon="cilSettings" class="text-success" /> {{ t('common.manage') }}
                      </CDropdownItem>
                      <CDropdownItem @click="toggle(school)" style="cursor:pointer;" class="d-flex align-items-center gap-2">
                        <CIcon :icon="school.is_active ? 'cilBan' : 'cilCheck'" :class="school.is_active ? 'text-warning' : 'text-success'" />
                        {{ school.is_active ? t('schools.inactive') : t('schools.active') }}
                      </CDropdownItem>
                      <CDropdownDivider />
                      <CDropdownItem @click="confirmDelete(school)" style="cursor:pointer;" class="d-flex align-items-center gap-2 text-danger">
                        <CIcon icon="cilTrash" /> {{ t('common.delete') }}
                      </CDropdownItem>
                    </CDropdownMenu>
                  </CDropdown>
                </CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </CCardBody>
      </CCard>

      <!-- ── Mobile cards ───────────────────────────────────────────────────── -->
      <div class="d-md-none">
        <div v-if="!filtered.length" class="text-center text-muted py-5">{{ t('schools.noMatch') }}</div>
        <div v-for="school in filtered" :key="school.id" class="mb-3">
          <CCard class="shadow-sm border-0" :style="`border-left:4px solid ${school.is_active ? '#198754' : '#dc3545'} !important`">
            <CCardBody class="p-3">
              <div class="d-flex align-items-start gap-3">
                <SchoolLogo :school="school" size="lg" />
                <div class="flex-grow-1 min-w-0">
                  <div class="fw-bold text-truncate">{{ school.name }}</div>
                  <div class="d-flex gap-2 flex-wrap mt-1">
                    <CBadge color="secondary" shape="rounded-pill">{{ school.code }}</CBadge>
                    <CBadge :color="school.level === 'primary' ? 'info' : 'warning'" shape="rounded-pill">
                      {{ school.level === 'primary' ? t('schools.primary') : t('schools.secondary') }}
                    </CBadge>
                    <CBadge :color="school.is_active ? 'success' : 'danger'" shape="rounded-pill">
                      {{ school.is_active ? t('schools.active') : t('schools.inactive') }}
                    </CBadge>
                  </div>
                  <div class="text-muted small mt-1">{{ [school.region, school.district].filter(Boolean).join(', ') || '—' }}</div>
                </div>
              </div>
              <div class="d-flex gap-2 mt-3">
                <CButton size="sm" color="info" variant="outline" @click="openDetail(school)" style="flex:1; min-height:40px;">
                  <CIcon icon="cilLowVision" class="me-1" />{{ t('common.view') }}
                </CButton>
                <CButton size="sm" color="primary" variant="outline" @click="openEdit(school)" style="flex:1; min-height:40px;">
                  <CIcon icon="cilPencil" class="me-1" />{{ t('common.edit') }}
                </CButton>
                <CButton size="sm" color="success" variant="outline" @click="openManage(school)" style="flex:1; min-height:40px;">
                  <CIcon icon="cilSettings" class="me-1" />{{ t('common.manage') }}
                </CButton>
              </div>
            </CCardBody>
          </CCard>
        </div>
      </div>
    </template>

    <!-- ═══════════════════════════════════════════════════════════════════════
         DETAIL MODAL — tabbed: Overview / Classes / Academic Years / Users
    ═══════════════════════════════════════════════════════════════════════════ -->
    <CModal :visible="showDetail" @close="showDetail=false" size="xl" class="modal-fullscreen-sm-down" scrollable>
      <CModalHeader>
        <CModalTitle class="d-flex align-items-center gap-2">
          <SchoolLogo v-if="selected" :school="selected" size="sm" />
          {{ selected?.name }}
        </CModalTitle>
      </CModalHeader>
      <CModalBody v-if="selected" class="p-0">
        <CNav variant="tabs" class="px-3 pt-2">
          <CNavItem v-for="(t, i) in detailTabs" :key="i">
            <CNavLink :active="detailTab === i" @click="detailTab = i" style="cursor:pointer;">{{ t }}</CNavLink>
          </CNavItem>
        </CNav>
        <div class="p-3">

          <!-- Tab 0: Overview -->
          <div v-show="detailTab === 0">
            <CRow class="g-3">
              <CCol xs="12" class="d-flex align-items-start gap-3">
                <SchoolLogo :school="selected" size="xl" />
                <div>
                  <h5 class="fw-bold mb-1">{{ selected.name }}</h5>
                  <div class="d-flex flex-wrap gap-2">
                    <CBadge color="secondary" shape="rounded-pill" class="fw-bold">{{ selected.code }}</CBadge>
                    <CBadge :color="selected.level === 'primary' ? 'info' : 'warning'" shape="rounded-pill">
                      {{ selected.level === 'primary' ? 'Msingi' : 'Sekondari' }}
                    </CBadge>
                    <CBadge :color="selected.is_active ? 'success' : 'danger'" shape="rounded-pill">
                      {{ selected.is_active ? 'Inafanya kazi' : 'Imezimwa' }}
                    </CBadge>
                  </div>
                  <div v-if="selected.motto" class="text-muted fst-italic small mt-1">"{{ selected.motto }}"</div>
                </div>
              </CCol>
              <CCol xs="12"><hr class="my-1"></CCol>

              <CCol xs="6" md="3">
                <div class="detail-label">{{ t('schools.students') }}</div>
                <div class="detail-value">{{ selected.students_count ?? '—' }}</div>
              </CCol>
              <CCol xs="6" md="3">
                <div class="detail-label">{{ t('schools.capacity') }}</div>
                <div class="detail-value">{{ selected.capacity ?? '—' }}</div>
              </CCol>
              <CCol xs="6" md="3">
                <div class="detail-label">{{ t('schools.establishedYear') }}</div>
                <div class="detail-value">{{ selected.established_year ?? '—' }}</div>
              </CCol>
              <CCol xs="6" md="3">
                <div class="detail-label">{{ t('schools.registrationNo') }}</div>
                <div class="detail-value">{{ selected.registration_number || '—' }}</div>
              </CCol>
              <CCol xs="12" md="6">
                <div class="detail-label">{{ t('schools.ownerName') }}</div>
                <div class="detail-value">{{ selected.owner_name || '—' }}</div>
              </CCol>
              <CCol xs="12"><hr class="my-1"></CCol>
              <CCol xs="12" md="6"><div class="detail-label">{{ t('schools.phone') }}</div><div class="detail-value">{{ selected.phone || '—' }}</div></CCol>
              <CCol xs="12" md="6"><div class="detail-label">{{ t('schools.email') }}</div><div class="detail-value">{{ selected.email || '—' }}</div></CCol>
              <CCol xs="12"><div class="detail-label">{{ t('schools.website') }}</div>
                <div class="detail-value">
                  <a v-if="selected.website" :href="selected.website" target="_blank" rel="noopener" class="text-primary">{{ selected.website }}</a>
                  <span v-else>—</span>
                </div>
              </CCol>
              <CCol xs="12"><div class="detail-label">{{ t('schools.address') }}</div><div class="detail-value">{{ selected.address || '—' }}</div></CCol>
              <CCol xs="4"><div class="detail-label">{{ t('schools.region') }}</div><div class="detail-value">{{ selected.region || '—' }}</div></CCol>
              <CCol xs="4"><div class="detail-label">{{ t('schools.district') }}</div><div class="detail-value">{{ selected.district || '—' }}</div></CCol>
              <CCol xs="4"><div class="detail-label">{{ t('schools.ward') }}</div><div class="detail-value">{{ selected.ward || '—' }}</div></CCol>
            </CRow>
          </div>

          <!-- Tab 1: Classes -->
          <div v-show="detailTab === 1">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="fw-bold mb-0">{{ t('schools.tabs.classes') }} ({{ classes.length }})</h6>
              <CButton size="sm" color="success" @click="openAddClass" style="min-height:36px;">
                <CIcon icon="cilPlus" class="me-1" />{{ t('schools.addClass') }}
              </CButton>
            </div>
            <div v-if="classLoading" class="text-center py-4"><CSpinner size="sm" /></div>
            <div v-else-if="!classes.length" class="text-center text-muted py-4">{{ t('schools.noClasses') }}</div>
            <CTable v-else hover responsive class="mb-0 align-middle">
              <CTableHead class="table-light">
                <CTableRow>
                  <CTableHeaderCell>{{ t('common.class') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('schools.capacity') }}</CTableHeaderCell>
                  <CTableHeaderCell class="text-center">{{ t('common.actions') }}</CTableHeaderCell>
                </CTableRow>
              </CTableHead>
              <CTableBody>
                <CTableRow v-for="cls in classes" :key="cls.id">
                  <CTableDataCell class="fw-semibold">{{ cls.name }}</CTableDataCell>
                  <CTableDataCell>{{ cls.capacity ?? '—' }}</CTableDataCell>
                  <CTableDataCell class="text-center">
                    <div class="d-flex gap-1 justify-content-center">
                      <CButton size="sm" color="primary" variant="outline" @click="openEditClass(cls)" v-c-tooltip="'Hariri darasa'">
                        <CIcon icon="cilPencil" />
                      </CButton>
                      <CButton size="sm" color="danger" variant="outline" @click="deleteClass(cls)" v-c-tooltip="'Futa darasa'">
                        <CIcon icon="cilTrash" />
                      </CButton>
                    </div>
                  </CTableDataCell>
                </CTableRow>
              </CTableBody>
            </CTable>
          </div>

          <!-- Tab 2: Academic Years -->
          <div v-show="detailTab === 2">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="fw-bold mb-0">{{ t('schools.tabs.years') }} ({{ years.length }})</h6>
              <CButton size="sm" color="success" @click="openAddYear" style="min-height:36px;">
                <CIcon icon="cilPlus" class="me-1" />{{ t('schools.addYear') }}
              </CButton>
            </div>
            <div v-if="yearLoading" class="text-center py-4"><CSpinner size="sm" /></div>
            <div v-else-if="!years.length" class="text-center text-muted py-4">{{ t('schools.noYears') }}</div>
            <CTable v-else hover responsive class="mb-0 align-middle">
              <CTableHead class="table-light">
                <CTableRow>
                  <CTableHeaderCell>{{ t('common.year') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('common.startDate') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('common.endDate') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('common.status') }}</CTableHeaderCell>
                  <CTableHeaderCell class="text-center">{{ t('common.actions') }}</CTableHeaderCell>
                </CTableRow>
              </CTableHead>
              <CTableBody>
                <CTableRow v-for="yr in years" :key="yr.id">
                  <CTableDataCell class="fw-semibold">{{ yr.name }}</CTableDataCell>
                  <CTableDataCell class="small">{{ yr.start_date }}</CTableDataCell>
                  <CTableDataCell class="small">{{ yr.end_date }}</CTableDataCell>
                  <CTableDataCell>
                    <CBadge :color="yr.is_current ? 'success' : 'secondary'" shape="rounded-pill">
                      {{ yr.is_current ? t('schools.currentYear') : t('schools.pastYear') }}
                    </CBadge>
                  </CTableDataCell>
                  <CTableDataCell class="text-center">
                    <div class="d-flex gap-1 justify-content-center">
                      <CButton v-if="!yr.is_current" size="sm" color="success" variant="outline"
                        @click="setCurrentYear(yr)" v-c-tooltip="'Weka kama mwaka wa sasa'">
                        <CIcon icon="cilCheck" />
                      </CButton>
                      <CButton size="sm" color="primary" variant="outline" @click="openEditYear(yr)" v-c-tooltip="'Hariri mwaka'">
                        <CIcon icon="cilPencil" />
                      </CButton>
                    </div>
                  </CTableDataCell>
                </CTableRow>
              </CTableBody>
            </CTable>
          </div>

          <!-- Tab 3: Users -->
          <div v-show="detailTab === 3">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="fw-bold mb-0">{{ t('schools.tabs.users') }} ({{ schoolUsers.length }})</h6>
              <CButton size="sm" color="success" @click="openAddUser" style="min-height:36px;">
                <CIcon icon="cilUserPlus" class="me-1" />{{ t('schools.addUser') }}
              </CButton>
            </div>
            <div v-if="userLoading" class="text-center py-4"><CSpinner size="sm" /></div>
            <div v-else-if="!schoolUsers.length" class="text-center text-muted py-4">{{ t('schools.noUsers') }}</div>
            <CTable v-else hover responsive class="mb-0 align-middle">
              <CTableHead class="table-light">
                <CTableRow>
                  <CTableHeaderCell>{{ t('common.name') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('common.email') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('schools.role') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('common.status') }}</CTableHeaderCell>
                  <CTableHeaderCell class="text-center">{{ t('common.actions') }}</CTableHeaderCell>
                </CTableRow>
              </CTableHead>
              <CTableBody>
                <CTableRow v-for="u in schoolUsers" :key="u.id">
                  <CTableDataCell class="fw-semibold">{{ u.name }}</CTableDataCell>
                  <CTableDataCell class="small text-muted">{{ u.email }}</CTableDataCell>
                  <CTableDataCell>
                    <CBadge :color="roleColor(u.roles?.[0]?.name)" shape="rounded-pill">
                      {{ roleLabel(u.roles?.[0]?.name) }}
                    </CBadge>
                  </CTableDataCell>
                  <CTableDataCell>
                    <CBadge :color="u.is_active !== false ? 'success' : 'secondary'" shape="rounded-pill">
                      {{ u.is_active !== false ? t('schools.active') : t('schools.inactive') }}
                    </CBadge>
                  </CTableDataCell>
                  <CTableDataCell class="text-center">
                    <div class="d-flex gap-1 justify-content-center">
                      <CButton size="sm" color="primary" variant="outline" @click="openEditUser(u)" v-c-tooltip="'Hariri mtumiaji'">
                        <CIcon icon="cilPencil" />
                      </CButton>
                      <CButton size="sm" color="danger" variant="outline" @click="confirmDeleteUser(u)" v-c-tooltip="'Futa mtumiaji'">
                        <CIcon icon="cilTrash" />
                      </CButton>
                    </div>
                  </CTableDataCell>
                </CTableRow>
              </CTableBody>
            </CTable>
          </div>

        </div>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" @click="showDetail=false">{{ t('common.close') }}</CButton>
        <CButton color="primary" @click="openEdit(selected); showDetail=false">
          <CIcon icon="cilPencil" class="me-1" />{{ t('common.edit') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- ═══════════════════════════════════════════════════════════════════════
         CREATE / EDIT SCHOOL MODAL
    ═══════════════════════════════════════════════════════════════════════════ -->
    <CModal :visible="showForm" @close="closeForm" size="lg" class="modal-fullscreen-sm-down" backdrop="static" scrollable>
      <CModalHeader>
        <CModalTitle>{{ editing ? t('schools.editTitle') : t('schools.addTitle') }}</CModalTitle>
      </CModalHeader>
      <CModalBody class="p-3">
        <CNav variant="tabs" class="mb-3">
          <CNavItem v-for="(t, i) in formTabs" :key="i">
            <CNavLink :active="formTab === i" @click="formTab = i" style="cursor:pointer;">{{ t }}</CNavLink>
          </CNavItem>
        </CNav>

        <!-- Tab 0: Basic Info -->
        <div v-show="formTab === 0">
          <CRow class="g-3">
            <CCol xs="12" md="8">
              <FieldLabel required>{{ t('schools.schoolName') }}</FieldLabel>
              <CFormInput v-model="form.name" placeholder="e.g. St. Margaret Primary School" :invalid="!!errors.name" />
              <ErrMsg :errors="errors.name" />
            </CCol>
            <CCol xs="12" md="4">
              <FieldLabel required>{{ t('schools.code') }}</FieldLabel>
              <CFormInput v-model="form.code" placeholder="SMP" maxlength="10"
                :invalid="!!errors.code" @input="form.code = form.code.toUpperCase()" />
              <ErrMsg :errors="errors.code" />
            </CCol>
            <CCol xs="12" md="6">
              <FieldLabel required>{{ t('schools.level') }}</FieldLabel>
              <CFormSelect v-model="form.level" :invalid="!!errors.level">
                <option value="">{{ t('common.select') }}</option>
                <option value="primary">{{ t('schools.primary') }}</option>
                <option value="secondary">{{ t('schools.secondary') }}</option>
              </CFormSelect>
              <ErrMsg :errors="errors.level" />
            </CCol>
            <CCol xs="12" md="6">
              <FieldLabel>{{ t('schools.registrationNo') }}</FieldLabel>
              <CFormInput v-model="form.registration_number" placeholder="e.g. PS/001/2005" />
            </CCol>
            <CCol xs="12" md="4">
              <FieldLabel>{{ t('schools.establishedYear') }}</FieldLabel>
              <CFormInput type="number" v-model.number="form.established_year" placeholder="1998" min="1800" :max="new Date().getFullYear()" />
            </CCol>
            <CCol xs="12" md="4">
              <FieldLabel>{{ t('schools.capacity') }}</FieldLabel>
              <CFormInput type="number" v-model.number="form.capacity" placeholder="500" min="1" />
            </CCol>
            <CCol xs="12" md="4">
              <FieldLabel>{{ t('common.status') }}</FieldLabel>
              <CFormSelect :modelValue="String(form.is_active)" @update:modelValue="val => form.is_active = (val === 'true' || val === true)">
                <option value="true">{{ t('schools.active') }}</option>
                <option value="false">{{ t('schools.inactive') }}</option>
              </CFormSelect>
            </CCol>
            <CCol xs="12">
              <FieldLabel>{{ t('schools.motto') }}</FieldLabel>
              <CFormInput v-model="form.motto" placeholder="e.g. Elimu ni Ufunguo" maxlength="200" />
            </CCol>
            <CCol xs="12">
              <FieldLabel>{{ t('schools.ownerName') }}</FieldLabel>
              <CFormInput v-model="form.owner_name" placeholder="Bw. Ahmed Salim" />
            </CCol>
            <CCol xs="12">
              <FieldLabel>{{ t('schools.logo') }}</FieldLabel>
              <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="logo-preview-box" :style="logoPreview ? `background-image:url(${logoPreview})` : ''">
                  <span v-if="!logoPreview" class="logo-initials-xl">{{ initials(form.name || '?') }}</span>
                </div>
                <div>
                  <CFormInput type="file" accept="image/jpeg,image/png,image/webp" @change="onLogoChange" :invalid="!!errors.logo" />
                  <div class="form-text">JPEG / PNG / WebP, max 2 MB</div>
                  <ErrMsg :errors="errors.logo" />
                </div>
              </div>
            </CCol>
          </CRow>
        </div>

        <!-- Tab 1: Contact & Location -->
        <div v-show="formTab === 1">
          <CRow class="g-3">
            <CCol xs="12" md="6">
              <FieldLabel>{{ t('schools.phone') }}</FieldLabel>
              <CFormInput v-model="form.phone" placeholder="+255 XXX XXX XXX" />
            </CCol>
            <CCol xs="12" md="6">
              <FieldLabel>{{ t('schools.email') }}</FieldLabel>
              <CFormInput type="email" v-model="form.email" placeholder="shule@example.go.tz" :invalid="!!errors.email" />
              <ErrMsg :errors="errors.email" />
            </CCol>
            <CCol xs="12">
              <FieldLabel>{{ t('schools.website') }}</FieldLabel>
              <CInputGroup>
                <CInputGroupText>https://</CInputGroupText>
                <CFormInput v-model="form.website" placeholder="www.shule.ac.tz" :invalid="!!errors.website" />
              </CInputGroup>
              <ErrMsg :errors="errors.website" />
            </CCol>
            <CCol xs="12">
              <FieldLabel>{{ t('schools.address') }}</FieldLabel>
              <CFormInput v-model="form.address" placeholder="S.L.P 123, Dar es Salaam" />
            </CCol>
            <CCol xs="12" md="4">
              <FieldLabel>{{ t('schools.region') }}</FieldLabel>
              <CFormInput v-model="form.region" placeholder="Dar es Salaam" />
            </CCol>
            <CCol xs="12" md="4">
              <FieldLabel>{{ t('schools.district') }}</FieldLabel>
              <CFormInput v-model="form.district" placeholder="Ilala" />
            </CCol>
            <CCol xs="12" md="4">
              <FieldLabel>{{ t('schools.ward') }}</FieldLabel>
              <CFormInput v-model="form.ward" placeholder="Kariakoo" />
            </CCol>
          </CRow>
        </div>

        <CAlert v-if="formError" color="danger" class="mt-3">{{ formError }}</CAlert>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" @click="closeForm">{{ t('common.cancel') }}</CButton>
        <CButton :color="editing ? 'primary' : 'success'" :disabled="saving" @click="submit" style="min-height:44px; min-width:130px;">
          <CSpinner v-if="saving" size="sm" class="me-1" />
          {{ editing ? t('common.save') : t('schools.add') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- ═══════════════════════════════════════════════════════════════════════
         ADD / EDIT CLASS MODAL
    ═══════════════════════════════════════════════════════════════════════════ -->
    <CModal :visible="showClassForm" @close="showClassForm=false" size="sm" backdrop="static">
      <CModalHeader><CModalTitle>{{ editingClass ? t('schools.editClass') : t('schools.addClass') }}</CModalTitle></CModalHeader>
      <CModalBody class="p-3">
        <div class="mb-3">
          <FieldLabel required>{{ t('schools.className') }}</FieldLabel>
          <CFormInput v-model="classForm.name" placeholder="e.g. STANDARD ONE" :invalid="!!classErrors.name" />
          <ErrMsg :errors="classErrors.name" />
        </div>
        <div class="mb-3">
          <FieldLabel>{{ t('schools.capacity') }}</FieldLabel>
          <CFormInput type="number" v-model.number="classForm.capacity" placeholder="45" min="1" />
        </div>
        <CAlert v-if="classFormError" color="danger">{{ classFormError }}</CAlert>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" @click="showClassForm=false">{{ t('common.cancel') }}</CButton>
        <CButton color="success" :disabled="classSaving" @click="submitClass" style="min-height:44px; min-width:100px;">
          <CSpinner v-if="classSaving" size="sm" class="me-1" />{{ t('common.save') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- ═══════════════════════════════════════════════════════════════════════
         ADD / EDIT ACADEMIC YEAR MODAL
    ═══════════════════════════════════════════════════════════════════════════ -->
    <CModal :visible="showYearForm" @close="showYearForm=false" size="sm" backdrop="static">
      <CModalHeader><CModalTitle>{{ editingYear ? t('schools.editYear') : t('schools.addYear') }}</CModalTitle></CModalHeader>
      <CModalBody class="p-3">
        <div class="mb-3">
          <FieldLabel required>{{ t('common.year') }}</FieldLabel>
          <CFormInput v-model="yearForm.name" placeholder="e.g. 2026" :invalid="!!yearErrors.name" />
          <ErrMsg :errors="yearErrors.name" />
        </div>
        <div class="mb-3">
          <FieldLabel required>{{ t('common.startDate') }}</FieldLabel>
          <CFormInput type="date" v-model="yearForm.start_date" :invalid="!!yearErrors.start_date" />
        </div>
        <div class="mb-3">
          <FieldLabel required>{{ t('common.endDate') }}</FieldLabel>
          <CFormInput type="date" v-model="yearForm.end_date" :invalid="!!yearErrors.end_date" />
        </div>
        <CAlert v-if="yearFormError" color="danger">{{ yearFormError }}</CAlert>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" @click="showYearForm=false">{{ t('common.cancel') }}</CButton>
        <CButton color="success" :disabled="yearSaving" @click="submitYear" style="min-height:44px; min-width:100px;">
          <CSpinner v-if="yearSaving" size="sm" class="me-1" />{{ t('common.save') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- ═══════════════════════════════════════════════════════════════════════
         ADD / EDIT USER MODAL
    ═══════════════════════════════════════════════════════════════════════════ -->
    <CModal :visible="showUserForm" @close="showUserForm=false" backdrop="static">
      <CModalHeader><CModalTitle>{{ editingUser ? t('schools.editUser') : t('schools.addUser') }}</CModalTitle></CModalHeader>
      <CModalBody class="p-3">
        <CRow class="g-3">
          <CCol xs="12">
            <FieldLabel required>{{ t('common.fullName') }}</FieldLabel>
            <CFormInput v-model="userForm.name" :placeholder="t('common.fullName')" :invalid="!!userErrors.name" />
            <ErrMsg :errors="userErrors.name" />
          </CCol>
          <CCol xs="12">
            <FieldLabel required>{{ t('common.email') }}</FieldLabel>
            <CFormInput type="email" v-model="userForm.email" placeholder="mtumiaji@shule.tz" :invalid="!!userErrors.email" />
            <ErrMsg :errors="userErrors.email" />
          </CCol>
          <CCol xs="12" md="6">
            <FieldLabel required>{{ t('schools.role') }}</FieldLabel>
            <CFormSelect v-model="userForm.role" :invalid="!!userErrors.role">
              <option value="">{{ t('common.select') }}</option>
              <option value="owner">{{ t('schools.roles.owner') }}</option>
              <option value="accountant">{{ t('schools.roles.accountant') }}</option>
            </CFormSelect>
            <ErrMsg :errors="userErrors.role" />
          </CCol>
          <CCol xs="12" md="6" v-if="!editingUser">
            <FieldLabel required>{{ t('common.password') }}</FieldLabel>
            <CFormInput type="password" v-model="userForm.password" :placeholder="t('common.passwordMin8')" :invalid="!!userErrors.password" />
            <ErrMsg :errors="userErrors.password" />
          </CCol>
        </CRow>
        <CAlert v-if="userFormError" color="danger" class="mt-3">{{ userFormError }}</CAlert>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" @click="showUserForm=false">{{ t('common.cancel') }}</CButton>
        <CButton color="success" :disabled="userSaving" @click="submitUser" style="min-height:44px; min-width:100px;">
          <CSpinner v-if="userSaving" size="sm" class="me-1" />{{ t('common.save') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- ═══════════════════════════════════════════════════════════════════════
         DELETE CONFIRM
    ═══════════════════════════════════════════════════════════════════════════ -->
    <CModal :visible="showDeleteConfirm" @close="showDeleteConfirm=false" size="sm">
      <CModalHeader><CModalTitle class="text-danger">{{ t('common.confirmDelete') }}</CModalTitle></CModalHeader>
      <CModalBody>
        <p>{{ t('schools.confirmDeleteMsg', { name: toDelete?.name }) }}</p>
        <p class="small text-muted mb-0">{{ t('schools.deleteNote') }}</p>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" @click="showDeleteConfirm=false">{{ t('common.cancel') }}</CButton>
        <CButton color="danger" :disabled="saving" @click="doDelete" style="min-height:44px;">
          <CSpinner v-if="saving" size="sm" class="me-1" />{{ t('common.delete') }}
        </CButton>
      </CModalFooter>
    </CModal>

  </CContainer>
</template>

<script setup>
import { ref, computed, onMounted, watch, defineComponent, h } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import { useSchoolsStore } from '@/stores/schools'

const { t } = useI18n()

// ── Mini components ──────────────────────────────────────────────────────────
const SchoolLogo = defineComponent({
  props: { school: Object, size: { type: String, default: 'sm' } },
  setup(props) {
    const dims = { sm: 40, lg: 56, xl: 72 }
    const px   = dims[props.size] || 40
    const fs   = { sm: '.7rem', lg: '.9rem', xl: '1.2rem' }[props.size] || '.7rem'
    const rad  = { sm: 10, lg: 12, xl: 16 }[props.size] || 10
    const init = (props.school?.name || '?').split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()
    const bg   = props.school?.logo_url ? `url(${props.school.logo_url})` : ''
    return () => h('div', {
      style: `width:${px}px;height:${px}px;border-radius:${rad}px;background:#e8f5e9;background-image:${bg};background-size:cover;background-position:center;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:1.5px solid #c8e6c9;`,
    }, bg ? [] : [h('span', { style: `font-size:${fs};font-weight:700;color:#2e7d32;` }, init)])
  },
})

const FieldLabel = defineComponent({
  props: { required: Boolean },
  setup(props, { slots }) {
    return () => h('label', { class: 'form-label fw-semibold' }, [
      slots.default?.(),
      props.required ? h('span', { class: 'text-danger ms-1' }, '*') : null,
    ])
  },
})

const ErrMsg = defineComponent({
  props: { errors: [Array, String] },
  setup(props) {
    return () => props.errors?.length
      ? h('div', { class: 'invalid-feedback d-block' }, Array.isArray(props.errors) ? props.errors[0] : props.errors)
      : null
  },
})

// ── Store ────────────────────────────────────────────────────────────────────
const store = useSchoolsStore()

// ── Registration number auto-generation ──────────────────────────────────────
function buildRegNumber(name, level) {
  if (!name || !level) return ''
  const prefix   = level === 'secondary' ? 'SEC' : 'PRM'
  const stopWords = new Set(['school', 'primary', 'secondary', 'st', 'saint', 'ya', 'the', 'of', 'and', 'la', 'da'])
  const words     = name.toLowerCase().split(/[\s.]+/).filter(w => w && !stopWords.has(w.replace(/\./g, '')))
  const abbrev    = words.map(w => w.replace(/[aeiou]/gi, '')).join('').toUpperCase().slice(0, 8) ||
                    name.replace(/\s+/g, '').toUpperCase().slice(0, 6)
  const year      = new Date().getFullYear()
  // Sequence will be set by backend; show 0001 as placeholder
  return `${prefix}/${abbrev}/0001/${year}`
}

// ── Filter ───────────────────────────────────────────────────────────────────
const search       = ref('')
const filterLevel  = ref('')
const filterStatus = ref('')

const filtered = computed(() => {
  let list = store.schools
  if (search.value)       list = list.filter(s =>
    s.name.toLowerCase().includes(search.value.toLowerCase()) ||
    s.code.toLowerCase().includes(search.value.toLowerCase()))
  if (filterLevel.value)  list = list.filter(s => s.level === filterLevel.value)
  if (filterStatus.value) list = list.filter(s => filterStatus.value === 'active' ? s.is_active : !s.is_active)
  return list
})

// ── School form state ─────────────────────────────────────────────────────────
const showForm  = ref(false)
const editing   = ref(false)
const formTab   = ref(0)
const saving    = ref(false)
const formError = ref('')
const errors    = ref({})
const logoPreview = ref('')
const logoFile    = ref(null)
const formTabs    = computed(() => [t('schools.tabs.basic'), t('schools.tabs.contact')])

const emptyForm = () => ({
  name: '', code: '', level: '', registration_number: '',
  established_year: '', capacity: '', owner_name: '', motto: '',
  phone: '', email: '', website: '', address: '',
  region: '', district: '', ward: '', is_active: true, _id: null,
})
const form = ref(emptyForm())

// ── Detail modal state ────────────────────────────────────────────────────────
const showDetail  = ref(false)
const selected    = ref(null)
const detailTab   = ref(0)
const detailTabs  = computed(() => [t('schools.tabs.overview'), t('schools.tabs.classes'), t('schools.tabs.years'), t('schools.tabs.users')])

// Classes sub-state
const classes       = ref([])
const classLoading  = ref(false)
const showClassForm = ref(false)
const editingClass  = ref(null)
const classForm     = ref({ name: '', capacity: '' })
const classErrors   = ref({})
const classFormError = ref('')
const classSaving   = ref(false)

// Years sub-state
const years        = ref([])
const yearLoading  = ref(false)
const showYearForm = ref(false)
const editingYear  = ref(null)
const yearForm     = ref({ name: '', start_date: '', end_date: '' })
const yearErrors   = ref({})
const yearFormError = ref('')
const yearSaving   = ref(false)

// Users sub-state
const schoolUsers   = ref([])
const userLoading   = ref(false)
const showUserForm  = ref(false)
const editingUser   = ref(null)
const userForm      = ref({ name: '', email: '', role: '', password: '' })
const userErrors    = ref({})
const userFormError = ref('')
const userSaving    = ref(false)

// Delete state
const showDeleteConfirm = ref(false)
const toDelete          = ref(null)

// ── Helpers ──────────────────────────────────────────────────────────────────
function initials(name) {
  return (name || '?').split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()
}
function roleColor(role) {
  return { owner: 'danger', accountant: 'primary', parent: 'info' }[role] || 'secondary'
}
function roleLabel(role) {
  const map = { owner: 'schools.roles.owner', accountant: 'schools.roles.accountant', parent: 'schools.roles.parent' }
  return map[role] ? t(map[role]) : (role || '—')
}

// ── School CRUD ───────────────────────────────────────────────────────────────
function openCreate() {
  editing.value = false
  form.value    = emptyForm()
  logoPreview.value = ''
  logoFile.value    = null
  errors.value      = {}
  formError.value   = ''
  formTab.value     = 0
  showForm.value    = true
}

function openEdit(school) {
  editing.value = true
  form.value = {
    name: school.name, code: school.code, level: school.level,
    registration_number: school.registration_number || '',
    established_year: school.established_year || '',
    capacity: school.capacity || '',
    owner_name: school.owner_name || '',
    motto: school.motto || '',
    phone: school.phone || '',
    email: school.email || '',
    website: school.website?.replace(/^https?:\/\//, '') || '',
    address: school.address || '',
    region: school.region || '',
    district: school.district || '',
    ward: school.ward || '',
    is_active: school.is_active,
    _id: school.id,
  }
  logoPreview.value = school.logo_url || ''
  logoFile.value    = null
  errors.value      = {}
  formError.value   = ''
  formTab.value     = 0
  showForm.value    = true
}

function closeForm() { showForm.value = false }

function onLogoChange(e) {
  logoFile.value    = e.target.files[0] || null
  if (logoFile.value) logoPreview.value = URL.createObjectURL(logoFile.value)
}

async function submit() {
  formError.value = ''
  errors.value    = {}
  saving.value    = true
  try {
    const fd = new FormData()
    Object.entries(form.value).forEach(([k, v]) => {
      if (k === '_id') return
      if (v !== '' && v !== null && v !== undefined) {
        if (k === 'website' && v && !v.startsWith('http')) {
          fd.append(k, 'https://' + v)
        } else if (k === 'is_active') {
          // FormData stringifies booleans — Laravel boolean validator requires 1/0
          fd.append(k, v ? '1' : '0')
        } else {
          fd.append(k, v)
        }
      }
    })
    if (logoFile.value) fd.append('logo', logoFile.value)

    if (editing.value) {
      await store.updateSchool(form.value._id, fd)
    } else {
      await store.createSchool(fd)
    }
    showForm.value = false
  } catch (e) {
    if (e?.response?.status === 422) {
      errors.value    = e.response.data.errors || {}
      formError.value = t('common.fixErrors')
      const contactKeys = ['phone', 'email', 'website', 'address', 'region', 'district', 'ward']
      const hasBasicError   = Object.keys(errors.value).some(k => !contactKeys.includes(k))
      const hasContactError = Object.keys(errors.value).some(k => contactKeys.includes(k))
      if (hasContactError && !hasBasicError) formTab.value = 1
    } else {
      formError.value = e?.response?.data?.message || t('common.serverError')
    }
  } finally {
    saving.value = false
  }
}

// ── Toggle / Delete ───────────────────────────────────────────────────────────
async function toggle(school) {
  try { await store.toggleStatus(school.id) } catch (e) {
    alert(e?.response?.data?.message || 'Hitilafu')
  }
}

function confirmDelete(school) {
  toDelete.value = school
  showDeleteConfirm.value = true
}

async function doDelete() {
  saving.value = true
  try {
    await store.deleteSchool(toDelete.value.id)
    showDeleteConfirm.value = false
  } catch (e) {
    alert(e?.response?.data?.message || 'Hitilafu ya kufuta')
  } finally { saving.value = false }
}

// ── Open detail (load related data) ──────────────────────────────────────────
async function openDetail(school) {
  selected.value  = school
  detailTab.value = 0
  showDetail.value = true
  loadClasses(school.id)
  loadYears(school.id)
  loadUsers(school.id)
}

async function openManage(school) {
  await openDetail(school)
  detailTab.value = 1
}

// ── Classes ───────────────────────────────────────────────────────────────────
async function loadClasses(schoolId) {
  classLoading.value = true
  try {
    const { data } = await api.get('/school-classes', { params: { school_id: schoolId, per_page: 100 } })
    classes.value = data.data || data
  } catch {} finally { classLoading.value = false }
}

function openAddClass() {
  editingClass.value = null
  classForm.value    = { name: '', capacity: '' }
  classErrors.value  = {}
  classFormError.value = ''
  showClassForm.value  = true
}

function openEditClass(cls) {
  editingClass.value = cls
  classForm.value    = { name: cls.name, capacity: cls.capacity || '' }
  classErrors.value  = {}
  classFormError.value = ''
  showClassForm.value  = true
}

async function submitClass() {
  classFormError.value = ''
  classErrors.value    = {}
  classSaving.value    = true
  try {
    const payload = { name: classForm.value.name, capacity: classForm.value.capacity || null, school_id: selected.value.id }
    if (editingClass.value) {
      await api.put(`/school-classes/${editingClass.value.id}`, payload)
    } else {
      await api.post('/school-classes', payload)
    }
    showClassForm.value = false
    await loadClasses(selected.value.id)
  } catch (e) {
    if (e?.response?.status === 422) classErrors.value = e.response.data.errors || {}
    classFormError.value = e?.response?.data?.message || 'Hitilafu'
  } finally { classSaving.value = false }
}

async function deleteClass(cls) {
  if (!confirm(`Futa darasa "${cls.name}"?`)) return
  try {
    await api.delete(`/school-classes/${cls.id}`)
    await loadClasses(selected.value.id)
  } catch (e) { alert(e?.response?.data?.message || 'Hitilafu ya kufuta') }
}

// ── Academic Years ────────────────────────────────────────────────────────────
async function loadYears(schoolId) {
  yearLoading.value = true
  try {
    const { data } = await api.get('/academic-years', { params: { school_id: schoolId } })
    years.value = data.data || data
  } catch {} finally { yearLoading.value = false }
}

function openAddYear() {
  editingYear.value = null
  yearForm.value    = { name: '', start_date: '', end_date: '' }
  yearErrors.value  = {}
  yearFormError.value = ''
  showYearForm.value  = true
}

function openEditYear(yr) {
  editingYear.value = yr
  yearForm.value    = { name: yr.name, start_date: yr.start_date, end_date: yr.end_date }
  yearErrors.value  = {}
  yearFormError.value = ''
  showYearForm.value  = true
}

async function submitYear() {
  yearFormError.value = ''
  yearErrors.value    = {}
  yearSaving.value    = true
  try {
    const payload = { ...yearForm.value, school_id: selected.value.id }
    if (editingYear.value) {
      await api.put(`/academic-years/${editingYear.value.id}`, payload)
    } else {
      await api.post('/academic-years', payload)
    }
    showYearForm.value = false
    await loadYears(selected.value.id)
  } catch (e) {
    if (e?.response?.status === 422) yearErrors.value = e.response.data.errors || {}
    yearFormError.value = e?.response?.data?.message || 'Hitilafu'
  } finally { yearSaving.value = false }
}

async function setCurrentYear(yr) {
  try {
    await api.patch(`/academic-years/${yr.id}`, { is_current: true, school_id: selected.value.id })
    await loadYears(selected.value.id)
  } catch (e) { alert(e?.response?.data?.message || 'Hitilafu') }
}

// ── Users ─────────────────────────────────────────────────────────────────────
async function loadUsers(schoolId) {
  userLoading.value = true
  try {
    const { data } = await api.get('/users', { params: { school_id: schoolId } })
    schoolUsers.value = data.data || data
  } catch {} finally { userLoading.value = false }
}

function openAddUser() {
  editingUser.value = null
  userForm.value    = { name: '', email: '', role: '', password: '' }
  userErrors.value  = {}
  userFormError.value = ''
  showUserForm.value  = true
}

function openEditUser(u) {
  editingUser.value = u
  userForm.value    = { name: u.name, email: u.email, role: u.roles?.[0]?.name || '', password: '' }
  userErrors.value  = {}
  userFormError.value = ''
  showUserForm.value  = true
}

async function submitUser() {
  userFormError.value = ''
  userErrors.value    = {}
  userSaving.value    = true
  try {
    const payload = { ...userForm.value, school_id: selected.value.id }
    if (!editingUser.value && !payload.password) {
      userErrors.value = { password: ['Neno la siri linahitajika'] }
      userSaving.value = false
      return
    }
    if (editingUser.value) {
      const p = { ...payload }
      if (!p.password) delete p.password
      await api.put(`/users/${editingUser.value.id}`, p)
    } else {
      await api.post('/users', payload)
    }
    showUserForm.value = false
    await loadUsers(selected.value.id)
  } catch (e) {
    if (e?.response?.status === 422) userErrors.value = e.response.data.errors || {}
    userFormError.value = e?.response?.data?.message || 'Hitilafu'
  } finally { userSaving.value = false }
}

async function confirmDeleteUser(u) {
  if (!confirm(`Futa mtumiaji "${u.name}"?`)) return
  try {
    await api.delete(`/users/${u.id}`)
    await loadUsers(selected.value.id)
  } catch (e) { alert(e?.response?.data?.message || 'Hitilafu ya kufuta') }
}

// Auto-fill registration number for new schools only
watch([() => form.value.name, () => form.value.level], ([name, level]) => {
  if (!editing.value && name && level) {
    form.value.registration_number = buildRegNumber(name, level)
  }
})

// ── Mount ─────────────────────────────────────────────────────────────────────
onMounted(async () => {
  try { await store.fetchSchools({ all: 1 }) } catch {}
})
</script>

<style scoped>
.stat-card { transition: transform .15s; }
.stat-card:hover { transform: translateY(-2px); }
.stat-label { font-size: .7rem; text-transform: uppercase; font-weight: 600; color: #6c757d; margin-bottom: 2px; }
.stat-value { font-size: 1.35rem; font-weight: 700; }

.action-btn { min-width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; }

.school-row:hover { background: rgba(0,127,62,.04); }
.schools-table th { font-size: .8rem; text-transform: uppercase; letter-spacing: .03em; }

.logo-preview-box {
  width: 72px; height: 72px; border-radius: 16px;
  background: #e8f5e9; background-size: cover; background-position: center;
  display: flex; align-items: center; justify-content: center;
  border: 2px solid #c8e6c9; flex-shrink: 0;
}
.logo-initials-xl { font-size: 1.2rem; font-weight: 700; color: #2e7d32; }

.detail-label { font-size: .75rem; text-transform: uppercase; color: #6c757d; font-weight: 600; margin-bottom: 2px; }
.detail-value { font-weight: 600; font-size: .95rem; }

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
