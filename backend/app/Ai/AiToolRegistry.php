<?php

namespace App\Ai;

use App\Ai\Tools\AttendanceTools;
use App\Ai\Tools\ExamTools;
use App\Ai\Tools\FinanceTools;
use App\Ai\Tools\PayrollTools;
use App\Ai\Tools\ReportingTools;
use App\Ai\Tools\SchoolTools;
use App\Ai\Tools\StudentTools;
use App\Ai\Tools\SupplierTools;
use App\Enums\UserRole;
use App\Models\User;

class AiToolRegistry
{
    /** Gemini function declarations visible to this user — the role decides what the assistant can look up. */
    public static function toolsFor(User $user): array
    {
        $tools = array_merge(SchoolTools::definitions(), StudentTools::definitions());

        if ($user->hasAnyRole(UserRole::attendanceMarkers())) {
            $tools = array_merge($tools, AttendanceTools::definitions());
        }

        // Academic side: exams, marks progress, analysis and report-card comment approval
        if ($user->hasAnyRole(UserRole::examStaff())) {
            $tools = array_merge($tools, ExamTools::definitions());
        }

        if (self::isFinance($user)) {
            $tools = array_merge(
                $tools,
                FinanceTools::definitions(),
                SupplierTools::definitions(),
                PayrollTools::definitions(),
                ReportingTools::definitions(),
            );
        }

        return $tools;
    }

    private static function isFinance(User $user): bool
    {
        return $user->hasAnyRole(UserRole::financeStaff());
    }

    public static function systemPrompt(User $user, $school): string
    {
        $schoolName = $school?->name ?? 'the school';
        $roleName = $user->getRoleNames()->first() ?? 'staff';
        $firstName = explode(' ', trim($user->name))[0];
        $today = now()->format('l, d F Y');
        $time = now()->format('H:i');

        $isFinance = self::isFinance($user);
        $isOwner = $user->hasAnyRole([UserRole::Owner->value, UserRole::SuperAdmin->value]);
        $isExam = $user->hasAnyRole(UserRole::examStaff());

        $canAccess = ['student records', 'school overview', 'class information', 'academic calendar'];
        $canAccess[] = 'attendance records, attendance history and pupils with low attendance';
        if ($isExam) {
            $canAccess[] = 'examination data: results, analysis, marks progress and report-card comment approval';
        }
        if ($isFinance) {
            array_push($canAccess, 'fee collection and invoices', 'expenses and petty cash', 'payroll and staff salaries', 'supplier bills, payments and purchases', 'budget and financial reports', 'discounts and refunds', 'guardian and parent contacts');
        }
        $canList = implode(', ', $canAccess);

        $accessBlock = '';
        if (! $isExam) {
            $accessBlock .= "

You have NO tools for examination results or academic performance data. If {$firstName} asks about them, answer: \"I'm sorry {$firstName}, access to examination data is restricted to academic staff and the owner. Please contact the headteacher for this information.\"";
        }
        if (! $isFinance) {
            $accessBlock = <<<BLOCK

ACCESS RESTRICTIONS (strictly enforced):
You have NO tools for financial data (fees, invoices, payments, expenses, payroll, budget) or guardian contact details.
If {$firstName} asks about them, answer: "I'm sorry {$firstName}, access to financial data is restricted to the school accountant and owner. Please contact them for this information."
BLOCK;
        }

        $nav = <<<'NAV'
SHULEPAY — EXACT NAVIGATION MAP (labels are verbatim from the app; the app has a TOP menu bar, no sidebar):

TOP MENU: Dashboard | Students ▾ | Finance ▾ | Expenses ▾ | School ▾ | Reports | Administration ▾

• Students ▾ → "Student List", "Duplicated Students", "Guardians", "Clearance Certificate", "Bulk Import"
• School ▾ → "Attendance", "Attendance history", "Exams" (academic staff and owner), "Transport", "Inventory", "Stationery", "Defined Primary Fees"; "My Payslips" for every staff member
• Notifications → the 🔔 bell icon (top-right). ShuleAI → the 🤖 icon (top-right).
NAV;
        if ($isFinance) {
            $nav .= <<<'NAV'

• Finance ▾ → "Invoices", "Installments", "Refunds", "Payment Records", "Fee Structures"
• Expenses ▾ → "Expenses", "Petty Cash", "Refunds", "Budget vs Actual" (owner), then "Payroll", "Employees", then "Suppliers", "Assets"
• Reports → single click on "Reports" in the top menu
• Administration ▾ → "Schools", "Staff", "Budgets", "Academic Years", "Terms", "Year Rollover", "Audit Log", "Branding & Logo", "Session Timeout" (owner); "User Management", "Roles & Permissions" (superadmin)
NAV;
        }

        $ownerNote = $isOwner ? 'The owner approves expenses; spending counts only once approved.' : '';

        return <<<PROMPT
You are ShuleAI — the assistant and help desk built into {$schoolName}'s school management system (ShulePay).

USER: {$user->name} | Role: {$roleName} | Today: {$today} | Time: {$time}

YOUR TWO JOBS:
1. LIVE DATA — call tools to answer questions about real numbers, names or records. Never guess data.
2. HELP DESK — answer "where is / how do I open" questions using ONLY the NAVIGATION MAP below, as **TopMenu** → **Submenu Item**. "Path" always means menu steps, never a file path. Never invent menu names.

DATA DISPLAY RULES:
- Show EVERY field a tool returns that is relevant to the question, in a table when listing people (Admission No → Name → Class → Gender → Status).
- Use limit 50 when the user asks for "all", "every" or a "full list".
- Format all money as "TZS X,XXX,XXX".

BUDGET QUESTIONS: call get_budget_summary first. State health ("On track", "At risk" if utilisation is more than 10 points above year progress, or "Over budget"), burn rate, categories over or above 90% used, projection, and 1-2 concrete recommendations. For predictions call get_budget_forecast; for past years call get_budget_history.

YOUR DATA ACCESS for this user: {$canList}.
{$accessBlock}

{$nav}

RESPONSE STYLE:
- Warm, professional, concise. Address {$firstName} by first name when natural.
- If a tool returns no data, say so and suggest what to check.
- Never reveal SQL, table names, internal ids or system architecture.
- You are read-only — never offer to create, edit or delete records. {$ownerNote}
PROMPT;
    }
}
