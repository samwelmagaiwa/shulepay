<template>
  <CModal :visible="visible" @close="$emit('close')" size="xl" class="modal-fullscreen-sm-down" :backdrop="'static'" scrollable>
    <CModalHeader style="border-bottom:2px solid #007f3e;">
      <CModalTitle class="fw-bold">{{ t('students.register') }}</CModalTitle>
      <div v-if="draftSaved" class="text-end text-success" style="font-size:.75rem;">
        ✓ {{ t('students.draftSaved') || 'Draft saved' }}
      </div>
    </CModalHeader>

    <CModalBody class="p-2 p-md-3">

      <!-- ── Resume vs Fresh Choice Dialog ──────────────────────────────── -->
      <div v-if="showResumeDialog" class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
           style="background:rgba(0,0,0,.5);z-index:2000;">
        <div class="bg-white rounded-3 shadow-lg p-4" style="max-width:500px;width:90%;">
          <div class="text-center mb-4">
            <div style="font-size:3rem;margin-bottom:1rem;">📋</div>
            <h5 class="fw-bold mb-2">{{ t('students.resumeRegistration') || 'Resume Registration?' }}</h5>
            <p class="text-muted small mb-0">{{ t('students.resumeHint') || 'You have an incomplete registration in progress. Would you like to continue where you left off, or start fresh?' }}</p>
          </div>

          <div class="d-grid gap-2">
            <CButton color="success" size="lg" @click="onResume()" class="fw-semibold">
              ↩️ {{ t('students.goWhereYouEnded') || 'Go Where You Ended' }}
              <div class="small text-muted mt-1">Continue from step {{ hasDraft ? '...' : '1' }}</div>
            </CButton>
            <CButton color="secondary" size="lg" @click="onStartFresh()" class="fw-semibold">
              🆕 {{ t('students.startFresh') || 'Start Fresh' }}
              <div class="small text-muted mt-1">Begin new registration</div>
            </CButton>
          </div>
        </div>
      </div>

      <!-- ── New / Existing toggle (STRONG DEFAULT) ──────────────────────────────────────── -->
      <div v-if="!showResumeDialog" class="d-flex align-items-center gap-3 mb-3 p-3 rounded-3 border"
           :style="form.is_existing_student
             ? 'border-color:#007f3e!important;background:#f0f9f5;border-width:2px!important;box-shadow:0 0 0 3px rgba(0,127,62,.1);'
             : 'border-color:#e0e0e0!important;background:#f9f9f9;'">
        <div class="flex-grow-1">
          <div class="d-flex align-items-center gap-2 mb-1">
            <div class="fw-bold" :style="form.is_existing_student ? 'color:#007f3e;font-size:1rem;' : 'font-size:.9rem;'">
              {{ form.is_existing_student ? '📚 ' + t('students.isExistingStudent') : '🆕 ' + t('students.isNewStudent') }}
            </div>
            <CBadge v-if="form.is_existing_student" color="success" style="font-size:.7rem;">DEFAULT</CBadge>
          </div>
          <div class="text-muted" style="font-size:.72rem;">
            {{ form.is_existing_student
              ? 'Record previous terms\' fees and payments as written in the books. The system will automatically calculate the current balance.'
              : t('students.isNewStudentHint') }}
          </div>
        </div>
        <div class="d-flex flex-column align-items-center gap-2">
          <CFormSwitch v-model="form.is_existing_student" id="existingStudentToggle"
                       @update:modelValue="onExistingToggleWithWarning" />
          <div class="small" :style="form.is_existing_student ? 'color:#007f3e;font-weight:600;' : 'color:#999;'">
            {{ form.is_existing_student ? '✓ ACTIVE' : 'Inactive' }}
          </div>
        </div>
      </div>

      <!-- Form content (hidden when resume dialog showing) -->
      <div v-if="!showResumeDialog">

      <!-- Step indicator — desktop -->
      <div class="d-none d-md-flex justify-content-center mb-4">
        <div v-for="(s, i) in steps" :key="i" class="d-flex align-items-center">
          <div class="d-flex flex-column align-items-center" style="min-width:64px;">
            <div
              :class="['rounded-circle d-flex align-items-center justify-content-center fw-bold',
                step > i+1 ? 'bg-success text-white' :
                step === i+1 ? 'bg-primary text-white' : 'bg-light text-muted border']"
              style="width:36px;height:36px;font-size:.85rem;"
            >
              <CIcon v-if="step > i+1" icon="cilCheckAlt" size="sm" />
              <span v-else>{{ i+1 }}</span>
            </div>
            <div class="mt-1 text-center" :class="step===i+1 ? 'text-primary fw-semibold' : 'text-muted'"
                 style="font-size:.72rem;white-space:nowrap;">{{ s }}</div>
          </div>
          <div v-if="i < steps.length-1" class="mx-1 flex-shrink-0"
               style="width:40px;height:2px;" :class="step > i+1 ? 'bg-success' : 'bg-light'"></div>
        </div>
      </div>

      <!-- Step indicator — mobile -->
      <div class="d-md-none mb-3 text-center">
        <CBadge color="primary" class="px-3 py-2">
          Hatua {{ step }}/{{ steps.length }}: {{ steps[step-1] }}
        </CBadge>
      </div>

      <!-- ═══════════════ STEP 1: Identity ═══════════════ -->
      <div v-if="step === 1">
        <!-- Photo + name row -->
        <CRow class="g-3 mb-2">
          <!-- Photo upload — left column -->
          <CCol xs="12" md="3" class="d-flex flex-column align-items-center">
            <div class="mb-2 position-relative" style="width:120px;height:120px;">
              <img
                v-if="photoPreview"
                :src="photoPreview"
                class="rounded-circle border shadow-sm"
                style="width:120px;height:120px;object-fit:cover;"
                alt="Picha"
              />
              <div v-else class="rounded-circle border d-flex align-items-center justify-content-center bg-light shadow-sm"
                   style="width:120px;height:120px;font-size:3rem;color:#adb5bd;">👤</div>
              <!-- Remove button -->
              <button v-if="photoPreview" type="button"
                      class="btn btn-sm btn-danger rounded-circle position-absolute p-0 d-flex align-items-center justify-content-center"
                      style="width:24px;height:24px;top:4px;right:4px;font-size:.7rem;"
                      @click="removePhoto" title="Ondoa picha">✕</button>
            </div>
            <label class="btn btn-outline-primary btn-sm w-100" style="cursor:pointer;">
              📷 {{ t('students.uploadPhoto') }}
              <input type="file" accept="image/*" class="d-none" @change="onPhotoChange" ref="photoInput" />
            </label>
            <div class="text-muted mt-1" style="font-size:.7rem;">{{ t('students.photoHint') }}</div>
          </CCol>

          <!-- Name + basic info — right columns -->
          <CCol xs="12" md="9">
            <CRow class="g-3">
              <CCol xs="12" sm="4">
                <label class="form-label fw-semibold">{{ t('students.firstName') }} <span class="text-danger">*</span></label>
                <CFormInput v-model="form.first_name" :class="{'is-invalid': errors.first_name}" placeholder="Jina la kwanza" />
                <div class="invalid-feedback">{{ errors.first_name }}</div>
              </CCol>
              <CCol xs="12" sm="4">
                <label class="form-label">{{ t('students.middleName') }}</label>
                <CFormInput v-model="form.middle_name" placeholder="Jina la kati" />
              </CCol>
              <CCol xs="12" sm="4">
                <label class="form-label fw-semibold">{{ t('students.lastName') }} <span class="text-danger">*</span></label>
                <CFormInput v-model="form.last_name" :class="{'is-invalid': errors.last_name}" placeholder="Jina la familia" />
                <div class="invalid-feedback">{{ errors.last_name }}</div>
              </CCol>

              <CCol xs="6" sm="3">
                <label class="form-label fw-semibold">{{ t('students.gender') }} <span class="text-danger">*</span></label>
                <CFormSelect v-model="form.gender" :class="{'is-invalid': errors.gender}">
                  <option value="">— {{ t('students.selectGender') }} —</option>
                  <option value="male">{{ t('students.genderMale') }}</option>
                  <option value="female">{{ t('students.genderFemale') }}</option>
                </CFormSelect>
                <div class="invalid-feedback">{{ errors.gender }}</div>
              </CCol>
              <CCol xs="6" sm="3">
                <label class="form-label fw-semibold">{{ t('students.dateOfBirth') }} <span class="text-danger">*</span></label>
                <CFormInput type="date" v-model="form.date_of_birth" :class="{'is-invalid': errors.date_of_birth}" />
                <div class="invalid-feedback">{{ errors.date_of_birth }}</div>
              </CCol>
              <CCol xs="6" sm="3">
                <label class="form-label fw-semibold">{{ t('common.status') }} <span class="text-danger">*</span></label>
                <CFormSelect v-model="form.status">
                  <option value="active">{{ t('students.statuses.active') }}</option>
                  <option value="sponsored">{{ t('students.statuses.sponsored') }}</option>
                  <option value="orphaned">{{ t('students.statuses.orphaned') }}</option>
                  <option value="transferred">{{ t('students.statuses.transferred') }}</option>
                  <option value="graduated">{{ t('students.statuses.graduated') }}</option>
                  <option value="dropped">{{ t('students.statuses.dropped') }}</option>
                </CFormSelect>
              </CCol>
              <CCol xs="6" sm="3">
                <label class="form-label">{{ t('students.nationality') }}</label>
                <CFormInput v-model="form.nationality" placeholder="Tanzanian" />
              </CCol>
            </CRow>
          </CCol>
        </CRow>

        <hr class="my-3" />

        <!-- Personal document details -->
        <div class="fw-semibold text-muted small mb-2 text-uppercase" style="letter-spacing:.05em;">📄 {{ t('students.docsSection') }}</div>
        <CRow class="g-3">
          <CCol xs="12" sm="4">
            <label class="form-label">{{ t('students.birthCertNo') }}</label>
            <CFormInput v-model="form.birth_certificate_no" placeholder="Nambari ya cheti cha kuzaliwa"
                        :class="{'is-invalid': errors.birth_certificate_no}" />
            <div class="invalid-feedback">{{ errors.birth_certificate_no }}</div>
          </CCol>
          <CCol xs="12" sm="4">
            <label class="form-label">{{ t('students.religion') }}</label>
            <CFormSelect v-model="form.religion">
              <option value="">— {{ t('common.select') }} —</option>
              <option value="Ukristo">{{ t('students.religions.christianity') }}</option>
              <option value="Uislamu">{{ t('students.religions.islam') }}</option>
              <option value="Uhindu">{{ t('students.religions.hinduism') }}</option>
              <option value="Nyingine">{{ t('students.religions.other') }}</option>
            </CFormSelect>
          </CCol>
          <CCol xs="12" sm="4">
            <label class="form-label">{{ t('students.admissionNo') }}</label>
            <CFormInput readonly :value="form.admission_no || t('students.admissionAuto')"
                        class="bg-light text-muted"
                        :class="{'is-invalid': errors.admission_no}" />
            <div class="text-muted" style="font-size:.72rem;">{{ t('students.admissionAutoHint') }}</div>
            <div class="invalid-feedback d-block" v-if="errors.admission_no">{{ errors.admission_no }}</div>
          </CCol>
        </CRow>
      </div>

      <!-- ═══════════════ STEP 2: Health & Address ═══════════════ -->
      <div v-if="step === 2">
        <!-- Health -->
        <div class="fw-semibold text-muted small mb-2 text-uppercase" style="letter-spacing:.05em;">🩺 {{ t('students.healthSection') }}</div>
        <CRow class="g-2">
          <CCol xs="12" sm="4">
            <label class="form-label fw-semibold mb-1">{{ t('students.bloodGroup') }}</label>
            <CFormSelect v-model="form.blood_group">
              <option value="">— {{ t('students.unknown') }} —</option>
              <option value="A+">A+ (A Positive)</option>
              <option value="A-">A− (A Negative)</option>
              <option value="B+">B+ (B Positive)</option>
              <option value="B-">B− (B Negative)</option>
              <option value="AB+">AB+ (AB Positive)</option>
              <option value="AB-">AB− (AB Negative)</option>
              <option value="O+">O+ (O Positive)</option>
              <option value="O-">O− (O Negative)</option>
            </CFormSelect>
          </CCol>
          <CCol xs="12" sm="4">
            <label class="form-label mb-1">{{ t('students.allergies') }}</label>
            <div class="d-flex align-items-center gap-2 mb-1">
              <CFormSwitch v-model="hasAllergies" id="allergiesToggle"
                           @update:modelValue="v => { if (!v) form.allergies = '' }" />
              <span class="small text-muted">{{ t('students.allergiesToggle') }}</span>
            </div>
            <CFormInput v-if="hasAllergies" v-model="form.allergies"
                        :placeholder="t('students.allergiesPlaceholder')" autofocus />
          </CCol>
          <CCol xs="12" sm="4">
            <label class="form-label mb-1">{{ t('students.medicalConditions') }}</label>
            <CFormInput v-model="form.medical_conditions" :placeholder="t('students.medicalConditionsPlaceholder')" />
          </CCol>
        </CRow>

        <hr class="my-2" />

        <!-- Address -->
        <div class="fw-semibold text-muted small mb-2 text-uppercase" style="letter-spacing:.05em;">📍 {{ t('students.addressSection') }}</div>
        <CRow class="g-2">
          <!-- Row 1: Region · District · Ward (all selects — same height) -->
          <CCol xs="12" sm="4">
            <label class="form-label mb-1">{{ t('students.region') }}</label>
            <CFormSelect v-model="form.region"
              @update:modelValue="onRegionChange"
              :disabled="loadingRegions">
              <option value="">{{ loadingRegions ? t('common.loading') : `— ${t('students.selectRegion')} —` }}</option>
              <option v-for="r in regions" :key="r.id" :value="r.name">{{ r.name }}</option>
            </CFormSelect>
          </CCol>
          <CCol xs="12" sm="4">
            <label class="form-label mb-1 d-flex align-items-center gap-1">
              {{ t('students.district') }}
              <CSpinner v-if="loadingDistricts" size="sm" style="width:12px;height:12px;" />
            </label>
            <CFormSelect v-model="form.district"
              :disabled="!form.region || loadingDistricts"
              @update:modelValue="onDistrictChange">
              <option value="">{{ loadingDistricts ? t('common.loading') : `— ${t('students.selectDistrict')} —` }}</option>
              <option v-for="d in districts" :key="d.id" :value="d.name">{{ d.name }}</option>
            </CFormSelect>
          </CCol>
          <CCol xs="12" sm="4">
            <label class="form-label mb-1 d-flex align-items-center gap-1">
              {{ t('students.ward') }}
              <CSpinner v-if="loadingWards" size="sm" style="width:12px;height:12px;" />
            </label>
            <CFormSelect v-model="form.ward"
              :disabled="!form.district || loadingWards"
              @update:modelValue="onWardChange">
              <option value="">{{ loadingWards ? t('common.loading') : `— ${t('students.selectWard')} —` }}</option>
              <option v-for="w in wards" :key="w.id" :value="w.name">{{ w.name }}</option>
            </CFormSelect>
          </CCol>

          <!-- Row 2: Street · Place · Full Address (all single-line) -->
          <CCol xs="12" sm="4">
            <label class="form-label mb-1 d-flex align-items-center gap-1">
              {{ t('students.street') }}
              <CSpinner v-if="loadingStreets" size="sm" style="width:12px;height:12px;" />
            </label>
            <CFormSelect v-if="streets.length > 0 || loadingStreets"
              v-model="form.street" :disabled="!form.ward || loadingStreets"
              @update:modelValue="onStreetChange">
              <option value="">{{ loadingStreets ? t('common.loading') : `— ${t('students.selectStreet')} —` }}</option>
              <option v-for="st in streets" :key="st.id" :value="st.name">{{ st.name }}</option>
            </CFormSelect>
            <CFormInput v-else
              v-model="form.street"
              :disabled="!form.ward"
              :placeholder="t('students.selectStreet')"
              @input="form.place = ''; places = []"
            />
            <div v-if="form.ward && !loadingStreets && streets.length === 0" class="text-muted mt-1" style="font-size:.72rem;">
              Hakuna data — andika mwenyewe
            </div>
          </CCol>
          <CCol xs="12" sm="4">
            <label class="form-label mb-1 d-flex align-items-center gap-1">
              {{ t('students.place') }}
              <CSpinner v-if="loadingPlaces" size="sm" style="width:12px;height:12px;" />
            </label>
            <CFormSelect v-if="places.length > 0 || (form.street && loadingPlaces)"
              v-model="form.place" :disabled="!form.street || loadingPlaces">
              <option value="">{{ loadingPlaces ? t('common.loading') : `— ${t('students.place')} —` }}</option>
              <option v-for="pl in places" :key="pl.id" :value="pl.name">{{ pl.name }}</option>
            </CFormSelect>
            <CFormInput v-else
              v-model="form.place"
              :disabled="!form.street || loadingPlaces"
              :placeholder="t('students.place')"
            />
          </CCol>
          <CCol xs="12" sm="4">
            <label class="form-label mb-1">{{ t('students.address') }}</label>
            <CFormInput v-model="form.address" :placeholder="t('students.addressPlaceholder')" />
          </CCol>

          <!-- Row 3: Notes — full width -->
          <CCol xs="12">
            <label class="form-label mb-1">{{ t('students.notes') }}</label>
            <CFormTextarea v-model="form.notes" rows="2" :placeholder="t('students.notesPlaceholder')" />
          </CCol>
        </CRow>
      </div>

      <!-- ═══════════════ STEP 3: Class / School ═══════════════ -->
      <div v-if="step === 3">
        <CRow class="g-3">
          <CCol xs="12" sm="6">
            <label class="form-label fw-semibold">{{ t('students.school') }} <span class="text-danger">*</span></label>
            <CFormSelect v-model="form.school_id" @update:modelValue="onSchoolChange" :class="{'is-invalid': errors.school_id}">
              <option value="">{{ t('students.selectSchool') }}</option>
              <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
            </CFormSelect>
            <div class="invalid-feedback">{{ errors.school_id }}</div>
          </CCol>
          <CCol xs="12" sm="6">
            <label class="form-label fw-semibold">{{ t('students.class') }} <span class="text-danger">*</span></label>
            <CFormSelect v-model="form.school_class_id" :class="{'is-invalid': errors.school_class_id}" :disabled="!form.school_id">
              <option value="">{{ t('students.selectClass') }}</option>
              <option v-for="c in filteredClasses" :key="c.id" :value="c.id">{{ c.name }}</option>
            </CFormSelect>
            <div class="invalid-feedback">{{ errors.school_class_id }}</div>
          </CCol>
          <CCol xs="12" sm="6">
            <label class="form-label fw-semibold">{{ t('students.academicYear') }} <span class="text-danger">*</span></label>
            <CFormSelect v-model="form.academic_year_id" :class="{'is-invalid': errors.academic_year_id}">
              <option value="">{{ t('students.selectYear') }}</option>
              <option v-for="y in academicYears" :key="y.id" :value="y.id">{{ y.name }}</option>
            </CFormSelect>
            <div class="invalid-feedback">{{ errors.academic_year_id }}</div>
          </CCol>
          <CCol xs="12" sm="6">
            <label class="form-label fw-semibold">{{ t('students.term') }} <span class="text-danger">*</span></label>
            <CFormSelect v-model="form.term_id" :class="{'is-invalid': errors.term_id}">
              <option value="">{{ t('students.selectTerm') }}</option>
              <option v-for="tm in terms" :key="tm.id" :value="tm.id">{{ tm.name }}</option>
            </CFormSelect>
            <div class="invalid-feedback">{{ errors.term_id }}</div>
          </CCol>
          <CCol xs="12" sm="6">
            <label class="form-label fw-semibold">{{ t('students.enrollmentDate') }} <span class="text-danger">*</span></label>
            <CFormInput type="date" v-model="form.enrollment_date" :class="{'is-invalid': errors.enrollment_date}" />
            <div class="invalid-feedback">{{ errors.enrollment_date }}</div>
          </CCol>
          <CCol xs="12" sm="6">
            <label class="form-label">{{ t('students.previousSchool') }}</label>
            <CFormInput v-model="form.previous_school" :placeholder="t('students.previousSchoolPlaceholder')" />
          </CCol>
        </CRow>
      </div>

      <!-- ═══════════════ STEP 4: Guardians ═══════════════ -->
      <div v-if="step === 4">
        <div v-for="(g, gi) in form.guardians" :key="gi" class="border rounded p-3 mb-3"
             :style="g.is_primary_contact ? 'border-color:#007f3e!important;background:rgba(0,127,62,.03);' : ''">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <strong>
              {{ t('guardians.guardian') }} {{ gi+1 }}
              <CBadge v-if="g.is_primary_contact" color="success" class="ms-2" style="font-size:.7rem;">Mkuu</CBadge>
            </strong>
            <CButton v-if="gi > 0" size="sm" color="danger" variant="ghost" @click="removeGuardian(gi)">
              <CIcon icon="cilTrash" />
            </CButton>
          </div>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem .75rem;">
            <!-- Full Name -->
            <div>
              <label class="form-label fw-semibold mb-1">{{ t('guardians.fullName') }} <span class="text-danger">*</span></label>
              <CFormInput v-model="g.full_name" :class="{'is-invalid': errors[`guardians.${gi}.full_name`]}" />
              <div class="invalid-feedback">{{ errors[`guardians.${gi}.full_name`] }}</div>
            </div>
            <!-- Relationship -->
            <div>
              <label class="form-label fw-semibold mb-1">{{ t('guardians.relationship') }} <span class="text-danger">*</span></label>
              <CFormSelect v-model="g.relationship" :class="{'is-invalid': errors[`guardians.${gi}.relationship`]}">
                <option value="">{{ t('common.select') }}</option>
                <option value="father">{{ t('guardians.father') }}</option>
                <option value="mother">{{ t('guardians.mother') }}</option>
                <option value="guardian">{{ t('guardians.guardian') }}</option>
              </CFormSelect>
              <div class="invalid-feedback">{{ errors[`guardians.${gi}.relationship`] }}</div>
            </div>
            <!-- Phone -->
            <div>
              <label class="form-label fw-semibold mb-1">{{ t('guardians.phone') }} <span class="text-danger">*</span></label>
              <CFormInput v-model="g.phone" type="tel" placeholder="0712345678"
                          :class="{'is-invalid': errors[`guardians.${gi}.phone`]}"
                          @blur="checkGuardianExists(gi)" />
              <div class="invalid-feedback">{{ errors[`guardians.${gi}.phone`] }}</div>
              <div v-if="g._exists" class="text-warning small mt-1">⚠ Nambari hii tayari ipo kwenye mfumo</div>
            </div>
            <!-- Alt Phone -->
            <div>
              <label class="form-label mb-1">{{ t('guardians.altPhone') }}</label>
              <CFormInput v-model="g.alt_phone" type="tel" :placeholder="t('guardians.altPhone')" />
            </div>
            <!-- Email -->
            <div>
              <label class="form-label mb-1">{{ t('common.email') }}</label>
              <CFormInput v-model="g.email" type="email" placeholder="barua@mfano.com" />
            </div>
            <!-- ID Type -->
            <div>
              <label class="form-label mb-1">{{ t('students.idType') }}</label>
              <CFormSelect v-model="g.id_type">
                <option value="">— {{ t('common.select') }} —</option>
                <option v-for="(label, key) in idTypes" :key="key" :value="key">{{ label }}</option>
              </CFormSelect>
            </div>
            <!-- ID Number -->
            <div>
              <label class="form-label mb-1">{{ t('students.idNumber') }}</label>
              <CFormInput v-model="g.national_id" :placeholder="t('guardians.nationalId')" />
            </div>
            <!-- Address — spans 2 cols -->
            <div style="grid-column:span 2;">
              <label class="form-label mb-1">{{ t('common.address') }}</label>
              <CFormInput v-model="g.address" :placeholder="t('common.address')" />
            </div>
            <!-- Primary contact — 1 col, bottom-aligned -->
            <div class="d-flex align-items-end pb-1">
              <CFormCheck v-model="g.is_primary_contact" :label="t('guardians.primaryContact')" @change="setPrimary(gi)" />
            </div>
          </div>
        </div>
        <CButton color="success" variant="outline" @click="addGuardian" style="min-height:44px;">
          <CIcon icon="cilPlus" class="me-1" /> {{ t('guardians.addAnother') }}
        </CButton>
        <div v-if="errors.guardians" class="text-danger small mt-2">{{ errors.guardians }}</div>
      </div>

      <!-- ═══════════════ STEP 5: Financial ═══════════════ -->
      <div v-if="step === 5">

        <!-- ── Sponsorship level ──────────────────────────────────────── -->
        <div class="p-3 rounded-3 border mb-3" style="background:#faf5ff;border-color:#8b5cf6!important;">
          <label class="form-label fw-bold small mb-2 d-flex align-items-center gap-2">
            🎗️ {{ t('students.sponsorshipLevel') || 'Sponsorship' }}
          </label>
          <div class="btn-group w-100" role="group">
            <input type="radio" class="btn-check" id="sponsorNone" value="none" v-model="form.sponsorship_type" autocomplete="off">
            <label class="btn btn-outline-secondary" for="sponsorNone">{{ t('students.notSponsored') || 'Not Sponsored' }}</label>

            <input type="radio" class="btn-check" id="sponsorHalf" value="half" v-model="form.sponsorship_type" autocomplete="off">
            <label class="btn btn-outline-warning" for="sponsorHalf">🤝 {{ t('students.halfSponsored') || 'Half Sponsored' }}</label>

            <input type="radio" class="btn-check" id="sponsorFull" value="full" v-model="form.sponsorship_type" autocomplete="off">
            <label class="btn btn-outline-success" for="sponsorFull">🎗️ {{ t('students.fullySponsored') || 'Fully Sponsored' }}</label>
          </div>
          <div class="text-muted mt-2" style="font-size:.72rem;">
            <span v-if="form.sponsorship_type === 'full'">{{ t('students.fullySponsoredHint') || 'Fully covered by a sponsor — no tuition fee to enter, no payment history step.' }}</span>
            <span v-else-if="form.sponsorship_type === 'half'">{{ t('students.halfSponsoredHint') || 'Partially covered by a sponsor — tracked as a label only; billing continues as normal below.' }}</span>
            <span v-else>{{ t('students.notSponsoredHint') || 'No sponsorship — standard billing.' }}</span>
          </div>
        </div>

        <template v-if="form.sponsorship_type !== 'full'">

        <!-- ── Fee structure preview ──────────────────────────────────── -->
        <div class="fw-semibold text-muted small mb-2 text-uppercase" style="letter-spacing:.05em;">💰 {{ t('fees.title') }}</div>

        <div v-if="loadingFeePreview" class="text-center py-3 text-muted small">
          <CSpinner size="sm" class="me-1" /> {{ t('common.loading') }}
        </div>

        <div v-else-if="feePreview" class="border rounded-3 p-3 mb-3" style="border-color:#007f3e!important;background:#f8fff8;">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-semibold text-success small">✓ {{ t('fees.title') }}</span>
            <span class="fw-bold text-success fs-6">{{ formatMoney(feePreview.total_cents) }}</span>
          </div>
          <div v-for="item in feePreview.items" :key="item.id"
               class="d-flex justify-content-between align-items-center small py-1"
               style="border-bottom:1px solid rgba(0,127,62,.12);">
            <span>
              {{ item.name }}
              <CBadge v-if="item.is_optional" color="secondary" class="ms-1" style="font-size:.65rem;">{{ t('fees.optional') }}</CBadge>
            </span>
            <span class="fw-semibold">{{ formatMoney(item.amount_cents) }}</span>
          </div>
          <div v-if="hasOpeningBalance && form.opening_balance > 0"
               class="d-flex justify-content-between small py-1 text-warning" style="border-bottom:1px solid rgba(0,127,62,.12);">
            <span>{{ t('students.openingBalance') }}</span>
            <span class="fw-semibold">+ {{ formatMoney(form.opening_balance * 100) }}</span>
          </div>
          <div v-if="form.discount_type && form.discount_amount > 0"
               class="d-flex justify-content-between small py-1 text-danger" style="border-bottom:1px solid rgba(0,127,62,.12);">
            <span>{{ t('students.discount') }} ({{ form.discount_type }})</span>
            <span class="fw-semibold">− {{ formatMoney(form.discount_amount * 100) }}</span>
          </div>
          <div class="d-flex justify-content-between small pt-2 fw-bold text-success">
            <span>{{ t('fees.total') }}</span>
            <span>{{ formatMoney(
              feePreview.total_cents
              + (hasOpeningBalance ? form.opening_balance * 100 : 0)
              - (form.discount_amount ? form.discount_amount * 100 : 0)
            ) }}</span>
          </div>
        </div>

        <CAlert v-else color="warning" class="py-2 mb-3 small">
          ⚠ {{ t('fees.noFees') }} — {{ t('students.generateFirstInvoiceHint') }}
        </CAlert>

        <!-- ── Total Tuition Fee (manual override) ───────────────────── -->
        <CRow class="g-3 mb-3">
          <CCol md="6">
            <div class="p-3 rounded-3 border" style="background:#f0f8ff;border-color:#4A9FD4!important;">
              <label class="form-label fw-bold small mb-2 d-flex align-items-center gap-2">
                💵 {{ t('students.totalTuitionFee') || 'Total Tuition Fee' }} <span class="text-danger">*</span>
              </label>
              <div class="text-muted small mb-2" style="font-size:.7rem;">{{ t('students.totalTuitionFeeHint') || 'Define the total tuition fee if no fee structure exists' }}</div>
              <CFormInput
                type="text"
                inputmode="numeric"
                :value="formatAmount(form.total_tuition_fee || 0)"
                @input="form.total_tuition_fee = parseAmount($event.target.value)"
                placeholder="0"
                class="fw-semibold"
                :class="{'is-invalid': errors.total_tuition_fee}"
              />
              <div class="invalid-feedback d-block" v-if="errors.total_tuition_fee">{{ errors.total_tuition_fee }}</div>
              <small v-if="form.total_tuition_fee > 0" class="d-block mt-2 text-info">
                <strong>{{ formatMoney((form.total_tuition_fee || 0) * 100) }}</strong>
              </small>
            </div>
          </CCol>
          <CCol md="6" class="d-flex align-items-center">
            <div class="w-100 p-3 rounded-3 border" style="background:#f8fff8;border-color:#007f3e!important;">
              <div class="fw-semibold text-success mb-2" style="font-size:.9rem;">📊 {{ t('students.invoiceCalculation') || 'Calculated Invoice' }}</div>
              <div class="d-flex justify-content-between mb-1">
                <span class="small text-muted">{{ t('students.baseFee') || 'Base Fee' }}:</span>
                <span class="fw-semibold">{{ formatMoney(feePreview?.total_cents || form.total_tuition_fee * 100 || 0) }}</span>
              </div>
              <div v-if="hasOpeningBalance && form.opening_balance > 0" class="d-flex justify-content-between mb-1">
                <span class="small text-warning">+ {{ t('students.openingBalance') }}:</span>
                <span class="fw-semibold text-warning">+ {{ formatMoney(form.opening_balance * 100) }}</span>
              </div>
              <div v-if="form.discount_type && form.discount_amount > 0" class="d-flex justify-content-between mb-2">
                <span class="small text-danger">− {{ t('students.discount') }}:</span>
                <span class="fw-semibold text-danger">− {{ formatMoney(form.discount_amount * 100) }}</span>
              </div>
              <hr class="my-1" />
              <div class="d-flex justify-content-between fw-bold text-success" style="font-size:.95rem;">
                <span>{{ t('fees.total') }}:</span>
                <span>{{ formatMoney(
                  (feePreview?.total_cents || form.total_tuition_fee * 100 || 0)
                  + (hasOpeningBalance ? form.opening_balance * 100 : 0)
                  - (form.discount_amount ? form.discount_amount * 100 : 0)
                ) }}</span>
              </div>
            </div>
          </CCol>
        </CRow>

        <!-- ── Controls: 3-column grid ───────────────────────────────── -->
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem;">

          <!-- Col 1: Opening balance toggle -->
          <div class="p-3 rounded-3 border" style="background:#f8f9fa;">
            <div class="d-flex align-items-center gap-2 mb-1">
              <CFormSwitch v-model="hasOpeningBalance" id="openingBalanceToggle"
                           @change="!hasOpeningBalance && (form.opening_balance = 0)" />
              <div class="fw-semibold small">{{ t('students.openingBalance') }}</div>
            </div>
            <div class="text-muted mb-2" style="font-size:.72rem;">{{ t('students.openingBalanceHint') }}</div>
            <CFormInput
              v-if="hasOpeningBalance"
              type="text"
              inputmode="numeric"
              :value="formatAmount(form.opening_balance)"
              @input="form.opening_balance = parseAmount($event.target.value)"
              placeholder="0"
              autofocus
            />
          </div>

          <!-- Col 2: Discount -->
          <div class="p-3 rounded-3 border" style="background:#f8f9fa;">
            <label class="form-label fw-semibold small mb-1">{{ t('students.discountType') }}</label>
            <CFormSelect v-model="form.discount_type" class="mb-2">
              <option value="">{{ t('students.noDiscount') }}</option>
              <option value="sibling">{{ t('students.discountSibling') }}</option>
              <option value="staff">{{ t('students.discountStaff') }}</option>
              <option value="sponsor">{{ t('students.discountSponsor') }}</option>
              <option value="other">{{ t('common.other') }}</option>
            </CFormSelect>
            <div v-if="form.discount_type">
              <label class="form-label small mb-1">{{ t('students.discountAmount') }} (TZS)</label>
              <CFormInput type="number" v-model.number="form.discount_amount" min="0" placeholder="0" />
            </div>
          </div>

          <!-- Col 3: Generate invoice toggle (hidden for existing students — history covers it) -->
          <div class="p-3 rounded-3 border d-flex flex-column justify-content-center gap-2" style="background:#f8f9fa;">
            <template v-if="!form.is_existing_student">
              <div class="d-flex align-items-center gap-2">
                <CFormSwitch v-model="form.generate_first_invoice" id="genInvoice" size="xl" />
                <div class="fw-semibold small">{{ t('students.generateFirstInvoice') }}</div>
              </div>
              <div class="text-muted" style="font-size:.72rem;">{{ t('students.generateFirstInvoiceHint') }}</div>
            </template>
            <template v-else>
              <div class="text-warning fw-semibold small">📚 {{ t('students.isExistingStudent') }}</div>
              <div class="text-muted" style="font-size:.72rem;">{{ t('students.migrationInvoiceNote') }}</div>
            </template>
          </div>

        </div>

        </template>
        <CAlert v-else color="success" class="mb-3">
          🎗️ {{ t('students.fullySponsoredConfirm') || 'This student is fully sponsored — no tuition fee or payment history is needed. They will be saved with no invoices.' }}
        </CAlert>

        <!-- Summary card — full review before submit -->
        <CCard class="mt-4" style="border:1.5px solid #007f3e;">
          <CCardHeader class="d-flex align-items-center gap-2" style="background:#007f3e;color:#fff;">
            <img v-if="photoPreview" :src="photoPreview"
              style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.5);" />
            <div v-else style="width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:1.3rem;">👤</div>
            <div>
              <div class="fw-bold" style="font-size:1rem;">{{ fullName || '—' }}</div>
              <div style="font-size:.72rem;opacity:.85;">📋 {{ t('students.registrationSummary') }}</div>
            </div>
          </CCardHeader>
          <CCardBody class="p-0">

            <!-- Personal -->
            <div class="px-3 pt-3 pb-2">
              <div class="text-uppercase fw-bold mb-2" style="font-size:.65rem;letter-spacing:.08em;color:#007f3e;">👤 {{ t('students.sumSectionPersonal') }}</div>
              <CRow class="g-1 small">
                <CCol xs="6" sm="4"><span class="text-muted">{{ t('guardians.fullName') }}:</span><br><strong>{{ fullName || '—' }}</strong></CCol>
                <CCol xs="6" sm="4"><span class="text-muted">{{ t('students.gender') }}:</span><br><strong>{{ form.gender === 'male' ? t('students.genderMale') : form.gender === 'female' ? t('students.genderFemale') : '—' }}</strong></CCol>
                <CCol xs="6" sm="4"><span class="text-muted">{{ t('students.dateOfBirth') }}:</span><br><strong>{{ form.date_of_birth || '—' }}</strong></CCol>
                <CCol xs="6" sm="4"><span class="text-muted">{{ t('students.admissionNo') }}:</span><br>
                  <strong v-if="form.admission_no">{{ form.admission_no }}</strong>
                  <span v-else class="text-muted small fst-italic">{{ t('students.admissionAuto') }}</span>
                </CCol>
                <CCol xs="6" sm="4"><span class="text-muted">{{ t('students.nationality') }}:</span><br><strong>{{ form.nationality || '—' }}</strong></CCol>
                <CCol xs="6" sm="4"><span class="text-muted">{{ t('students.religion') }}:</span><br><strong>{{ form.religion || '—' }}</strong></CCol>
                <CCol xs="6" sm="4"><span class="text-muted">{{ t('students.status') }}:</span><br>
                  <CBadge :color="form.status === 'active' ? 'success' : form.status === 'sponsored' ? 'info' : form.status === 'orphaned' ? 'warning' : 'secondary'" style="font-size:.72rem;">
                    {{ form.status ? t('students.statuses.' + form.status) : '—' }}
                  </CBadge>
                </CCol>
                <CCol xs="6" sm="4"><span class="text-muted">{{ t('students.birthCertNo') }}:</span><br><strong>{{ form.birth_certificate_no || '—' }}</strong></CCol>
                <CCol xs="6" sm="4"><span class="text-muted">{{ t('students.photo') }}:</span><br>
                  <CBadge :color="photoFile ? 'success' : 'secondary'" style="font-size:.72rem;">
                    {{ photoFile ? t('students.sumUploaded') : t('students.sumNone') }}
                  </CBadge>
                </CCol>
              </CRow>
            </div>

            <hr class="my-0" />

            <!-- Academic -->
            <div class="px-3 py-2">
              <div class="text-uppercase fw-bold mb-2" style="font-size:.65rem;letter-spacing:.08em;color:#007f3e;">🎓 {{ t('students.sumSectionAcademic') }}</div>
              <CRow class="g-1 small">
                <CCol xs="6" sm="4"><span class="text-muted">{{ t('students.school') }}:</span><br><strong>{{ selectedSchoolName }}</strong></CCol>
                <CCol xs="6" sm="4"><span class="text-muted">{{ t('students.class') }}:</span><br><strong>{{ selectedClassName }}</strong></CCol>
                <CCol xs="6" sm="4"><span class="text-muted">{{ t('students.academicYear') }}:</span><br>
                  <strong>{{ academicYears.find(y => String(y.id) === String(form.academic_year_id))?.name || '—' }}</strong>
                </CCol>
                <CCol xs="6" sm="4"><span class="text-muted">{{ t('students.term') }}:</span><br>
                  <strong>{{ terms.find(t => String(t.id) === String(form.term_id))?.name || '—' }}</strong>
                </CCol>
                <CCol xs="6" sm="4"><span class="text-muted">{{ t('students.enrollmentDate') }}:</span><br><strong>{{ form.enrollment_date || '—' }}</strong></CCol>
                <CCol xs="6" sm="4" v-if="form.previous_school"><span class="text-muted">{{ t('students.previousSchool') }}:</span><br><strong>{{ form.previous_school }}</strong></CCol>
              </CRow>
            </div>

            <hr class="my-0" />

            <!-- Health -->
            <div class="px-3 py-2">
              <div class="text-uppercase fw-bold mb-2" style="font-size:.65rem;letter-spacing:.08em;color:#007f3e;">🩺 {{ t('students.sumSectionHealth') }}</div>
              <CRow class="g-1 small">
                <CCol xs="6" sm="4"><span class="text-muted">{{ t('students.bloodGroup') }}:</span><br><strong>{{ form.blood_group || '—' }}</strong></CCol>
                <CCol xs="6" sm="4" v-if="form.allergies"><span class="text-muted">{{ t('students.allergies') }}:</span><br><strong>{{ form.allergies }}</strong></CCol>
                <CCol xs="6" sm="4" v-if="form.medical_conditions"><span class="text-muted">{{ t('students.medicalConditions') }}:</span><br><strong>{{ form.medical_conditions }}</strong></CCol>
              </CRow>
            </div>

            <!-- Address (only if filled) -->
            <template v-if="form.region || form.district || form.ward">
              <hr class="my-0" />
              <div class="px-3 py-2">
                <div class="text-uppercase fw-bold mb-2" style="font-size:.65rem;letter-spacing:.08em;color:#007f3e;">📍 {{ t('students.sumSectionAddress') }}</div>
                <CRow class="g-1 small">
                  <CCol xs="6" sm="4" v-if="form.region"><span class="text-muted">{{ t('students.region') }}:</span><br><strong>{{ form.region }}</strong></CCol>
                  <CCol xs="6" sm="4" v-if="form.district"><span class="text-muted">{{ t('students.district') }}:</span><br><strong>{{ form.district }}</strong></CCol>
                  <CCol xs="6" sm="4" v-if="form.ward"><span class="text-muted">{{ t('students.ward') }}:</span><br><strong>{{ form.ward }}</strong></CCol>
                  <CCol xs="6" sm="4" v-if="form.street"><span class="text-muted">{{ t('students.street') }}:</span><br><strong>{{ form.street }}</strong></CCol>
                  <CCol xs="6" sm="4" v-if="form.place"><span class="text-muted">{{ t('students.place') }}:</span><br><strong>{{ form.place }}</strong></CCol>
                  <CCol xs="12" v-if="form.address"><span class="text-muted">{{ t('students.address') }}:</span> <strong>{{ form.address }}</strong></CCol>
                </CRow>
              </div>
            </template>

            <hr class="my-0" />

            <!-- Guardians -->
            <div class="px-3 py-2">
              <div class="text-uppercase fw-bold mb-2" style="font-size:.65rem;letter-spacing:.08em;color:#007f3e;">👪 {{ t('students.sumSectionGuardians') }} ({{ form.guardians.length }})</div>
              <div v-for="(g, gi) in form.guardians" :key="gi" class="mb-1 small d-flex align-items-start gap-2">
                <span class="text-muted" style="min-width:18px;">{{ gi+1 }}.</span>
                <div>
                  <strong>{{ g.full_name || '—' }}</strong>
                  <span v-if="g.relationship" class="text-muted ms-1">({{ t('guardians.' + g.relationship) }})</span>
                  <span v-if="g.phone" class="ms-2">📞 {{ g.phone }}</span>
                  <CBadge v-if="g.is_primary_contact" color="success" class="ms-1" style="font-size:.65rem;">{{ t('students.sumPrimary') }}</CBadge>
                </div>
              </div>
            </div>

            <hr class="my-0" />

            <!-- Financial -->
            <div class="px-3 py-2">
              <div class="text-uppercase fw-bold mb-2" style="font-size:.65rem;letter-spacing:.08em;color:#007f3e;">💰 {{ t('students.sumSectionFinancial') }}</div>
              <CRow class="g-1 small">
                <CCol xs="6" sm="4"><span class="text-muted">{{ t('students.firstInvoice') }}:</span><br>
                  <CBadge :color="form.generate_first_invoice ? 'success' : 'secondary'" style="font-size:.72rem;">
                    {{ form.generate_first_invoice ? t('common.yes') : t('common.no') }}
                  </CBadge>
                </CCol>
                <CCol xs="6" sm="4" v-if="form.opening_balance > 0"><span class="text-muted">{{ t('students.openingBalance') }}:</span><br><strong>TZS {{ (form.opening_balance || 0).toLocaleString() }}</strong></CCol>
                <CCol xs="6" sm="4" v-if="form.discount_type"><span class="text-muted">{{ t('students.discount') }}:</span><br><strong>{{ form.discount_type }} — TZS {{ (form.discount_amount || 0).toLocaleString() }}</strong></CCol>
              </CRow>
            </div>

          </CCardBody>
        </CCard>
      </div>

      <!-- ═══════════════ STEP 6: Migration History (existing students only) ═══════════════ -->
      <div v-if="step === 6">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div>
            <div class="fw-semibold text-muted small text-uppercase" style="letter-spacing:.05em;">📚 {{ t('students.stepMigration') }}</div>
            <div class="text-muted small">{{ t('students.migrationHint') }}</div>
          </div>
        </div>

        <!-- ── Entry Mode Toggle ─────────────────────────────────────────── -->
        <div class="d-flex gap-2 mb-4">
          <div class="btn-group w-100" role="group">
            <input type="radio" class="btn-check" name="migrationMode" id="modeDetailed" v-model="migrationMode" value="detailed" />
            <label class="btn btn-outline-primary" for="modeDetailed" style="cursor:pointer;">
              📋 {{ t('students.detailedByTerm') || 'Detailed by Term' }}
            </label>
            <input type="radio" class="btn-check" name="migrationMode" id="modeLumpSum" v-model="migrationMode" value="lumpsum" />
            <label class="btn btn-outline-success" for="modeLumpSum" style="cursor:pointer;">
              💰 {{ t('students.annualSummary') || 'Annual Summary' }}
            </label>
          </div>
        </div>

        <!-- ── DETAILED MODE: Term by Term ──────────────────────────────── -->
        <div v-if="migrationMode === 'detailed'">
          <div class="mb-3">
            <CBadge color="warning">{{ form.payment_history.length }} {{ t('students.terms') || 'Terms' }}</CBadge>
          </div>

          <!-- Term entries -->
          <div v-for="(entry, ei) in form.payment_history" :key="ei"
             class="border rounded-3 p-3 mb-3 position-relative"
             style="border-color:#ffc107!important;background:#fffdf0;">

          <!-- Remove term -->
          <CButton v-if="form.payment_history.length > 0" size="sm" color="danger" variant="ghost"
                   class="position-absolute" style="top:8px;right:8px;"
                   @click="removeTermHistory(ei)">✕</CButton>

          <div class="fw-semibold small mb-2">📅 {{ t('students.term') }} {{ ei + 1 }}</div>

          <CRow class="g-2 mb-2">
            <CCol xs="12" sm="4">
              <label class="form-label fw-semibold mb-1">{{ t('students.academicYear') }} <span class="text-danger">*</span></label>
              <CFormSelect v-model="entry.academic_year_id" @update:modelValue="autoFillFee(ei)">
                <option value="">— {{ t('students.selectYear') }} —</option>
                <option v-for="y in academicYears" :key="y.id" :value="y.id">{{ y.name }}</option>
              </CFormSelect>
            </CCol>
            <CCol xs="12" sm="4">
              <label class="form-label fw-semibold mb-1">{{ t('students.term') }} <span class="text-danger">*</span></label>
              <CFormSelect v-model="entry.term_id" @update:modelValue="autoFillFee(ei)">
                <option value="">— {{ t('students.selectTerm') }} —</option>
                <option v-for="tm in terms" :key="tm.id" :value="tm.id">{{ tm.name }}</option>
              </CFormSelect>
            </CCol>
            <CCol xs="12" sm="4">
              <label class="form-label fw-semibold mb-1">{{ t('students.termFeeAmount') }} <span class="text-danger">*</span></label>
              <CFormInput
                type="text" inputmode="numeric"
                :value="formatAmount(entry.fee_amount)"
                @input="entry.fee_amount = parseAmount($event.target.value)"
                placeholder="0"
              />
            </CCol>
          </CRow>

          <!-- Payments for this term -->
          <div class="small fw-semibold text-muted mb-1">{{ t('students.paymentsRecorded') }}</div>
          <div v-for="(pmt, pi) in entry.payments" :key="pi"
               class="d-flex gap-2 align-items-end mb-2 flex-wrap">
            <div style="flex:1;min-width:120px;">
              <label class="form-label mb-1 small">{{ t('students.paidDate') }}</label>
              <CFormInput type="date" v-model="pmt.paid_at" :max="today" />
            </div>
            <div style="flex:1;min-width:100px;">
              <label class="form-label mb-1 small">{{ t('students.paidAmount') }}</label>
              <CFormInput
                type="text" inputmode="numeric"
                :value="formatAmount(pmt.amount)"
                @input="pmt.amount = parseAmount($event.target.value)"
                placeholder="0"
              />
            </div>
            <div style="flex:1;min-width:100px;">
              <label class="form-label mb-1 small">{{ t('students.paymentMethod') }}</label>
              <CFormSelect v-model="pmt.method">
                <option value="cash">Taslimu (Cash)</option>
                <option value="mpesa">M-Pesa</option>
                <option value="bank">Benki (Bank)</option>
                <option value="cheque">Hundi (Cheque)</option>
              </CFormSelect>
            </div>
            <div style="flex:1.5;min-width:120px;">
              <label class="form-label mb-1 small">{{ t('students.migrationNote') }}</label>
              <CFormInput v-model="pmt.notes" placeholder="e.g. Receipt #123" />
            </div>
            <div class="pb-1">
              <CButton size="sm" color="danger" variant="ghost" @click="removePayment(ei, pi)" title="Remove">✕</CButton>
            </div>
          </div>

          <CButton size="sm" color="warning" variant="outline" @click="addPayment(ei)">
            + {{ t('students.addPayment') }}
          </CButton>

          <!-- Term balance preview -->
          <div v-if="entry.fee_amount > 0" class="mt-2 small d-flex justify-content-between"
               style="border-top:1px dashed #ffc107;padding-top:.5rem;">
            <span class="text-muted">{{ t('fees.total') }}: <strong>{{ formatMoney(entry.fee_amount * 100) }}</strong></span>
            <span class="text-muted">{{ t('common.paid') }}: <strong class="text-success">{{ formatMoney(termPaid(entry)) }}</strong></span>
            <span :class="termBalance(entry) > 0 ? 'text-danger fw-bold' : 'text-success fw-bold'">
              {{ t('common.balance') }}: {{ formatMoney(termBalance(entry)) }}
            </span>
          </div>
        </div>

          <CButton color="warning" variant="outline" @click="addTermHistory" style="min-height:44px;">
            + {{ t('students.addTermHistory') }}
          </CButton>
        </div>

        <!-- ── ANNUAL SUMMARY MODE: Lump Sum ───────────────────────────── -->
        <div v-if="migrationMode === 'lumpsum'" class="border rounded-3 p-4" style="border-color:#007f3e!important;background:#f8fff8;">
          <div class="fw-semibold text-success mb-4" style="font-size:1.05rem;">📊 {{ t('students.annualPaymentSummary') || 'Annual Payment Summary' }}</div>

          <CRow class="g-3 mb-4">
            <CCol md="6">
              <label class="form-label fw-bold mb-2">{{ t('students.totalFeesCharged') || 'Total Fees Charged (All Years)' }} <span class="text-danger">*</span></label>
              <div class="input-group">
                <CFormInput
                  type="text" inputmode="numeric"
                  :value="formatAmount(form.lumpsum_total_charged || 0)"
                  @input="form.lumpsum_total_charged = parseAmount($event.target.value)"
                  placeholder="0"
                  class="fw-semibold"
                  :class="{'is-invalid': errors.lumpsum_total_charged}"
                />
                <span class="input-group-text">TZS</span>
              </div>
              <div class="invalid-feedback d-block" v-if="errors.lumpsum_total_charged">{{ errors.lumpsum_total_charged }}</div>
              <small class="d-block mt-2 text-success fw-bold">{{ formatMoney((form.lumpsum_total_charged || 0) * 100) }}</small>
            </CCol>
            <CCol md="6">
              <label class="form-label fw-bold mb-2">{{ t('students.totalAmountPaid') || 'Total Amount Already Paid' }} <span class="text-danger">*</span></label>
              <div class="input-group">
                <CFormInput
                  type="text" inputmode="numeric"
                  :value="formatAmount(form.lumpsum_total_paid || 0)"
                  @input="form.lumpsum_total_paid = parseAmount($event.target.value)"
                  placeholder="0"
                  class="fw-semibold"
                  :class="{'is-invalid': errors.lumpsum_total_paid}"
                />
                <span class="input-group-text">TZS</span>
              </div>
              <div class="invalid-feedback d-block" v-if="errors.lumpsum_total_paid">{{ errors.lumpsum_total_paid }}</div>
              <small class="d-block mt-2 text-success fw-bold">{{ formatMoney((form.lumpsum_total_paid || 0) * 100) }}</small>
            </CCol>
            <CCol md="6" v-if="form.lumpsum_total_paid > 0">
              <label class="form-label fw-bold mb-2">{{ t('students.lumpsumPaymentDate') || 'When Was This Paid?' }} <span class="text-danger">*</span></label>
              <CFormInput
                type="date" v-model="form.lumpsum_payment_date" :max="today"
                :class="{'is-invalid': errors.lumpsum_payment_date}"
              />
              <div class="invalid-feedback d-block" v-if="errors.lumpsum_payment_date">{{ errors.lumpsum_payment_date }}</div>
              <div class="text-muted small mt-1" style="font-size:.7rem;">{{ t('students.lumpsumPaymentDateHint') || 'The actual historical date this amount was paid — not today, unless it really was.' }}</div>
            </CCol>
          </CRow>

          <!-- Outstanding Balance Summary -->
          <CCard class="border-0 p-3" style="background:#e3f2fd;">
            <CRow class="g-3">
              <CCol md="4" class="text-center">
                <div class="small text-muted mb-1">💰 Charged</div>
                <div class="fw-bold text-dark" style="font-size:1.1rem;">{{ formatMoney((form.lumpsum_total_charged || 0) * 100) }}</div>
              </CCol>
              <CCol md="4" class="text-center">
                <div class="small text-muted mb-1">✅ Paid</div>
                <div class="fw-bold text-success" style="font-size:1.1rem;">{{ formatMoney((form.lumpsum_total_paid || 0) * 100) }}</div>
              </CCol>
              <CCol md="4" class="text-center">
                <div class="small text-muted mb-1">⚠️ Outstanding</div>
                <div class="fw-bold" :class="lumpsumBalance() > 0 ? 'text-danger' : 'text-success'" style="font-size:1.1rem;">
                  {{ formatMoney(lumpsumBalance()) }}
                </div>
              </CCol>
            </CRow>
          </CCard>

          <CAlert v-if="lumpsumBalance() > 0" color="warning" class="mt-3 mb-0 small">
            ⚠️ {{ t('students.studentOwes') || 'Student owes' }} <strong>{{ formatMoney(lumpsumBalance()) }}</strong> {{ t('students.toCompleteAnnualFees') || 'to complete annual tuition fees' }}
          </CAlert>
          <CAlert v-else color="success" class="mt-3 mb-0 small">
            ✅ {{ t('students.allFeesPaidInFull') || 'All fees paid in full' }}
          </CAlert>
        </div>

        <CAlert v-if="errors.payment_history_total" color="danger" class="mt-3 mb-0 small">
          ⚠️ {{ errors.payment_history_total }}
        </CAlert>

        <!-- Annual Debt Summary -->
        <CCard v-if="form.payment_history.length > 0" class="mt-4 border-0" style="background:#f0f8ff;border-left:4px solid #d32f2f!important;">
          <CCardBody class="py-3">
            <div class="fw-bold text-danger mb-3" style="font-size:1.05rem;">
              📊 {{ t('students.annualDebtSummary') || 'Annual Debt Summary' }}
            </div>
            <CRow class="g-3">
              <CCol md="3">
                <div class="text-center">
                  <div class="small text-muted mb-1">💰 {{ t('students.totalChargedFees') || 'Total Charged' }}</div>
                  <div class="fw-bold text-dark" style="font-size:1.15rem;">
                    {{ formatMoney(totalHistoryFees() * 100) }}
                  </div>
                </div>
              </CCol>
              <CCol md="3">
                <div class="text-center">
                  <div class="small text-muted mb-1">✅ {{ t('students.totalPaidAmount') || 'Total Paid' }}</div>
                  <div class="fw-bold text-success" style="font-size:1.15rem;">
                    {{ formatMoney(totalHistoryPaid()) }}
                  </div>
                </div>
              </CCol>
              <CCol md="3">
                <div class="text-center">
                  <div class="small text-muted mb-1">⚠️ {{ t('students.outstandingBalance') || 'Outstanding Balance' }}</div>
                  <div class="fw-bold text-danger" style="font-size:1.15rem;">
                    {{ formatMoney(totalHistoryBalance()) }}
                  </div>
                </div>
              </CCol>
              <CCol md="3">
                <div class="text-center">
                  <div class="small text-muted mb-1">📈 {{ t('students.completionRate') || 'Paid %' }}</div>
                  <div class="fw-bold" :class="completionPercentage() >= 50 ? 'text-success' : 'text-warning'" style="font-size:1.15rem;">
                    {{ completionPercentage() }}%
                  </div>
                </div>
              </CCol>
            </CRow>
            <hr class="my-2" />
            <div class="small text-muted" style="font-size:.85rem;">
              <span v-if="totalHistoryBalance() > 0" class="text-danger">
                🔴 {{ t('students.studentStillOwes') || 'Student still owes' }} <strong>{{ formatMoney(totalHistoryBalance()) }}</strong> {{ t('students.toCompleteAnnualFees') || 'to complete annual tuition fees' }}
              </span>
              <span v-else class="text-success">
                ✅ {{ t('students.allFeesCleared') || 'All historical fees have been cleared' }}
              </span>
            </div>
          </CCardBody>
        </CCard>

        <CAlert v-if="form.payment_history.length === 0" color="secondary" class="mt-3 small">
          ℹ️ {{ t('students.noHistoryHint') }}
        </CAlert>
      </div>

      <CAlert v-if="submitError" color="danger" class="mt-3 mb-0">
        <div class="fw-semibold mb-1">⚠️ {{ submitError }}</div>
        <ul v-if="Object.keys(errors).length" class="mb-0 ps-3" style="font-size:.875rem;">
          <li v-for="(msg, field) in errors" :key="field">{{ msg }}</li>
        </ul>
      </CAlert>

      </div> <!-- End form content div -->
    </CModalBody>

    <CModalFooter>
      <CButton v-if="step > 1" color="secondary" @click="step--" style="min-height:44px;">
        {{ t('common.back') }}
      </CButton>
      <div class="ms-auto d-flex gap-2">
        <CButton v-if="step < steps.length" color="primary" @click="nextStep" style="min-height:44px;min-width:120px;">
          {{ t('common.next') }} →
        </CButton>
        <CButton v-if="step === steps.length" color="success" :disabled="saving" @click="submit"
                 style="min-height:44px;min-width:160px;background:#007f3e;border-color:#007f3e;">
          <CSpinner v-if="saving" size="sm" class="me-1" />
          ✓ {{ t('students.save') }}
        </CButton>
      </div>
    </CModalFooter>
  </CModal>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSchoolsStore } from '@/stores/schools'
