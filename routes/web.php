<?php

use App\Http\Controllers\AssistantController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\PhoneOtpController;
use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\WorkspaceSelectionController;
use App\Http\Controllers\Workspace\AiSettingController;
use App\Http\Controllers\Workspace\CategoryController;
use App\Http\Controllers\Workspace\ConversationController;
use App\Http\Controllers\Workspace\CustomerController;
use App\Http\Controllers\Workspace\DashboardController as WorkspaceDashboardController;
use App\Http\Controllers\Workspace\EmployeeInvitationController;
use App\Http\Controllers\Workspace\EmailController;
use App\Http\Controllers\Workspace\Finance\AccountingController as FinanceAccountingController;
use App\Http\Controllers\Workspace\Finance\DashboardController as FinanceDashboardController;
use App\Http\Controllers\Workspace\Finance\ExpenseController as FinanceExpenseController;
use App\Http\Controllers\Workspace\Finance\InvoiceController as FinanceInvoiceController;
use App\Http\Controllers\Workspace\Finance\ModulePageController as FinanceModulePageController;
use App\Http\Controllers\Workspace\Finance\ReportController as FinanceReportController;
use App\Http\Controllers\Workspace\Finance\SettingsController as FinanceSettingsController;
use App\Http\Controllers\Workspace\Finance\SupplierController as FinanceSupplierController;
use App\Http\Controllers\Workspace\InventoryController;
use App\Http\Controllers\Workspace\OrderController;
use App\Http\Controllers\Workspace\PaymentController;
use App\Http\Controllers\Workspace\PaymentGatewayController;
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
        Route::post('emails/accounts', [EmailController::class, 'storeAccount'])->name('emails.accounts.store');
        Route::put('emails/accounts/{emailAccount}', [EmailController::class, 'updateAccount'])->name('emails.accounts.update');
        Route::post('emails/accounts/{emailAccount}/sync', [EmailController::class, 'syncAccount'])->name('emails.accounts.sync');
        Route::post('emails/messages/send', [EmailController::class, 'sendMessage'])->name('emails.messages.send');
        Route::delete('emails/messages/{emailMessage}', [EmailController::class, 'destroyMessage'])->name('emails.messages.destroy');

        Route::prefix('finance')->as('finance.')->group(function (): void {
            Route::get('/', [FinanceDashboardController::class, 'index'])->name('dashboard');

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
            Route::get('payroll', [FinanceModulePageController::class, 'payroll'])->name('payroll.index');
            Route::get('vat', [FinanceModulePageController::class, 'vat'])->name('vat.index');
            Route::get('banks', [FinanceModulePageController::class, 'banks'])->name('banks.index');
            Route::get('modules/{key}', [FinanceModulePageController::class, 'placeholder'])->name('modules.show');

            Route::get('sales', [FinanceModulePageController::class, 'sales'])->name('sales.index');
            Route::get('purchases', [FinanceModulePageController::class, 'purchases'])->name('purchases.index');
            Route::get('cashbox', [FinanceModulePageController::class, 'cashbox'])->name('cashbox.index');
        });

        Route::get('employees', [EmployeeInvitationController::class, 'index'])->name('employees.index');
        Route::get('employees/invite', [EmployeeInvitationController::class, 'create'])->name('employees.create');
        Route::post('employees/invite', [EmployeeInvitationController::class, 'store'])->name('employees.store');
        Route::delete('employees/invitations/{employee}', [EmployeeInvitationController::class, 'destroy'])->name('employees.destroy');
    });

require __DIR__.'/auth.php';
