<template>
  <CModal :visible="visible" @close="$emit('close')" size="xl" class="modal-fullscreen-sm-down" :backdrop="'static'" scrollable>
    <CModalHeader style="border-bottom:2px solid #007f3e;">
      <CModalTitle class="fw-bold">{{ t('students.register') }}</CModalTitle>
    </CModalHeader>

    <CModalBody class="p-2 p-md-3">

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
            <CFormInput v-model="form.birth_certificate_no" placeholder="Nambari ya cheti cha kuzaliwa" />
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
            <div class="input-group">
              <CFormInput v-model="form.admission_no" placeholder="Auto" maxlength="30" :class="{'is-invalid': errors.admission_no}" />
              <CButton color="secondary" variant="outline" size="sm"
                       @click="fetchAdmissionNo()" :disabled="!form.school_id || fetchingAdmNo">
                <CSpinner v-if="fetchingAdmNo" size="sm" />
                <span v-else>Auto</span>
              </CButton>
            </div>
            <div class="invalid-feedback d-block" v-if="errors.admission_no">{{ errors.admission_no }}</div>
          </CCol>
        </CRow>
      </div>

      <!-- ═══════════════ STEP 2: Health & Address ═══════════════ -->
      <div v-if="step === 2">
        <!-- Health -->
        <div class="fw-semibold text-muted small mb-3 text-uppercase" style="letter-spacing:.05em;">🩺 {{ t('students.healthSection') }}</div>
        <CRow class="g-3 mb-2">
          <CCol xs="12" sm="4">
            <label class="form-label fw-semibold">{{ t('students.bloodGroup') }}</label>
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
            <label class="form-label">{{ t('students.allergies') }}</label>
            <CFormInput v-model="form.allergies" :placeholder="t('students.allergiesPlaceholder')" />
          </CCol>
          <CCol xs="12" sm="4">
            <label class="form-label">{{ t('students.medicalConditions') }}</label>
            <CFormInput v-model="form.medical_conditions" :placeholder="t('students.medicalConditionsPlaceholder')" />
          </CCol>
        </CRow>

        <hr class="my-3" />

        <!-- Address -->
        <div class="fw-semibold text-muted small mb-3 text-uppercase" style="letter-spacing:.05em;">📍 {{ t('students.addressSection') }}</div>
        <CRow class="g-3">
          <CCol xs="12" sm="4">
            <label class="form-label">{{ t('students.address') }}</label>
            <CFormTextarea v-model="form.address" rows="2" :placeholder="t('students.addressPlaceholder')" />
          </CCol>
          <CCol xs="12" sm="4">
            <label class="form-label">{{ t('students.region') }}</label>
            <CFormSelect v-model="form.region"
              @update:modelValue="onRegionChange"
              :disabled="loadingRegions">
              <option value="">{{ loadingRegions ? t('common.loading') : `— ${t('students.selectRegion')} —` }}</option>
              <option v-for="r in regions" :key="r.id" :value="r.name">{{ r.name }}</option>
            </CFormSelect>
          </CCol>
          <CCol xs="12" sm="4">
            <label class="form-label d-flex align-items-center gap-1">
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
            <label class="form-label d-flex align-items-center gap-1">
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
          <CCol xs="12" sm="4">
            <label class="form-label d-flex align-items-center gap-1">
              {{ t('students.street') }}
              <CSpinner v-if="loadingStreets" size="sm" style="width:12px;height:12px;" />
            </label>
            <!-- Dropdown when streets exist; free-text when ward has no street data -->
            <CFormSelect v-if="streets.length > 0 || loadingStreets"
              v-model="form.street" :disabled="!form.ward || loadingStreets"
              @update:modelValue="onStreetChange">
              <option value="">{{ loadingStreets ? t('common.loading') : `— ${t('students.selectStreet')} —` }}</option>
              <option v-for="st in streets" :key="st.id" :value="st.name">{{ st.name }}</option>
            </CFormSelect>
            <CFormInput v-else
              v-model="form.street"
              :disabled="!form.ward"
              placeholder="Andika jina la mtaa / kijiji"
              @input="form.place = ''; places = []"
            />
            <div v-if="form.ward && !loadingStreets && streets.length === 0" class="text-muted mt-1" style="font-size:.72rem;">
              Hakuna data — andika mwenyewe
            </div>
          </CCol>
          <CCol xs="12" sm="4">
            <label class="form-label d-flex align-items-center gap-1">
              Mtaa / Kijiji (Place)
              <CSpinner v-if="loadingPlaces" size="sm" style="width:12px;height:12px;" />
            </label>
            <!-- Dropdown when places loaded from DB -->
            <CFormSelect v-if="places.length > 0 || (form.street && loadingPlaces)"
              v-model="form.place" :disabled="!form.street || loadingPlaces">
              <option value="">{{ loadingPlaces ? t('common.loading') : '— Chagua mtaa / sehemu —' }}</option>
              <option v-for="pl in places" :key="pl.id" :value="pl.name">{{ pl.name }}</option>
            </CFormSelect>
            <!-- Always show input; disabled until street is chosen -->
            <CFormInput v-else
              v-model="form.place"
              :disabled="!form.street || loadingPlaces"
              placeholder="Andika jina la sehemu (optional)"
            />
          </CCol>
          <CCol xs="12" sm="4">
            <label class="form-label">{{ t('students.notes') }}</label>
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
          <CRow class="g-2">
            <CCol xs="12" sm="6">
              <label class="form-label fw-semibold">{{ t('guardians.fullName') }} <span class="text-danger">*</span></label>
              <CFormInput v-model="g.full_name" :class="{'is-invalid': errors[`guardians.${gi}.full_name`]}" />
              <div class="invalid-feedback">{{ errors[`guardians.${gi}.full_name`] }}</div>
            </CCol>
            <CCol xs="12" sm="6">
              <label class="form-label fw-semibold">{{ t('guardians.relationship') }} <span class="text-danger">*</span></label>
              <CFormSelect v-model="g.relationship" :class="{'is-invalid': errors[`guardians.${gi}.relationship`]}">
                <option value="">{{ t('common.select') }}</option>
                <option value="father">{{ t('guardians.father') }}</option>
                <option value="mother">{{ t('guardians.mother') }}</option>
                <option value="guardian">{{ t('guardians.guardian') }}</option>
              </CFormSelect>
              <div class="invalid-feedback">{{ errors[`guardians.${gi}.relationship`] }}</div>
            </CCol>
            <CCol xs="12" sm="6">
              <label class="form-label fw-semibold">{{ t('guardians.phone') }} <span class="text-danger">*</span></label>
              <CFormInput v-model="g.phone" type="tel" placeholder="0712345678"
                          :class="{'is-invalid': errors[`guardians.${gi}.phone`]}"
                          @blur="checkGuardianExists(gi)" />
              <div class="invalid-feedback">{{ errors[`guardians.${gi}.phone`] }}</div>
              <div v-if="g._exists" class="text-warning small mt-1">⚠ Nambari hii tayari ipo kwenye mfumo</div>
            </CCol>
            <CCol xs="12" sm="6">
              <label class="form-label">{{ t('guardians.altPhone') }}</label>
              <CFormInput v-model="g.alt_phone" type="tel" placeholder="Nambari mbadala" />
            </CCol>
            <CCol xs="12" sm="6">
              <label class="form-label">{{ t('common.email') }}</label>
              <CFormInput v-model="g.email" type="email" placeholder="barua@mfano.com" />
            </CCol>
            <CCol xs="12" sm="6">
              <label class="form-label">{{ t('guardians.nationalId') }}</label>
              <CFormInput v-model="g.national_id" placeholder="Nambari ya kitambulisho" />
            </CCol>
            <CCol xs="12">
              <label class="form-label">{{ t('common.address') }}</label>
              <CFormTextarea v-model="g.address" rows="2" placeholder="Anwani ya mlezi" />
            </CCol>
            <CCol xs="12">
              <CFormCheck v-model="g.is_primary_contact" :label="t('guardians.primaryContact')" @change="setPrimary(gi)" />
            </CCol>
          </CRow>
        </div>
        <CButton color="success" variant="outline" @click="addGuardian" style="min-height:44px;">
          <CIcon icon="cilPlus" class="me-1" /> {{ t('guardians.addAnother') }}
        </CButton>
        <div v-if="errors.guardians" class="text-danger small mt-2">{{ errors.guardians }}</div>
      </div>

      <!-- ═══════════════ STEP 5: Financial ═══════════════ -->
      <div v-if="step === 5">
        <CRow class="g-3">
          <CCol xs="12" sm="6">
            <label class="form-label">{{ t('students.openingBalance') }}</label>
            <CFormInput
              type="text"
              inputmode="numeric"
              :value="formatAmount(form.opening_balance)"
              @input="form.opening_balance = parseAmount($event.target.value)"
              placeholder="0"
            />
            <div class="text-muted small">{{ t('students.openingBalanceHint') }}</div>
          </CCol>
          <CCol xs="12" sm="6">
            <label class="form-label">{{ t('students.discountType') }}</label>
            <CFormSelect v-model="form.discount_type">
              <option value="">{{ t('students.noDiscount') }}</option>
              <option value="sibling">{{ t('students.discountSibling') }}</option>
              <option value="staff">{{ t('students.discountStaff') }}</option>
              <option value="sponsor">{{ t('students.discountSponsor') }}</option>
              <option value="other">{{ t('common.other') }}</option>
            </CFormSelect>
          </CCol>
          <CCol xs="12" sm="6" v-if="form.discount_type">
            <label class="form-label">{{ t('students.discountAmount') }} (TZS)</label>
            <CFormInput type="number" v-model.number="form.discount_amount" min="0" placeholder="0" />
          </CCol>
          <CCol xs="12">
            <div class="d-flex align-items-center gap-3 p-3 rounded" style="background:#f8f9fa;">
              <CFormSwitch v-model="form.generate_first_invoice" id="genInvoice" size="xl" />
              <div>
                <div class="fw-semibold">{{ t('students.generateFirstInvoice') }}</div>
                <div class="text-muted small">{{ t('students.generateFirstInvoiceHint') }}</div>
              </div>
            </div>
          </CCol>
        </CRow>

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
              <div class="text-uppercase fw-bold mb-2" style="font-size:.65rem;letter-spacing:.08em;color:#007f3e;">👤 Taarifa za Mwanafunzi</div>
              <CRow class="g-1 small">
                <CCol xs="6" sm="4"><span class="text-muted">Jina Kamili:</span><br><strong>{{ fullName || '—' }}</strong></CCol>
                <CCol xs="6" sm="4"><span class="text-muted">Jinsia:</span><br><strong>{{ form.gender === 'male' ? 'Kiume' : form.gender === 'female' ? 'Kike' : '—' }}</strong></CCol>
                <CCol xs="6" sm="4"><span class="text-muted">Tarehe ya Kuzaliwa:</span><br><strong>{{ form.date_of_birth || '—' }}</strong></CCol>
                <CCol xs="6" sm="4"><span class="text-muted">Nambari ya Usajili:</span><br><strong>{{ form.admission_no || '(Auto)' }}</strong></CCol>
                <CCol xs="6" sm="4"><span class="text-muted">Uraia:</span><br><strong>{{ form.nationality || '—' }}</strong></CCol>
                <CCol xs="6" sm="4"><span class="text-muted">Dini:</span><br><strong>{{ form.religion || '—' }}</strong></CCol>
                <CCol xs="6" sm="4"><span class="text-muted">Hali:</span><br>
                  <CBadge :color="form.status === 'active' ? 'success' : form.status === 'sponsored' ? 'info' : form.status === 'orphaned' ? 'warning' : 'secondary'" style="font-size:.72rem;">
                    {{ form.status || '—' }}
                  </CBadge>
                </CCol>
                <CCol xs="6" sm="4"><span class="text-muted">Namba ya Cheti:</span><br><strong>{{ form.birth_certificate_no || '—' }}</strong></CCol>
                <CCol xs="6" sm="4"><span class="text-muted">Picha:</span><br>
                  <CBadge :color="photoFile ? 'success' : 'secondary'" style="font-size:.72rem;">
                    {{ photoFile ? 'Imepakiwa' : 'Hakuna' }}
                  </CBadge>
                </CCol>
              </CRow>
            </div>

            <hr class="my-0" />

            <!-- Academic -->
            <div class="px-3 py-2">
              <div class="text-uppercase fw-bold mb-2" style="font-size:.65rem;letter-spacing:.08em;color:#007f3e;">🎓 Taarifa za Shule</div>
              <CRow class="g-1 small">
                <CCol xs="6" sm="4"><span class="text-muted">Shule:</span><br><strong>{{ selectedSchoolName }}</strong></CCol>
                <CCol xs="6" sm="4"><span class="text-muted">Darasa:</span><br><strong>{{ selectedClassName }}</strong></CCol>
                <CCol xs="6" sm="4"><span class="text-muted">Mwaka wa Masomo:</span><br>
                  <strong>{{ academicYears.find(y => String(y.id) === String(form.academic_year_id))?.name || '—' }}</strong>
                </CCol>
                <CCol xs="6" sm="4"><span class="text-muted">Muhula:</span><br>
                  <strong>{{ terms.find(t => String(t.id) === String(form.term_id))?.name || '—' }}</strong>
                </CCol>
                <CCol xs="6" sm="4"><span class="text-muted">Tarehe ya Kuandikishwa:</span><br><strong>{{ form.enrollment_date || '—' }}</strong></CCol>
                <CCol xs="6" sm="4" v-if="form.previous_school"><span class="text-muted">Shule ya Awali:</span><br><strong>{{ form.previous_school }}</strong></CCol>
              </CRow>
            </div>

            <hr class="my-0" />

            <!-- Health -->
            <div class="px-3 py-2">
              <div class="text-uppercase fw-bold mb-2" style="font-size:.65rem;letter-spacing:.08em;color:#007f3e;">🩺 Afya</div>
              <CRow class="g-1 small">
                <CCol xs="6" sm="4"><span class="text-muted">Kundi la Damu:</span><br><strong>{{ form.blood_group || '—' }}</strong></CCol>
                <CCol xs="6" sm="4" v-if="form.allergies"><span class="text-muted">Mzio:</span><br><strong>{{ form.allergies }}</strong></CCol>
                <CCol xs="6" sm="4" v-if="form.medical_conditions"><span class="text-muted">Hali ya Kiafya:</span><br><strong>{{ form.medical_conditions }}</strong></CCol>
              </CRow>
            </div>

            <!-- Address (only if filled) -->
            <template v-if="form.region || form.district || form.ward">
              <hr class="my-0" />
              <div class="px-3 py-2">
                <div class="text-uppercase fw-bold mb-2" style="font-size:.65rem;letter-spacing:.08em;color:#007f3e;">📍 Makazi</div>
                <CRow class="g-1 small">
                  <CCol xs="6" sm="4" v-if="form.region"><span class="text-muted">Mkoa:</span><br><strong>{{ form.region }}</strong></CCol>
                  <CCol xs="6" sm="4" v-if="form.district"><span class="text-muted">Wilaya:</span><br><strong>{{ form.district }}</strong></CCol>
                  <CCol xs="6" sm="4" v-if="form.ward"><span class="text-muted">Kata:</span><br><strong>{{ form.ward }}</strong></CCol>
                  <CCol xs="6" sm="4" v-if="form.street"><span class="text-muted">Mtaa:</span><br><strong>{{ form.street }}</strong></CCol>
                  <CCol xs="6" sm="4" v-if="form.place"><span class="text-muted">Sehemu:</span><br><strong>{{ form.place }}</strong></CCol>
                  <CCol xs="12" v-if="form.address"><span class="text-muted">Anwani:</span> <strong>{{ form.address }}</strong></CCol>
                </CRow>
              </div>
            </template>

            <hr class="my-0" />

            <!-- Guardians -->
            <div class="px-3 py-2">
              <div class="text-uppercase fw-bold mb-2" style="font-size:.65rem;letter-spacing:.08em;color:#007f3e;">👪 Walezi ({{ form.guardians.length }})</div>
              <div v-for="(g, gi) in form.guardians" :key="gi" class="mb-1 small d-flex align-items-start gap-2">
                <span class="text-muted" style="min-width:18px;">{{ gi+1 }}.</span>
                <div>
                  <strong>{{ g.full_name || '—' }}</strong>
                  <span v-if="g.relationship" class="text-muted ms-1">({{ g.relationship }})</span>
                  <span v-if="g.phone" class="ms-2">📞 {{ g.phone }}</span>
                  <CBadge v-if="g.is_primary_contact" color="success" class="ms-1" style="font-size:.65rem;">Mkuu</CBadge>
                </div>
              </div>
            </div>

            <hr class="my-0" />

            <!-- Financial -->
            <div class="px-3 py-2">
              <div class="text-uppercase fw-bold mb-2" style="font-size:.65rem;letter-spacing:.08em;color:#007f3e;">💰 Fedha</div>
              <CRow class="g-1 small">
                <CCol xs="6" sm="4"><span class="text-muted">Ankara ya Kwanza:</span><br>
                  <CBadge :color="form.generate_first_invoice ? 'success' : 'secondary'" style="font-size:.72rem;">
                    {{ form.generate_first_invoice ? t('common.yes') : t('common.no') }}
                  </CBadge>
                </CCol>
                <CCol xs="6" sm="4" v-if="form.opening_balance > 0"><span class="text-muted">Salio la Awali:</span><br><strong>TZS {{ (form.opening_balance || 0).toLocaleString() }}</strong></CCol>
                <CCol xs="6" sm="4" v-if="form.discount_type"><span class="text-muted">Punguzo:</span><br><strong>{{ form.discount_type }} — TZS {{ (form.discount_amount || 0).toLocaleString() }}</strong></CCol>
              </CRow>
            </div>

          </CCardBody>
        </CCard>
      </div>

      <CAlert v-if="submitError" color="danger" class="mt-3 mb-0">
        <div class="fw-semibold mb-1">⚠️ {{ submitError }}</div>
        <ul v-if="Object.keys(errors).length" class="mb-0 ps-3" style="font-size:.875rem;">
          <li v-for="(msg, field) in errors" :key="field">{{ msg }}</li>
        </ul>
      </CAlert>
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
import api from '@/services/api'