import { useSchoolStore } from '@/stores/school'
import api from '@/services/api'
import studentDraftService from '@/services/studentDraftService'

const { t } = useI18n()

const props = defineProps({ visible: Boolean })
const emit  = defineEmits(['close', 'registered'])

const schoolsStore = useSchoolsStore()
const schoolStore  = useSchoolStore()
const schools      = computed(() => schoolsStore.schools)

const step          = ref(1)
const saving        = ref(false)
const fetchingAdmNo = ref(false)
const submitError   = ref('')
const errors        = ref({})
const photoPreview  = ref(null)
const photoFile     = ref(null)
const photoInput    = ref(null)
const academicYears = ref([])
const terms         = ref([])
const allClasses    = ref([])

// Draft management
const currentDraft  = ref(null)
const autoSaveFn    = ref(null)
const draftLoading  = ref(false)
const draftSaved    = ref(false)

// Allergies toggle (Step 2)
const hasAllergies = ref(false)

// Migration mode: detailed by term or lump sum annual
const migrationMode = ref('detailed')

// Guardian document types (adults only — no Student ID Card)
const idTypes = {
  nida:             t('students.idTypes.nida'),
  driving_license:  t('students.idTypes.driving_license'),
  voter_id:         t('students.idTypes.voter_id'),
  passport:         t('students.idTypes.passport'),
  birth_certificate:t('students.idTypes.birth_certificate'),
  other:            t('students.idTypes.other'),
}

