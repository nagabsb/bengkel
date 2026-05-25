<?php

namespace App\Services\Owner;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\ServiceOrder;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OwnerInvoiceService
{
    public function __construct(
        private readonly OwnerMenuService $ownerMenuService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildPageData(
        Request $request,
        string $tenantId,
        string $activeWorkshopId,
        TenantPlanResolver $planResolver,
        ?Authenticatable $user,
        string $activeTab,
    ): array {
        $search = trim((string) $request->query('finance_search', ''));
        $status = trim((string) $request->query('finance_status', ''));
        $method = trim((string) $request->query('finance_method', ''));
        $state = trim((string) $request->query('finance_state', ''));
        $defaultSortBy = match ($activeTab) {
            'payments' => 'paid_at',
            'receivables' => 'due_date',
            default => 'invoice_date',
        };
        $sortBy = $this->resolveSortBy($activeTab, (string) $request->query('finance_sort_by', $defaultSortBy));
        $sortDir = $this->resolveSortDirection((string) $request->query('finance_sort_dir', 'desc'));
        $perPage = $this->resolvePerPage((int) $request->query('finance_per_page', 10));
        $cursor = trim((string) $request->query('finance_cursor', ''));

        $package = $planResolver->forTenantId($tenantId);
        $planId = data_get($package, 'plan.id');

        $menuTree = $this->ownerMenuService->buildOwnerMenuTree(
            $tenantId,
            $planId,
            hasPlanMenuTable: Schema::hasTable('plan_menu'),
        );

        $menuItems = $this->ownerMenuService->buildSidebarMenuItems(
            $menuTree,
            $tenantId,
            $user,
            $this->resolveCurrentUri($request),
        );

        $records = [
            'mode' => 'cursor',
            'data' => [],
            'per_page' => $perPage,
            'total' => 0,
            'from' => 0,
            'to' => 0,
            'current_cursor' => null,
            'next_cursor' => null,
            'prev_cursor' => null,
            'has_more_pages' => false,
        ];

        $summary = [
            'invoice_total' => 0,
            'invoice_paid' => 0,
            'invoice_unpaid' => 0,
            'invoice_partial' => 0,
            'overdue_count' => 0,
            'due_soon_count' => 0,
            'total_amount' => 0,
            'paid_amount' => 0,
            'remaining_amount' => 0,
            'payments_total' => 0,
            'payments_count' => 0,
        ];

        if (Schema::hasTable('invoices')) {
            $summary = $this->resolveSummary($tenantId, $activeWorkshopId);
            $records = $this->resolveRecords(
                $activeTab,
                $tenantId,
                $activeWorkshopId,
                $search,
                $status,
                $method,
                $state,
                $sortBy,
                $sortDir,
                $perPage,
                $cursor,
            );
        }

        return [
            'tenantId' => $tenantId,
            'package' => $package,
            'menuItems' => $menuItems,
            'activeTab' => $activeTab,
            'records' => $records,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'method' => $method,
                'state' => $state,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
                'per_page' => $perPage,
                'cursor' => $records['current_cursor'],
            ],
            'summary' => $summary,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createPayment(
        string $tenantId,
        string $activeWorkshopId,
        string $invoiceId,
        array $validated,
        ?Authenticatable $actor = null,
    ): void {
        $this->assertInvoiceTablesReady();

        DB::transaction(function () use ($tenantId, $activeWorkshopId, $invoiceId, $validated, $actor): void {
            $invoice = $this->findTenantInvoiceOrFail(
                $tenantId,
                $activeWorkshopId,
                $invoiceId,
                'invoice_payment',
                lockForUpdate: true,
            );

            $amount = max((int) ($validated['amount'] ?? 0), 0);
            if ($amount < 1) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal pembayaran minimal Rp 1.',
                ]);
            }

            $remainingAmount = max((int) ($invoice->remaining_amount ?? 0), 0);
            if ($remainingAmount < 1) {
                throw ValidationException::withMessages([
                    'invoice_payment' => 'Invoice ini sudah lunas.',
                ]);
            }

            if ($amount > $remainingAmount) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal pembayaran melebihi sisa piutang invoice.',
                ]);
            }

            InvoicePayment::query()->create([
                'tenant_id' => $tenantId,
                'workshop_id' => (string) ($invoice->workshop_id ?? ''),
                'invoice_id' => (string) $invoice->id,
                'paid_at' => (string) ($validated['paid_at'] ?? now()->toDateString()),
                'amount' => $amount,
                'method' => trim((string) ($validated['method'] ?? 'cash')),
                'reference_number' => $this->normalizeNullableString($validated['reference_number'] ?? null),
                'notes' => $this->normalizeNullableString($validated['notes'] ?? null),
                'created_by_user_id' => $this->resolveActorUserId($actor),
            ]);

            $nextPaidAmount = max((int) ($invoice->paid_amount ?? 0), 0) + $amount;
            $this->refreshInvoiceFinancials($invoice, $nextPaidAmount);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateDueDate(
        string $tenantId,
        string $activeWorkshopId,
        string $invoiceId,
        array $validated,
    ): void {
        $this->assertInvoiceTablesReady();

        DB::transaction(function () use ($tenantId, $activeWorkshopId, $invoiceId, $validated): void {
            $invoice = $this->findTenantInvoiceOrFail(
                $tenantId,
                $activeWorkshopId,
                $invoiceId,
                'invoice_due_date',
                lockForUpdate: true,
            );

            $invoice->forceFill([
                'due_date' => (string) ($validated['due_date'] ?? now()->toDateString()),
            ])->save();
        });
    }

    public function markReminderSent(string $tenantId, string $activeWorkshopId, string $invoiceId): void
    {
        $this->assertInvoiceTablesReady();

        DB::transaction(function () use ($tenantId, $activeWorkshopId, $invoiceId): void {
            $invoice = $this->findTenantInvoiceOrFail(
                $tenantId,
                $activeWorkshopId,
                $invoiceId,
                'invoice_reminder',
                lockForUpdate: true,
            );

            $invoice->forceFill([
                'reminder_sent_at' => now(),
            ])->save();
        });
    }

    public function syncInvoiceFromServiceOrder(
        string $tenantId,
        string $activeWorkshopId,
        ServiceOrder $order,
        ?Authenticatable $actor = null,
    ): ?Invoice {
        if (! Schema::hasTable('invoices')) {
            return null;
        }

        $orderId = trim((string) $order->id);
        if ($orderId === '') {
            return null;
        }

        if (strtolower(trim((string) ($order->status ?? ''))) !== 'done') {
            return null;
        }

        return DB::transaction(function () use ($tenantId, $activeWorkshopId, $orderId, $actor): Invoice {
            $orderWithDetails = ServiceOrder::query()
                ->with(['customer:id,name,phone,workshop_id'])
                ->where('tenant_id', $tenantId)
                ->where('id', $orderId)
                ->lockForUpdate()
                ->first();

            if (! $orderWithDetails) {
                throw ValidationException::withMessages([
                    'invoice_sync' => 'Data servis tidak ditemukan saat sinkron invoice.',
                ]);
            }

            $serviceDate = $orderWithDetails->service_date instanceof Carbon
                ? $orderWithDetails->service_date->toDateString()
                : now()->toDateString();
            $invoiceDate = $orderWithDetails->completed_at instanceof Carbon
                ? $orderWithDetails->completed_at->toDateString()
                : $serviceDate;

            $totalAmount = max((int) ($orderWithDetails->total_amount ?? 0), 0);
            $serviceFee = max((int) ($orderWithDetails->service_fee ?? 0), 0);
            if ($totalAmount < $serviceFee) {
                $totalAmount = $serviceFee;
            }

            $invoice = Invoice::query()
                ->where('tenant_id', $tenantId)
                ->where('service_order_id', $orderId)
                ->lockForUpdate()
                ->first();

            $isNewInvoice = ! $invoice;
            if (! $invoice) {
                $invoice = new Invoice();
                $invoice->forceFill([
                    'tenant_id' => $tenantId,
                    'service_order_id' => $orderId,
                    'code' => $this->generateInvoiceCode(),
                    'paid_amount' => 0,
                    'remaining_amount' => $totalAmount,
                    'status' => 'unpaid',
                    'created_by_user_id' => $this->resolveActorUserId($actor),
                ]);
            }

            $customerName = trim((string) ($orderWithDetails->customer?->name ?? ''));
            $customerPhone = trim((string) ($orderWithDetails->customer?->phone ?? ''));
            $orderWorkshopId = trim((string) ($orderWithDetails->customer?->workshop_id ?? ''));
            $resolvedWorkshopId = $orderWorkshopId !== '' ? $orderWorkshopId : trim($activeWorkshopId);

            if (OwnerWorkshopSwitcherService::isAllWorkshopsId($resolvedWorkshopId)) {
                $resolvedWorkshopId = $orderWorkshopId;
            }

            $invoice->forceFill([
                'workshop_id' => $resolvedWorkshopId !== '' ? $resolvedWorkshopId : null,
                'customer_id' => $orderWithDetails->customer_id,
                'invoice_date' => $invoiceDate,
                'due_date' => $invoice->due_date instanceof Carbon
                    ? $invoice->due_date->toDateString()
                    : Carbon::parse($invoiceDate)->addDays(7)->toDateString(),
                'total_amount' => $totalAmount,
                'customer_name' => $customerName !== '' ? $customerName : null,
                'customer_phone' => $customerPhone !== '' ? $customerPhone : null,
                'notes' => $this->normalizeNullableString($invoice->notes),
            ]);

            if ($isNewInvoice) {
                $invoice->created_by_user_id = $this->resolveActorUserId($actor);
            }

            $invoice->save();

            $this->refreshInvoiceFinancials($invoice, max((int) ($invoice->paid_amount ?? 0), 0), false);

            return $invoice;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveSummary(string $tenantId, string $activeWorkshopId): array
    {
        $invoiceBaseQuery = Invoice::query();
        $this->applyInvoiceScope($invoiceBaseQuery, $tenantId, $activeWorkshopId);

        $today = now()->toDateString();
        $dueSoonDate = now()->addDays(3)->toDateString();

        $invoiceTotal = (int) (clone $invoiceBaseQuery)->count();

        return [
            'invoice_total' => $invoiceTotal,
            'invoice_paid' => (int) (clone $invoiceBaseQuery)->where('status', 'paid')->count(),
            'invoice_unpaid' => (int) (clone $invoiceBaseQuery)->where('status', 'unpaid')->count(),
            'invoice_partial' => (int) (clone $invoiceBaseQuery)->where('status', 'partial')->count(),
            'overdue_count' => (int) (clone $invoiceBaseQuery)
                ->where('remaining_amount', '>', 0)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $today)
                ->count(),
            'due_soon_count' => (int) (clone $invoiceBaseQuery)
                ->where('remaining_amount', '>', 0)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '>=', $today)
                ->whereDate('due_date', '<=', $dueSoonDate)
                ->count(),
            'total_amount' => (int) ((clone $invoiceBaseQuery)->sum('total_amount') ?? 0),
            'paid_amount' => (int) ((clone $invoiceBaseQuery)->sum('paid_amount') ?? 0),
            'remaining_amount' => (int) ((clone $invoiceBaseQuery)->sum('remaining_amount') ?? 0),
            'payments_total' => $this->resolvePaymentTotal($tenantId, $activeWorkshopId),
            'payments_count' => $this->resolvePaymentCount($tenantId, $activeWorkshopId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveRecords(
        string $activeTab,
        string $tenantId,
        string $activeWorkshopId,
        string $search,
        string $status,
        string $method,
        string $state,
        string $sortBy,
        string $sortDir,
        int $perPage,
        string $cursor,
    ): array {
        if ($activeTab === 'payments') {
            return $this->resolvePaymentRecords(
                $tenantId,
                $activeWorkshopId,
                $search,
                $method,
                $sortBy,
                $sortDir,
                $perPage,
                $cursor,
            );
        }

        if ($activeTab === 'receivables') {
            return $this->resolveReceivableRecords(
                $tenantId,
                $activeWorkshopId,
                $search,
                $state,
                $sortBy,
                $sortDir,
                $perPage,
                $cursor,
            );
        }

        return $this->resolveInvoiceRecords(
            $tenantId,
            $activeWorkshopId,
            $search,
            $status,
            $sortBy,
            $sortDir,
            $perPage,
            $cursor,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveInvoiceRecords(
        string $tenantId,
        string $activeWorkshopId,
        string $search,
        string $status,
        string $sortBy,
        string $sortDir,
        int $perPage,
        string $cursor,
    ): array {
        $query = Invoice::query()
            ->with([
                'serviceOrder:id,code',
                'workshop:id,name,code',
            ]);

        $this->applyInvoiceScope($query, $tenantId, $activeWorkshopId);

        $query
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%")
                        ->orWhereHas('serviceOrder', function (Builder $serviceOrderQuery) use ($search): void {
                            $serviceOrderQuery->where('code', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status !== '', function (Builder $builder) use ($status): void {
                $builder->where('status', $status);
            });

        $sortableColumn = [
            'code' => 'invoices.code',
            'invoice_date' => 'invoices.invoice_date',
            'due_date' => 'invoices.due_date',
            'total_amount' => 'invoices.total_amount',
            'remaining_amount' => 'invoices.remaining_amount',
            'status' => 'invoices.status',
        ][$sortBy] ?? 'invoices.invoice_date';

        $total = (int) (clone $query)->count();
        $paginator = $this->cursorPaginateWithFallback(
            (clone $query)
                ->orderBy($sortableColumn, $sortDir)
                ->orderBy('invoices.id', $sortDir),
            $perPage,
            [
                'invoices.id',
                'invoices.workshop_id',
                'invoices.service_order_id',
                'invoices.code',
                'invoices.invoice_date',
                'invoices.due_date',
                'invoices.status',
                'invoices.total_amount',
                'invoices.paid_amount',
                'invoices.remaining_amount',
                'invoices.last_paid_at',
                'invoices.reminder_sent_at',
                'invoices.customer_name',
                'invoices.customer_phone',
                'invoices.notes',
                'invoices.created_at',
                'invoices.updated_at',
            ],
            $cursor,
            'finance_cursor',
            'finance_cursor_fallback',
        );

        $today = now()->toDateString();
        $rows = collect($paginator->items())
            ->map(function (Invoice $invoice) use ($today): array {
                $dueDate = $invoice->due_date instanceof Carbon
                    ? $invoice->due_date->toDateString()
                    : null;
                $isOverdue = $dueDate !== null
                    && $dueDate < $today
                    && max((int) ($invoice->remaining_amount ?? 0), 0) > 0;

                return [
                    'id' => (string) $invoice->id,
                    'workshop_id' => (string) ($invoice->workshop_id ?? ''),
                    'workshop_name' => trim((string) ($invoice->workshop?->name ?? '')),
                    'workshop_code' => trim((string) ($invoice->workshop?->code ?? '')),
                    'service_order_id' => (string) ($invoice->service_order_id ?? ''),
                    'service_order_code' => trim((string) ($invoice->serviceOrder?->code ?? '')),
                    'code' => (string) ($invoice->code ?? ''),
                    'invoice_date' => $invoice->invoice_date,
                    'due_date' => $invoice->due_date,
                    'status' => (string) ($invoice->status ?? 'unpaid'),
                    'status_label' => $this->resolveInvoiceStatusLabel((string) ($invoice->status ?? 'unpaid')),
                    'total_amount' => max((int) ($invoice->total_amount ?? 0), 0),
                    'paid_amount' => max((int) ($invoice->paid_amount ?? 0), 0),
                    'remaining_amount' => max((int) ($invoice->remaining_amount ?? 0), 0),
                    'last_paid_at' => $invoice->last_paid_at,
                    'reminder_sent_at' => $invoice->reminder_sent_at,
                    'customer_name' => (string) ($invoice->customer_name ?? '-'),
                    'customer_phone' => (string) ($invoice->customer_phone ?? ''),
                    'notes' => (string) ($invoice->notes ?? ''),
                    'is_overdue' => $isOverdue,
                    'created_at' => $invoice->created_at,
                    'updated_at' => $invoice->updated_at,
                ];
            })
            ->values();

        return [
            'mode' => 'cursor',
            'data' => $rows->all(),
            'per_page' => $paginator->perPage(),
            'total' => $total,
            'from' => $rows->isEmpty() ? 0 : 1,
            'to' => $rows->count(),
            'current_cursor' => $paginator->cursor()?->encode(),
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'prev_cursor' => $paginator->previousCursor()?->encode(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePaymentRecords(
        string $tenantId,
        string $activeWorkshopId,
        string $search,
        string $method,
        string $sortBy,
        string $sortDir,
        int $perPage,
        string $cursor,
    ): array {
        if (! Schema::hasTable('invoice_payments')) {
            return [
                'mode' => 'cursor',
                'data' => [],
                'per_page' => $perPage,
                'total' => 0,
                'from' => 0,
                'to' => 0,
                'current_cursor' => null,
                'next_cursor' => null,
                'prev_cursor' => null,
                'has_more_pages' => false,
            ];
        }

        $query = InvoicePayment::query()
            ->with([
                'invoice:id,code,status,total_amount,paid_amount,remaining_amount',
                'workshop:id,name,code',
            ]);

        $this->applyPaymentScope($query, $tenantId, $activeWorkshopId);

        $query
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('reference_number', 'like', "%{$search}%")
                        ->orWhere('method', 'like', "%{$search}%")
                        ->orWhereHas('invoice', function (Builder $invoiceQuery) use ($search): void {
                            $invoiceQuery
                                ->where('code', 'like', "%{$search}%")
                                ->orWhere('customer_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($method !== '', function (Builder $builder) use ($method): void {
                $builder->where('method', $method);
            });

        $sortableColumn = [
            'paid_at' => 'invoice_payments.paid_at',
            'amount' => 'invoice_payments.amount',
            'method' => 'invoice_payments.method',
            'created_at' => 'invoice_payments.created_at',
        ][$sortBy] ?? 'invoice_payments.paid_at';

        $total = (int) (clone $query)->count();
        $paginator = $this->cursorPaginateWithFallback(
            (clone $query)
                ->orderBy($sortableColumn, $sortDir)
                ->orderBy('invoice_payments.id', $sortDir),
            $perPage,
            [
                'invoice_payments.id',
                'invoice_payments.invoice_id',
                'invoice_payments.workshop_id',
                'invoice_payments.paid_at',
                'invoice_payments.amount',
                'invoice_payments.method',
                'invoice_payments.reference_number',
                'invoice_payments.notes',
                'invoice_payments.created_at',
            ],
            $cursor,
            'finance_cursor',
            'finance_cursor_fallback',
        );

        $rows = collect($paginator->items())
            ->map(function (InvoicePayment $payment): array {
                return [
                    'id' => (string) $payment->id,
                    'invoice_id' => (string) ($payment->invoice_id ?? ''),
                    'invoice_code' => trim((string) ($payment->invoice?->code ?? '')),
                    'invoice_status' => (string) ($payment->invoice?->status ?? 'unpaid'),
                    'workshop_name' => trim((string) ($payment->workshop?->name ?? '')),
                    'workshop_code' => trim((string) ($payment->workshop?->code ?? '')),
                    'paid_at' => $payment->paid_at,
                    'amount' => max((int) ($payment->amount ?? 0), 0),
                    'method' => (string) ($payment->method ?? ''),
                    'reference_number' => (string) ($payment->reference_number ?? ''),
                    'notes' => (string) ($payment->notes ?? ''),
                    'created_at' => $payment->created_at,
                ];
            })
            ->values();

        return [
            'mode' => 'cursor',
            'data' => $rows->all(),
            'per_page' => $paginator->perPage(),
            'total' => $total,
            'from' => $rows->isEmpty() ? 0 : 1,
            'to' => $rows->count(),
            'current_cursor' => $paginator->cursor()?->encode(),
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'prev_cursor' => $paginator->previousCursor()?->encode(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveReceivableRecords(
        string $tenantId,
        string $activeWorkshopId,
        string $search,
        string $state,
        string $sortBy,
        string $sortDir,
        int $perPage,
        string $cursor,
    ): array {
        $query = Invoice::query()
            ->with([
                'serviceOrder:id,code',
                'workshop:id,name,code',
            ])
            ->where('remaining_amount', '>', 0);

        $this->applyInvoiceScope($query, $tenantId, $activeWorkshopId);

        $today = now()->toDateString();
        $dueSoonDate = now()->addDays(3)->toDateString();

        $query
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhereHas('serviceOrder', function (Builder $serviceOrderQuery) use ($search): void {
                            $serviceOrderQuery->where('code', 'like', "%{$search}%");
                        });
                });
            })
            ->when($state === 'overdue', function (Builder $builder) use ($today): void {
                $builder->whereNotNull('due_date')
                    ->whereDate('due_date', '<', $today);
            })
            ->when($state === 'due_soon', function (Builder $builder) use ($today, $dueSoonDate): void {
                $builder->whereNotNull('due_date')
                    ->whereDate('due_date', '>=', $today)
                    ->whereDate('due_date', '<=', $dueSoonDate);
            });

        $sortableColumn = [
            'due_date' => 'invoices.due_date',
            'invoice_date' => 'invoices.invoice_date',
            'remaining_amount' => 'invoices.remaining_amount',
            'customer_name' => 'invoices.customer_name',
        ][$sortBy] ?? 'invoices.due_date';

        $total = (int) (clone $query)->count();
        $paginator = $this->cursorPaginateWithFallback(
            (clone $query)
                ->orderBy($sortableColumn, $sortDir)
                ->orderBy('invoices.id', $sortDir),
            $perPage,
            [
                'invoices.id',
                'invoices.workshop_id',
                'invoices.service_order_id',
                'invoices.code',
                'invoices.invoice_date',
                'invoices.due_date',
                'invoices.status',
                'invoices.total_amount',
                'invoices.paid_amount',
                'invoices.remaining_amount',
                'invoices.reminder_sent_at',
                'invoices.customer_name',
                'invoices.customer_phone',
                'invoices.created_at',
            ],
            $cursor,
            'finance_cursor',
            'finance_cursor_fallback',
        );

        $rows = collect($paginator->items())
            ->map(function (Invoice $invoice) use ($today): array {
                $dueDate = $invoice->due_date instanceof Carbon
                    ? $invoice->due_date->toDateString()
                    : null;

                $isOverdue = $dueDate !== null && $dueDate < $today;

                return [
                    'id' => (string) $invoice->id,
                    'code' => (string) ($invoice->code ?? ''),
                    'service_order_code' => trim((string) ($invoice->serviceOrder?->code ?? '')),
                    'workshop_name' => trim((string) ($invoice->workshop?->name ?? '')),
                    'workshop_code' => trim((string) ($invoice->workshop?->code ?? '')),
                    'customer_name' => (string) ($invoice->customer_name ?? '-'),
                    'customer_phone' => (string) ($invoice->customer_phone ?? ''),
                    'invoice_date' => $invoice->invoice_date,
                    'due_date' => $invoice->due_date,
                    'status' => (string) ($invoice->status ?? 'unpaid'),
                    'status_label' => $this->resolveInvoiceStatusLabel((string) ($invoice->status ?? 'unpaid')),
                    'total_amount' => max((int) ($invoice->total_amount ?? 0), 0),
                    'paid_amount' => max((int) ($invoice->paid_amount ?? 0), 0),
                    'remaining_amount' => max((int) ($invoice->remaining_amount ?? 0), 0),
                    'reminder_sent_at' => $invoice->reminder_sent_at,
                    'is_overdue' => $isOverdue,
                    'created_at' => $invoice->created_at,
                ];
            })
            ->values();

        return [
            'mode' => 'cursor',
            'data' => $rows->all(),
            'per_page' => $paginator->perPage(),
            'total' => $total,
            'from' => $rows->isEmpty() ? 0 : 1,
            'to' => $rows->count(),
            'current_cursor' => $paginator->cursor()?->encode(),
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'prev_cursor' => $paginator->previousCursor()?->encode(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }

    private function resolvePaymentTotal(string $tenantId, string $activeWorkshopId): int
    {
        if (! Schema::hasTable('invoice_payments')) {
            return 0;
        }

        $query = InvoicePayment::query();
        $this->applyPaymentScope($query, $tenantId, $activeWorkshopId);

        return (int) ($query->sum('amount') ?? 0);
    }

    private function resolvePaymentCount(string $tenantId, string $activeWorkshopId): int
    {
        if (! Schema::hasTable('invoice_payments')) {
            return 0;
        }

        $query = InvoicePayment::query();
        $this->applyPaymentScope($query, $tenantId, $activeWorkshopId);

        return (int) $query->count();
    }

    private function applyInvoiceScope(Builder $query, string $tenantId, string $activeWorkshopId): void
    {
        $query->where('tenant_id', $tenantId);

        if ($this->shouldApplyWorkshopScope($activeWorkshopId)) {
            $query->where('workshop_id', $activeWorkshopId);
        }
    }

    private function applyPaymentScope(Builder $query, string $tenantId, string $activeWorkshopId): void
    {
        $query->where('tenant_id', $tenantId);

        if ($this->shouldApplyWorkshopScope($activeWorkshopId)) {
            $query->where('workshop_id', $activeWorkshopId);
        }
    }

    private function refreshInvoiceFinancials(
        Invoice $invoice,
        int $paidAmount,
        bool $touchPaymentTimestamp = true,
    ): void
    {
        $totalAmount = max((int) ($invoice->total_amount ?? 0), 0);
        $normalizedPaidAmount = max($paidAmount, 0);
        if ($normalizedPaidAmount > $totalAmount) {
            $normalizedPaidAmount = $totalAmount;
        }

        $remainingAmount = max($totalAmount - $normalizedPaidAmount, 0);
        $status = 'unpaid';

        if ($remainingAmount === 0) {
            $status = 'paid';
        } elseif ($normalizedPaidAmount > 0) {
            $status = 'partial';
        }

        $lastPaidAt = null;
        if ($normalizedPaidAmount > 0) {
            $lastPaidAt = $touchPaymentTimestamp ? now() : $invoice->last_paid_at;
        }

        $invoice->forceFill([
            'paid_amount' => $normalizedPaidAmount,
            'remaining_amount' => $remainingAmount,
            'status' => $status,
            'last_paid_at' => $lastPaidAt,
        ])->save();
    }

    private function findTenantInvoiceOrFail(
        string $tenantId,
        string $activeWorkshopId,
        string $invoiceId,
        string $errorKey,
        bool $lockForUpdate = false,
    ): Invoice {
        $query = Invoice::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $invoiceId);

        if ($this->shouldApplyWorkshopScope($activeWorkshopId)) {
            $query->where('workshop_id', $activeWorkshopId);
        }

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $invoice = $query->first();
        if (! $invoice) {
            throw ValidationException::withMessages([
                $errorKey => 'Data invoice tidak ditemukan di cabang aktif.',
            ]);
        }

        return $invoice;
    }

    private function generateInvoiceCode(): string
    {
        $prefix = 'INV-'.now()->format('Ymd');

        for ($sequence = 1; $sequence <= 999; $sequence++) {
            $candidateCode = $prefix.'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

            $exists = Invoice::query()
                ->withoutGlobalScopes()
                ->where('code', $candidateCode)
                ->exists();

            if (! $exists) {
                return $candidateCode;
            }
        }

        return $prefix.'-'.Str::upper(Str::random(4));
    }

    private function resolveInvoiceStatusLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            'paid' => 'Lunas',
            'partial' => 'Sebagian',
            default => 'Belum Lunas',
        };
    }

    private function resolveSortBy(string $activeTab, string $sortBy): string
    {
        $allowed = match ($activeTab) {
            'payments' => ['paid_at', 'amount', 'method', 'created_at'],
            'receivables' => ['due_date', 'invoice_date', 'remaining_amount', 'customer_name'],
            default => ['code', 'invoice_date', 'due_date', 'total_amount', 'remaining_amount', 'status'],
        };

        return in_array($sortBy, $allowed, true)
            ? $sortBy
            : ($activeTab === 'payments' ? 'paid_at' : 'invoice_date');
    }

    private function resolveSortDirection(string $sortDirection): string
    {
        return strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';
    }

    private function resolvePerPage(int $perPage): int
    {
        return in_array($perPage, [10, 20, 50], true) ? $perPage : 10;
    }

    private function resolveActorUserId(?Authenticatable $actor): ?string
    {
        if (! $actor) {
            return null;
        }

        $actorId = trim((string) $actor->getAuthIdentifier());

        return $actorId !== '' ? $actorId : null;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function shouldApplyWorkshopScope(string $activeWorkshopId): bool
    {
        return ! OwnerWorkshopSwitcherService::isAllWorkshopsId($activeWorkshopId);
    }

    private function assertInvoiceTablesReady(): void
    {
        if (! Schema::hasTable('invoices') || ! Schema::hasTable('invoice_payments')) {
            throw ValidationException::withMessages([
                'invoice' => 'Modul invoice belum siap. Jalankan migrasi terbaru.',
            ]);
        }
    }

    private function resolveCurrentUri(Request $request): string
    {
        $path = '/'.ltrim((string) $request->path(), '/');
        $queryString = trim((string) $request->getQueryString());

        return $queryString !== '' ? $path.'?'.$queryString : $path;
    }

    private function cursorPaginateWithFallback(
        Builder $query,
        int $perPage,
        array $columns,
        string $cursor,
        string $cursorParameter,
        string $fallbackCursorParameter,
    ): CursorPaginator {
        $cursorValue = $cursor !== '' ? $cursor : null;

        try {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, $cursorParameter, $cursorValue)
                ->withQueryString();
        } catch (\Throwable) {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, $fallbackCursorParameter, null)
                ->withQueryString();
        }
    }
}
