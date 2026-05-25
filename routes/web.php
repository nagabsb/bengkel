<?php

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Billing\MidtransWebhookController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EstimateApprovalController;
use App\Http\Controllers\Owner\DashboardController as OwnerDashboardController;
use App\Http\Controllers\Owner\BookingController as OwnerBookingController;
use App\Http\Controllers\Owner\BookingPageBuilderController as OwnerBookingPageBuilderController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\Owner\CustomerController as OwnerCustomerController;
use App\Http\Controllers\Owner\ExpenseCategoryController as OwnerExpenseCategoryController;
use App\Http\Controllers\Owner\ExpenseController as OwnerExpenseController;
use App\Http\Controllers\Owner\InvoiceController as OwnerInvoiceController;
use App\Http\Controllers\Owner\ReportController as OwnerReportController;
use App\Http\Controllers\Owner\ServiceOrderController as OwnerServiceOrderController;
use App\Http\Controllers\Owner\ServiceOrderEstimateController as OwnerServiceOrderEstimateController;
use App\Http\Controllers\Owner\SparePartCategoryController as OwnerSparePartCategoryController;
use App\Http\Controllers\Owner\SettingsController as OwnerSettingsController;
use App\Http\Controllers\Owner\SparePartController as OwnerSparePartController;
use App\Http\Controllers\Owner\SparePartUnitController as OwnerSparePartUnitController;
use App\Http\Controllers\Owner\SupplierController as OwnerSupplierController;
use App\Http\Controllers\Owner\SubdomainRedirectController as OwnerSubdomainRedirectController;
use App\Http\Controllers\Owner\UserController as OwnerUserController;
use App\Http\Controllers\Owner\VehicleController as OwnerVehicleController;
use App\Http\Controllers\Owner\WarehouseController as OwnerWarehouseController;
use App\Http\Controllers\Owner\WorkshopController as OwnerWorkshopController;
use App\Http\Controllers\Platform\AiAgentSettingController;
use App\Http\Controllers\Platform\ApplicationSettingController;
use App\Http\Controllers\Platform\DashboardController as PlatformDashboardController;
use App\Http\Controllers\Platform\MenuController;
use App\Http\Controllers\Platform\PaymentSettingController;
use App\Http\Controllers\Platform\PermissionController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\TenantController;
use App\Http\Controllers\Platform\VehicleMasterController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SessionController::class, 'show'])
    ->middleware('guest')
    ->name('home');

Route::get('/login', [SessionController::class, 'show'])
    ->middleware('guest')
    ->name('login');

Route::post('/login', [SessionController::class, 'login'])
    ->middleware(['guest', 'throttle:login'])
    ->name('login.submit');

Route::post('/webhooks/midtrans/notification', [MidtransWebhookController::class, 'notification'])
    ->middleware(['throttle:web'])
    ->name('webhooks.midtrans.notification');

Route::get('/estimate-approval/{token}', [EstimateApprovalController::class, 'show'])
    ->middleware(['throttle:web'])
    ->where('token', '[A-Za-z0-9]+')
    ->name('estimate-approval.show');

Route::post('/estimate-approval/{token}/respond', [EstimateApprovalController::class, 'respond'])
    ->middleware(['throttle:web'])
    ->where('token', '[A-Za-z0-9]+')
    ->name('estimate-approval.respond');

Route::get('/booking', [PublicBookingController::class, 'show'])
    ->middleware(['throttle:web'])
    ->name('booking.public');

Route::post('/booking', [PublicBookingController::class, 'store'])
    ->middleware(['throttle:web'])
    ->name('booking.public.store');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'throttle:web'])
    ->name('dashboard');