// Phone normalize: any TZ format → 255XXXXXXXXX
function normalizePhone(raw) {
  const d = (raw || '').replace(/\D/g, '')
  if (d.startsWith('0') && d.length === 10) return '255' + d.slice(1)
  if (d.startsWith('255') && d.length === 12) return d
  if ((d.startsWith('7') || d.startsWith('6')) && d.length === 9) return '255' + d
  return d
}

// Fee preview (Step 5)
const feePreview          = ref(null)   // { items, total_cents } or null
const loadingFeePreview   = ref(false)
const hasOpeningBalance   = ref(false)

async function fetchFeePreview() {
  const f = form.value
  if (!f.school_id || !f.school_class_id || !f.academic_year_id || !f.term_id) {
    feePreview.value = null
    return
  }
  loadingFeePreview.value = true
  try {
    const res = await api.get('/fee-structures', {
      params: {
        school_id:        f.school_id,
        school_class_id:  f.school_class_id,
        academic_year_id: f.academic_year_id,
        term_id:          f.term_id,
      },
    })
    const structures = res.data.data ?? res.data
    if (structures.length) {
      const items = structures.flatMap(s => s.fee_items ?? s.feeItems ?? [])
      const totalCents = items.reduce((sum, i) => sum + (i.amount_cents ?? 0), 0)
      feePreview.value = { items, total_cents: totalCents }
    } else {
      feePreview.value = null
    }
  } catch {
    feePreview.value = null
  } finally {
    loadingFeePreview.value = false
  }
}

