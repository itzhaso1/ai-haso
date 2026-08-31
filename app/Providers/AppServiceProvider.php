<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\Appointment\AppointmentBooking;
use App\Models\Appointment\AppointmentRequest;
use App\Models\Appointment\AppointmentRequestSlot;
use App\Models\Appointment\AppointmentResource;
use App\Models\Appointment\AppointmentReminder;
use App\Models\Appointment\AppointmentService as AppointmentServiceModel;
use App\Models\Appointment\AppointmentSetting;
use App\Models\Appointment\AppointmentStaff;
use App\Models\Appointment\AppointmentHoliday;
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
use App\Models\PosMenuItem;
use App\Models\PosCashierInvoice;
use App\Models\PosCashierInvoiceItem;
use App\Models\PosItemCategory;
use App\Models\PaymentGateway;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\TableSession;
use App\Models\WhatsAppAccount;
use App\Models\Website\Website;
use App\Models\Website\WebsiteAsset;
use App\Models\Website\WebsiteDomain;
use App\Models\Website\WebsiteDomainContact;
use App\Models\Website\WebsiteDomainOperation;
use App\Models\Website\WebsitePage;
use App\Models\Website\WebsiteSection;
use App\Models\Website\WebsiteTemplate;
use App\Models\Workspace;
use App\Observers\FinanceInvoicePaymentObserver;
use App\Observers\WebsiteResolverObserver;
use App\Observers\WorkspaceAuditObserver;
use App\Notifications\Channels\CentralMailChannel;
use App\Policies\CategoryPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\DiningTablePolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProductPolicy;
use App\Policies\PosCashierInvoicePolicy;
use App\Policies\PosItemCategoryPolicy;
use App\Policies\PosMenuItemPolicy;
use App\Policies\SubscriptionPolicy;
use App\Policies\WebsiteDomainPolicy;
use App\Policies\WebsitePolicy;
use App\Policies\WorkspacePolicy;
use App\Services\Domain\Contracts\DomainRegistrarInterface;
use App\Services\Domain\NamecheapRegistrar;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WorkspaceContext::class);
        $this->app->bind(DomainRegistrarInterface::class, NamecheapRegistrar::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Notification::extend('central_mail', fn ($app) => $app->make(CentralMailChannel::class));

        Gate::policy(Workspace::class, WorkspacePolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(DiningTable::class, DiningTablePolicy::class);
        Gate::policy(PosItemCategory::class, PosItemCategoryPolicy::class);
        Gate::policy(PosMenuItem::class, PosMenuItemPolicy::class);
        Gate::policy(PosCashierInvoice::class, PosCashierInvoicePolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Conversation::class, ConversationPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Subscription::class, SubscriptionPolicy::class);
        Gate::policy(Website::class, WebsitePolicy::class);
        Gate::policy(WebsiteDomain::class, WebsiteDomainPolicy::class);

        Product::observe(WorkspaceAuditObserver::class);
        Category::observe(WorkspaceAuditObserver::class);
        Customer::observe(WorkspaceAuditObserver::class);
        Order::observe(WorkspaceAuditObserver::class);
        DiningTable::observe(WorkspaceAuditObserver::class);
        TableSession::observe(WorkspaceAuditObserver::class);
        PosItemCategory::observe(WorkspaceAuditObserver::class);
        PosMenuItem::observe(WorkspaceAuditObserver::class);
        PosCashierInvoice::observe(WorkspaceAuditObserver::class);
        PosCashierInvoiceItem::observe(WorkspaceAuditObserver::class);
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
        AppointmentHoliday::observe(WorkspaceAuditObserver::class);
        Contract::observe(WorkspaceAuditObserver::class);
        ContractItem::observe(WorkspaceAuditObserver::class);
        ContractAttachment::observe(WorkspaceAuditObserver::class);
        Subscription::observe(WorkspaceAuditObserver::class);
        EmployeeInvitation::observe(WorkspaceAuditObserver::class);
        WhatsAppAccount::observe(WorkspaceAuditObserver::class);
        Website::observe(WorkspaceAuditObserver::class);
        WebsiteTemplate::observe(WorkspaceAuditObserver::class);
        WebsitePage::observe(WorkspaceAuditObserver::class);
        WebsiteSection::observe(WorkspaceAuditObserver::class);
        WebsiteDomain::observe(WorkspaceAuditObserver::class);
        WebsiteAsset::observe(WorkspaceAuditObserver::class);
        WebsiteDomainOperation::observe(WorkspaceAuditObserver::class);
        WebsiteDomainContact::observe(WorkspaceAuditObserver::class);
        Website::observe(WebsiteResolverObserver::class);
        WebsiteDomain::observe(WebsiteResolverObserver::class);
    }
}
