<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * expenses.category_id is a plain constrained() foreign key, so the database
 * RESTRICTs deleting a category that is in use. Unhandled, that reached the
 * browser as a bare HTTP 500 "Server Error".
 */
class ExpenseCategoryDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['accountant', 'owner', 'parent', 'superadmin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->school = School::create([
            'name' => 'Msingi', 'code' => 'MSG', 'slug' => 'msingi', 'level' => 'primary',
        ]);
        $this->accountant = User::factory()->create(['school_id' => $this->school->id]);
        $this->accountant->assignRole('accountant');
    }

    private function token(): string
    {
        $this->app['auth']->forgetGuards();

        return $this->accountant->createToken('t')->plainTextToken;
    }

    public function test_an_unused_category_can_be_deleted(): void
    {
        $category = ExpenseCategory::create([
            'school_id' => $this->school->id, 'name' => 'Umeme', 'type' => 'operational',
        ]);

        $this->withToken($this->token())
            ->deleteJson("/api/expense-categories/{$category->id}")
            ->assertOk();

        $this->assertDatabaseMissing('expense_categories', ['id' => $category->id]);
    }

    public function test_a_category_in_use_is_refused_with_a_readable_message(): void
    {
        $category = ExpenseCategory::create([
            'school_id' => $this->school->id, 'name' => 'Umeme', 'type' => 'operational',
        ]);
        Expense::create([
            'school_id' => $this->school->id, 'category_id' => $category->id,
            'amount_cents' => 100000, 'description' => 'Bili ya umeme',
            'expense_date' => '2026-08-31', 'recorded_by' => $this->accountant->id,
            'status' => 'pending',
        ]);

        $res = $this->withToken($this->token())
            ->deleteJson("/api/expense-categories/{$category->id}")
            // 422, not the 500 the raw database error produced.
            ->assertStatus(422);

        $this->assertSame(1, $res->json('expenses_count'));
        $this->assertStringContainsString('cannot be deleted', $res->json('message'));
        $this->assertDatabaseHas('expense_categories', ['id' => $category->id]);
    }

    /** A refused delete must not leave an audit entry claiming it happened. */
    public function test_a_refused_delete_is_not_audited_as_deleted(): void
    {
        $category = ExpenseCategory::create([
            'school_id' => $this->school->id, 'name' => 'Maji', 'type' => 'operational',
        ]);
        Expense::create([
            'school_id' => $this->school->id, 'category_id' => $category->id,
            'amount_cents' => 50000, 'description' => 'Bili ya maji',
            'expense_date' => '2026-08-31', 'recorded_by' => $this->accountant->id,
            'status' => 'pending',
        ]);

        $this->withToken($this->token())
            ->deleteJson("/api/expense-categories/{$category->id}")
            ->assertStatus(422);

        $this->assertDatabaseMissing('audit_logs', ['action' => 'expense_category.deleted']);
    }
}