function formatMoney(cents) {
  return 'TZS ' + Math.round((cents || 0) / 100).toLocaleString('sw-TZ')
}

// Location lists
const regions        = ref([])
const districts      = ref([])
const wards          = ref([])
const streets        = ref([])
const places         = ref([])
const loadingRegions  = ref(false)
const loadingDistricts= ref(false)
const loadingWards    = ref(false)
const loadingStreets  = ref(false)
const loadingPlaces   = ref(false)

const steps = computed(() => {
  const base = [
    t('students.stepPersonal'),
    t('students.stepHealthAddress'),
    t('students.stepClass'),
    t('students.stepGuardians'),
    t('students.stepFinancial'),
  ]
  // A fully-sponsored student has no billing at all — no payment history/
  // migration step makes sense since there's nothing to ever collect.
  if (form.value.is_existing_student && form.value.sponsorship_type !== 'full') {
    base.push(t('students.stepMigration'))
  }
  return base
})

const defaultGuardian = () => ({
  full_name: '', relationship: '', phone: '', alt_phone: '',
  email: '', id_type: '', national_id: '', address: '', is_primary_contact: false, _exists: false,
})

const today = new Date().toISOString().slice(0, 10)

function blankForm() {
  return {
    admission_no: '',
    first_name: '', middle_name: '', last_name: '',
    gender: '', date_of_birth: '',
    birth_certificate_no: '', nationality: 'Tanzanian', religion: '',
    status: 'active',
    sponsorship_type: 'none', // 'none' | 'half' | 'full'
    // Health
    blood_group: '', allergies: '', medical_conditions: '',
    // Address
    address: '', region: '', district: '', ward: '', street: '', place: '',
    notes: '',
    // Enrollment
    school_id: '', school_class_id: '', academic_year_id: '', term_id: '',
    enrollment_date: today, previous_school: '',
    // Guardians
    guardians: [defaultGuardian()],
    // Financial
    total_tuition_fee: 0, opening_balance: 0, discount_type: '', discount_amount: 0,
    generate_first_invoice: false,  // Existing students don't generate first invoice
    // Migration
    is_existing_student: true,  // Default: all students are existing (migrating from books)
    payment_history: [{ academic_year_id: '', term_id: '', fee_amount: 0, payments: [] }],  // Start with one term entry ready to fill
    // Annual summary (lump sum mode)
    lumpsum_total_charged: 0,
    lumpsum_total_paid: 0,
    lumpsum_payment_date: '',  // when the recorded amount was actually paid (historical) — must not default to today
  }
}