const { t } = useI18n()

const props = defineProps({ visible: Boolean })
const emit  = defineEmits(['close', 'registered'])

const schoolsStore = useSchoolsStore()
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

const steps = computed(() => [
  'Taarifa za Mwanafunzi',
  'Afya & Makazi',
  t('students.stepClass'),
  t('students.stepGuardians'),
  t('students.stepFinancial'),
])

const defaultGuardian = () => ({
  full_name: '', relationship: '', phone: '', alt_phone: '',
  email: '', national_id: '', address: '', is_primary_contact: false, _exists: false,
})

const today = new Date().toISOString().slice(0, 10)

function blankForm() {
  return {
    admission_no: '',
    first_name: '', middle_name: '', last_name: '',
    gender: '', date_of_birth: '',
    birth_certificate_no: '', nationality: 'Tanzanian', religion: '',
    status: 'active',
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
    opening_balance: 0, discount_type: '', discount_amount: 0,
    generate_first_invoice: true,
  }
}

const form = ref(blankForm())

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

async function checkGuardianExists(idx) {
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
    if (!form.value.first_name)   errors.value.first_name   = t('students.errors.firstNameRequired')
    if (!form.value.last_name)    errors.value.last_name    = t('students.errors.lastNameRequired')
    if (!form.value.gender)       errors.value.gender       = t('students.errors.genderRequired')
    if (!form.value.date_of_birth) errors.value.date_of_birth = t('students.errors.dobRequired')
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
  return Object.keys(errors.value).length === 0
}

