<?php

use App\Http\Controllers\AssistantController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\PhoneOtpController;
use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Webhook\ResendWebhookController;
use App\Http\Controllers\WorkspaceSelectionController;
use App\Http\Controllers\Workspace\AiSettingController;
use App\Http\Controllers\Workspace\Appointments\BookingController as AppointmentsBookingController;
use App\Http\Controllers\Workspace\Appointments\CustomerProfileController as AppointmentsCustomerProfileController;
use App\Http\Controllers\Workspace\Appointments\CustomerPortalController as AppointmentsCustomerPortalController;
use App\Http\Controllers\Workspace\Appointments\DashboardController as AppointmentsDashboardController;
use App\Http\Controllers\Workspace\Appointments\ModulePageController as AppointmentsModulePageController;
use App\Http\Controllers\Workspace\Appointments\RequestController as AppointmentsRequestController;
use App\Http\Controllers\Workspace\CategoryController;
use App\Http\Controllers\Workspace\ConversationController;
use App\Http\Controllers\Workspace\ContractController as WorkspaceContractController;
use App\Http\Controllers\Workspace\CustomerController;
use App\Http\Controllers\Workspace\DashboardController as WorkspaceDashboardController;
use App\Http\Controllers\Workspace\EmployeeInvitationController;
use App\Http\Controllers\Workspace\EmailController;
use App\Http\Controllers\Workspace\Finance\AccountingController as FinanceAccountingController;
use App\Http\Controllers\Workspace\Finance\DashboardController as FinanceDashboardController;
use App\Http\Controllers\Workspace\Finance\FinanceEmployeeController as FinanceEmployeeController;
use App\Http\Controllers\Workspace\Finance\ExpenseController as FinanceExpenseController;
use App\Http\Controllers\Workspace\Finance\FiscalYearController as FinanceFiscalYearController;
use App\Http\Controllers\Workspace\Finance\InvoiceController as FinanceInvoiceController;
use App\Http\Controllers\Workspace\Finance\ModulePageController as FinanceModulePageController;
use App\Http\Controllers\Workspace\Finance\PayrollAdjustmentController as FinancePayrollAdjustmentController;
use App\Http\Controllers\Workspace\Finance\PriceListController as FinancePriceListController;
use App\Http\Controllers\Workspace\Finance\ReportController as FinanceReportController;
use App\Http\Controllers\Workspace\Finance\SalaryAdvanceController as FinanceSalaryAdvanceController;
use App\Http\Controllers\Workspace\Finance\SalesController as FinanceSalesController;
use App\Http\Controllers\Workspace\Finance\SettingsController as FinanceSettingsController;
use App\Http\Controllers\Workspace\Finance\SupplierController as FinanceSupplierController;
use App\Http\Controllers\Workspace\InventoryController;
use App\Http\Controllers\Workspace\OrderController;
use App\Http\Controllers\Workspace\PaymentController;
use App\Http\Controllers\Workspace\PaymentGatewayController;
use App\Http\Controllers\Workspace\Pos\CashierController as PosCashierController;
use App\Http\Controllers\Workspace\Pos\PosCashierInvoiceController as PosCashierInvoiceController;
use App\Http\Controllers\Workspace\Pos\CustomerMenuController as PosCustomerMenuController;
use App\Http\Controllers\Workspace\Pos\PosItemCategoryController as PosItemCategoryController;
use App\Http\Controllers\Workspace\Pos\PosKitchenController as PosKitchenController;
use App\Http\Controllers\Workspace\Pos\PosMenuItemController as PosMenuItemController;
use App\Http\Controllers\Workspace\Pos\PosMenuPageController as PosMenuPageController;
use App\Http\Controllers\Workspace\Pos\PosOrderController as PosOrderController;
use App\Http\Controllers\Workspace\Pos\PosReportController as PosReportController;
use App\Http\Controllers\Workspace\Pos\PosSettingsController as PosSettingsController;
use App\Http\Controllers\Workspace\Pos\TableController as PosTableController;
use App\Http\Controllers\Workspace\ProductController;
use App\Http\Controllers\Workspace\SubscriptionController;
use App\Http\Controllers\Workspace\WhatsAppAccountController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::get('/', function () {
    $plans = collect();
    if (Schema::hasTable('plans')) {
        $plans = \App\Models\Plan::query()
            ->where('is_active', true)
            ->orderBy('workspace_type')
            ->orderBy('price')
            ->get();
    }

    return view('landing', [
        'plansByType' => $plans->groupBy('workspace_type'),
    ]);
});