const form = ref(blankForm())

// The steps array can shrink at runtime (e.g. switching to Fully Sponsored
// removes the Payment History step). If the user was sitting on a step number
// that no longer exists, clamp back onto the new last step instead of leaving
// step.value pointing past the end of the array — otherwise "step === steps.length"
// never matches, the Save button never renders, and the modal gets stuck.
// Placed here (after both `form` and `step` exist) deliberately: watch()
// evaluates its source once synchronously to capture a baseline, and `steps`
// reads form.value — declaring this watch any earlier reads form before its
// own `const form = ref(...)` has run.
watch(steps, (newSteps) => {
  if (step.value > newSteps.length) step.value = newSteps.length
})

function formatAmount(val) {
  if (!val && val !== 0) return ''
  return Number(val).toLocaleString('en-TZ')
}

function parseAmount(str) {
  const n = parseInt(String(str).replace(/,/g, ''), 10)
  return isNaN(n) ? 0 : n
}

const fullName = computed(() =>
  [form.value.first_name, form.value.middle_name, form.value.last_name].filter(Boolean).join(' ')
)

const filteredClasses = computed(() =>
  allClasses.value.filter(c => !form.value.school_id || String(c.school_id) === String(form.value.school_id))
)

const selectedSchoolName = computed(() =>
  schools.value.find(s => String(s.id) === String(form.value.school_id))?.name || '—'
)

