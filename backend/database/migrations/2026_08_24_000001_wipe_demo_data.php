<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time data wipe: clears all demo/imported data.
 * Keeps: superadmin, owner, accountant users; schools; school classes; location data.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $truncate = fn (string $table) => Schema::hasTable($table) && DB::table($table)->truncate();

        // Operational logs / notifications
        $truncate('audit_logs');
        $truncate('sms_logs');
        $truncate('login_history');
        $truncate('otp_codes');
        $truncate('school_notifications');

        // Finance — payments first, then invoices
        $truncate('payment_promises');
        $truncate('installment_payments');
        $truncate('installment_plans');
        $truncate('refunds');
        $truncate('discounts');
        $truncate('receipts');
        $truncate('payments');
        $truncate('invoice_lines');
        $truncate('invoices');

        // Fee structures
        $truncate('fee_items');
        $truncate('fee_structures');

        // Academic calendar
        $truncate('terms');
        $truncate('academic_years');

        // Exams / attendance / subjects
        $truncate('exam_results');
        $truncate('exams');
        $truncate('class_subjects');
        $truncate('subjects');
        $truncate('attendances');

        // Students
        $truncate('student_identifications');
        $truncate('guardian_student');
        $truncate('enrollments');
        $truncate('students');

        // Guardians — then delete their user accounts
        $parentUserIds = DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.name', 'parent')
            ->pluck('model_has_roles.model_id');

        DB::table('model_has_roles')->whereIn('model_id', $parentUserIds)->delete();
        $truncate('guardians');
        DB::table('users')->whereIn('id', $parentUserIds)->delete();

        // Expenses & payroll
        $truncate('expenses');
        $truncate('expense_categories');
        $truncate('petty_cash_entries');
        $truncate('payroll_entries');
        $truncate('employees');
        $truncate('supplier_payments');
        $truncate('suppliers');

        // Assets & inventory
        $truncate('assets');
        $truncate('inventory_transactions');
        $truncate('inventory_items');

        // Transport
        $truncate('student_transport_subscriptions');
        $truncate('vehicle_maintenance');
        $truncate('vehicle_routes');
        $truncate('vehicles');

        // Stationery / budgets
        $truncate('stationary_requests');
        $truncate('budgets');
        $truncate('budget_items');

        // Multi-school user access (reset to default)
        $truncate('user_school_access');

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        // Irreversible data wipe — no rollback
    }
};
