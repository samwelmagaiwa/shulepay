<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearDemoData extends Command
{
    protected $signature   = 'shulepay:clear-demo
                                {--school-id=1 : Only clear students belonging to this school}
                                {--force : Skip confirmation prompt}';

    protected $description = 'Remove all demo/seeded student data (students, enrollments, invoices, payments, guardians). Keeps schools, classes, users, and fee structures intact.';

    public function handle(): int
    {
        $schoolId = (int) $this->option('school-id');

        if (!$this->option('force')) {
            $this->warn("This will PERMANENTLY delete all students, enrollments, invoices, payments, and guardian records for school ID {$schoolId}.");
            if (!$this->confirm('Continue?')) {
                $this->info('Aborted.');
                return 0;
            }
        }

        DB::transaction(function () use ($schoolId) {
            // Disable FK checks so we can truncate in any order
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            // Payments first (FK → invoices)
            $paymentCount = DB::table('payments')->where('school_id', $schoolId)->delete();

            // Invoice lines (FK → invoices)
            $invoiceIds = DB::table('invoices')->where('school_id', $schoolId)->pluck('id');
            $lineCount  = DB::table('invoice_lines')->whereIn('invoice_id', $invoiceIds)->delete();

            // Invoices
            $invoiceCount = DB::table('invoices')->where('school_id', $schoolId)->delete();

            // Enrollments (FK → students)
            $enrollCount = DB::table('enrollments')->where('school_id', $schoolId)->delete();

            // Student-guardian pivot
            $studentIds = DB::table('students')->pluck('id');
            $pivotCount = DB::table('guardian_student')->whereIn('student_id', $studentIds)->delete();

            // Students (no school_id column — delete all since enrollments are gone)
            $studentCount = DB::table('students')->delete();

            // Guardians that are NOT system users (keep owner/accountant accounts)
            // Guardians linked via pivot to any user with role 'parent'
            $keepUserIds = DB::table('model_has_roles')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->whereIn('roles.name', ['owner', 'accountant'])
                ->pluck('model_has_roles.model_id');

            $guardianCount = DB::table('guardians')
                ->whereNotIn('user_id', $keepUserIds)
                ->delete();

            // Parent-role users that no longer have guardians
            $orphanParentIds = DB::table('users')
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('roles.name', 'parent')
                ->whereNotIn('users.id', $keepUserIds)
                ->pluck('users.id');

            DB::table('users')->whereIn('id', $orphanParentIds)->delete();

            // Audit logs for student-related actions
            DB::table('audit_logs')
                ->whereIn('action', ['student.created', 'student.imported_excel', 'payment.created', 'invoice.created'])
                ->delete();

            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            $this->table(['Table', 'Rows deleted'], [
                ['payments',        $paymentCount],
                ['invoice_lines',   $lineCount],
                ['invoices',        $invoiceCount],
                ['enrollments',     $enrollCount],
                ['student_guardian',$pivotCount],
                ['students',        $studentCount],
                ['guardians',       $guardianCount],
                ['users (parent)',  $orphanParentIds->count()],
            ]);
        });

        $this->info('Demo data cleared. Ready for real import.');
        return 0;
    }
}
