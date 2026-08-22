<?php

use App\Http\Controllers\Accountant\AssetController;
use App\Http\Controllers\Accountant\BulkImportController;
use App\Http\Controllers\Accountant\ClearanceController;
use App\Http\Controllers\Accountant\DiscountController;
use App\Http\Controllers\Accountant\EmployeeController;
use App\Http\Controllers\Accountant\ExpenseCategoryController;
use App\Http\Controllers\Accountant\ExpenseController;
use App\Http\Controllers\Accountant\FeeStructureController;
use App\Http\Controllers\Accountant\GuardianController;
use App\Http\Controllers\Accountant\InstallmentController;
use App\Http\Controllers\Accountant\InvoiceController;
use App\Http\Controllers\Accountant\PaymentController;
use App\Http\Controllers\Accountant\PaymentPromiseController;
use App\Http\Controllers\Accountant\PayrollController;
use App\Http\Controllers\Accountant\PettyCashController;
use App\Http\Controllers\Accountant\ReceiptController;
use App\Http\Controllers\Accountant\RefundController;
use App\Http\Controllers\Accountant\RolloverController;
use App\Http\Controllers\Accountant\StudentController;
use App\Http\Controllers\Accountant\SupplierController;
use App\Http\Controllers\Accountant\SupplierPaymentController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Owner\BudgetController;
use App\Http\Controllers\Owner\DashboardController;
use App\Http\Controllers\Owner\UserController;
use App\Http\Controllers\Owner\UserSchoolAccessController;
use App\Http\Controllers\ParentPortal\ChildController;
use App\Http\Controllers\ParentPortal\StatementController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Shared\AcademicYearController;
use App\Http\Controllers\Shared\AuditLogController;
use App\Http\Controllers\Shared\LocationController;
use App\Http\Controllers\Shared\LoginHistoryController;
use App\Http\Controllers\Shared\SchoolClassController;
use App\Http\Controllers\Shared\SchoolController;
use App\Http\Controllers\Shared\TermController;
use App\Http\Controllers\Shared\UserSettingsController;
use App\Http\Controllers\Sms\SmsController;
use App\Http\Controllers\Superadmin\RolePermissionController;
use App\Http\Controllers\Superadmin\SuperadminUserController;
use App\Http\Controllers\Transport\TransportController;
use Illuminate\Support\Facades\Route;

// ── Public ────────────────────────────────────────────────────
Route::get('/auth/schools', [AuthController::class, 'schools']);
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/auth/me', [AuthController::class, 'me'])->middleware('auth:sanctum');

// 2FA — public because user is not yet authenticated when calling these
Route::post('2fa/request', [TwoFactorController::class, 'request'])->middleware('throttle:10,1');
Route::post('2fa/verify', [TwoFactorController::class, 'verify'])->middleware('throttle:5,15');

