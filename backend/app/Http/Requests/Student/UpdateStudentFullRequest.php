<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Backs PUT /students/{student}/full — the wizard-driven edit path used when
 * "Edit" reopens AddStudentModal instead of the old single-page form.
 * Deliberately separate from UpdateStudentRequest (the narrower, pre-existing
 * PUT /students/{student} contract) so that endpoint's callers are unaffected.
 */
class UpdateStudentFullRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        if (!($user->hasRole('superadmin') || $user->hasRole('accountant') || $user->hasRole('owner'))) {
            return false;
        }

        if ($user->school_id === null) {
            return true;
        }

        $student = $this->route('student');

        return $student?->currentEnrollment?->school_id === $user->school_id;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'sometimes|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female',
            'status' => 'sometimes|in:active,transferred,graduated,dropped,sponsored,orphaned,half_sponsored',
            'sponsorship_type' => 'sometimes|in:none,half,full,full_paid',
            'birth_certificate_no' => 'nullable|string|max:50',
            'nationality' => 'nullable|string|max:50',
            'religion' => 'nullable|string|max:50',
            'blood_group' => 'nullable|string|max:10',
            'allergies' => 'nullable|string|max:255',
            'medical_conditions' => 'nullable|string|max:1000',
            'address' => 'nullable|string|max:500',
            'region' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'ward' => 'nullable|string|max:100',
            'street' => 'nullable|string|max:100',
            'place' => 'nullable|string|max:100',
            'photo' => 'nullable|image|max:2048',
            'notes' => 'nullable|string',
            'school_class_id' => 'sometimes|exists:school_classes,id',

            'guardians' => 'nullable|array',
            'guardians.*.full_name' => 'required_with:guardians|string|max:200',
            'guardians.*.relationship' => 'required_with:guardians|in:father,mother,guardian',
            'guardians.*.phone' => 'nullable|string|max:20',
            'guardians.*.alt_phone' => 'nullable|string|max:20',
            'guardians.*.email' => 'nullable|email|max:200',
            'guardians.*.national_id' => 'nullable|string|max:50',
            'guardians.*.address' => 'nullable|string',
            'guardians.*.is_primary_contact' => 'nullable|in:true,false,1,0,yes,no',

            // Opt-in only: entering these never touches an existing invoice, it
            // creates one new invoice, and only when generate_new_invoice is true.
            'generate_new_invoice' => 'nullable|boolean',
            'total_tuition_fee_cents' => 'nullable|integer|min:0',
            'sponsored_amount_cents' => 'nullable|integer|min:0',
            'discount_type' => 'nullable|in:sibling,staff,sponsor,other',
            'discount_amount_cents' => 'nullable|integer|min:0',
            'opening_balance_cents' => 'nullable|integer|min:0',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->boolean('generate_new_invoice')) {
                return;
            }

            $sponsorship = $this->input('sponsorship_type', 'none');
            $fee = (int) $this->input('total_tuition_fee_cents', 0);
            $discountType = $this->input('discount_type');
            $discount = (int) $this->input('discount_amount_cents', 0);

            if ($sponsorship === 'full') {
                $validator->errors()->add(
                    'generate_new_invoice',
                    'A fully sponsored student with no payments cannot have an invoice generated.'
                );

                return;
            }

            if ($fee <= 0) {
                $validator->errors()->add('total_tuition_fee_cents', 'Total tuition fee is required.');
            }

            if ($sponsorship === 'full_paid') {
                $sponsored = (int) $this->input('sponsored_amount_cents', 0);
                if ($sponsored <= 0) {
                    $validator->errors()->add('sponsored_amount_cents', 'Enter the amount the sponsor is covering.');
                } elseif ($fee > 0 && $sponsored > $fee) {
                    $validator->errors()->add(
                        'sponsored_amount_cents',
                        'Sponsored amount cannot be greater than the total tuition fee.'
                    );
                }
            }

            if ($discountType) {
                if ($discount <= 0) {
                    $validator->errors()->add(
                        'discount_amount_cents',
                        'Enter the discount amount for the selected discount type.'
                    );
                } elseif ($fee > 0 && $discount > $fee) {
                    $validator->errors()->add(
                        'discount_amount_cents',
                        'Discount cannot be greater than the total tuition fee.'
                    );
                }
            } elseif ($discount > 0) {
                $validator->errors()->add('discount_type', 'Select a discount type for the amount entered.');
            }
        });
    }
}
