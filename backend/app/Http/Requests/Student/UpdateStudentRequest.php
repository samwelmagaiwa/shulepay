<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        // Only superadmin, accountant, and owner can edit students
        if (! ($user->hasRole('superadmin') || $user->hasRole('accountant') || $user->hasRole('owner'))) {
            return false;
        }

        if ($user->school_id === null) {
            return true;
        } // superadmin
        $student = $this->route('student');

        return $student?->currentEnrollment?->school_id === $user->school_id;
    }

    public function rules(): array
    {
        return [
            // Identity
            'first_name' => 'sometimes|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female',
            'status' => 'sometimes|in:active,transferred,graduated,dropped',
            'birth_certificate_no' => 'nullable|string|max:50',
            'nationality' => 'nullable|string|max:50',
            'religion' => 'nullable|string|max:50',
            // Health
            'blood_group' => 'nullable|string|max:10',
            'allergies' => 'nullable|string|max:255',
            'medical_conditions' => 'nullable|string|max:1000',
            // Address
            'address' => 'nullable|string|max:500',
            'region' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'ward' => 'nullable|string|max:100',
            'street' => 'nullable|string|max:100',
            'place' => 'nullable|string|max:100',
            // Photo
            'photo' => 'nullable|image|max:2048',
            // Notes
            'notes' => 'nullable|string',
            // Enrollment update
            'school_class_id' => 'sometimes|exists:school_classes,id',
        ];
    }
}