const selectedClassName = computed(() =>
  allClasses.value.find(c => String(c.id) === String(form.value.school_class_id))?.name || '—'
)

// ── Photo ─────────────────────────────────────────────────────────────────────
function onPhotoChange(e) {
  const file = e.target.files[0]
  if (!file) return
  if (file.size > 2 * 1024 * 1024) {
    alert('Picha ni kubwa sana. Maks 2MB.')
    return
  }
  photoFile.value    = file
  photoPreview.value = URL.createObjectURL(file)
}

function removePhoto() {
  photoFile.value    = null
  photoPreview.value = null
  if (photoInput.value) photoInput.value.value = ''
}

// ── Admission number ──────────────────────────────────────────────────────────
async function fetchAdmissionNo(schoolId) {
  const id = schoolId ?? form.value.school_id
  if (!id) return
  fetchingAdmNo.value = true
  try {
    const { data } = await api.get('/students/next-admission-number', { params: { school_id: id } })
    form.value.admission_no = data.admission_number
  } catch {} finally { fetchingAdmNo.value = false }
}

async function onSchoolChange(newId) {
  form.value.school_class_id = ''
  if (newId) await fetchAdmissionNo(newId)
}

// ── Location cascade ──────────────────────────────────────────────────────────
async function fetchRegions() {
  loadingRegions.value = true
  try {
    const { data } = await api.get('/locations/regions')
    regions.value = data.data || []
  } catch {} finally { loadingRegions.value = false }
}

async function onRegionChange(newVal) {
  form.value.region = newVal
  form.value.district = ''; form.value.ward = ''; form.value.street = ''; form.value.place = ''
  districts.value = []; wards.value = []; streets.value = []; places.value = []
  const r = regions.value.find(r => r.name === newVal)
  if (!r) return
  loadingDistricts.value = true
  try {
    const { data } = await api.get('/locations/districts', { params: { state_id: r.id } })
    districts.value = data.data || []
  } catch {} finally { loadingDistricts.value = false }
}

async function onDistrictChange(newVal) {
  form.value.district = newVal
  form.value.ward = ''; form.value.street = ''; form.value.place = ''
  wards.value = []; streets.value = []; places.value = []
  const d = districts.value.find(d => d.name === newVal)
  if (!d) return
  loadingWards.value = true
  try {
    const { data } = await api.get('/locations/wards', { params: { lga_id: d.id } })
    wards.value = data.data || []
  } catch {} finally { loadingWards.value = false }
}

