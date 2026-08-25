<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class RegisterStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Student identity
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'admission_no' => 'nullable|string|max:30',
            'gender' => 'required|in:male,female',
            'date_of_birth' => 'required|date|before:today',
            'birth_certificate_no' => 'nullable|string|max:50',
            'nationality' => 'nullable|string|max:50',
            'photo' => 'nullable|image|max:2048',
            'blood_group' => 'nullable|string|max:10',
            'allergies' => 'nullable|string|max:255',
            'medical_conditions' => 'nullable|string|max:1000',
            'address' => 'nullable|string|max:500',
            'religion' => 'nullable|string|max:50',
            'region' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'ward' => 'nullable|string|max:100',
            'street' => 'nullable|string|max:100',
            'status' => 'required|in:active,transferred,graduated,dropped,sponsored,orphaned',
            'sponsorship_type' => 'nullable|in:none,half,full',
            'notes' => 'nullable|string',

            // Enrollment
            'school_id' => 'required|exists:schools,id',
            'school_class_id' => 'required|exists:school_classes,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'term_id' => 'required|exists:terms,id',
            'enrollment_date' => 'required|date',
            'previous_school' => 'nullable|string|max:200',

            // Identifications
            'identifications' => 'nullable|array',
            'identifications.*.type' => 'required_with:identifications|string|in:nida,driving_license,voter_id,passport,birth_certificate,student_id,other',
            'identifications.*.number' => 'required_with:identifications|string|max:100',
            'identifications.*.expires_at' => 'nullable|date|after:today',
            'identifications.*.is_primary' => 'nullable|boolean',

            // Guardians
            'guardians' => 'required|array|min:1',
            'guardians.*.full_name' => 'required|string|max:200',
            'guardians.*.relationship' => 'required|in:father,mother,guardian',
            'guardians.*.phone' => 'required|string|max:20',
            'guardians.*.alt_phone' => 'nullable|string|max:20',
            'guardians.*.email' => 'nullable|email|max:200',
            'guardians.*.national_id' => 'nullable|string|max:50',
            'guardians.*.address' => 'nullable|string',
            'guardians.*.is_primary_contact' => 'nullable|in:true,false,1,0,yes,no',

            // Financial
            'total_tuition_fee_cents' => 'nullable|integer|min:0',
            'discount_type' => 'nullable|in:sibling,staff,sponsor,other',
            'discount_amount_cents' => 'nullable|integer|min:0',
            'opening_balance_cents' => 'nullable|integer|min:0',
            'generate_first_invoice' => 'nullable|boolean',

            // Migration: existing student payment history
            'is_existing_student' => 'nullable|boolean',
            'migration_mode' => 'nullable|in:detailed,lumpsum',
            // Payment history — lenient validation, detailed mode only enforced in controller
            'payment_history' => 'nullable|array',
            'payment_history.*.term_id' => 'nullable|exists:terms,id',
            'payment_history.*.academic_year_id' => 'nullable|exists:academic_years,id',
            'payment_history.*.fee_amount_cents' => 'nullable|integer|min:1',
            'payment_history.*.payments' => 'nullable|array',
            // min:0, not min:1 — a payment row with amount 0 means "nothing paid yet
            // for this term" and is a valid state; the import service already skips
            // creating a Payment record for it (StudentRegistrationService::importPaymentHistory).
            'payment_history.*.payments.*.amount_cents' => 'nullable|integer|min:0',
            'payment_history.*.payments.*.paid_at' => 'nullable|date|before_or_equal:today',
            'payment_history.*.payments.*.method' => 'nullable|in:cash,mpesa,bank,cheque',
            'payment_history.*.payments.*.notes' => 'nullable|string|max:300',
            // Lump sum mode: annual summary
            'lumpsum_total_charged_cents' => 'nullable|integer|min:0',
            'lumpsum_total_paid_cents' => 'nullable|integer|min:0',
            'lumpsum_payment_date' => 'nullable|date|before_or_equal:today',
        ];
    }

    public function messages(): array
    {
        return [
            // Student identity
            'first_name.required' => 'First name is required.',
            'first_name.max' => 'First name must not exceed 100 characters.',
            'last_name.required' => 'Last name is required.',
            'last_name.max' => 'Last name must not exceed 100 characters.',
            'gender.required' => 'Please select a gender.',
            'gender.in' => 'Gender must be "male" or "female".',
            'date_of_birth.required' => 'Date of birth is required.',
            'date_of_birth.date' => 'Date of birth is not a valid date.',
            'date_of_birth.before' => 'Date of birth must be before today.',
            'status.required' => 'Student status is required.',
            'status.in' => 'Invalid student status.',
            'photo.image' => 'Photo must be an image file (JPG, PNG, etc.).',
            'photo.max' => 'Photo must not exceed 2 MB.',
            // Identifications
            'identifications.*.type.required_with' => 'Identification type is required.',
            'identifications.*.type.in' => 'Invalid identification type.',
            'identifications.*.number.required_with' => 'Identification number is required.',
            'identifications.*.expires_at.date' => 'Expiry date is not a valid date.',
            'identifications.*.expires_at.after' => 'Expiry date must be in the future.',
            // Enrollment
            'school_id.required' => 'Please select a school.',
            'school_id.exists' => 'The selected school does not exist.',
            'school_class_id.required' => 'Please select a class.',
            'school_class_id.exists' => 'The selected class does not exist.',
            'academic_year_id.required' => 'Please select an academic year.',
            'academic_year_id.exists' => 'The selected academic year does not exist.',
            'term_id.required' => 'Please select a term.',
            'term_id.exists' => 'The selected term does not exist.',
            'enrollment_date.required' => 'Enrollment date is required.',
            'enrollment_date.date' => 'Enrollment date is not a valid date.',
            // Guardians
            'guardians.required' => 'At least one guardian is required.',
            'guardians.array' => 'Guardian data is invalid.',
            'guardians.min' => 'At least one guardian is required.',
            'guardians.*.full_name.required' => 'Guardian full name is required.',
            'guardians.*.relationship.required' => 'Guardian relationship is required.',
            'guardians.*.relationship.in' => 'Relationship must be: father, mother, or guardian.',
            'guardians.*.phone.required' => 'Guardian phone number is required.',
            'guardians.*.email.email' => 'Guardian email address is invalid.',
            // Financial
            'discount_amount_cents.integer' => 'Discount amount must be a number.',
            'discount_amount_cents.min' => 'Discount amount cannot be negative.',
            'opening_balance_cents.integer' => 'Opening balance must be a number.',
            'opening_balance_cents.min' => 'Opening balance cannot be negative.',
        ];
    }
}
