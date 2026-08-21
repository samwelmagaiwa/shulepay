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
            'birth_certificate_no' => 'required|string|max:50',
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
            'notes' => 'nullable|string',

            // Enrollment
            'school_id' => 'required|exists:schools,id',
            'school_class_id' => 'required|exists:school_classes,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'term_id' => 'required|exists:terms,id',
            'enrollment_date' => 'required|date',
            'previous_school' => 'nullable|string|max:200',

            // Identifications
            'identifications'            => 'nullable|array',
            'identifications.*.type'     => 'required_with:identifications|string|in:nida,driving_license,voter_id,passport,birth_certificate,student_id,other',
            'identifications.*.number'   => 'required_with:identifications|string|max:100',
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
            'discount_type' => 'nullable|in:sibling,staff,sponsor,other',
            'discount_amount_cents' => 'nullable|integer|min:0',
            'opening_balance_cents' => 'nullable|integer|min:0',
            'generate_first_invoice' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            // Student identity
            'first_name.required' => 'Jina la kwanza linahitajika.',
            'first_name.max' => 'Jina la kwanza halizidi herufi 100.',
            'last_name.required' => 'Jina la familia linahitajika.',
            'last_name.max' => 'Jina la familia halizidi herufi 100.',
            'gender.required' => 'Tafadhali chagua jinsia.',
            'gender.in' => 'Jinsia lazima iwe "Kiume" au "Kike".',
            'date_of_birth.required' => 'Tarehe ya kuzaliwa inahitajika.',
            'date_of_birth.date' => 'Tarehe ya kuzaliwa si sahihi.',
            'date_of_birth.before' => 'Tarehe ya kuzaliwa lazima iwe kabla ya leo.',
            'status.required' => 'Hali ya mwanafunzi inahitajika.',
            'status.in' => 'Hali si sahihi.',
            'photo.image' => 'Faili lazima iwe picha (JPG, PNG, n.k).',
            'photo.max' => 'Picha haitakiwi kuzidi MB 2.',
            // Enrollment
            'school_id.required' => 'Tafadhali chagua shule.',
            'school_id.exists' => 'Shule iliyochaguliwa haipo.',
            'school_class_id.required' => 'Tafadhali chagua darasa.',
            'school_class_id.exists' => 'Darasa lililochaguliwa haipo.',
            'academic_year_id.required' => 'Tafadhali chagua mwaka wa masomo.',
            'academic_year_id.exists' => 'Mwaka wa masomo haipo.',
            'term_id.required' => 'Tafadhali chagua muhula.',
            'term_id.exists' => 'Muhula uliochaguliwa haipo.',
            'enrollment_date.required' => 'Tarehe ya kuandikishwa inahitajika.',
            'enrollment_date.date' => 'Tarehe ya kuandikishwa si sahihi.',
            // Guardians
            'guardians.required' => 'Angalau mlezi mmoja anahitajika.',
            'guardians.array' => 'Taarifa za walezi si sahihi.',
            'guardians.min' => 'Angalau mlezi mmoja anahitajika.',
            'guardians.*.full_name.required' => 'Jina kamili la mlezi linahitajika.',
            'guardians.*.relationship.required' => 'Uhusiano wa mlezi na mwanafunzi unahitajika.',
            'guardians.*.relationship.in' => 'Uhusiano lazima uwe: baba, mama, au mlezi.',
            'birth_certificate_no.required' => 'Nambari ya cheti cha kuzaliwa inahitajika.',
            'identifications.*.type.required_with' => 'Aina ya utambulisho inahitajika.',
            'identifications.*.type.in' => 'Aina ya utambulisho si sahihi.',
            'identifications.*.number.required_with' => 'Nambari ya utambulisho inahitajika.',
            'identifications.*.expires_at.date' => 'Tarehe ya mwisho si sahihi.',
            'identifications.*.expires_at.after' => 'Tarehe ya mwisho lazima iwe baadaye ya leo.',
            'guardians.*.phone.required' => 'Nambari ya simu ya mlezi inahitajika.',
            'guardians.*.email.email' => 'Barua pepe ya mlezi si sahihi.',
            // Financial
            'discount_amount_cents.integer' => 'Kiasi cha punguzo lazima kiwe namba.',
            'discount_amount_cents.min' => 'Kiasi cha punguzo hakiwezi kuwa chini ya sifuri.',
            'opening_balance_cents.integer' => 'Bakaa ya awali lazima iwe namba.',
            'opening_balance_cents.min' => 'Bakaa ya awali haiwezi kuwa chini ya sifuri.',
        ];
    }
}