async function onWardChange(newVal) {
  form.value.ward = newVal
  form.value.street = ''; form.value.place = ''; streets.value = []; places.value = []
  const w = wards.value.find(w => w.name === newVal)
  if (!w) return
  loadingStreets.value = true
  try {
    const { data } = await api.get('/locations/streets', { params: { ward_id: w.id } })
    streets.value = data.data || []
  } catch {} finally { loadingStreets.value = false }
}

async function onStreetChange(newVal) {
  form.value.street = newVal
  form.value.place = ''; places.value = []
  const s = streets.value.find(s => s.name === newVal)
  if (!s) return
  loadingPlaces.value = true
  try {
    const { data } = await api.get('/locations/places', { params: { village_id: s.id } })
    places.value = data.data || []
  } catch {} finally { loadingPlaces.value = false }
}

// ── Guardians ─────────────────────────────────────────────────────────────────
function addGuardian()     { form.value.guardians.push(defaultGuardian()) }
function removeGuardian(i) { form.value.guardians.splice(i, 1) }
function setPrimary(idx)   { form.value.guardians.forEach((g, i) => { if (i !== idx) g.is_primary_contact = false }) }

// ── Existing student toggle with warning ───────────────────────────────────────────────────
function onExistingToggleWithWarning(isExisting) {
  // If toggling TO new student (false), show confirmation
  if (!isExisting) {
    const confirmed = confirm(
      '⚠️ WARNING!\n\n' +
      'You are switching to "New Student" mode.\n\n' +
      'This will:\n' +
      '✗ Remove all payment history entries\n' +
      '✗ Enable "Generate First Invoice"\n' +
      '✗ Return to Step 5\n\n' +
      'Most students are existing (migrating from books).\n\n' +
      'Continue?'
    )
    if (!confirmed) {
      // Revert the toggle
      form.value.is_existing_student = true
      return
    }
  }
  // Proceed with the toggle
  onExistingToggle(isExisting)
}

function onExistingToggle(isExisting) {
  if (!isExisting) {
    form.value.payment_history = []
    form.value.generate_first_invoice = true  // New students generate invoice
    // If user was on step 6 (migration), drop back to step 5
    if (step.value > 5) step.value = 5
  } else {
    form.value.generate_first_invoice = false  // Existing students don't generate invoice
    if (form.value.payment_history.length === 0) addTermHistory()
  }
}

// ── Migration history helpers ─────────────────────────────────────────────────
function defaultTermEntry() {
  return {
    academic_year_id: form.value.academic_year_id || '',
    term_id: '',
    fee_amount: 0,
    payments: [],
  }
}

function defaultPayment() {
  return { paid_at: today, amount: 0, method: 'cash', notes: '' }
}

function addTermHistory() { form.value.payment_history.push(defaultTermEntry()) }
function removeTermHistory(i) { form.value.payment_history.splice(i, 1) }
function addPayment(ei) { form.value.payment_history[ei].payments.push(defaultPayment()) }
function removePayment(ei, pi) { form.value.payment_history[ei].payments.splice(pi, 1) }

// ── Annual debt summary calculations ──────────────────────────────────────────
function totalHistoryFees() {
  return form.value.payment_history.reduce((sum, entry) => sum + (entry.fee_amount || 0), 0)
}

function totalHistoryPaid() {
  return form.value.payment_history.reduce((sum, entry) => {
    const paid = entry.payments.reduce((s, p) => s + ((p.amount || 0) * 100), 0)
    return sum + paid
  }, 0)
}

function totalHistoryBalance() {
  const totalFees = totalHistoryFees() * 100
  const totalPaid = totalHistoryPaid()
  return Math.max(0, totalFees - totalPaid)
}

function completionPercentage() {
  const totalFees = totalHistoryFees()
  if (totalFees === 0) return 0
  const totalPaid = totalHistoryPaid() / 100
  return Math.round((totalPaid / totalFees) * 100)
}

// ── Lump sum balance calculation ──────────────────────────────────────────
function lumpsumBalance() {
  const totalCharged = (form.value.lumpsum_total_charged || 0) * 100
  const totalPaid = (form.value.lumpsum_total_paid || 0) * 100
  return Math.max(0, totalCharged - totalPaid)
}

async function autoFillFee(ei) {
  const entry = form.value.payment_history[ei]
  const f = form.value
  if (!f.school_id || !f.school_class_id || !entry.academic_year_id || !entry.term_id) return
  try {
    const res = await api.get('/fee-structures', {
      params: {
        school_id: f.school_id,
        school_class_id: f.school_class_id,
        academic_year_id: entry.academic_year_id,
        term_id: entry.term_id,
      },
    })
    const structures = res.data.data ?? res.data
    if (structures.length) {
      const items = structures.flatMap(s => s.fee_items ?? s.feeItems ?? [])
      const totalCents = items.reduce((sum, i) => sum + (i.amount_cents ?? 0), 0)
      if (totalCents > 0) entry.fee_amount = Math.round(totalCents / 100)
    }
  } catch {}
}

function termPaid(entry) {
  return entry.payments.reduce((s, p) => s + ((p.amount || 0) * 100), 0)
}
function termBalance(entry) {
  return Math.max(0, (entry.fee_amount || 0) * 100 - termPaid(entry))
}

async function checkGuardianExists(idx) {
  // Normalize phone to 255 format on blur
  const raw = form.value.guardians[idx].phone
  form.value.guardians[idx].phone = normalizePhone(raw)
  const phone = form.value.guardians[idx].phone
  if (!phone || phone.length < 9) return
  try {
    const { data } = await api.get('/guardians', { params: { search: phone } })
    const list = data.data || data
    form.value.guardians[idx]._exists = Array.isArray(list) && list.some(g => (g.phone || g.user?.phone) === phone)
  } catch { form.value.guardians[idx]._exists = false }
}

// ── Validation ────────────────────────────────────────────────────────────────
function validateStep() {
  errors.value = {}
  if (step.value === 1) {
    if (!form.value.first_name)         errors.value.first_name          = t('students.errors.firstNameRequired')
    if (!form.value.last_name)          errors.value.last_name           = t('students.errors.lastNameRequired')
    if (!form.value.gender)             errors.value.gender              = t('students.errors.genderRequired')
    if (!form.value.date_of_birth)      errors.value.date_of_birth       = t('students.errors.dobRequired')
  }
  if (step.value === 3) {
    if (!form.value.school_id)        errors.value.school_id        = t('students.errors.schoolRequired')
    if (!form.value.school_class_id)  errors.value.school_class_id  = t('students.errors.classRequired')
    if (!form.value.academic_year_id) errors.value.academic_year_id = t('students.errors.yearRequired')
    if (!form.value.term_id)          errors.value.term_id          = t('students.errors.termRequired')
    if (!form.value.enrollment_date)  errors.value.enrollment_date  = t('students.errors.enrollmentDateRequired')
  }
  if (step.value === 4) {
    const g = form.value.guardians[0]
    if (!g?.full_name)    errors.value['guardians.0.full_name']    = t('guardians.errors.nameRequired')
    if (!g?.phone)        errors.value['guardians.0.phone']        = t('guardians.errors.phoneRequired')
    if (!g?.relationship) errors.value['guardians.0.relationship'] = t('guardians.errors.relationshipRequired')
  }
  if (step.value === 5) {
    // Fully-sponsored students have no tuition fee to enter — sponsorship
    // covers everything, so the field doesn't apply.
    if (form.value.sponsorship_type !== 'full') {
      if (!form.value.total_tuition_fee || form.value.total_tuition_fee <= 0) {
        errors.value.total_tuition_fee = t('students.errors.totalTuitionFeeRequired')
      }
    }
  }
  if (step.value === 6) {
    // Only validate if existing student
    if (form.value.is_existing_student) {
      // Detailed mode: validate payment_history
      if (migrationMode.value === 'detailed') {
        if (!form.value.payment_history || form.value.payment_history.length === 0) {
          errors.value['payment_history'] = t('students.errors.paymentHistoryRequired')
        } else {
          form.value.payment_history.forEach((entry, ei) => {
            if (!entry.academic_year_id) errors.value[`payment_history.${ei}.academic_year_id`] = t('students.errors.yearRequired')
            if (!entry.term_id)          errors.value[`payment_history.${ei}.term_id`]          = t('students.errors.termRequired')
            if (!entry.fee_amount || entry.fee_amount <= 0)
              errors.value[`payment_history.${ei}.fee_amount_cents`] = t('students.errors.feeAmountRequired')
            ;(entry.payments || []).forEach((p, pi) => {
              // A payment row with amount 0 means "nothing paid yet for this term" —
              // that's a valid state (matches backend which silently skips amount<=0
              // rows during import), not an error. Only require a date when an actual
              // amount was entered.
              const hasAmount = p.amount && p.amount > 0
              if (hasAmount && !p.paid_at) {
                errors.value[`payment_history.${ei}.payments.${pi}.paid_at`] = t('students.paidDate') + ' ' + t('common.required')
              }
            })
          })

          // The sum of every term's "Fee amount for this term" must not exceed the
          // annual Total Tuition Fee defined in Step 5 — that figure is the cap for
          // the whole year's history.
          const cap = form.value.total_tuition_fee || 0
          if (cap > 0 && totalHistoryFees() > cap) {
            errors.value['payment_history_total'] =
              `${t('students.errors.termFeesExceedTuition') || 'Total term fees exceed the Total Tuition Fee'}: ${formatMoney(totalHistoryFees() * 100)} > ${formatMoney(cap * 100)}`
          }
        }
      }
      // Lumpsum mode: validate lumpsum fields
      else if (migrationMode.value === 'lumpsum') {
        if (!form.value.lumpsum_total_charged || form.value.lumpsum_total_charged <= 0)
          errors.value['lumpsum_total_charged'] = t('students.errors.totalChargedRequired')
        if (!form.value.lumpsum_total_paid || form.value.lumpsum_total_paid < 0)
          errors.value['lumpsum_total_paid'] = t('students.errors.totalPaidRequired')
        // If any amount was recorded as paid, the historical payment date is required —
        // without it the backend has no correct date to use and "Today's Collections"
        // on the dashboard gets silently inflated with old money.
        if (form.value.lumpsum_total_paid > 0 && !form.value.lumpsum_payment_date) {
          errors.value['lumpsum_payment_date'] = t('students.errors.lumpsumPaymentDateRequired')
        }
      }
    }
  }
  return Object.keys(errors.value).length === 0
}

function nextStep() {
  if (!validateStep()) return
  step.value++
  if (step.value === 5) {
    hasOpeningBalance.value = false
    form.value.opening_balance = 0
    fetchFeePreview()
  }
}