Route::get('/platform/dashboard', [PlatformDashboardController::class, 'index'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.view'])
    ->name('platform.dashboard');

Route::get('/platform/tenants', [TenantController::class, 'index'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.view'])
    ->name('platform.tenants');

Route::get('/platform/tenants/export', [TenantController::class, 'export'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.view'])
    ->name('platform.tenants.export');

Route::post('/platform/tenants', [TenantController::class, 'store'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.manage'])
    ->name('platform.tenants.store');

Route::patch('/platform/tenants/{tenant}', [TenantController::class, 'update'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.manage'])
    ->name('platform.tenants.update');

Route::patch('/platform/tenants/{tenant}/status', [TenantController::class, 'updateStatus'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.manage'])
    ->name('platform.tenants.status');

Route::get('/platform/settings/permissions', [PermissionController::class, 'index'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.view'])
    ->name('platform.permissions');

Route::post('/platform/settings/permissions/sync', [PermissionController::class, 'sync'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.manage'])
    ->name('platform.permissions.sync');

Route::get('/platform/settings/menus', [MenuController::class, 'index'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.view'])
    ->name('platform.menus');

Route::post('/platform/settings/menus', [MenuController::class, 'store'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.manage'])
    ->name('platform.menus.store');

Route::patch('/platform/settings/menus/{menu}/status', [MenuController::class, 'updateStatus'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.manage'])
    ->name('platform.menus.status');

Route::post('/platform/settings/menus/reorder', [MenuController::class, 'reorder'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.manage'])
    ->name('platform.menus.reorder');

Route::patch('/platform/settings/menus/{menu}', [MenuController::class, 'update'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.manage'])
    ->name('platform.menus.update');

Route::delete('/platform/settings/menus/{menu}', [MenuController::class, 'destroy'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.manage'])
    ->name('platform.menus.destroy');

Route::get('/platform/settings/plans', [PlanController::class, 'index'])
    ->middleware(['auth', 'throttle:web', 'can:platform.billing.view'])
    ->name('platform.plans');

Route::get('/platform/settings/application', [ApplicationSettingController::class, 'index'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.manage'])
    ->name('platform.application');

Route::post('/platform/settings/application', [ApplicationSettingController::class, 'update'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.manage'])
    ->name('platform.application.update');

Route::get('/platform/settings/payments', [PaymentSettingController::class, 'index'])
    ->middleware(['auth', 'throttle:web', 'can:platform.billing.manage'])
    ->name('platform.payments');

Route::post('/platform/settings/payments', [PaymentSettingController::class, 'update'])
    ->middleware(['auth', 'throttle:web', 'can:platform.billing.manage'])
    ->name('platform.payments.update');

Route::get('/platform/settings/ai-agent', [AiAgentSettingController::class, 'index'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.manage'])
    ->name('platform.ai-agent');

Route::get('/platform/settings/vehicle-masters', [VehicleMasterController::class, 'index'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.manage'])
    ->name('platform.vehicle-masters');

Route::post('/platform/settings/ai-agent', [AiAgentSettingController::class, 'store'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.manage'])
    ->name('platform.ai-agent.store');

Route::post('/platform/settings/vehicle-masters/sync', [VehicleMasterController::class, 'sync'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.manage'])
    ->name('platform.vehicle-masters.sync');

Route::post('/platform/settings/vehicle-masters/import', [VehicleMasterController::class, 'import'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.manage'])
    ->name('platform.vehicle-masters.import');

Route::get('/platform/settings/vehicle-masters/template', [VehicleMasterController::class, 'template'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.manage'])
    ->name('platform.vehicle-masters.template');

Route::get('/platform/settings/vehicle-masters/export', [VehicleMasterController::class, 'export'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.manage'])
    ->name('platform.vehicle-masters.export');

Route::patch('/platform/settings/ai-agent/{agent}', [AiAgentSettingController::class, 'update'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.manage'])
    ->name('platform.ai-agent.update');

Route::patch('/platform/settings/ai-agent/{agent}/status', [AiAgentSettingController::class, 'updateStatus'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.manage'])
    ->name('platform.ai-agent.status');

Route::patch('/platform/settings/ai-agent/{agent}/default', [AiAgentSettingController::class, 'setDefault'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.manage'])
    ->name('platform.ai-agent.default');

Route::delete('/platform/settings/ai-agent/{agent}', [AiAgentSettingController::class, 'destroy'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.manage'])
    ->name('platform.ai-agent.destroy');

Route::post('/platform/settings/ai-agent/{agent}/test', [AiAgentSettingController::class, 'test'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.manage'])
    ->name('platform.ai-agent.test');

Route::patch('/platform/settings/ai-agent/prompts/{feature}', [AiAgentSettingController::class, 'updatePrompt'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.manage'])
    ->name('platform.ai-agent.prompts.update');

Route::post('/platform/settings/ai-agent/prompts/{feature}/test-output', [AiAgentSettingController::class, 'testPromptOutput'])
    ->middleware(['auth', 'throttle:web', 'can:platform.tenants.manage'])
    ->name('platform.ai-agent.prompts.test-output');

Route::post('/platform/settings/plans', [PlanController::class, 'store'])
    ->middleware(['auth', 'throttle:web', 'can:platform.billing.manage'])
    ->name('platform.plans.store');

Route::patch('/platform/settings/plans/{plan}', [PlanController::class, 'update'])
    ->middleware(['auth', 'throttle:web', 'can:platform.billing.manage'])
    ->name('platform.plans.update');

Route::patch('/platform/settings/plans/{plan}/status', [PlanController::class, 'updateStatus'])
    ->middleware(['auth', 'throttle:web', 'can:platform.billing.manage'])
    ->name('platform.plans.status');

Route::get('/owner/{tenant}/dashboard', [OwnerDashboardController::class, 'index'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:owner.dashboard.view'])
    ->name('owner.dashboard');

Route::get('/owner/{tenant}/settings', [OwnerSettingsController::class, 'index'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:owner.dashboard.view'])
    ->name('owner.settings');

Route::post('/owner/{tenant}/settings/permissions/sync', [OwnerSettingsController::class, 'syncPermissions'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:users.manage'])
    ->name('owner.permissions.sync');

Route::post('/owner/{tenant}/settings/print', [OwnerSettingsController::class, 'updatePrintSetting'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:users.manage'])
    ->name('owner.settings.print.update');

Route::get('/owner/{tenant}/settings/print/kiosk-installer', [OwnerSettingsController::class, 'downloadKioskInstaller'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:users.manage'])
    ->name('owner.settings.print.kiosk-installer');

Route::get('/owner/{tenant}/workshops', [OwnerWorkshopController::class, 'index'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:users.manage'])
    ->name('owner.workshops.index');

Route::get('/owner/{tenant}/customers', [OwnerCustomerController::class, 'index'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:customers.view'])
    ->name('owner.customers.index');

Route::get('/owner/{tenant}/suppliers', [OwnerSupplierController::class, 'index'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:suppliers.view'])
    ->name('owner.suppliers.index');

Route::get('/owner/{tenant}/warehouses', [OwnerWarehouseController::class, 'index'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:warehouses.view'])
    ->name('owner.warehouses.index');

Route::get('/owner/{tenant}/spareparts', [OwnerSparePartController::class, 'index'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:spareparts.view'])
    ->name('owner.spareparts.index');

Route::get('/owner/{tenant}/sparepart-categories', [OwnerSparePartCategoryController::class, 'index'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:sparepart_categories.view'])
    ->name('owner.sparepart-categories.index');

Route::get('/owner/{tenant}/sparepart-units', [OwnerSparePartUnitController::class, 'index'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:sparepart_units.view'])
    ->name('owner.sparepart-units.index');

Route::get('/owner/{tenant}/orders', [OwnerServiceOrderController::class, 'index'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:service_orders.view'])
    ->name('owner.orders.index');

Route::get('/owner/{tenant}/bookings', [OwnerBookingController::class, 'index'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:bookings.view'])
    ->name('owner.bookings.index');

Route::get('/owner/{tenant}/bookings/builder', [OwnerBookingPageBuilderController::class, 'index'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:bookings.manage'])
    ->name('owner.bookings.builder');

Route::get('/owner/{tenant}/expenses', [OwnerExpenseController::class, 'index'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:expenses.view'])
    ->name('owner.expenses.index');

Route::get('/owner/{tenant}/expense-categories', [OwnerExpenseCategoryController::class, 'index'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:expense_categories.view'])
    ->name('owner.expense-categories.index');

Route::get('/owner/{tenant}/reports/sales', [OwnerReportController::class, 'sales'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:service_orders.view'])
    ->name('owner.reports.sales.index');

Route::get('/owner/{tenant}/reports/sales/export', [OwnerReportController::class, 'exportSales'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:service_orders.view'])
    ->name('owner.reports.sales.export');

Route::get('/owner/{tenant}/reports/spareparts', [OwnerReportController::class, 'spareparts'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:spareparts.view'])
    ->name('owner.reports.spareparts.index');

Route::get('/owner/{tenant}/reports/expenses', [OwnerReportController::class, 'expenses'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:expenses.view'])
    ->name('owner.reports.expenses.index');

Route::get('/owner/{tenant}/reports/customers', [OwnerReportController::class, 'customers'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:customers.view'])
    ->name('owner.reports.customers.index');

Route::get('/owner/{tenant}/reports/profit-loss', [OwnerReportController::class, 'profitLoss'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:expenses.view'])
    ->name('owner.reports.profit-loss.index');

Route::get('/owner/{tenant}/reports/ai-monthly', [OwnerReportController::class, 'aiMonthly'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:service_orders.view'])
    ->name('owner.reports.ai-monthly.index');

Route::get('/owner/{tenant}/invoices', [OwnerInvoiceController::class, 'index'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:invoices.view'])
    ->name('owner.invoices.index');

Route::get('/owner/{tenant}/invoice-payments', [OwnerInvoiceController::class, 'payments'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:invoice_payments.view'])
    ->name('owner.invoice-payments.index');

Route::get('/owner/{tenant}/receivables', [OwnerInvoiceController::class, 'receivables'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:receivables.view'])
    ->name('owner.receivables.index');

Route::get('/owner/{tenant}/vehicles', [OwnerVehicleController::class, 'index'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:service_orders.view'])
    ->name('owner.vehicles.index');

Route::get('/owner/{tenant}/users', [OwnerUserController::class, 'index'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:users.manage'])
    ->name('owner.users.index');

Route::post('/owner/{tenant}/workshops', [OwnerWorkshopController::class, 'store'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:users.manage'])
    ->name('owner.workshops.store');

Route::post('/owner/{tenant}/workshops/switch-active', [OwnerWorkshopController::class, 'switchActiveWorkshop'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:owner.dashboard.view'])
    ->name('owner.workshops.switch-active');

Route::post('/owner/{tenant}/customers', [OwnerCustomerController::class, 'store'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:customers.manage'])
    ->name('owner.customers.store');

Route::post('/owner/{tenant}/suppliers', [OwnerSupplierController::class, 'store'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:suppliers.manage'])
    ->name('owner.suppliers.store');

Route::post('/owner/{tenant}/warehouses', [OwnerWarehouseController::class, 'store'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:warehouses.manage'])
    ->name('owner.warehouses.store');

Route::post('/owner/{tenant}/spareparts', [OwnerSparePartController::class, 'store'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:spareparts.manage'])
    ->name('owner.spareparts.store');

Route::post('/owner/{tenant}/sparepart-categories', [OwnerSparePartCategoryController::class, 'store'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:sparepart_categories.manage'])
    ->name('owner.sparepart-categories.store');

Route::post('/owner/{tenant}/sparepart-units', [OwnerSparePartUnitController::class, 'store'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:sparepart_units.manage'])
    ->name('owner.sparepart-units.store');

Route::post('/owner/{tenant}/orders', [OwnerServiceOrderController::class, 'store'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:service_orders.manage'])
    ->name('owner.orders.store');

Route::post('/owner/{tenant}/orders/{order}/estimates', [OwnerServiceOrderEstimateController::class, 'store'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:service_orders.manage'])
    ->name('owner.orders.estimates.store');

Route::post('/owner/{tenant}/orders/{order}/estimates/ai-draft', [OwnerServiceOrderEstimateController::class, 'generateAiDraft'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:service_orders.manage'])
    ->name('owner.orders.estimates.ai-draft');

Route::post('/owner/{tenant}/orders/{order}/diagnosis/ai-draft', [OwnerServiceOrderEstimateController::class, 'generateDiagnosisDraft'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:service_orders.manage'])
    ->name('owner.orders.diagnosis.ai-draft');

Route::post('/owner/{tenant}/bookings', [OwnerBookingController::class, 'store'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:bookings.manage'])
    ->name('owner.bookings.store');

Route::post('/owner/{tenant}/expenses', [OwnerExpenseController::class, 'store'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:expenses.manage'])
    ->name('owner.expenses.store');

Route::post('/owner/{tenant}/expense-categories', [OwnerExpenseCategoryController::class, 'store'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:expense_categories.manage'])
    ->name('owner.expense-categories.store');

Route::post('/owner/{tenant}/invoices/{invoice}/payments', [OwnerInvoiceController::class, 'storePayment'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:invoice_payments.manage'])
    ->name('owner.invoices.payments.store');

Route::patch('/owner/{tenant}/orders/{order}/status', [OwnerServiceOrderController::class, 'updateStatus'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:service_orders.manage'])
    ->name('owner.orders.update-status');

Route::patch('/owner/{tenant}/invoices/{invoice}/due-date', [OwnerInvoiceController::class, 'updateDueDate'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:receivables.manage'])
    ->name('owner.invoices.due-date.update');

Route::patch('/owner/{tenant}/invoices/{invoice}/reminder', [OwnerInvoiceController::class, 'markReminder'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:receivables.manage'])
    ->name('owner.invoices.reminder.mark');

Route::patch('/owner/{tenant}/bookings/{booking}/status', [OwnerBookingController::class, 'updateStatus'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:bookings.manage'])
    ->name('owner.bookings.update-status');

Route::patch('/owner/{tenant}/bookings/builder', [OwnerBookingPageBuilderController::class, 'update'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:bookings.manage'])
    ->name('owner.bookings.builder.update');

Route::post('/owner/{tenant}/vehicles', [OwnerVehicleController::class, 'store'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:service_orders.manage'])
    ->name('owner.vehicles.store');

Route::post('/owner/{tenant}/vehicles/sync', [OwnerVehicleController::class, 'sync'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:service_orders.manage'])
    ->name('owner.vehicles.sync');

Route::post('/owner/{tenant}/users', [OwnerUserController::class, 'store'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:users.manage'])
    ->name('owner.users.store');

Route::post('/owner/{tenant}/workshops/switch-plan', [OwnerWorkshopController::class, 'switchPlan'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:users.manage'])
    ->name('owner.workshops.switch-plan');

Route::post('/owner/{tenant}/workshops/continue-pending-payment', [OwnerWorkshopController::class, 'continuePendingPayment'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:users.manage'])
    ->name('owner.workshops.continue-pending-payment');

Route::post('/owner/{tenant}/workshops/confirm-midtrans-payment', [OwnerWorkshopController::class, 'confirmMidtransPayment'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:users.manage'])
    ->name('owner.workshops.confirm-midtrans-payment');

Route::patch('/owner/{tenant}/workshops/{workshop}', [OwnerWorkshopController::class, 'update'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:users.manage'])
    ->name('owner.workshops.update');

Route::patch('/owner/{tenant}/customers/{customer}', [OwnerCustomerController::class, 'update'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:customers.manage'])
    ->name('owner.customers.update');

Route::patch('/owner/{tenant}/suppliers/{supplier}', [OwnerSupplierController::class, 'update'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:suppliers.manage'])
    ->name('owner.suppliers.update');

Route::patch('/owner/{tenant}/warehouses/{warehouse}', [OwnerWarehouseController::class, 'update'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:warehouses.manage'])
    ->name('owner.warehouses.update');

Route::patch('/owner/{tenant}/spareparts/{sparepart}', [OwnerSparePartController::class, 'update'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:spareparts.manage'])
    ->name('owner.spareparts.update');

Route::patch('/owner/{tenant}/sparepart-categories/{sparepart_category}', [OwnerSparePartCategoryController::class, 'update'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:sparepart_categories.manage'])
    ->name('owner.sparepart-categories.update');

Route::patch('/owner/{tenant}/sparepart-units/{sparepart_unit}', [OwnerSparePartUnitController::class, 'update'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:sparepart_units.manage'])
    ->name('owner.sparepart-units.update');

Route::patch('/owner/{tenant}/users/{user}', [OwnerUserController::class, 'update'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:users.manage'])
    ->name('owner.users.update');

Route::patch('/owner/{tenant}/expenses/{expense}', [OwnerExpenseController::class, 'update'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:expenses.manage'])
    ->name('owner.expenses.update');

Route::patch('/owner/{tenant}/expense-categories/{expense_category}', [OwnerExpenseCategoryController::class, 'update'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:expense_categories.manage'])
    ->name('owner.expense-categories.update');

Route::patch('/owner/{tenant}/vehicles/{vehicle}', [OwnerVehicleController::class, 'update'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:service_orders.manage'])
    ->name('owner.vehicles.update');

Route::delete('/owner/{tenant}/workshops/{workshop}', [OwnerWorkshopController::class, 'destroy'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:users.manage'])
    ->name('owner.workshops.destroy');

Route::delete('/owner/{tenant}/customers/{customer}', [OwnerCustomerController::class, 'destroy'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:customers.manage'])
    ->name('owner.customers.destroy');

Route::delete('/owner/{tenant}/suppliers/{supplier}', [OwnerSupplierController::class, 'destroy'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:suppliers.manage'])
    ->name('owner.suppliers.destroy');

Route::delete('/owner/{tenant}/warehouses/{warehouse}', [OwnerWarehouseController::class, 'destroy'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:warehouses.manage'])
    ->name('owner.warehouses.destroy');

Route::delete('/owner/{tenant}/spareparts/{sparepart}', [OwnerSparePartController::class, 'destroy'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:spareparts.manage'])
    ->name('owner.spareparts.destroy');

Route::delete('/owner/{tenant}/sparepart-categories/{sparepart_category}', [OwnerSparePartCategoryController::class, 'destroy'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:sparepart_categories.manage'])
    ->name('owner.sparepart-categories.destroy');

Route::delete('/owner/{tenant}/sparepart-units/{sparepart_unit}', [OwnerSparePartUnitController::class, 'destroy'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:sparepart_units.manage'])
    ->name('owner.sparepart-units.destroy');

Route::delete('/owner/{tenant}/users/{user}', [OwnerUserController::class, 'destroy'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:users.manage'])
    ->name('owner.users.destroy');

Route::delete('/owner/{tenant}/expenses/{expense}', [OwnerExpenseController::class, 'destroy'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:expenses.manage'])
    ->name('owner.expenses.destroy');

Route::delete('/owner/{tenant}/expense-categories/{expense_category}', [OwnerExpenseCategoryController::class, 'destroy'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:expense_categories.manage'])
    ->name('owner.expense-categories.destroy');

Route::delete('/owner/{tenant}/vehicles/{vehicle}', [OwnerVehicleController::class, 'destroy'])
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant', 'owner.menu.access', 'can:service_orders.manage'])
    ->name('owner.vehicles.destroy');

Route::get('/owner/{path}', [OwnerSubdomainRedirectController::class, 'redirect'])
    ->where('path', '.*')
    ->middleware(['auth', 'throttle:web', 'tenant.context:tenant'])
    ->name('owner.subdomain.redirect');

Route::post('/logout', [SessionController::class, 'logout'])
    ->middleware(['auth', 'throttle:web'])
    ->name('logout');