function nextStep() {
  if (!validateStep()) return
  step.value++
}

// ── Submit ────────────────────────────────────────────────────────────────────
async function submit() {
  if (!validateStep()) return
  saving.value = true
  submitError.value = ''
  errors.value = {}

  try {
    const fd = new FormData()
    const f  = form.value

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
      notes:                  f.notes,
      school_id:              f.school_id,
      school_class_id:        f.school_class_id,
      academic_year_id:       f.academic_year_id,
      term_id:                f.term_id,
      enrollment_date:        f.enrollment_date,
      previous_school:        f.previous_school,
      opening_balance_cents:  Math.round((f.opening_balance || 0) * 100),
      discount_type:          f.discount_type || '',
      discount_amount_cents:  Math.round((f.discount_amount || 0) * 100),
      generate_first_invoice: f.generate_first_invoice ? 1 : 0,
    }

    Object.entries(fields).forEach(([k, v]) => fd.append(k, v ?? ''))
    f.guardians.forEach((g, i) => {
      Object.entries(g).forEach(([k, v]) => {
        if (!k.startsWith('_')) fd.append(`guardians[${i}][${k}]`, v ?? '')
      })
    })
    if (photoFile.value) fd.append('photo', photoFile.value)

    await api.post('/students/register', fd, { headers: { 'Content-Type': 'multipart/form-data' } })

    emit('registered')
    resetForm()
  } catch (e) {
    if (e?.response?.status === 422) {
      const errs = e.response.data.errors || {}
      errors.value = Object.fromEntries(Object.entries(errs).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]))
      submitError.value = t('common.fixErrors')
      // Navigate to step with first error
      const firstErr = Object.keys(errors.value)[0]
      if (['first_name','last_name','gender','date_of_birth','admission_no','status','nationality','birth_certificate_no'].includes(firstErr)) {
        step.value = 1
      } else if (['blood_group','allergies','medical_conditions','address','region','district','ward','street','religion'].includes(firstErr)) {
        step.value = 2
      } else if (['school_id','school_class_id','academic_year_id','term_id','enrollment_date'].includes(firstErr)) {
        step.value = 3
      } else if (firstErr?.startsWith('guardians')) {
        step.value = 4
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
  form.value = blankForm()
}

watch(() => props.visible, async (v) => {
  if (v) {
    resetForm()
    // Re-fetch regions each time the modal opens so IDs are always fresh
    try { await fetchRegions() } catch {}
  }
})

onMounted(async () => {
  try { await schoolsStore.fetchSchools() } catch {}
  try { await fetchRegions() } catch {}
  try {
    const [yrRes, tmRes, clRes] = await Promise.all([
      api.get('/academic-years'),
      api.get('/terms'),
      api.get('/school-classes', { params: { all: 1 } }),
    ])
    academicYears.value = yrRes.data.data || yrRes.data || []
    terms.value         = tmRes.data.data || tmRes.data || []
    allClasses.value    = clRes.data.data || clRes.data || []

    const currentYear = academicYears.value.find(y => y.is_current) || academicYears.value[0]
    const currentTerm = terms.value.find(tm => tm.is_current) || terms.value[0]
    if (currentYear) form.value.academic_year_id = currentYear.id
    if (currentTerm) form.value.term_id = currentTerm.id
  } catch {}
})
</script>