// ── Submit ────────────────────────────────────────────────────────────────────
async function submit() {
  if (saving.value) return  // guard against double-submit (fast double-click/tap)

  let isValid = false
  try {
    isValid = validateStep()
  } catch (e) {
    // If validation itself throws (e.g. malformed data resumed from an old draft),
    // this must never fail silently — surface it instead of leaving the button
    // looking dead.
    submitError.value = t('common.saveFailed')
    return
  }
  if (!isValid) {
    // Always surface SOME visible feedback — a silent early-return here looks
    // exactly like "the button isn't working" if the failing field has no
    // inline error UI of its own.
    submitError.value = t('common.fixErrors')
    return
  }
  saving.value = true
  submitError.value = ''
  errors.value = {}

  try {
    const fd = new FormData()
    const f  = form.value

    // In lumpsum mode, explicitly clear payment_history before sending
    if (f.is_existing_student && migrationMode.value === 'lumpsum') {
      f.payment_history = []
    }
    // Fully-sponsored students never go through Step 6, but blankForm() seeds
    // payment_history with one blank entry by default — without this guard that
    // stale entry (empty term_id, zero fee) still gets sent and the backend
    // correctly 422s it, which then jumps the UI to a step that no longer
    // exists for a fully-sponsored student.
    if (f.sponsorship_type === 'full') {
      f.payment_history = []
    }

    const fields = {
      admission_no:           f.admission_no,
      first_name:             f.first_name,
      middle_name:            f.middle_name,
      last_name:              f.last_name,
      gender:                 f.gender,
      date_of_birth:          f.date_of_birth,
      birth_certificate_no:   f.birth_certificate_no,
      nationality:            f.nationality || 'Tanzanian',
      religion:               f.religion,
      blood_group:            f.blood_group,
      allergies:              f.allergies,
      medical_conditions:     f.medical_conditions,
      address:                f.address,
      region:                 f.region,
      district:               f.district,
      ward:                   f.ward,
      street:                 f.street,
      place:                  f.place,
      status:                 f.status,
      sponsorship_type:       f.sponsorship_type || 'none',
      notes:                  f.notes,
      school_id:              f.school_id,
      school_class_id:        f.school_class_id,
      academic_year_id:       f.academic_year_id,
      term_id:                f.term_id,
      enrollment_date:        f.enrollment_date,
      previous_school:        f.previous_school,
      total_tuition_fee_cents: Math.round((f.total_tuition_fee || 0) * 100),
      opening_balance_cents:  Math.round((f.opening_balance || 0) * 100),
      discount_type:          f.discount_type || '',
      discount_amount_cents:  Math.round((f.discount_amount || 0) * 100),
      generate_first_invoice: f.generate_first_invoice ? 1 : 0,
      is_existing_student: f.is_existing_student ? 1 : 0,
      migration_mode: migrationMode.value || 'detailed',
      lumpsum_total_charged_cents: Math.round((f.lumpsum_total_charged || 0) * 100),
      lumpsum_total_paid_cents: Math.round((f.lumpsum_total_paid || 0) * 100),
      lumpsum_payment_date: f.lumpsum_payment_date || '',
    }

    Object.entries(fields).forEach(([k, v]) => fd.append(k, v ?? ''))
    f.guardians.forEach((g, i) => {
      Object.entries(g).forEach(([k, v]) => {
        if (!k.startsWith('_')) fd.append(`guardians[${i}][${k}]`, v ?? '')
      })
    })

    // Migration history — ONLY in detailed mode, NEVER in lumpsum mode
    // In lumpsum mode, payment_history must not be appended to FormData at all
    if (f.is_existing_student && migrationMode.value === 'detailed' && f.payment_history.length > 0) {
      f.payment_history.forEach((entry, ei) => {
        fd.append(`payment_history[${ei}][term_id]`, entry.term_id)
        fd.append(`payment_history[${ei}][academic_year_id]`, entry.academic_year_id)
        fd.append(`payment_history[${ei}][fee_amount_cents]`, Math.round((entry.fee_amount || 0) * 100))
        // A payment row with amount 0 carries no information — the term's fee_amount
        // alone already represents the outstanding debt. Sending it would fail the
        // backend's own `min:1` validation on amount_cents, so it's dropped here
        // rather than submitted as a meaningless zero-value payment record.
        ;(entry.payments || []).filter(p => p.amount && p.amount > 0).forEach((p, pi) => {
          fd.append(`payment_history[${ei}][payments][${pi}][amount_cents]`, Math.round((p.amount || 0) * 100))
          fd.append(`payment_history[${ei}][payments][${pi}][paid_at]`, p.paid_at)
          fd.append(`payment_history[${ei}][payments][${pi}][method]`, p.method || 'cash')
          fd.append(`payment_history[${ei}][payments][${pi}][notes]`, p.notes || 'Migrated from books')
        })
      })
    } else if (migrationMode.value === 'lumpsum') {
      // SAFETY CHECK: In lumpsum mode, explicitly do NOT append payment_history
      // This prevents the required_with:payment_history validation from triggering
    }

    if (photoFile.value) fd.append('photo', photoFile.value)

    await api.post('/students/register', fd, { headers: { 'Content-Type': 'multipart/form-data' } })

    // Delete draft after successful registration
    await deleteDraft()

    emit('registered')
    resetForm()
  } catch (e) {
    if (e?.response?.status === 422) {
      const errs = e.response.data.errors || {}
      errors.value = Object.fromEntries(Object.entries(errs).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]))
      submitError.value = t('common.fixErrors')
      // Navigate to step with first error
      const firstErr = Object.keys(errors.value)[0]
      if (['first_name','last_name','gender','date_of_birth','admission_no','status','nationality'].includes(firstErr)) {
        step.value = 1
      } else if (['blood_group','allergies','medical_conditions','address','region','district','ward','street','religion'].includes(firstErr)) {
        step.value = 2
      } else if (['school_id','school_class_id','academic_year_id','term_id','enrollment_date'].includes(firstErr)) {
        step.value = 3
      } else if (firstErr?.startsWith('guardians')) {
        step.value = 4
      } else if (firstErr?.startsWith('payment_history')) {
        step.value = 6
      }
    } else {
      submitError.value = e?.response?.data?.message || t('common.saveFailed')
    }
  } finally {
    saving.value = false
  }
}

function resetForm() {
  step.value = 1
  photoPreview.value = null
  photoFile.value    = null
  errors.value       = {}
  submitError.value  = ''
  districts.value    = []
  wards.value        = []
  streets.value      = []
  places.value       = []
  form.value    = blankForm()
  hasAllergies.value = false
  currentDraft.value = null
  draftSaved.value   = false
}

const showResumeDialog = ref(false)
const hasDraft = ref(false)

watch(() => props.visible, async (v) => {
  if (v) {
    resetForm()
    // Check if there's a draft available
    if (schoolStore.activeSchoolId) {
      const drafts = await studentDraftService.getDrafts(schoolStore.activeSchoolId)
      hasDraft.value = drafts && drafts.length > 0
      if (hasDraft.value) {
        showResumeDialog.value = true
        return // Don't proceed with normal init until user chooses
      }
    }
    // If no draft, proceed with normal initialization
    await initializeModal()
  }
})

async function initializeModal() {
  // Re-fetch regions each time the modal opens so IDs are always fresh
  try { await fetchRegions() } catch {}
  // Restore the current-year default and its terms
  const currentYear = academicYears.value.find(y => y.is_current) || academicYears.value[0]
  if (currentYear) form.value.academic_year_id = currentYear.id
}

function onResume() {
  showResumeDialog.value = false
  loadDraft()
  setupAutoSave()
}

function onStartFresh() {
  showResumeDialog.value = false
  hasDraft.value = false
  initializeModal()
  setupAutoSave()
}

// Terms belong to an academic year — reload them whenever the selected year changes
async function loadTerms(yearId) {
  if (!yearId) { terms.value = []; return }
  try {
    const { data } = await api.get('/terms', { params: { academic_year_id: yearId } })
    terms.value = data.data || data || []
  } catch { terms.value = [] }
}

watch(() => form.value.academic_year_id, async (yearId) => {
  await loadTerms(yearId)
  // keep the selected term valid for the new year
  if (!terms.value.some(tm => String(tm.id) === String(form.value.term_id))) {
    const currentTerm = terms.value.find(tm => tm.is_current) || terms.value[0]
    form.value.term_id = currentTerm ? currentTerm.id : ''
  }
})

// Draft functions
async function loadDraft() {
  if (!schoolStore.activeSchoolId) return
  draftLoading.value = true
  try {
    const drafts = await studentDraftService.getDrafts(schoolStore.activeSchoolId)
    if (drafts && drafts.length > 0) {
      const draft = drafts[0]
      currentDraft.value = draft
      // Populate form from draft
      Object.keys(form.value).forEach(key => {
        if (draft[key] !== undefined && draft[key] !== null) {
          form.value[key] = draft[key]
        }
      })
      // Normalize payment_history shape — a draft saved by an older code path may be
      // missing fields (e.g. `payments`), which would otherwise crash validateStep()
      // silently when the user tries to save.
      if (Array.isArray(form.value.payment_history)) {
        form.value.payment_history = form.value.payment_history.map(entry => ({
          academic_year_id: entry?.academic_year_id ?? '',
          term_id: entry?.term_id ?? '',
          fee_amount: entry?.fee_amount ?? 0,
          payments: Array.isArray(entry?.payments) ? entry.payments : [],
        }))
      }
      step.value = draft.current_step || 1
    }
  } catch (e) {
    // Silently ignore draft load errors - not critical
    currentDraft.value = null
  } finally {
    draftLoading.value = false
  }
}

function setupAutoSave() {
  if (!schoolStore.activeSchoolId) return
  autoSaveFn.value = studentDraftService.createAutoSaveFn(
    schoolStore.activeSchoolId,
    (saved) => {
      // Update tracked draft with latest version (important for fallback scenarios)
      if (saved && saved.id) {
        currentDraft.value = saved
      }
      draftSaved.value = true
      setTimeout(() => { draftSaved.value = false }, 2000)
    }
  )
}

async function saveDraftNow() {
  if (!schoolStore.activeSchoolId) return

  // Only save if we have at least name and step
  if (!form.value.first_name || !form.value.last_name) return

  try {
    const draftData = {
      ...form.value,
      current_step: step.value,
    }
    await studentDraftService.saveDraft(schoolStore.activeSchoolId, draftData)
  } catch (e) {
    // Silently ignore draft save failures
  }
}

async function deleteDraft() {
  if (!currentDraft.value) return
  try {
    await studentDraftService.deleteDraft(currentDraft.value.id)
    currentDraft.value = null
  } catch (e) {
    console.error('Failed to delete draft:', e)
  }
}

// Auto-populate lumpsum_total_charged from total_tuition_fee
watch(() => migrationMode.value, (newMode) => {
  if (newMode === 'lumpsum' && form.value.total_tuition_fee && form.value.total_tuition_fee > 0) {
    // Auto-fill charged amount from step 5 total tuition fee
    if (!form.value.lumpsum_total_charged || form.value.lumpsum_total_charged === 0) {
      form.value.lumpsum_total_charged = form.value.total_tuition_fee
    }
  }
})

// Auto-save on form changes (with guards to prevent invalid saves)
watch(() => form.value, (newForm) => {
  if (!schoolStore.activeSchoolId || !autoSaveFn.value) return
  if (!newForm.first_name || !newForm.last_name) return

  const draftData = {
    ...newForm,
    current_step: step.value,
  }
  autoSaveFn.value(draftData)
}, { deep: true, throttle: 500 })

watch(() => step.value, () => {
  if (!schoolStore.activeSchoolId) return
  if (!form.value.first_name || !form.value.last_name) return
  saveDraftNow()
}, { throttle: 500 })

onMounted(async () => {
  try { await schoolsStore.fetchSchools() } catch {}
  try { await fetchRegions() } catch {}
  try {
    const schoolId = schoolStore.activeSchoolId
    const [yrRes, clRes] = await Promise.all([
      api.get('/academic-years', { params: schoolId ? { school_id: schoolId } : {} }),
      api.get('/school-classes', { params: { all: 1 } }),
    ])
    academicYears.value = yrRes.data.data || yrRes.data || []
    allClasses.value    = clRes.data.data || clRes.data || []

    const currentYear = academicYears.value.find(y => y.is_current) || academicYears.value[0]
    if (currentYear) form.value.academic_year_id = currentYear.id

    // Setup draft functionality
    setupAutoSave()
    await loadDraft()
  } catch {}
})
</script>
