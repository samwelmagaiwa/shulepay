<?php
namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest {
    public function authorize(): bool
    {
        $user = auth()->user();
        if (! $user) return false;
        if ($user->school_id === null) return true; // superadmin
        $requestedSchoolId = $this->integer('school_id');
        return $requestedSchoolId === $user->school_id;
    }

    public function rules(): array {
        return [
            // Identity fields
            'first_name'       => 'required|string|max:100',
            'last_name'        => 'required|string|max:100',
            'date_of_birth'    => 'nullable|date|before:today',
            'gender'           => 'nullable|in:male,female',
            'notes'            => 'nullable|string',

            // Enrollment fields
            'school_id'        => 'required|exists:schools,id',
            'school_class_id'  => 'required|exists:school_classes,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'admitted_at'      => 'required|date',
        ];
    }
}
