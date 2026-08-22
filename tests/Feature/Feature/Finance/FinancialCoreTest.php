<?php

namespace Tests\Feature\Feature\Finance;

use App\Models\Customer;
use App\Models\Finance\FinanceExpense;
use App\Models\Finance\FinanceInvoice;
use App\Models\Finance\FinanceJournalEntry;
use App\Models\Finance\FinanceSupplier;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Finance\AccountingService;
use App\Services\Finance\ChartOfAccountsService;
use App\Services\Finance\PayrollService;
use App\Services\Finance\TaxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class FinancialCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_invoice_creation_recalculates_and_posts_balanced_entry(): void
    {
        [$user, $workspace] = $this->createWorkspaceOwner('company');
        $customer = Customer::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'ACME Customer',
            'phone' => '0500001111',
            'email' => 'acme@example.com',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.finance.invoices.store'), [
                'type' => 'sales',
                'customer_id' => $customer->id,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(10)->toDateString(),
                'currency' => 'SAR',
                'status' => 'unpaid',
                'tax_profile_type' => 'standard',
                'tax_rate' => 15,
                'items_json' => json_encode([
                    [
                        'product_name' => 'Consulting Service',
                        'description' => 'Consulting',
                        'quantity' => 2,
                        'unit_price' => 100,
                        'discount' => 0,
                        'tax_rate' => 15,
                        'tax_type' => 'standard',
                    ],
                ]),
            ]);

        $response->assertRedirect();

        $invoice = FinanceInvoice::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('sales', $invoice->type);
        $this->assertSame('unpaid', $invoice->status);
        $this->assertSame('200.00', (string) $invoice->subtotal);
        $this->assertSame('200.00', (string) $invoice->taxable_amount);
        $this->assertSame('30.00', (string) $invoice->tax_amount);
        $this->assertSame('230.00', (string) $invoice->total);
        $this->assertSame('230.00', (string) $invoice->amount_due);

        $entry = FinanceJournalEntry::withoutGlobalScopes()
            ->where('reference_type', FinanceInvoice::class)
            ->where('reference_id', $invoice->id)
            ->firstOrFail();

        $entry->load('lines');
        $debit = (float) $entry->lines->sum('debit');
        $credit = (float) $entry->lines->sum('credit');
        $this->assertEquals($debit, $credit);
    }

    public function test_partial_and_full_payment_update_invoice_status_and_due(): void
    {
        [$user, $workspace] = $this->createWorkspaceOwner('company');
        $customer = Customer::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Partial Customer',
            'phone' => '0500002222',
        ]);

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.finance.invoices.store'), [
                'type' => 'sales',
                'customer_id' => $customer->id,
                'issue_date' => now()->toDateString(),
                'currency' => 'SAR',
                'status' => 'unpaid',
                'tax_profile_type' => 'standard',
                'tax_rate' => 15,
                'items_json' => json_encode([
                    [
                        'product_name' => 'Implementation',
                        'quantity' => 1,
                        'unit_price' => 200,
                        'discount' => 0,
                        'tax_rate' => 15,
                        'tax_type' => 'standard',
                    ],
                ]),
            ])
            ->assertRedirect();

        $invoice = FinanceInvoice::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('230.00', (string) $invoice->amount_due);

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.finance.invoices.payments.store', $invoice), [
                'payment_date' => now()->toDateString(),
                'amount' => 100,
                'method' => 'cash',
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame('partial', $invoice->status);
        $this->assertSame('100.00', (string) $invoice->amount_paid);
        $this->assertSame('130.00', (string) $invoice->amount_due);

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.finance.invoices.payments.store', $invoice), [
                'payment_date' => now()->toDateString(),
                'amount' => 130,
                'method' => 'cash',
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('230.00', (string) $invoice->amount_paid);
        $this->assertSame('0.00', (string) $invoice->amount_due);
    }

    public function test_purchase_invoice_posts_accounts_payable_entry(): void
    {
        [$user, $workspace] = $this->createWorkspaceOwner('company');
        $supplier = FinanceSupplier::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Supplier A',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.finance.invoices.store'), [
                'type' => 'purchase',
                'supplier_id' => $supplier->id,
                'issue_date' => now()->toDateString(),
                'currency' => 'SAR',
                'status' => 'unpaid',
                'tax_profile_type' => 'standard',
                'tax_rate' => 15,
                'items_json' => json_encode([
                    [
                        'product_name' => 'Vendor Service',
                        'quantity' => 1,
                        'unit_price' => 300,
                        'discount' => 0,
                        'tax_rate' => 15,
                        'tax_type' => 'standard',
                    ],
                ]),
            ])
            ->assertRedirect();

        $invoice = FinanceInvoice::withoutGlobalScopes()->firstOrFail();
        $entry = FinanceJournalEntry::withoutGlobalScopes()
            ->where('reference_type', FinanceInvoice::class)
            ->where('reference_id', $invoice->id)
            ->firstOrFail();

        $entry->load('lines.account');
        $apLine = $entry->lines->first(fn ($line) => $line->account?->code === '2000');
        $this->assertNotNull($apLine);
        $this->assertSame('345.00', number_format((float) $apLine->credit, 2, '.', ''));
    }

    public function test_finance_invoice_isolated_per_workspace(): void
    {
        [$userA, $workspaceA] = $this->createWorkspaceOwner('company');
        [, $workspaceB] = $this->createWorkspaceOwner('store');

        $invoiceB = FinanceInvoice::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceB->id,
            'invoice_number' => 'INV-B-000001',
            'type' => 'sales',
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'currency' => 'SAR',
            'subtotal' => 100,
            'discount' => 0,
            'taxable_amount' => 100,
            'tax_amount' => 15,
            'total' => 115,
            'amount_paid' => 0,
            'amount_due' => 115,
            'tax_profile_type' => 'standard',
            'tax_rate' => 15,
        ]);

        $this->actingAs($userA)
            ->withSession(['current_workspace_id' => $workspaceA->id])
            ->get(route('workspace.finance.invoices.show', $invoiceB))
            ->assertNotFound();
    }

    public function test_accounting_service_rejects_unbalanced_entry(): void
    {
        [, $workspace] = $this->createWorkspaceOwner('company');
        $chart = app(ChartOfAccountsService::class);
        $chart->ensureDefaultAccounts($workspace);

        $ar = \App\Models\Finance\FinanceAccount::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->where('code', '1200')
            ->firstOrFail();

        $sales = \App\Models\Finance\FinanceAccount::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->where('code', '4000')
            ->firstOrFail();

        $service = app(AccountingService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Journal entry is not balanced');

        $service->createEntry(
            workspaceId: $workspace->id,
            entryDate: now()->toDateString(),
            type: 'manual',
            lines: [
                ['account_id' => $ar->id, 'debit' => 100, 'credit' => 0],
                ['account_id' => $sales->id, 'debit' => 0, 'credit' => 90],
            ],
        );
    }

    public function test_expense_creation_posts_accounting_entry(): void
    {
        [$user, $workspace] = $this->createWorkspaceOwner('company');

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.finance.expenses.store'), [
                'expense_date' => now()->toDateString(),
                'amount' => 100,
                'tax_profile_type' => 'standard',
                'tax_rate' => 15,
                'payment_method' => 'cash',
                'status' => 'paid',
            ])
            ->assertRedirect();

        $expense = FinanceExpense::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('100.00', (string) $expense->amount);
        $this->assertSame('15.00', (string) $expense->tax_amount);
        $this->assertSame('115.00', (string) $expense->total);

        $entry = FinanceJournalEntry::withoutGlobalScopes()
            ->where('reference_type', FinanceExpense::class)
            ->where('reference_id', $expense->id)
            ->firstOrFail();
        $entry->load('lines');
        $this->assertEquals((float) $entry->lines->sum('debit'), (float) $entry->lines->sum('credit'));
    }

    public function test_tax_and_payroll_services_calculate_expected_values(): void
    {
        $taxService = app(TaxService::class);
        $standard = $taxService->calculateAmount(1000, 'standard', 15);
        $exempt = $taxService->calculateAmount(1000, 'exempt', 15);

        $this->assertSame(1000.0, $standard['taxable_amount']);
        $this->assertSame(150.0, $standard['tax_amount']);
        $this->assertSame(1150.0, $standard['total']);
        $this->assertSame(0.0, $exempt['tax_amount']);
        $this->assertSame(1000.0, $exempt['total']);

        $payrollService = app(PayrollService::class);
        $payroll = $payrollService->calculate([
            'basic_salary' => 5000,
            'housing_allowance' => 1000,
            'transport_allowance' => 500,
            'other_allowances' => 300,
            'overtime' => 200,
            'bonuses' => 100,
            'deductions' => 250,
            'advances' => 400,
            'absence_deduction' => 150,
        ]);

        $this->assertSame(7100.0, $payroll['gross']);
        $this->assertSame(800.0, $payroll['deductions']);
        $this->assertSame(6300.0, $payroll['net']);
    }

    public function test_finance_access_requires_permission_or_elevated_membership_role(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $owner->id,
            'type' => 'company',
        ]);
        $workspace->users()->attach($owner->id, [
            'membership_role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $agent = User::factory()->create();
        $workspace->users()->attach($agent->id, [
            'membership_role' => 'agent',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($agent)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.finance.dashboard'))
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Workspace}
     */
    private function createWorkspaceOwner(string $workspaceType): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $user->id,
            'type' => $workspaceType,
        ]);

        $workspace->users()->attach($user->id, [
            'membership_role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return [$user, $workspace];
    }
}
