<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Appointment\AppointmentBooking;
use App\Models\Appointment\AppointmentRequest;
use App\Models\Appointment\AppointmentRequestSlot;
use App\Models\Appointment\AppointmentResource;
use App\Models\Appointment\AppointmentReminder;
use App\Models\Appointment\AppointmentService as AppointmentServiceModel;
use App\Models\Appointment\AppointmentSetting;
use App\Models\Appointment\AppointmentStaff;
use App\Models\Contract\Contract;
use App\Models\Contract\ContractAttachment;
use App\Models\Contract\ContractItem;
use App\Models\EmailAccount;
use App\Models\EmailContact;
use App\Models\EmailMessage;
use App\Models\EmployeeInvitation;
use App\Models\Finance\FinanceExpense;
use App\Models\Finance\FinanceEmployee;
use App\Models\Finance\FinanceEmployeeProfile;
use App\Models\Finance\FinanceEmployeePayrollRecord;
use App\Models\Finance\FinanceFiscalYear;
use App\Models\Finance\FinanceInvoice;
use App\Models\Finance\FinanceInvoiceItem;
use App\Models\Finance\FinanceInvoicePayment;
use App\Models\Finance\FinanceJournalEntry;
use App\Models\Finance\FinancePayrollAdjustment;
use App\Models\Finance\FinancePriceList;
use App\Models\Finance\FinanceSetting;
use App\Models\Finance\FinanceSalaryAdvance;
use App\Models\Finance\FinanceSalaryAdvanceRepayment;
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
use App\Observers\FinanceInvoicePaymentObserver;
use App\Observers\WorkspaceAuditObserver;
use App\Notifications\Channels\CentralMailChannel;
use App\Policies\CategoryPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProductPolicy;
use App\Policies\SubscriptionPolicy;
use App\Policies\WorkspacePolicy;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
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
        Notification::extend('central_mail', fn ($app) => $app->make(CentralMailChannel::class));

        $this->configureMobileRateLimiting();

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
        FinanceInvoiceItem::observe(WorkspaceAuditObserver::class);
        FinanceInvoicePayment::observe(WorkspaceAuditObserver::class);
        FinanceInvoicePayment::observe(FinanceInvoicePaymentObserver::class);
        FinanceExpense::observe(WorkspaceAuditObserver::class);
        FinanceEmployee::observe(WorkspaceAuditObserver::class);
        FinanceEmployeeProfile::observe(WorkspaceAuditObserver::class);
        FinanceEmployeePayrollRecord::observe(WorkspaceAuditObserver::class);
        FinanceTreasuryAccount::observe(WorkspaceAuditObserver::class);
        FinanceJournalEntry::observe(WorkspaceAuditObserver::class);
        FinanceFiscalYear::observe(WorkspaceAuditObserver::class);
        FinancePriceList::observe(WorkspaceAuditObserver::class);
        FinancePayrollAdjustment::observe(WorkspaceAuditObserver::class);
        FinanceSalaryAdvance::observe(WorkspaceAuditObserver::class);
        FinanceSalaryAdvanceRepayment::observe(WorkspaceAuditObserver::class);
        AppointmentSetting::observe(WorkspaceAuditObserver::class);
        AppointmentServiceModel::observe(WorkspaceAuditObserver::class);
        AppointmentStaff::observe(WorkspaceAuditObserver::class);
        AppointmentBooking::observe(WorkspaceAuditObserver::class);
        AppointmentRequest::observe(WorkspaceAuditObserver::class);
        AppointmentRequestSlot::observe(WorkspaceAuditObserver::class);
        AppointmentResource::observe(WorkspaceAuditObserver::class);
        AppointmentReminder::observe(WorkspaceAuditObserver::class);
        Contract::observe(WorkspaceAuditObserver::class);
        ContractItem::observe(WorkspaceAuditObserver::class);
        ContractAttachment::observe(WorkspaceAuditObserver::class);
        Subscription::observe(WorkspaceAuditObserver::class);
        EmployeeInvitation::observe(WorkspaceAuditObserver::class);
        WhatsAppAccount::observe(WorkspaceAuditObserver::class);
    }

    private function configureMobileRateLimiting(): void
    {
        RateLimiter::for('mobile-api', function (Request $request) {
            return Limit::perMinute(120)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('mobile-login', function (Request $request) {
            return Limit::perMinute(10)->by((string) $request->ip());
        });

        RateLimiter::for('mobile-messages', function (Request $request) {
            return Limit::perMinute(60)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('mobile-email', function (Request $request) {
            return Limit::perMinute(20)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('mobile-ai', function (Request $request) {
            return Limit::perMinute(10)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('mobile-attachments', function (Request $request) {
            return Limit::perMinute(30)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('mobile-write', function (Request $request) {
            return Limit::perMinute(40)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('mobile-search', function (Request $request) {
            return Limit::perMinute(30)->by((string) ($request->user()?->id ?: $request->ip()));
        });
    }
}