Route::post('/assistant/chat', [AssistantController::class, 'chat'])
    ->middleware('throttle:20,1')
    ->name('assistant.chat');

Route::post('/webhooks/resend', [ResendWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('webhooks.resend');

Route::middleware('throttle:30,1')->group(function (): void {
    Route::get('/appointments/portal/{token}', [AppointmentsCustomerPortalController::class, 'show'])->name('appointments.portal.show');
    Route::post('/appointments/portal/{token}/confirm', [AppointmentsCustomerPortalController::class, 'confirmAttendance'])->name('appointments.portal.confirm');
    Route::post('/appointments/portal/{token}/reschedule', [AppointmentsCustomerPortalController::class, 'requestReschedule'])->name('appointments.portal.reschedule');
    Route::post('/appointments/portal/{token}/cancel', [AppointmentsCustomerPortalController::class, 'requestCancellation'])->name('appointments.portal.cancel');

    Route::get('/menu/{workspace:slug}', [PosCustomerMenuController::class, 'generalMenu'])->name('menu.general');
    Route::post('/menu/{workspace:slug}/order', [PosCustomerMenuController::class, 'placeGeneralOrder'])->name('menu.general.order');
    Route::post('/menu/{workspace:slug}/ai-chat', [PosCustomerMenuController::class, 'askAi'])->name('menu.general.ai');
    Route::get('/menu/{workspace:slug}/table/{token}', [PosCustomerMenuController::class, 'tableMenu'])->name('menu.table');
    Route::post('/menu/{workspace:slug}/table/{token}/order', [PosCustomerMenuController::class, 'placeTableOrder'])->name('menu.table.order');
    Route::post('/menu/{workspace:slug}/table/{token}/ai-chat', [PosCustomerMenuController::class, 'askAi'])->name('menu.table.ai');
});

Route::middleware(['guest'])->group(function (): void {
    Route::get('/otp/login', [PhoneOtpController::class, 'create'])->name('otp.login');
    Route::post('/otp/request', [PhoneOtpController::class, 'requestOtp'])->name('otp.request');
    Route::get('/otp/verify', [PhoneOtpController::class, 'verifyForm'])->name('otp.verify.form');
    Route::post('/otp/verify', [PhoneOtpController::class, 'verify'])->name('otp.verify');

    Route::get('/auth/{provider}/redirect', [SocialLoginController::class, 'redirect'])
        ->whereIn('provider', ['google', 'facebook'])
        ->name('social.redirect');
    Route::get('/auth/{provider}/callback', [SocialLoginController::class, 'callback'])
        ->whereIn('provider', ['google', 'facebook'])
        ->name('social.callback');
});

Route::middleware(['auth'])->group(function (): void {
    Route::get('/dashboard', fn () => redirect()->route('workspace.subscriptions.index'))->name('dashboard');

    Route::get('/workspaces/choose', [WorkspaceSelectionController::class, 'choose'])->name('workspace.choose');
    Route::post('/workspaces/{workspace}/switch', [WorkspaceSelectionController::class, 'switch'])->name('workspace.switch');
    Route::redirect('/subscription', '/workspace/subscriptions')->name('subscription.page');
    Route::redirect('/billing', '/workspace/payments/create')->name('billing.page');
    Route::redirect('/settings', '/profile')->name('settings.page');
    Route::redirect('/security', '/profile')->name('security.page');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
});

Route::middleware(['auth', 'workspace.selected', 'workspace.member'])
    ->prefix('workspace')
    ->as('workspace.')
    ->group(function (): void {
        Route::get('/', [WorkspaceDashboardController::class, 'index'])->name('dashboard');

        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::resource('products', ProductController::class)->except(['show']);
        Route::resource('customers', CustomerController::class)->except(['show']);
        Route::resource('orders', OrderController::class)->except(['show']);
        Route::get('contracts', [WorkspaceContractController::class, 'index'])->name('contracts.index');
        Route::get('contracts/create', [WorkspaceContractController::class, 'create'])->name('contracts.create');
        Route::post('contracts', [WorkspaceContractController::class, 'store'])->name('contracts.store');
        Route::get('contracts/{contract}', [WorkspaceContractController::class, 'show'])->name('contracts.show');
        Route::get('contracts/{contract}/edit', [WorkspaceContractController::class, 'edit'])->name('contracts.edit');
        Route::put('contracts/{contract}', [WorkspaceContractController::class, 'update'])->name('contracts.update');
        Route::post('contracts/{contract}/activate', [WorkspaceContractController::class, 'activate'])->name('contracts.activate');
        Route::post('contracts/{contract}/close', [WorkspaceContractController::class, 'close'])->name('contracts.close');
        Route::post('contracts/{contract}/cancel', [WorkspaceContractController::class, 'cancel'])->name('contracts.cancel');
        Route::get('contracts/{contract}/pdf', [WorkspaceContractController::class, 'downloadPdf'])->name('contracts.pdf');
        Route::get('contracts/{contract}/attachments/{attachment}', [WorkspaceContractController::class, 'downloadAttachment'])->name('contracts.attachments.download');
        Route::delete('contracts/{contract}/attachments/{attachment}', [WorkspaceContractController::class, 'destroyAttachment'])->name('contracts.attachments.destroy');
        Route::resource('conversations', ConversationController::class)->except(['show']);
        Route::post('conversations/{conversation}/messages', [ConversationController::class, 'storeMessage'])->name('conversations.messages.store');

        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('inventory/create', [InventoryController::class, 'create'])->name('inventory.create');
        Route::post('inventory', [InventoryController::class, 'store'])->name('inventory.store');

        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/create', [PaymentController::class, 'create'])->name('payments.create');
        Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');

        Route::resource('payment-gateways', PaymentGatewayController::class)->except(['show']);

        Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::post('subscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');
        Route::get('subscriptions/checkout/{checkoutSession}', [SubscriptionController::class, 'showCheckout'])->name('subscriptions.checkout.show');
        Route::post('subscriptions/checkout/{checkoutSession}/confirm-payment', [SubscriptionController::class, 'confirmCheckoutPayment'])->name('subscriptions.checkout.confirm-payment');
        Route::delete('subscriptions/{subscription}', [SubscriptionController::class, 'destroy'])->name('subscriptions.destroy');

        Route::get('ai-settings', [AiSettingController::class, 'edit'])->name('ai-settings.edit');
        Route::put('ai-settings', [AiSettingController::class, 'update'])->name('ai-settings.update');

        Route::resource('whatsapp-accounts', WhatsAppAccountController::class)->except(['show']);

        Route::get('emails', [EmailController::class, 'index'])->name('emails.index');
        Route::get('emails/inbox', [EmailController::class, 'inbox'])->name('emails.inbox');
        Route::get('emails/sent', [EmailController::class, 'sent'])->name('emails.sent');
        Route::get('emails/messages/{emailMessage}', [EmailController::class, 'showMessage'])->name('emails.messages.show');
        Route::delete('emails/messages/{emailMessage}', [EmailController::class, 'destroyMessage'])->name('emails.messages.destroy');

        Route::get('emails/compose', [EmailController::class, 'compose'])->name('emails.compose');
        Route::post('emails/compose/clear', [EmailController::class, 'clearComposeDraft'])->name('emails.compose.clear');
        Route::post('emails/messages/send', [EmailController::class, 'sendMessage'])->name('emails.messages.send');

        Route::get('emails/contacts', [EmailController::class, 'contacts'])->name('emails.contacts.index');
        Route::post('emails/contacts', [EmailController::class, 'storeContact'])->name('emails.contacts.store');
        Route::put('emails/contacts/{emailContact}', [EmailController::class, 'updateContact'])->name('emails.contacts.update');
        Route::delete('emails/contacts/{emailContact}', [EmailController::class, 'destroyContact'])->name('emails.contacts.destroy');
        Route::get('emails/contacts/search', [EmailController::class, 'searchContacts'])->name('emails.contacts.search');
        Route::get('emails/contacts/lookup', [EmailController::class, 'lookupContact'])->name('emails.contacts.lookup');

        Route::get('emails/accounts', [EmailController::class, 'accounts'])->name('emails.accounts.index');
        Route::post('emails/accounts', [EmailController::class, 'storeAccount'])->name('emails.accounts.store');
        Route::put('emails/accounts/{emailAccount}', [EmailController::class, 'updateAccount'])->name('emails.accounts.update');
        Route::delete('emails/accounts/{emailAccount}', [EmailController::class, 'destroyAccount'])->name('emails.accounts.destroy');
        Route::post('emails/accounts/{emailAccount}/sync', [EmailController::class, 'syncAccount'])->name('emails.accounts.sync');

        Route::prefix('finance')->as('finance.')->group(function (): void {
            Route::get('/', [FinanceDashboardController::class, 'index'])->name('dashboard');
            Route::get('contracts', [WorkspaceContractController::class, 'index'])->name('contracts.index');
            Route::get('contracts/create', [WorkspaceContractController::class, 'create'])->name('contracts.create');
            Route::post('contracts', [WorkspaceContractController::class, 'store'])->name('contracts.store');
            Route::get('contracts/{contract}', [WorkspaceContractController::class, 'show'])->name('contracts.show');
            Route::get('contracts/{contract}/edit', [WorkspaceContractController::class, 'edit'])->name('contracts.edit');
            Route::put('contracts/{contract}', [WorkspaceContractController::class, 'update'])->name('contracts.update');
            Route::post('contracts/{contract}/activate', [WorkspaceContractController::class, 'activate'])->name('contracts.activate');
            Route::post('contracts/{contract}/close', [WorkspaceContractController::class, 'close'])->name('contracts.close');
            Route::post('contracts/{contract}/cancel', [WorkspaceContractController::class, 'cancel'])->name('contracts.cancel');
            Route::get('contracts/{contract}/pdf', [WorkspaceContractController::class, 'downloadPdf'])->name('contracts.pdf');
            Route::get('contracts/{contract}/attachments/{attachment}', [WorkspaceContractController::class, 'downloadAttachment'])->name('contracts.attachments.download');
            Route::delete('contracts/{contract}/attachments/{attachment}', [WorkspaceContractController::class, 'destroyAttachment'])->name('contracts.attachments.destroy');

            Route::get('invoices', [FinanceInvoiceController::class, 'index'])->name('invoices.index');
            Route::get('invoices/create', [FinanceInvoiceController::class, 'create'])->name('invoices.create');
            Route::post('invoices', [FinanceInvoiceController::class, 'store'])->name('invoices.store');
            Route::get('invoices/{invoice}', [FinanceInvoiceController::class, 'show'])->name('invoices.show');
            Route::get('invoices/{invoice}/pdf', [FinanceInvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
            Route::post('invoices/{invoice}/cancel', [FinanceInvoiceController::class, 'cancel'])->name('invoices.cancel');
            Route::post('invoices/{invoice}/payments', [FinanceInvoiceController::class, 'storePayment'])->name('invoices.payments.store');

            Route::get('suppliers', [FinanceSupplierController::class, 'index'])->name('suppliers.index');
            Route::post('suppliers', [FinanceSupplierController::class, 'store'])->name('suppliers.store');
            Route::put('suppliers/{supplier}', [FinanceSupplierController::class, 'update'])->name('suppliers.update');

            Route::get('expenses', [FinanceExpenseController::class, 'index'])->name('expenses.index');
            Route::post('expenses', [FinanceExpenseController::class, 'store'])->name('expenses.store');

            Route::get('accounting', [FinanceAccountingController::class, 'dashboard'])->name('accounting.dashboard');
            Route::get('reports', [FinanceReportController::class, 'index'])->name('reports.index');

            Route::get('settings', [FinanceSettingsController::class, 'index'])->name('settings.index');
            Route::put('settings/company', [FinanceSettingsController::class, 'updateCompany'])->name('settings.company.update');
            Route::post('settings/tax-rates', [FinanceSettingsController::class, 'storeTaxRate'])->name('settings.tax-rates.store');
            Route::post('settings/treasury-accounts', [FinanceSettingsController::class, 'storeTreasuryAccount'])->name('settings.treasury-accounts.store');

            Route::get('customers', [FinanceModulePageController::class, 'customers'])->name('customers.index');
            Route::get('products', [FinanceModulePageController::class, 'products'])->name('products.index');
            Route::get('inventory', [FinanceModulePageController::class, 'inventory'])->name('inventory.index');
            Route::get('employees', [FinanceEmployeeController::class, 'index'])->name('employees.index');
            Route::post('employees', [FinanceEmployeeController::class, 'store'])->name('employees.store');
            Route::get('employees/{employee}', [FinanceEmployeeController::class, 'show'])->name('employees.show');
            Route::put('employees/{employee}', [FinanceEmployeeController::class, 'update'])->name('employees.update');
            Route::delete('employees/{employee}', [FinanceEmployeeController::class, 'destroy'])->name('employees.destroy');
            Route::post('employees/{employee}/payroll-records', [FinanceEmployeeController::class, 'storePayrollRecord'])->name('employees.payroll-records.store');
            Route::get('payroll', [FinanceModulePageController::class, 'payroll'])->name('payroll.index');
            Route::get('vat', [FinanceModulePageController::class, 'vat'])->name('vat.index');
            Route::get('banks', [FinanceModulePageController::class, 'banks'])->name('banks.index');
            Route::get('sales', [FinanceSalesController::class, 'index'])->name('sales.index');
            Route::get('price-lists', [FinancePriceListController::class, 'index'])->name('price-lists.index');
            Route::post('price-lists', [FinancePriceListController::class, 'store'])->name('price-lists.store');
            Route::put('price-lists/{priceList}', [FinancePriceListController::class, 'update'])->name('price-lists.update');
            Route::post('price-lists/{priceList}/items', [FinancePriceListController::class, 'addItem'])->name('price-lists.items.store');
            Route::put('price-lists/items/{item}', [FinancePriceListController::class, 'updateItem'])->name('price-lists.items.update');
            Route::delete('price-lists/items/{item}', [FinancePriceListController::class, 'deleteItem'])->name('price-lists.items.destroy');
            Route::post('price-lists/{priceList}/approve', [FinancePriceListController::class, 'approve'])->name('price-lists.approve');
            Route::post('price-lists/{priceList}/mark-draft', [FinancePriceListController::class, 'markDraft'])->name('price-lists.mark-draft');
            Route::post('price-lists/{priceList}/cancel', [FinancePriceListController::class, 'cancel'])->name('price-lists.cancel');

            Route::get('allowances', [FinancePayrollAdjustmentController::class, 'allowances'])->name('allowances.index');
            Route::get('bonuses', [FinancePayrollAdjustmentController::class, 'bonuses'])->name('bonuses.index');
            Route::get('deductions', [FinancePayrollAdjustmentController::class, 'deductions'])->name('deductions.index');
            Route::post('payroll-adjustments', [FinancePayrollAdjustmentController::class, 'store'])->name('payroll-adjustments.store');
            Route::post('payroll-adjustments/{adjustment}/approve', [FinancePayrollAdjustmentController::class, 'approve'])->name('payroll-adjustments.approve');
            Route::post('payroll-adjustments/{adjustment}/post', [FinancePayrollAdjustmentController::class, 'post'])->name('payroll-adjustments.post');
            Route::post('payroll-adjustments/{adjustment}/cancel', [FinancePayrollAdjustmentController::class, 'cancel'])->name('payroll-adjustments.cancel');

            Route::get('salary-advances', [FinanceSalaryAdvanceController::class, 'index'])->name('salary-advances.index');
            Route::post('salary-advances', [FinanceSalaryAdvanceController::class, 'store'])->name('salary-advances.store');
            Route::post('salary-advances/{advance}/repay', [FinanceSalaryAdvanceController::class, 'repay'])->name('salary-advances.repay');

            Route::get('fiscal-years', [FinanceFiscalYearController::class, 'index'])->name('fiscal-years.index');
            Route::post('fiscal-years', [FinanceFiscalYearController::class, 'store'])->name('fiscal-years.store');
            Route::put('fiscal-years/{fiscalYear}', [FinanceFiscalYearController::class, 'update'])->name('fiscal-years.update');
            Route::post('fiscal-years/{fiscalYear}/close', [FinanceFiscalYearController::class, 'close'])->name('fiscal-years.close');
            Route::post('fiscal-years/{fiscalYear}/open', [FinanceFiscalYearController::class, 'open'])->name('fiscal-years.open');
            Route::post('fiscal-years/{fiscalYear}/generate-monthly-periods', [FinanceFiscalYearController::class, 'generateMonthlyPeriods'])->name('fiscal-years.generate-monthly-periods');
            Route::post('fiscal-years/{fiscalYear}/periods', [FinanceFiscalYearController::class, 'storePeriod'])->name('fiscal-years.periods.store');
            Route::post('fiscal-years/periods/{period}/status', [FinanceFiscalYearController::class, 'setPeriodStatus'])->name('fiscal-years.periods.set-status');

            Route::get('modules/{key}', [FinanceModulePageController::class, 'placeholder'])->name('modules.show');
            Route::get('purchases', [FinanceModulePageController::class, 'purchases'])->name('purchases.index');
            Route::get('cashbox', [FinanceModulePageController::class, 'cashbox'])->name('cashbox.index');
        });

        Route::prefix('appointments')->as('appointments.')->group(function (): void {
            Route::get('/', [AppointmentsModulePageController::class, 'overview'])->name('dashboard');
            Route::get('overview', [AppointmentsModulePageController::class, 'overview'])->name('overview');
            Route::get('bookings', [AppointmentsModulePageController::class, 'bookings'])->name('bookings.index');
            Route::get('bookings/{booking}', [AppointmentsModulePageController::class, 'bookingDetails'])->name('bookings.show');
            Route::get('calendar', [AppointmentsModulePageController::class, 'calendar'])->name('calendar.index');
            Route::get('requests', [AppointmentsModulePageController::class, 'requests'])->name('requests.index');
            Route::get('requests/{appointmentRequest}', [AppointmentsModulePageController::class, 'requestDetails'])->name('requests.show');
            Route::get('customers', [AppointmentsModulePageController::class, 'customers'])->name('customers.index');
            Route::get('settings', [AppointmentsModulePageController::class, 'settings'])->name('settings.index');
            Route::post('settings', [AppointmentsDashboardController::class, 'updateSettings'])->name('settings.update');

            Route::post('services', [AppointmentsDashboardController::class, 'storeService'])->name('services.store');
            Route::put('services/{service}', [AppointmentsDashboardController::class, 'updateService'])->name('services.update');

            Route::post('staff', [AppointmentsDashboardController::class, 'storeStaff'])->name('staff.store');
            Route::put('staff/{staff}', [AppointmentsDashboardController::class, 'updateStaff'])->name('staff.update');

            Route::post('resources', [AppointmentsDashboardController::class, 'storeResource'])->name('resources.store');
            Route::put('resources/{resource}', [AppointmentsDashboardController::class, 'updateResource'])->name('resources.update');

            Route::post('bookings', [AppointmentsDashboardController::class, 'storeBooking'])->name('bookings.store');
            Route::post('bookings/{booking}/status', [AppointmentsDashboardController::class, 'updateBookingStatus'])->name('bookings.status');
            Route::post('bookings/{booking}/reschedule', [AppointmentsBookingController::class, 'reschedule'])->name('bookings.reschedule');
            Route::post('bookings/{booking}/payment-link', [AppointmentsBookingController::class, 'createPaymentLink'])->name('bookings.payment-link');
            Route::post('bookings/{booking}/send-reminder', [AppointmentsBookingController::class, 'sendReminder'])->name('bookings.send-reminder');
            Route::get('calendar/events', [AppointmentsBookingController::class, 'calendarEvents'])->name('calendar.events');
            Route::get('customers/{customer}/profile', [AppointmentsCustomerProfileController::class, 'show'])->name('customers.profile');

            Route::post('requests', [AppointmentsRequestController::class, 'store'])->name('requests.store');
            Route::post('requests/{appointmentRequest}/approve', [AppointmentsRequestController::class, 'approve'])->name('requests.approve');
            Route::post('requests/{appointmentRequest}/reject', [AppointmentsRequestController::class, 'reject'])->name('requests.reject');
            Route::post('requests/{appointmentRequest}/awaiting-customer', [AppointmentsRequestController::class, 'markAwaitingCustomer'])->name('requests.awaiting-customer');
            Route::post('requests/{appointmentRequest}/cancel', [AppointmentsRequestController::class, 'cancel'])->name('requests.cancel');
            Route::post('requests/{appointmentRequest}/slots', [AppointmentsRequestController::class, 'proposeSlots'])->name('requests.slots.store');
            Route::post('requests/{appointmentRequest}/slots/{slot}/select', [AppointmentsRequestController::class, 'selectSlot'])->name('requests.slots.select');
        });

        Route::prefix('pos')->as('pos.')->group(function (): void {
            Route::get('/', [PosTableController::class, 'index'])->name('dashboard');
            Route::get('cashier', [PosCashierController::class, 'index'])->name('cashier.index');
            Route::get('menu', [PosMenuPageController::class, 'index'])->name('menu.index');

            Route::post('orders', [PosCashierController::class, 'storeOrder'])->name('orders.store');
            Route::get('orders/running', [PosOrderController::class, 'running'])->name('orders.running');
            Route::get('kitchen', [PosKitchenController::class, 'index'])->name('kitchen.index');
            Route::post('orders/{order}/status', [PosOrderController::class, 'updateStatus'])->name('orders.status');
            Route::post('orders/{order}/invoice', [PosOrderController::class, 'createInvoice'])->name('orders.invoice');
            Route::post('orders/{order}/payment-link', [PosOrderController::class, 'createPaymentLink'])->name('orders.payment-link');
            Route::post('orders/{order}/items', [PosOrderController::class, 'updateItems'])->name('orders.update-items');
            Route::get('orders/{order}/print', [PosOrderController::class, 'printOrder'])->name('orders.print');

            Route::get('invoices', [PosCashierInvoiceController::class, 'index'])->name('invoices.index');
            Route::get('invoices/{invoice}', [PosCashierInvoiceController::class, 'show'])->name('invoices.show');
            Route::get('invoices/{invoice}/print', [PosCashierInvoiceController::class, 'print'])->name('invoices.print');
            Route::get('reports/daily', [PosReportController::class, 'daily'])->name('reports.daily');

            Route::get('tables', [PosTableController::class, 'index'])->name('tables.index');
            Route::post('tables', [PosTableController::class, 'store'])->name('tables.store');
            Route::get('tables/{table}', [PosTableController::class, 'show'])->name('tables.show');
            Route::put('tables/{table}', [PosTableController::class, 'update'])->name('tables.update');
            Route::post('tables/{table}/open-session', [PosTableController::class, 'openSession'])->name('tables.sessions.open');
            Route::post('tables/{table}/orders', [PosTableController::class, 'addOrder'])->name('tables.orders.store');
            Route::post('tables/{table}/sessions/{session}/close', [PosTableController::class, 'closeSession'])->name('tables.sessions.close');
            Route::post('tables/{table}/sessions/{session}/cancel', [PosTableController::class, 'cancelSession'])->name('tables.sessions.cancel');
            Route::post('tables/{table}/sessions/{session}/discount', [PosTableController::class, 'applyDiscount'])->name('tables.sessions.discount');
            Route::post('tables/{table}/qr/regenerate', [PosTableController::class, 'regenerateQr'])->name('tables.qr.regenerate');

            Route::get('items', [PosMenuItemController::class, 'index'])->name('items.index');
            Route::post('items', [PosMenuItemController::class, 'store'])->name('items.store');
            Route::put('items/{item}', [PosMenuItemController::class, 'update'])->name('items.update');
            Route::delete('items/{item}', [PosMenuItemController::class, 'destroy'])->name('items.destroy');
            Route::post('categories', [PosItemCategoryController::class, 'store'])->name('categories.store');
            Route::put('categories/{category}', [PosItemCategoryController::class, 'update'])->name('categories.update');
            Route::delete('categories/{category}', [PosItemCategoryController::class, 'destroy'])->name('categories.destroy');
            Route::post('settings/menu-slider', [PosSettingsController::class, 'updateMenuSlider'])->name('settings.menu-slider');
        });

        Route::get('employees', [EmployeeInvitationController::class, 'index'])->name('employees.index');
        Route::get('employees/invite', [EmployeeInvitationController::class, 'create'])->name('employees.create');
        Route::post('employees/invite', [EmployeeInvitationController::class, 'store'])->name('employees.store');
        Route::delete('employees/invitations/{employee}', [EmployeeInvitationController::class, 'destroy'])->name('employees.destroy');
    });

require __DIR__.'/auth.php';