Route::middleware('auth:sanctum')->group(function () {

    // Shared — all authenticated roles
    Route::apiResource('schools', SchoolController::class)->only(['index', 'show']);
    Route::apiResource('academic-years', AcademicYearController::class)->only(['index', 'show']);
    Route::apiResource('school-classes', SchoolClassController::class)->only(['index', 'show']);
    Route::apiResource('terms', TermController::class)->only(['index', 'show']);

    // Locations (Tanzania regions, districts, wards, streets)
    Route::get('locations/regions', [LocationController::class, 'regions']);
    Route::get('locations/districts', [LocationController::class, 'districts']);
    Route::get('locations/wards', [LocationController::class, 'wards']);
    Route::get('locations/streets', [LocationController::class, 'streets']);
    Route::get('locations/places', [LocationController::class, 'places']);

    // Branding — read: all authenticated; write: owner/superadmin only
    Route::get('branding', [BrandingController::class, 'show']);
    Route::post('branding', [BrandingController::class, 'update'])->middleware('role:owner|superadmin');
    Route::delete('branding/logo', [BrandingController::class, 'deleteLogo'])->middleware('role:owner|superadmin');

    // User settings (all authenticated users)
    Route::get('settings/profile', [UserSettingsController::class, 'profile']);
    Route::put('settings/profile', [UserSettingsController::class, 'updateProfile']);
    Route::post('settings/toggle-2fa', [UserSettingsController::class, 'toggle2fa']);

    // Teaching staff — attendance + notifications
    Route::middleware('role:teacher|head_teacher|headmaster|academic_teacher')->group(function () {
        Route::get('attendance/register', [AttendanceController::class, 'getRegister']);
        Route::post('attendance/bulk-mark', [AttendanceController::class, 'bulkMark']);
        Route::get('attendance/summary', [AttendanceController::class, 'summary']);
        Route::get('attendance/student-report', [AttendanceController::class, 'studentReport']);
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);
    });

    // Accountant + Owner + Superadmin
    Route::middleware('role:accountant|owner|superadmin')->group(function () {

        // Dashboard
        Route::get('dashboard/stats', [DashboardController::class, 'stats']);

        // Students — custom routes MUST precede apiResource to avoid {student} capturing them
        Route::post('students/register', [StudentController::class, 'register']);
        Route::get('students/next-admission-number', [StudentController::class, 'nextAdmissionNumber']);
        Route::apiResource('students', StudentController::class);

        // Guardians
        Route::apiResource('guardians', GuardianController::class);

        // Fee Structures
        Route::apiResource('fee-structures', FeeStructureController::class);

        // Discounts
        Route::apiResource('discounts', DiscountController::class)->only(['index', 'store', 'destroy']);

        // Invoices
        Route::post('invoices/generate-preview', [InvoiceController::class, 'generatePreview']);
        Route::post('invoices/generate', [InvoiceController::class, 'generate']);
        Route::apiResource('invoices', InvoiceController::class)->only(['index', 'show']);

        // Payments
        Route::apiResource('payments', PaymentController::class)->only(['index', 'store']);

        // Receipts
        Route::get('receipts/{receipt}/download', [ReceiptController::class, 'download']);

        // Refunds
        Route::apiResource('refunds', RefundController::class)->only(['index', 'store', 'destroy']);

        // Installment plans
        Route::post('installments/bulk-by-class', [InstallmentController::class, 'bulkByClass']);
        Route::apiResource('installments', InstallmentController::class)->only(['index', 'store', 'show']);
        Route::post('installments/{installment}/mark-paid', [InstallmentController::class, 'markPaid']);

        // Payment promises (debt management)
        Route::apiResource('payment-promises', PaymentPromiseController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        // Fee clearance
        Route::post('clearance/issue', [ClearanceController::class, 'issue']);
        Route::get('clearance/check', [ClearanceController::class, 'check']);

        // Academic year rollover
        Route::get('rollover/preview', [RolloverController::class, 'preview']);
        Route::post('rollover/execute', [RolloverController::class, 'execute']);

        // Bulk student import
        Route::get('bulk-import/template', [BulkImportController::class, 'template']);
        Route::post('bulk-import/preview', [BulkImportController::class, 'preview']);
        Route::post('bulk-import/import', [BulkImportController::class, 'import']);

        // ── Phase 2: Expenses & Accounting ────────────────────────

        // Expense Categories
        Route::apiResource('expense-categories', ExpenseCategoryController::class)->except(['show']);

        // Expenses
        Route::apiResource('expenses', ExpenseController::class);
        Route::post('expenses/{expense}/approve', [ExpenseController::class, 'approve'])->middleware('role:owner');

        // Petty Cash
        Route::get('petty-cash/balance', [PettyCashController::class, 'balance']);
        Route::apiResource('petty-cash', PettyCashController::class)->only(['index', 'store']);

        // Employees & Payroll
        Route::get('employees/active', [EmployeeController::class, 'active']);
        Route::apiResource('employees', EmployeeController::class);
        Route::post('payroll/bulk-generate', [PayrollController::class, 'bulkGenerate']);
        Route::apiResource('payroll', PayrollController::class)->only(['index', 'store', 'show']);
        Route::post('payroll/{payroll}/mark-paid', [PayrollController::class, 'markPaid']);

        // Suppliers
        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('supplier-payments', SupplierPaymentController::class)->only(['index', 'store', 'destroy']);

        // Assets — custom routes MUST precede apiResource
        Route::get('assets/next-tag', [AssetController::class, 'nextTag']);
        Route::post('assets/{asset}/dispose', [AssetController::class, 'dispose']);
        Route::apiResource('assets', AssetController::class);

        // Budgets
        Route::apiResource('budgets', BudgetController::class);
        Route::post('budgets/{budget}/activate', [BudgetController::class, 'activate']);
        Route::post('budgets/{budget}/close', [BudgetController::class, 'close']);

        // ── Phase 3: Reports & Financial Statements ───────────────
        Route::prefix('reports')->group(function () {
            Route::get('collections', [ReportController::class, 'collections']);
            Route::get('debtor-aging', [ReportController::class, 'debtorAging']);
            Route::get('income-statement', [ReportController::class, 'incomeStatement']);
            Route::get('balance-sheet', [ReportController::class, 'balanceSheet']);
            Route::get('cash-flow', [ReportController::class, 'cashFlow']);
            Route::get('student-statement', [ReportController::class, 'studentStatement']);
            Route::get('{type}/pdf', [ReportController::class, 'exportPdf']);
            Route::get('{type}/excel', [ReportController::class, 'exportExcel']);
        });

        // ── Phase 4: SMS ──────────────────────────────────────────
        Route::post('sms/blast', [SmsController::class, 'blast']);
        Route::post('sms/reminder', [SmsController::class, 'reminder']);
        Route::get('sms/logs', [SmsController::class, 'logs']);

        // ── Phase 5: Academics & Missing Modules ──────────────────
        Route::apiResource('subjects', SubjectController::class);
        Route::apiResource('exams', ExamController::class);
        Route::apiResource('attendances', AttendanceController::class);

        // Transport
        Route::prefix('transport')->group(function () {
            Route::get('vehicles', [TransportController::class, 'vehicles']);
            Route::post('vehicles', [TransportController::class, 'storeVehicle']);
            Route::put('vehicles/{vehicle}', [TransportController::class, 'updateVehicle']);
            Route::delete('vehicles/{vehicle}', [TransportController::class, 'deleteVehicle']);
            Route::get('routes', [TransportController::class, 'routes']);
            Route::post('routes', [TransportController::class, 'storeRoute']);
            Route::put('routes/{route}', [TransportController::class, 'updateRoute']);
            Route::get('subscriptions', [TransportController::class, 'subscriptions']);
            Route::post('subscriptions', [TransportController::class, 'subscribe']);
            Route::delete('subscriptions/{subscription}', [TransportController::class, 'unsubscribe']);
            Route::get('vehicles/{vehicle}/maintenance', [TransportController::class, 'maintenance']);
            Route::post('vehicles/{vehicle}/maintenance', [TransportController::class, 'storeMaintenance']);
            Route::get('summary', [TransportController::class, 'vehicleSummary']);
        });

        // Inventory (consumables + fixed assets)
        Route::prefix('inventory')->group(function () {
            Route::get('next-tag', [InventoryController::class, 'nextTag']);
            Route::get('items', [InventoryController::class, 'index']);
            Route::post('items', [InventoryController::class, 'store']);
            Route::put('items/{item}', [InventoryController::class, 'update']);
            Route::delete('items/{item}', [InventoryController::class, 'destroy']);
            Route::post('items/{item}/dispose', [InventoryController::class, 'dispose']);
            Route::get('items/{item}/transactions', [InventoryController::class, 'transactions']);
            Route::post('items/{item}/transaction', [InventoryController::class, 'transaction']);
            Route::get('items/{item}/staff-usage', [InventoryController::class, 'staffUsage']);
            Route::get('summary', [InventoryController::class, 'summary']);
        });
    });

    // Owner + Superadmin
    Route::middleware('role:owner|superadmin')->group(function () {
        Route::get('audit-logs', [AuditLogController::class, 'index']);
        Route::get('login-history', [LoginHistoryController::class, 'index']);

        // School management — owner can create/update/delete/toggle schools
        Route::post('schools', [SchoolController::class, 'store']);
        Route::put('schools/{school}', [SchoolController::class, 'update']);
        Route::patch('schools/{school}', [SchoolController::class, 'update']);
        Route::delete('schools/{school}', [SchoolController::class, 'destroy']);
        Route::patch('schools/{school}/toggle-status', [SchoolController::class, 'toggleStatus']);

        // User management
        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::put('users/{user}', [UserController::class, 'update']);
        Route::patch('users/{user}', [UserController::class, 'update']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);

        // Multi-school access management
        Route::get('user-school-access/{user}', [UserSchoolAccessController::class, 'show']);
        Route::post('user-school-access', [UserSchoolAccessController::class, 'grant']);
        Route::delete('user-school-access/{user}/{school}', [UserSchoolAccessController::class, 'revoke']);

        // Academic year writes
        Route::post('academic-years', [AcademicYearController::class, 'store']);
        Route::put('academic-years/{academicYear}', [AcademicYearController::class, 'update']);
        Route::patch('academic-years/{academicYear}', [AcademicYearController::class, 'update']);
        Route::delete('academic-years/{academicYear}', [AcademicYearController::class, 'destroy']);

        // School class writes
        Route::post('school-classes', [SchoolClassController::class, 'store']);
        Route::put('school-classes/{schoolClass}', [SchoolClassController::class, 'update']);
        Route::patch('school-classes/{schoolClass}', [SchoolClassController::class, 'update']);
        Route::delete('school-classes/{schoolClass}', [SchoolClassController::class, 'destroy']);
    });

    // Accountant + Owner + Superadmin — term management
    Route::middleware('role:accountant|owner|superadmin')->group(function () {
        Route::post('terms', [TermController::class, 'store']);
        Route::put('terms/{term}', [TermController::class, 'update']);
        Route::patch('terms/{term}', [TermController::class, 'update']);
        Route::delete('terms/{term}', [TermController::class, 'destroy']);
    });

    // Superadmin — roles, permissions & user management
    Route::middleware('role:superadmin')->prefix('superadmin')->group(function () {
        // Roles & permissions
        Route::get('roles', [RolePermissionController::class, 'index']);
        Route::post('roles', [RolePermissionController::class, 'store']);
        Route::delete('roles/{role}', [RolePermissionController::class, 'destroy']);
        Route::put('roles/{role}/permissions', [RolePermissionController::class, 'syncPermissions']);
        Route::get('permissions', [RolePermissionController::class, 'permissions']);

        // User management
        Route::get('users', [SuperadminUserController::class, 'index']);
        Route::post('users', [SuperadminUserController::class, 'store']);
        Route::get('users/{user}', [SuperadminUserController::class, 'show']);
        Route::put('users/{user}', [SuperadminUserController::class, 'update']);
        Route::delete('users/{user}', [SuperadminUserController::class, 'destroy']);
        Route::post('users/{user}/deactivate', [SuperadminUserController::class, 'deactivate']);
        Route::post('users/{user}/reactivate', [SuperadminUserController::class, 'reactivate']);
        Route::put('users/{user}/permissions', [SuperadminUserController::class, 'syncPermissions']);
        Route::put('users/{user}/restrict', [SuperadminUserController::class, 'restrictPermissions']);
    });

    // Parent portal — read-only, own children only
    Route::middleware('role:parent')->prefix('parent')->group(function () {
        Route::get('children', [ChildController::class, 'index']);
        Route::get('children/{student}', [ChildController::class, 'show'])
            ->middleware('parent.owns_student');
        Route::get('statement', [StatementController::class, 'index']);
        Route::get('statement/pdf', [StatementController::class, 'download']);
    });
});
