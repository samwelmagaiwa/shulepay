<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Models\School;
use App\Models\Supplier;
use App\Models\SupplierCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Supplier purchasing: suppliers, bills the owner approves, payments against them, statements and
 * vouchers — with the money adding up and one school never seeing another's suppliers.
 */
class SupplierModuleTest extends TestCase
{
    use RefreshDatabase;

    private School $mine;

    private School $theirs;

    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        foreach (['owner', 'accountant', 'teacher_pri'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        $this->mine = School::create(['name' => 'Alpha', 'code' => 'ALP', 'slug' => 'alpha', 'level' => 'primary', 'is_active' => true]);
        $this->theirs = School::create(['name' => 'Beta', 'code' => 'BET', 'slug' => 'beta', 'level' => 'primary', 'is_active' => true]);
        $this->category = ExpenseCategory::create(['school_id' => $this->mine->id, 'name' => 'Supplies', 'type' => 'operational']);
    }

    private function as(string $role): static
    {
        $u = User::factory()->create(['school_id' => $this->mine->id]);
        $u->assignRole($role);
        $this->app['auth']->forgetGuards();

        return $this->withToken($u->createToken('t')->plainTextToken);
    }

    private function supplier(string $name = 'Kalamu Stationers'): int
    {
        $cat = $this->as('accountant')->getJson('/api/suppliers/categories')->assertOk()->json('data.0.id')
            ?? $this->as('accountant')->getJson('/api/suppliers/categories')->json('0.id');

        $r = $this->as('accountant')->postJson('/api/suppliers', [
            'name' => $name, 'supplier_category_id' => $cat, 'phone' => '+255712345678',
        ])->assertCreated();

        return (int) ($r->json('id') ?? $r->json('data.id'));
    }

    private function bill(int $supplier, string $number = 'B-001', int $unit = 50000, float $qty = 2): int
    {
        return $this->as('accountant')->postJson('/api/supplier-bills', [
            'supplier_id' => $supplier, 'bill_number' => $number, 'bill_date' => now()->toDateString(),
            'expense_category_id' => $this->category->id,
            'items' => [['description' => 'Exercise books', 'quantity' => $qty, 'unit_price_cents' => $unit]],
        ])->assertSuccessful()->json('id') ?? Supplier::count();
    }

    public function test_a_supplier_is_created_with_a_code_and_the_default_types_are_seeded(): void
    {
        $id = $this->supplier();
        $row = $this->as('accountant')->getJson("/api/suppliers/{$id}")->assertOk()->json();
        $row = $row['data'] ?? $row;
        $this->assertNotEmpty($row['code']);
        $this->assertSame('active', $row['status']);
        $this->assertDatabaseHas('suppliers', ['id' => $id, 'school_id' => $this->mine->id]);
    }

    public function test_a_possible_duplicate_is_flagged_before_it_is_saved(): void
    {
        $this->supplier('Kalamu Stationers');
        $cat = SupplierCategory::first()->id;
        $this->as('accountant')->postJson('/api/suppliers', ['name' => 'KALAMU STATIONERS', 'supplier_category_id' => $cat, 'phone' => '+255700000001'])
            ->assertStatus(409);
        $this->as('accountant')->postJson('/api/suppliers', ['name' => 'KALAMU STATIONERS', 'supplier_category_id' => $cat, 'phone' => '+255700000001', 'confirm_duplicate' => true])
            ->assertCreated();
    }

    public function test_a_bill_owes_nothing_until_the_owner_approves_it(): void
    {
        $supplier = $this->supplier();
        $bill = $this->bill($supplier);

        $s = $this->as('accountant')->getJson("/api/suppliers/{$supplier}")->json();
        $this->assertSame(0, (int) (($s['data'] ?? $s)['balance_cents']), 'a bill waiting for approval is not yet a debt');

        $this->as('teacher_pri')->postJson("/api/supplier-bills/{$bill}/approve")->assertForbidden();
        $this->as('owner')->postJson("/api/supplier-bills/{$bill}/approve")->assertSuccessful();

        $s = $this->as('accountant')->getJson("/api/suppliers/{$supplier}")->json();
        $this->assertSame(100000, (int) (($s['data'] ?? $s)['balance_cents']), '2 × 50,000 owed after approval');
        $this->assertDatabaseHas('expenses', ['supplier_id' => $supplier, 'status' => 'approved', 'amount_cents' => 100000]);
    }

    public function test_a_payment_reduces_what_is_owed_and_prints(): void
    {
        $supplier = $this->supplier();
        $bill = $this->bill($supplier);
        $this->as('owner')->postJson("/api/supplier-bills/{$bill}/approve")->assertSuccessful();

        $pay = $this->as('owner')->postJson('/api/supplier-payments', [
            'supplier_id' => $supplier, 'amount_cents' => 40000, 'method' => 'cash', 'payment_date' => now()->toDateString(),
            'allocations' => [['supplier_bill_id' => $bill, 'amount_cents' => 40000]],
        ])->assertCreated()->json();
        $payId = $pay['id'] ?? $pay['data']['id'];

        $s = $this->as('accountant')->getJson("/api/suppliers/{$supplier}")->json();
        $this->assertSame(60000, (int) (($s['data'] ?? $s)['balance_cents']));

        $stmt = $this->as('accountant')->get("/api/suppliers/{$supplier}/statement/pdf");
        $stmt->assertOk();
        $this->assertStringContainsString('%PDF', substr($stmt->getContent(), 0, 10));

        $voucher = $this->as('owner')->get("/api/vouchers/supplier-payment/{$payId}");
        $voucher->assertOk();
        $this->assertSame('application/pdf', $voucher->headers->get('Content-Type'));
    }

    public function test_a_payment_may_not_exceed_what_the_bill_leaves(): void
    {
        $supplier = $this->supplier();
        $bill = $this->bill($supplier);
        $this->as('owner')->postJson("/api/supplier-bills/{$bill}/approve")->assertSuccessful();

        $this->as('owner')->postJson('/api/supplier-payments', [
            'supplier_id' => $supplier, 'amount_cents' => 50000, 'method' => 'cash', 'payment_date' => now()->toDateString(),
            'allocations' => [['supplier_bill_id' => $bill, 'amount_cents' => 90000]],
        ])->assertStatus(422);
    }

    public function test_another_schools_suppliers_are_out_of_reach(): void
    {
        $foreign = Supplier::withoutGlobalScopes()->create(['school_id' => $this->theirs->id, 'name' => 'Beta Supplies', 'phone' => '+255711111111', 'balance_cents' => 0]);

        $this->as('accountant')->getJson("/api/suppliers/{$foreign->id}")->assertNotFound();
        $this->as('accountant')->getJson("/api/suppliers/{$foreign->id}/statement/pdf")->assertNotFound();
        $names = collect($this->as('accountant')->getJson('/api/suppliers')->assertOk()->json('data'))->pluck('name')->all();
        $this->assertNotContains('Beta Supplies', $names);
        $names = collect($this->as('accountant')->withHeaders(['X-School-Id' => '0'])->getJson('/api/suppliers')->assertOk()->json('data'))->pluck('name')->all();
        $this->assertNotContains('Beta Supplies', $names, '"all schools" mode must not open other schools');
    }

    public function test_teachers_do_not_reach_purchasing(): void
    {
        $this->as('teacher_pri')->getJson('/api/suppliers')->assertForbidden();
        $this->as('teacher_pri')->getJson('/api/supplier-bills')->assertForbidden();
    }
}
