<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\EmailAccount;
use App\Models\EmailContact;
use App\Models\EmailMessage;
use App\Models\EmployeeInvitation;
use App\Models\Finance\FinanceExpense;
use App\Models\Finance\FinanceInvoice;
use App\Models\Finance\FinanceInvoicePayment;
use App\Models\Finance\FinanceJournalEntry;
use App\Models\Finance\FinanceSetting;
use App\Models\Finance\FinanceSupplier;
use App\Models\Finance\FinanceTaxRate;
use App\Models\Finance\FinanceTreasuryAccount;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\WhatsAppAccount;
use App\Models\Workspace;
use App\Observers\WorkspaceAuditObserver;
use App\Policies\CategoryPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProductPolicy;
use App\Policies\SubscriptionPolicy;
use App\Policies\WorkspacePolicy;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WorkspaceContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Workspace::class, WorkspacePolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Conversation::class, ConversationPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Subscription::class, SubscriptionPolicy::class);

        Product::observe(WorkspaceAuditObserver::class);
        Category::observe(WorkspaceAuditObserver::class);
        Customer::observe(WorkspaceAuditObserver::class);
        Order::observe(WorkspaceAuditObserver::class);
        Payment::observe(WorkspaceAuditObserver::class);
        PaymentGateway::observe(WorkspaceAuditObserver::class);
        InventoryMovement::observe(WorkspaceAuditObserver::class);
        Conversation::observe(WorkspaceAuditObserver::class);
        EmailAccount::observe(WorkspaceAuditObserver::class);
        EmailContact::observe(WorkspaceAuditObserver::class);
        EmailMessage::observe(WorkspaceAuditObserver::class);
        FinanceSetting::observe(WorkspaceAuditObserver::class);
        FinanceTaxRate::observe(WorkspaceAuditObserver::class);
        FinanceSupplier::observe(WorkspaceAuditObserver::class);
        FinanceInvoice::observe(WorkspaceAuditObserver::class);
        FinanceInvoicePayment::observe(WorkspaceAuditObserver::class);
        FinanceExpense::observe(WorkspaceAuditObserver::class);
        FinanceTreasuryAccount::observe(WorkspaceAuditObserver::class);
        FinanceJournalEntry::observe(WorkspaceAuditObserver::class);
        Subscription::observe(WorkspaceAuditObserver::class);
        EmployeeInvitation::observe(WorkspaceAuditObserver::class);
        WhatsAppAccount::observe(WorkspaceAuditObserver::class);
    }
}
