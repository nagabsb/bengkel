<?php

namespace App\Services\Owner;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\CustomerVehicle;
use App\Models\Invoice;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEstimate;
use App\Models\ServiceOrderEstimateAiLog;
use App\Models\ServiceOrderEstimateItem;
use App\Models\ServiceOrderMechanic;
use App\Models\ServiceOrderSparePart;
use App\Models\SparePart;
use App\Models\TenantVehicleMaster;
use App\Models\User;
use App\Models\WarehouseSparePartStock;
use App\Models\Workshop;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Carbon;

class OwnerServiceOrderService
{
    public function __construct(
        private readonly OwnerMenuService $ownerMenuService,
        private readonly OwnerServiceOrderEstimateService $estimateService,
        private readonly OwnerInvoiceService $invoiceService,
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
    ): array {
        $orderSearch = trim((string) $request->query('order_search', ''));
        $orderSortBy = $this->resolveSortBy((string) $request->query('order_sort_by', 'service_date'));
        $orderSortDir = $this->resolveSortDirection((string) $request->query('order_sort_dir', 'desc'));
        $orderPerPage = $this->resolvePerPage((int) $request->query('order_per_page', 10));
        $orderCursor = trim((string) $request->query('order_cursor', ''));
        $completionSparePartSearch = trim((string) $request->query('completion_sparepart_search', ''));
        $completionSparePartCategory = trim((string) $request->query('completion_sparepart_category', ''));
        $completionSparePartCursor = trim((string) $request->query('completion_sparepart_cursor', ''));
        $completionSparePartPerPage = $this->resolveCompletionSparePartPerPage(
            (int) $request->query('completion_sparepart_per_page', 20),
        );

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

        $ordersPayload = [
            'mode' => 'cursor',
            'data' => [],
            'per_page' => $orderPerPage,
            'total' => 0,
            'from' => 0,
            'to' => 0,
            'current_cursor' => null,
            'next_cursor' => null,
            'prev_cursor' => null,
            'has_more_pages' => false,
        ];

        $orderSummary = [
            'total' => 0,
            'open' => 0,
            'in_progress' => 0,
            'done' => 0,
            'cancelled' => 0,
        ];

        $customerOptions = $this->resolveCustomerOptions($tenantId, $activeWorkshopId);
        $customerVehicleOptions = $this->resolveCustomerVehicleOptions($tenantId, $activeWorkshopId);
        $vehicleMasterOptions = $this->resolveTenantVehicleMasterOptions($tenantId);
        $mechanicOptions = $this->resolveMechanicOptions($tenantId, $activeWorkshopId);
        $completionSparePartOptions = $this->resolveCompletionSparePartOptions(
            $tenantId,
            $activeWorkshopId,
            $completionSparePartSearch,
            $completionSparePartCategory,
            $completionSparePartCursor,
            $completionSparePartPerPage,
        );
        $applyCustomerWorkshopScope = $this->shouldApplyCustomerWorkshopScope($tenantId, $activeWorkshopId);
        $hasStartedAtColumn = Schema::hasColumn('service_orders', 'started_at');
        $hasCompletedAtColumn = Schema::hasColumn('service_orders', 'completed_at');
        $hasCompletionNotesColumn = Schema::hasColumn('service_orders', 'completion_notes');
        $hasServiceFeeColumn = Schema::hasColumn('service_orders', 'service_fee');
        $hasTotalAmountColumn = Schema::hasColumn('service_orders', 'total_amount');
        $hasEstimateTables = Schema::hasTable('service_order_estimates')
            && Schema::hasTable('service_order_estimate_items');
        $hasServiceOrderAiLogsTable = Schema::hasTable('service_order_estimate_ai_logs');
        $hasInvoicesTable = Schema::hasTable('invoices');
        $canLoadMechanicDetails = Schema::hasTable('service_order_mechanics') && Schema::hasTable('users');
        $canLoadSparePartDetails = Schema::hasTable('service_order_spare_parts') && Schema::hasTable('spare_parts');

        if (Schema::hasTable('service_orders')) {
            $summaryQuery = ServiceOrder::query()
                ->where('tenant_id', $tenantId)
                ->when($applyCustomerWorkshopScope, function (Builder $query) use ($activeWorkshopId): void {
                    $query->whereHas('customer', function (Builder $customerQuery) use ($activeWorkshopId): void {
                        $customerQuery->where('workshop_id', $activeWorkshopId);
                    });
                });

            $totalOrders = (int) (clone $summaryQuery)->count();

            $orderSummary = [
                'total' => $totalOrders,
                'open' => (int) (clone $summaryQuery)->where('status', 'open')->count(),
                'in_progress' => (int) (clone $summaryQuery)->where('status', 'in_progress')->count(),
                'done' => (int) (clone $summaryQuery)->where('status', 'done')->count(),
                'cancelled' => (int) (clone $summaryQuery)->where('status', 'cancelled')->count(),
            ];

            $sortableColumn = [
                'code' => 'service_orders.code',
                'service_date' => 'service_orders.service_date',
                'status' => 'service_orders.status',
                'created_at' => 'service_orders.created_at',
            ][$orderSortBy] ?? 'service_orders.service_date';

            $orderRelations = [
                'customer:id,name,phone,workshop_id',
                'customer.workshop:id,name,code',
                'vehicle:id,brand,model,plate_number',
            ];

            if ($canLoadMechanicDetails) {
                $orderRelations[] = 'mechanics:id,service_order_id,user_id';
                $orderRelations[] = 'mechanics.mechanic:id,name';
            }

            if ($canLoadSparePartDetails) {
                $orderRelations[] = 'spareParts:id,service_order_id,spare_part_id,qty,unit_price,subtotal,notes';
                $orderRelations[] = 'spareParts.sparePart:id,name,category,unit';
            }

            if ($hasEstimateTables) {
                $orderRelations[] = 'latestEstimate';
                $orderRelations[] = 'latestEstimate.items:id,service_order_estimate_id,item_type,spare_part_id,label,unit_label,description,qty,unit_price,subtotal';
            }

            if ($hasInvoicesTable) {
                $orderRelations[] = 'invoice:id,tenant_id,service_order_id,code,status,total_amount,paid_amount,remaining_amount,due_date,last_paid_at';
            }

            $orderSelectColumns = [
                'service_orders.id',
                'service_orders.code',
                'service_orders.customer_id',
                'service_orders.customer_vehicle_id',
                'service_orders.service_date',
                'service_orders.status',
                'service_orders.complaint',
                'service_orders.vehicle_condition',
                'service_orders.estimated_days',
                'service_orders.estimated_finish_date',
                'service_orders.odometer',
                'service_orders.created_at',
                'service_orders.updated_at',
            ];

            if ($hasStartedAtColumn) {
                $orderSelectColumns[] = 'service_orders.started_at';
            }

            if ($hasCompletedAtColumn) {
                $orderSelectColumns[] = 'service_orders.completed_at';
            }

            if ($hasCompletionNotesColumn) {
                $orderSelectColumns[] = 'service_orders.completion_notes';
            }

            if ($hasServiceFeeColumn) {
                $orderSelectColumns[] = 'service_orders.service_fee';
            }

            if ($hasTotalAmountColumn) {
                $orderSelectColumns[] = 'service_orders.total_amount';
            }

            $ordersPaginator = $this->cursorPaginateWithFallback(
                ServiceOrder::query()
                    ->with($orderRelations)
                    ->where('tenant_id', $tenantId)
                    ->when($applyCustomerWorkshopScope, function (Builder $query) use ($activeWorkshopId): void {
                        $query->whereHas('customer', function (Builder $customerQuery) use ($activeWorkshopId): void {
                            $customerQuery->where('workshop_id', $activeWorkshopId);
                        });
                    })
                    ->when($orderSearch !== '', function (Builder $query) use ($orderSearch): void {
                        $query->where(function (Builder $nestedQuery) use ($orderSearch): void {
                            $nestedQuery
                                ->where('service_orders.code', 'like', "%{$orderSearch}%")
                                ->orWhere('service_orders.status', 'like', "%{$orderSearch}%")
                                ->orWhere('service_orders.complaint', 'like', "%{$orderSearch}%")
                                ->orWhereHas('customer', function (Builder $customerQuery) use ($orderSearch): void {
                                    $customerQuery
                                        ->where('name', 'like', "%{$orderSearch}%")
                                        ->orWhere('phone', 'like', "%{$orderSearch}%");
                                })
                                ->orWhereHas('vehicle', function (Builder $vehicleQuery) use ($orderSearch): void {
                                    $vehicleQuery
                                        ->where('brand', 'like', "%{$orderSearch}%")
                                        ->orWhere('model', 'like', "%{$orderSearch}%")
                                        ->orWhere('plate_number', 'like', "%{$orderSearch}%");
                                });
                        });
                    })
                    ->orderBy($sortableColumn, $orderSortDir)
                    ->orderBy('service_orders.id', $orderSortDir),
                $orderPerPage,
                $orderSelectColumns,
                $orderCursor,
            );

            $orderItems = collect($ordersPaginator->items());
            $latestDiagnosisByOrderId = collect();

            if ($hasServiceOrderAiLogsTable) {
                $orderIds = $orderItems
                    ->map(fn (ServiceOrder $serviceOrder): string => (string) $serviceOrder->id)
                    ->filter(fn (string $serviceOrderId): bool => trim($serviceOrderId) !== '')
                    ->values()
                    ->all();

                if (count($orderIds) > 0) {
                    $latestDiagnosisByOrderId = ServiceOrderEstimateAiLog::query()
                        ->where('tenant_id', $tenantId)
                        ->whereIn('service_order_id', $orderIds)
                        ->where('feature_key', 'symptom_diagnosis_v1')
                        ->where('status', 'success')
                        ->orderByDesc('created_at')
                        ->get([
                            'id',
                            'service_order_id',
                            'output_payload',
                            'created_at',
                        ])
                        ->groupBy('service_order_id')
                        ->map(fn ($logs) => $this->normalizeLatestDiagnosisPayload(
                            $logs->first() instanceof ServiceOrderEstimateAiLog
                                ? $logs->first()
                                : null,
                        ));
                }
            }

            $orderRows = $orderItems
                ->map(function (ServiceOrder $serviceOrder) use (
                    $canLoadMechanicDetails,
                    $canLoadSparePartDetails,
                    $hasStartedAtColumn,
                    $hasCompletedAtColumn,
                    $hasCompletionNotesColumn,
                    $hasServiceFeeColumn,
                    $hasTotalAmountColumn,
                    $hasEstimateTables,
                    $hasInvoicesTable,
                    $latestDiagnosisByOrderId,
                ): array {
                    $vehicleName = trim(implode(' ', array_filter([
                        (string) ($serviceOrder->vehicle?->brand ?? ''),
                        (string) ($serviceOrder->vehicle?->model ?? ''),
                    ])));
                    $workshopId = trim((string) ($serviceOrder->customer?->workshop_id ?? ''));
                    $workshopName = trim((string) ($serviceOrder->customer?->workshop?->name ?? ''));
                    $workshopCode = trim((string) ($serviceOrder->customer?->workshop?->code ?? ''));
                    $mechanicNames = collect();

                    if ($canLoadMechanicDetails) {
                        $mechanicNames = $serviceOrder->mechanics
                            ->map(fn (ServiceOrderMechanic $mechanic): string => trim((string) ($mechanic->mechanic?->name ?? '')))
                            ->filter(fn (string $name): bool => $name !== '')
                            ->values();
                    }

                    $sparePartRows = collect();
                    if ($canLoadSparePartDetails) {
                        $sparePartRows = $serviceOrder->spareParts
                            ->map(function (ServiceOrderSparePart $serviceOrderSparePart): array {
                                return [
                                    'id' => (string) $serviceOrderSparePart->id,
                                    'spare_part_id' => (string) $serviceOrderSparePart->spare_part_id,
                                    'name' => trim((string) ($serviceOrderSparePart->sparePart?->name ?? '')),
                                    'category' => trim((string) ($serviceOrderSparePart->sparePart?->category ?? '')),
                                    'unit' => trim((string) ($serviceOrderSparePart->sparePart?->unit ?? '')),
                                    'qty' => max((int) ($serviceOrderSparePart->qty ?? 0), 0),
                                    'unit_price' => max((int) ($serviceOrderSparePart->unit_price ?? 0), 0),
                                    'subtotal' => max((int) ($serviceOrderSparePart->subtotal ?? 0), 0),
                                    'notes' => trim((string) ($serviceOrderSparePart->notes ?? '')),
                                ];
                            })
                            ->filter(fn (array $row): bool => $row['spare_part_id'] !== '')
                            ->values();
                    }

                    $sparePartCount = $sparePartRows->count();
                    $sparePartTotalQty = (int) $sparePartRows->sum('qty');
                    $latestEstimate = $hasEstimateTables ? $serviceOrder->latestEstimate : null;
                    $latestEstimateStatus = strtolower(trim((string) ($latestEstimate?->status ?? '')));
                    $latestEstimateApprovalTokenHash = trim((string) ($latestEstimate?->approval_token_hash ?? ''));
                    $latestEstimateApprovalLink = null;
                    if ($latestEstimateStatus === 'pending_approval' && $latestEstimateApprovalTokenHash !== '') {
                        $latestEstimateApprovalLink = route('estimate-approval.show', [
                            'token' => $latestEstimateApprovalTokenHash,
                        ]);
                    }
                    $invoice = $hasInvoicesTable ? $serviceOrder->invoice : null;
                    $latestEstimateItems = collect();
                    if ($latestEstimate instanceof ServiceOrderEstimate) {
                        $latestEstimateItems = $latestEstimate->items
                            ->map(function (ServiceOrderEstimateItem $item): array {
                                return [
                                    'id' => (string) $item->id,
                                    'item_type' => (string) ($item->item_type ?? 'service'),
                                    'label' => (string) ($item->label ?? ''),
                                    'unit_label' => (string) ($item->unit_label ?? ''),
                                    'description' => (string) ($item->description ?? ''),
                                    'qty' => max((int) ($item->qty ?? 0), 0),
                                    'unit_price' => max((int) ($item->unit_price ?? 0), 0),
                                    'subtotal' => max((int) ($item->subtotal ?? 0), 0),
                                    'spare_part_id' => (string) ($item->spare_part_id ?? ''),
                                ];
                            })
                            ->filter(fn (array $item): bool => trim((string) ($item['label'] ?? '')) !== '')
                            ->values();
                    }

                    $invoicePayload = null;
                    if ($invoice instanceof Invoice) {
                        $invoiceStatus = strtolower(trim((string) ($invoice->status ?? 'unpaid')));
                        $invoicePayload = [
                            'id' => (string) $invoice->id,
                            'code' => (string) ($invoice->code ?? ''),
                            'status' => $invoiceStatus,
                            'status_label' => match ($invoiceStatus) {
                                'paid' => 'Lunas',
                                'partial' => 'Sebagian',
                                default => 'Belum Lunas',
                            },
                            'total_amount' => max((int) ($invoice->total_amount ?? 0), 0),
                            'paid_amount' => max((int) ($invoice->paid_amount ?? 0), 0),
                            'remaining_amount' => max((int) ($invoice->remaining_amount ?? 0), 0),
                            'due_date' => $invoice->due_date,
                            'last_paid_at' => $invoice->last_paid_at,
                        ];
                    }

                    return [
                        'id' => (string) $serviceOrder->id,
                        'workshop_id' => $workshopId,
                        'workshop_name' => $workshopName,
                        'workshop_code' => $workshopCode,
                        'code' => (string) $serviceOrder->code,
                        'customer_name' => (string) ($serviceOrder->customer?->name ?? '-'),
                        'customer_phone' => (string) ($serviceOrder->customer?->phone ?? ''),
                        'vehicle_name' => $vehicleName !== '' ? $vehicleName : '-',
                        'vehicle_plate_number' => (string) ($serviceOrder->vehicle?->plate_number ?? ''),
                        'service_date' => $serviceOrder->service_date,
                        'status' => (string) $serviceOrder->status,
                        'complaint' => (string) ($serviceOrder->complaint ?? ''),
                        'vehicle_condition' => (string) ($serviceOrder->vehicle_condition ?? ''),
                        'estimated_days' => $serviceOrder->estimated_days !== null ? (int) $serviceOrder->estimated_days : null,
                        'estimated_finish_date' => $serviceOrder->estimated_finish_date,
                        'odometer' => $serviceOrder->odometer !== null ? (int) $serviceOrder->odometer : null,
                        'started_at' => $hasStartedAtColumn ? $serviceOrder->started_at : null,
                        'completed_at' => $hasCompletedAtColumn ? $serviceOrder->completed_at : null,
                        'completion_notes' => $hasCompletionNotesColumn ? (string) ($serviceOrder->completion_notes ?? '') : '',
                        'service_fee' => $hasServiceFeeColumn ? max((int) ($serviceOrder->service_fee ?? 0), 0) : 0,
                        'total_amount' => $hasTotalAmountColumn ? max((int) ($serviceOrder->total_amount ?? 0), 0) : 0,
                        'mechanic_names' => $mechanicNames->all(),
                        'mechanic_count' => $mechanicNames->count(),
                        'spareparts' => $sparePartRows->all(),
                        'sparepart_count' => $sparePartCount,
                        'sparepart_total_qty' => $sparePartTotalQty,
                        'invoice' => $invoicePayload,
                        'latest_diagnosis' => $latestDiagnosisByOrderId->get((string) $serviceOrder->id),
                        'latest_estimate' => $latestEstimate ? [
                            'id' => (string) $latestEstimate->id,
                            'code' => (string) ($latestEstimate->code ?? ''),
                            'revision' => (int) ($latestEstimate->revision ?? 1),
                            'status' => $latestEstimateStatus !== '' ? $latestEstimateStatus : 'draft',
                            'status_label' => $this->resolveEstimateStatusLabel(
                                $latestEstimateStatus !== '' ? $latestEstimateStatus : 'draft',
                            ),
                            'subtotal_service' => max((int) ($latestEstimate->subtotal_service ?? 0), 0),
                            'subtotal_sparepart' => max((int) ($latestEstimate->subtotal_sparepart ?? 0), 0),
                            'total_amount' => max((int) ($latestEstimate->total_amount ?? 0), 0),
                            'valid_until' => $latestEstimate->valid_until,
                            'approval_requested_at' => $latestEstimate->approval_requested_at,
                            'approval_link' => $latestEstimateApprovalLink,
                            'approved_at' => $latestEstimate->approved_at,
                            'rejected_at' => $latestEstimate->rejected_at,
                            'expired_at' => $latestEstimate->expired_at,
                            'internal_note' => (string) ($latestEstimate->internal_note ?? ''),
                            'items' => $latestEstimateItems->all(),
                        ] : null,
                        'created_at' => $serviceOrder->created_at,
                        'updated_at' => $serviceOrder->updated_at,
                    ];
                })
                ->values();

            $ordersPayload = [
                'mode' => 'cursor',
                'data' => $orderRows->all(),
                'per_page' => $ordersPaginator->perPage(),
                'total' => $totalOrders,
                'from' => $orderRows->isEmpty() ? 0 : 1,
                'to' => $orderRows->count(),
                'current_cursor' => $ordersPaginator->cursor()?->encode(),
                'next_cursor' => $ordersPaginator->nextCursor()?->encode(),
                'prev_cursor' => $ordersPaginator->previousCursor()?->encode(),
                'has_more_pages' => $ordersPaginator->hasMorePages(),
            ];
        }

        return [
            'tenantId' => $tenantId,
            'package' => $package,
            'menuItems' => $menuItems,
            'orders' => $ordersPayload,
            'orderFilters' => [
                'search' => $orderSearch,
                'sort_by' => $orderSortBy,
                'sort_dir' => $orderSortDir,
                'per_page' => $orderPerPage,
                'cursor' => $ordersPayload['current_cursor'],
            ],
            'orderSummary' => $orderSummary,
            'customerOptions' => $customerOptions,
            'customerVehicleOptions' => $customerVehicleOptions,
            'vehicleMasterOptions' => $vehicleMasterOptions,
            'mechanicOptions' => $mechanicOptions,
            'completionSparePartOptions' => $completionSparePartOptions,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createServiceOrder(
        string $tenantId,
        string $activeWorkshopId,
        array $validated,
        ?Authenticatable $actor = null,
    ): void
    {
        $this->assertTablesReady();
        $targetWorkshopId = $this->resolveTargetWorkshopId(
            $tenantId,
            $activeWorkshopId,
            $validated,
            'workshop_id',
        );

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                DB::transaction(function () use ($tenantId, $targetWorkshopId, $validated, $actor): void {
                    $customer = $this->resolveCustomer($tenantId, $targetWorkshopId, $validated);
                    $vehicle = $this->resolveVehicle($tenantId, (string) $customer->id, $validated);
                    $serviceDate = (string) ($validated['service_date'] ?? now()->toDateString());
                    $estimatedDays = $this->normalizeNullableInteger($validated['estimated_days'] ?? null);
                    if ($estimatedDays !== null) {
                        $estimatedDays = max(1, $estimatedDays);
                    }

                    $orderPayload = [
                        'tenant_id' => $tenantId,
                        'customer_id' => (string) $customer->id,
                        'customer_vehicle_id' => (string) $vehicle->id,
                        'code' => $this->generateServiceOrderCode(),
                        'service_date' => $serviceDate,
                        'status' => 'open',
                        'complaint' => $this->normalizeNullableString($validated['complaint'] ?? null),
                        'vehicle_condition' => $this->normalizeNullableString($validated['vehicle_condition'] ?? null),
                        'estimated_days' => $estimatedDays,
                        'estimated_finish_date' => $this->resolveEstimatedFinishDate($serviceDate, $estimatedDays),
                        'odometer' => $this->normalizeNullableInteger($validated['odometer'] ?? null),
                        'total_amount' => 0,
                        'created_by_user_id' => $this->resolveActorUserId($actor),
                    ];

                    if (Schema::hasColumn('service_orders', 'service_fee')) {
                        $orderPayload['service_fee'] = 0;
                    }

                    ServiceOrder::query()->create($orderPayload);
                });

                return;
            } catch (QueryException $queryException) {
                if (! $this->isDuplicateServiceOrderCodeViolation($queryException)) {
                    throw $queryException;
                }

                if ($attempt === 4) {
                    throw ValidationException::withMessages([
                        'create_service_order' => 'Kode servis bentrok dengan data lain. Silakan simpan ulang.',
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function updateOrderStatus(
        string $tenantId,
        string $activeWorkshopId,
        string $orderId,
        array $validated,
        ?Authenticatable $actor = null,
    ): array {
        $this->assertServiceOrdersTableReady('update_order_status', 'Tabel servis belum siap.');

        $nextStatus = strtolower(trim((string) ($validated['status'] ?? '')));
        if (! in_array($nextStatus, ['in_progress', 'done', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Status servis tidak valid.',
            ]);
        }

        return DB::transaction(function () use ($tenantId, $activeWorkshopId, $orderId, $nextStatus, $validated, $actor): array {
            $order = $this->findTenantServiceOrderOrFail(
                $tenantId,
                $activeWorkshopId,
                $orderId,
                'update_order_status',
                lockForUpdate: true,
            );
            $scopedWorkshopId = $this->resolveOrderScopedWorkshopId($tenantId, $activeWorkshopId, $order);

            $currentStatus = strtolower(trim((string) ($order->status ?? 'open')));
            if ($currentStatus === $nextStatus) {
                return [
                    'from_status' => $currentStatus,
                    'to_status' => $nextStatus,
                    'message' => 'Status servis sudah sesuai.',
                ];
            }

            if (! $this->isAllowedStatusTransition($currentStatus, $nextStatus)) {
                throw ValidationException::withMessages([
                    'status' => 'Status servis '.$this->resolveStatusLabel($currentStatus).' tidak bisa diubah ke '.$this->resolveStatusLabel($nextStatus).'.',
                ]);
            }

            if (
                $nextStatus === 'in_progress'
                && ! $this->estimateService->hasApprovedEstimate($tenantId, (string) $order->id)
            ) {
                throw ValidationException::withMessages([
                    'update_order_status' => 'Servis belum bisa dimulai. Estimasi biaya harus disetujui pelanggan terlebih dahulu.',
                ]);
            }

            if ($nextStatus === 'done') {
                $this->completeServiceOrder($tenantId, $scopedWorkshopId, $order, $validated, $actor);
            } else {
                $payload = [
                    'status' => $nextStatus,
                ];

                if ($nextStatus === 'in_progress' && Schema::hasColumn('service_orders', 'started_at')) {
                    $payload['started_at'] = now();
                }

                $order->forceFill($payload)->save();
            }

            if ($nextStatus === 'cancelled') {
                $this->syncBookingStatusForCancelledServiceOrder($tenantId, $order);
            }

            return [
                'from_status' => $currentStatus,
                'to_status' => $nextStatus,
                'message' => 'Status servis diubah ke '.$this->resolveStatusLabel($nextStatus).'.',
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function completeServiceOrder(
        string $tenantId,
        string $activeWorkshopId,
        ServiceOrder $order,
        array $validated,
        ?Authenticatable $actor = null,
    ): void {
        if (! Schema::hasTable('service_order_mechanics') || ! Schema::hasTable('service_order_spare_parts')) {
            throw ValidationException::withMessages([
                'update_order_status' => 'Struktur data penyelesaian servis belum siap. Jalankan migrasi terbaru.',
            ]);
        }

        $allowNoSpareparts = (bool) ($validated['allow_no_spareparts'] ?? false);
        $serviceFee = max((int) ($this->normalizeNullableInteger($validated['service_fee'] ?? null) ?? 0), 0);

        $mechanicIds = collect($validated['mechanic_user_ids'] ?? [])
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values();

        if ($mechanicIds->isEmpty()) {
            throw ValidationException::withMessages([
                'mechanic_user_ids' => 'Mekanik wajib diisi sebelum servis diselesaikan.',
            ]);
        }

        $mechanicRows = User::query()
            ->where('tenant_id', $tenantId)
            ->when($this->shouldApplyUserWorkshopScope($tenantId, $activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                $query->where('workshop_id', $activeWorkshopId);
            })
            ->whereIn('id', $mechanicIds->all())
            ->where('is_superadmin', false)
            ->where('is_owner', false)
            ->where(function (Builder $query): void {
                $query
                    ->whereRaw('LOWER(COALESCE(user_type, ?)) = ?', ['', 'mekanik'])
                    ->orWhereRaw('LOWER(COALESCE(role, ?)) = ?', ['', 'mekanik'])
                    ->orWhereHas('roles', function (Builder $roleQuery): void {
                        $roleQuery->whereRaw('LOWER(name) = ?', ['mekanik']);
                    });
            })
            ->get(['id']);

        if ($mechanicRows->count() !== $mechanicIds->count()) {
            throw ValidationException::withMessages([
                'mechanic_user_ids' => 'Ada mekanik yang tidak valid di bengkel servis ini.',
            ]);
        }

        $sparePartEntries = collect($validated['spareparts'] ?? [])
            ->filter(fn ($entry): bool => is_array($entry))
            ->map(function (array $entry): array {
                return [
                    'spare_part_id' => trim((string) ($entry['spare_part_id'] ?? '')),
                    'warehouse_id' => $this->normalizeNullableString($entry['warehouse_id'] ?? null),
                    'qty' => max((int) ($this->normalizeNullableInteger($entry['qty'] ?? null) ?? 0), 0),
                    'notes' => $this->normalizeNullableString($entry['notes'] ?? null),
                ];
            })
            ->filter(fn (array $entry): bool => $entry['spare_part_id'] !== '' && $entry['qty'] > 0)
            ->values();

        if ($sparePartEntries->isEmpty() && ! $allowNoSpareparts) {
            throw ValidationException::withMessages([
                'spareparts' => 'Sparepart terpakai wajib diisi sebelum servis diselesaikan.',
            ]);
        }

        $sparePartIds = $sparePartEntries
            ->pluck('spare_part_id')
            ->filter(fn ($id): bool => is_string($id) && trim($id) !== '')
            ->unique()
            ->values();

        $spareParts = SparePart::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $sparePartIds->all())
            ->where('is_active', true)
            ->lockForUpdate()
            ->get(['id', 'selling_price', 'stock']);

        if ($spareParts->count() !== $sparePartIds->count()) {
            throw ValidationException::withMessages([
                'spareparts' => 'Ada sparepart yang tidak ditemukan atau nonaktif.',
            ]);
        }

        $sparePartById = $spareParts->keyBy(fn (SparePart $sparePart): string => (string) $sparePart->id);
        $affectedSparePartIds = [];
        $sparePartRows = [];
        $totalAmount = $serviceFee;
        $hasWarehouseStockTable = Schema::hasTable('warehouse_spare_part_stocks');

        foreach ($sparePartEntries as $index => $entry) {
            $sparePartId = (string) $entry['spare_part_id'];
            $qty = (int) $entry['qty'];
            $warehouseId = $this->normalizeNullableString($entry['warehouse_id'] ?? null);
            $sparePart = $sparePartById->get($sparePartId);

            if (! $sparePart) {
                throw ValidationException::withMessages([
                    "spareparts.{$index}.spare_part_id" => 'Sparepart tidak ditemukan.',
                ]);
            }

            if ($hasWarehouseStockTable) {
                $warehouseStock = $this->resolveWarehouseStockForServiceCompletion(
                    $tenantId,
                    $activeWorkshopId,
                    $sparePartId,
                    $qty,
                    $warehouseId,
                );

                if (! $warehouseStock) {
                    throw ValidationException::withMessages([
                        "spareparts.{$index}.qty" => 'Stok sparepart tidak cukup di bengkel servis ini.',
                    ]);
                }

                $warehouseStock->stock = max((int) $warehouseStock->stock - $qty, 0);
                $warehouseStock->save();
                $warehouseId = (string) $warehouseStock->warehouse_id;
            } else {
                if ((int) $sparePart->stock < $qty) {
                    throw ValidationException::withMessages([
                        "spareparts.{$index}.qty" => 'Stok sparepart tidak cukup.',
                    ]);
                }

                $sparePart->stock = max((int) $sparePart->stock - $qty, 0);
                $sparePart->save();
            }

            $unitPrice = max((int) ($sparePart->selling_price ?? 0), 0);
            $subtotal = $unitPrice * $qty;

            $sparePartPayload = [
                'tenant_id' => $tenantId,
                'service_order_id' => (string) $order->id,
                'spare_part_id' => $sparePartId,
                'warehouse_id' => $warehouseId,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'notes' => $this->normalizeNullableString($entry['notes'] ?? null),
            ];

            if (Schema::hasColumn('service_order_spare_parts', 'workshop_id')) {
                $sparePartPayload['workshop_id'] = $activeWorkshopId;
            }

            $sparePartRows[] = $sparePartPayload;

            $affectedSparePartIds[] = $sparePartId;
            $totalAmount += $subtotal;
        }

        ServiceOrderMechanic::query()
            ->where('tenant_id', $tenantId)
            ->where('service_order_id', (string) $order->id)
            ->delete();

        ServiceOrderSparePart::query()
            ->where('tenant_id', $tenantId)
            ->where('service_order_id', (string) $order->id)
            ->delete();

        foreach ($mechanicIds as $mechanicId) {
            $mechanicPayload = [
                'tenant_id' => $tenantId,
                'service_order_id' => (string) $order->id,
                'user_id' => (string) $mechanicId,
            ];

            if (Schema::hasColumn('service_order_mechanics', 'workshop_id')) {
                $mechanicPayload['workshop_id'] = $activeWorkshopId;
            }

            ServiceOrderMechanic::query()->create($mechanicPayload);
        }

        foreach ($sparePartRows as $sparePartRow) {
            ServiceOrderSparePart::query()->create($sparePartRow);
        }

        if ($hasWarehouseStockTable) {
            collect($affectedSparePartIds)
                ->unique()
                ->values()
                ->each(function (string $sparePartId) use ($tenantId): void {
                    $sparePart = SparePart::query()
                        ->where('tenant_id', $tenantId)
                        ->where('id', $sparePartId)
                        ->first();

                    if ($sparePart) {
                        $this->syncSparePartTotalStock($tenantId, $sparePart);
                    }
                });
        }

        $orderPayload = [
            'status' => 'done',
            'total_amount' => $totalAmount,
        ];

        if (Schema::hasColumn('service_orders', 'service_fee')) {
            $orderPayload['service_fee'] = $serviceFee;
        }

        if (Schema::hasColumn('service_orders', 'started_at') && $order->started_at === null) {
            $orderPayload['started_at'] = now();
        }

        if (Schema::hasColumn('service_orders', 'completed_at')) {
            $orderPayload['completed_at'] = now();
        }

        if (Schema::hasColumn('service_orders', 'completion_notes')) {
            $orderPayload['completion_notes'] = $this->normalizeNullableString($validated['completion_notes'] ?? null);
        }

        $order->forceFill($orderPayload)->save();

        $this->invoiceService->syncInvoiceFromServiceOrder(
            $tenantId,
            $activeWorkshopId,
            $order,
            $actor,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveCustomerOptions(string $tenantId, string $activeWorkshopId): array
    {
        if (! Schema::hasTable('customers')) {
            return [];
        }

        return Customer::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->when($this->shouldApplyCustomerWorkshopScope($tenantId, $activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                $query->where('workshop_id', $activeWorkshopId);
            })
            ->with(['workshop:id,name,code'])
            ->orderBy('name')
            ->limit(300)
            ->get(['id', 'workshop_id', 'name', 'phone', 'email', 'address'])
            ->map(function (Customer $customer): array {
                $workshopName = trim((string) ($customer->workshop?->name ?? ''));
                $workshopCode = trim((string) ($customer->workshop?->code ?? ''));
                $subtitleParts = array_filter([
                    trim((string) ($customer->phone ?? '')),
                    trim((string) ($customer->email ?? '')),
                    $workshopName !== '' ? $workshopName.($workshopCode !== '' ? " ({$workshopCode})" : '') : '',
                ]);

                return [
                    'id' => (string) $customer->id,
                    'workshop_id' => (string) ($customer->workshop_id ?? ''),
                    'workshop_name' => $workshopName,
                    'workshop_code' => $workshopCode,
                    'name' => (string) $customer->name,
                    'phone' => (string) ($customer->phone ?? ''),
                    'email' => (string) ($customer->email ?? ''),
                    'address' => (string) ($customer->address ?? ''),
                    'subtitle' => implode(' | ', $subtitleParts),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveCustomerVehicleOptions(string $tenantId, string $activeWorkshopId): array
    {
        if (! Schema::hasTable('customer_vehicles')) {
            return [];
        }

        return CustomerVehicle::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->when($this->shouldApplyCustomerWorkshopScope($tenantId, $activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                $query->whereHas('customer', function (Builder $customerQuery) use ($activeWorkshopId): void {
                    $customerQuery
                        ->where('workshop_id', $activeWorkshopId)
                        ->where('is_active', true);
                });
            })
            ->with(['customer:id,workshop_id'])
            ->orderBy('brand')
            ->orderBy('model')
            ->orderBy('plate_number')
            ->limit(600)
            ->get(['id', 'customer_id', 'vehicle_type', 'brand', 'model', 'variant', 'plate_number', 'year'])
            ->map(function (CustomerVehicle $vehicle): array {
                $vehicleType = $this->normalizeVehicleType($vehicle->vehicle_type);
                $name = trim(implode(' ', array_filter([
                    trim((string) $vehicle->brand),
                    trim((string) $vehicle->model),
                    trim((string) ($vehicle->variant ?? '')),
                ])));

                return [
                    'id' => (string) $vehicle->id,
                    'customer_id' => (string) $vehicle->customer_id,
                    'workshop_id' => (string) ($vehicle->customer?->workshop_id ?? ''),
                    'vehicle_type' => $vehicleType,
                    'vehicle_type_label' => $vehicleType === 'mobil' ? 'Mobil' : 'Motor',
                    'brand' => (string) $vehicle->brand,
                    'model' => (string) $vehicle->model,
                    'variant' => (string) ($vehicle->variant ?? ''),
                    'plate_number' => (string) ($vehicle->plate_number ?? ''),
                    'year' => $vehicle->year !== null ? (int) $vehicle->year : null,
                    'display_name' => $name !== '' ? $name : 'Kendaraan',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveTenantVehicleMasterOptions(string $tenantId): array
    {
        if (! Schema::hasTable('tenant_vehicle_masters')) {
            return [];
        }

        return TenantVehicleMaster::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('vehicle_type')
            ->orderBy('brand')
            ->orderBy('model')
            ->limit(1000)
            ->get(['id', 'vehicle_type', 'brand', 'model', 'source'])
            ->map(function (TenantVehicleMaster $master): array {
                $vehicleType = strtolower(trim((string) $master->vehicle_type));
                return [
                    'id' => (string) $master->id,
                    'vehicle_type' => in_array($vehicleType, ['motor', 'mobil'], true) ? $vehicleType : 'motor',
                    'brand' => (string) $master->brand,
                    'model' => (string) $master->model,
                    'source' => (string) ($master->source ?? 'manual'),
                    'label' => trim((string) $master->brand).' - '.trim((string) $master->model),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function resolveMechanicOptions(string $tenantId, string $activeWorkshopId): array
    {
        if (! Schema::hasTable('users')) {
            return [];
        }

        return User::query()
            ->where('tenant_id', $tenantId)
            ->when($this->shouldApplyUserWorkshopScope($tenantId, $activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                $query->where('workshop_id', $activeWorkshopId);
            })
            ->where('is_superadmin', false)
            ->where('is_owner', false)
            ->where(function (Builder $query): void {
                $query
                    ->whereRaw('LOWER(COALESCE(user_type, ?)) = ?', ['', 'mekanik'])
                    ->orWhereRaw('LOWER(COALESCE(role, ?)) = ?', ['', 'mekanik'])
                    ->orWhereHas('roles', function (Builder $roleQuery): void {
                        $roleQuery->whereRaw('LOWER(name) = ?', ['mekanik']);
                    });
            })
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'workshop_id', 'name', 'email'])
            ->map(fn (User $user): array => [
                'id' => (string) $user->id,
                'workshop_id' => (string) ($user->workshop_id ?? ''),
                'name' => (string) $user->name,
                'email' => (string) ($user->email ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveCompletionSparePartOptions(
        string $tenantId,
        string $activeWorkshopId,
        string $search = '',
        string $category = '',
        string $cursor = '',
        int $perPage = 20,
    ): array
    {
        $normalizedSearch = trim($search);
        $normalizedCategory = trim($category);

        $payload = [
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
            'search' => $normalizedSearch,
            'category' => $normalizedCategory,
            'categories' => [],
        ];

        if (! Schema::hasTable('spare_parts')) {
            return $payload;
        }

        if (! Schema::hasTable('warehouse_spare_part_stocks')) {
            $categoryOptions = SparePart::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->whereRaw("TRIM(COALESCE(category, '')) != ''")
                ->orderBy('category')
                ->distinct()
                ->pluck('category')
                ->map(fn ($value): string => trim((string) $value))
                ->filter(fn (string $value): bool => $value !== '')
                ->unique()
                ->values()
                ->all();

            $baseQuery = SparePart::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->when($normalizedCategory !== '', function (Builder $query) use ($normalizedCategory): void {
                    $query->whereRaw('LOWER(COALESCE(category, ?)) = ?', ['', Str::lower($normalizedCategory)]);
                })
                ->when($normalizedSearch !== '', function (Builder $query) use ($normalizedSearch): void {
                    $query->where(function (Builder $nestedQuery) use ($normalizedSearch): void {
                        $nestedQuery
                            ->where('name', 'like', "%{$normalizedSearch}%")
                            ->orWhere('sku', 'like', "%{$normalizedSearch}%")
                            ->orWhere('category', 'like', "%{$normalizedSearch}%")
                            ->orWhere('unit', 'like', "%{$normalizedSearch}%");
                    });
                })
                ->orderBy('name')
                ->orderBy('id');

            $total = (int) (clone $baseQuery)->count();
            $paginator = $this->cursorPaginateCompletionSparePartsWithFallback(
                $baseQuery,
                $perPage,
                ['id', 'name', 'category', 'unit', 'stock', 'selling_price'],
                $cursor,
            );

            $rows = collect($paginator->items())
                ->map(fn (SparePart $sparePart): array => [
                    'id' => (string) $sparePart->id,
                    'workshop_id' => '',
                    'name' => (string) $sparePart->name,
                    'category' => (string) ($sparePart->category ?? ''),
                    'unit' => (string) ($sparePart->unit ?? ''),
                    'stock' => (int) ($sparePart->stock ?? 0),
                    'selling_price' => (int) ($sparePart->selling_price ?? 0),
                ])
                ->values()
                ->all();

            return [
                ...$payload,
                'data' => $rows,
                'per_page' => $paginator->perPage(),
                'total' => $total,
                'from' => $rows === [] ? 0 : 1,
                'to' => count($rows),
                'current_cursor' => $paginator->cursor()?->encode(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
                'has_more_pages' => $paginator->hasMorePages(),
                'categories' => $categoryOptions,
            ];
        }

        $stocks = WarehouseSparePartStock::query()
            ->where('tenant_id', $tenantId)
            ->when($this->shouldApplyWarehouseStockWorkshopScope($tenantId, $activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                $query->where('workshop_id', $activeWorkshopId);
            })
            ->selectRaw('spare_part_id')
            ->selectRaw('SUM(stock) as total_stock')
            ->selectRaw('MAX(workshop_id) as workshop_id')
            ->groupBy('spare_part_id');

        $categoryOptions = SparePart::query()
            ->from('spare_parts')
            ->leftJoinSub($stocks, 'stock_agg', function ($join): void {
                $join->on('stock_agg.spare_part_id', '=', 'spare_parts.id');
            })
            ->where('spare_parts.tenant_id', $tenantId)
            ->where('spare_parts.is_active', true)
            ->whereRaw('COALESCE(stock_agg.total_stock, 0) > 0')
            ->whereRaw("TRIM(COALESCE(spare_parts.category, '')) != ''")
            ->orderBy('spare_parts.category')
            ->distinct()
            ->pluck('spare_parts.category')
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();

        $baseQuery = SparePart::query()
            ->from('spare_parts')
            ->leftJoinSub($stocks, 'stock_agg', function ($join): void {
                $join->on('stock_agg.spare_part_id', '=', 'spare_parts.id');
            })
            ->where('spare_parts.tenant_id', $tenantId)
            ->where('spare_parts.is_active', true)
            ->whereRaw('COALESCE(stock_agg.total_stock, 0) > 0')
            ->when($normalizedCategory !== '', function (Builder $query) use ($normalizedCategory): void {
                $query->whereRaw('LOWER(COALESCE(spare_parts.category, ?)) = ?', ['', Str::lower($normalizedCategory)]);
            })
            ->when($normalizedSearch !== '', function (Builder $query) use ($normalizedSearch): void {
                $query->where(function (Builder $nestedQuery) use ($normalizedSearch): void {
                    $nestedQuery
                        ->where('spare_parts.name', 'like', "%{$normalizedSearch}%")
                        ->orWhere('spare_parts.sku', 'like', "%{$normalizedSearch}%")
                        ->orWhere('spare_parts.category', 'like', "%{$normalizedSearch}%")
                        ->orWhere('spare_parts.unit', 'like', "%{$normalizedSearch}%");
                });
            })
            ->orderBy('spare_parts.name')
            ->orderBy('spare_parts.id');

        $total = (int) (clone $baseQuery)->count();
        $paginator = $this->cursorPaginateCompletionSparePartsWithFallback(
            $baseQuery,
            $perPage,
            [
                'spare_parts.id',
                'spare_parts.name',
                'spare_parts.category',
                'spare_parts.unit',
                'spare_parts.selling_price',
                DB::raw('COALESCE(stock_agg.total_stock, 0) as available_stock'),
                DB::raw("COALESCE(stock_agg.workshop_id, '') as workshop_id"),
            ],
            $cursor,
        );

        $rows = collect($paginator->items())
            ->map(function (SparePart $sparePart): array {
                return [
                    'id' => (string) $sparePart->id,
                    'workshop_id' => (string) ($sparePart->getAttribute('workshop_id') ?? ''),
                    'name' => (string) $sparePart->name,
                    'category' => (string) ($sparePart->category ?? ''),
                    'unit' => (string) ($sparePart->unit ?? ''),
                    'stock' => (int) ($sparePart->getAttribute('available_stock') ?? 0),
                    'selling_price' => (int) ($sparePart->selling_price ?? 0),
                ];
            })
            ->values()
            ->all();

        return [
            ...$payload,
            'data' => $rows,
            'per_page' => $paginator->perPage(),
            'total' => $total,
            'from' => $rows === [] ? 0 : 1,
            'to' => count($rows),
            'current_cursor' => $paginator->cursor()?->encode(),
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'prev_cursor' => $paginator->previousCursor()?->encode(),
            'has_more_pages' => $paginator->hasMorePages(),
            'categories' => $categoryOptions,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveCustomer(string $tenantId, string $activeWorkshopId, array $validated): Customer
    {
        $customerId = trim((string) ($validated['customer_id'] ?? ''));
        if ($customerId !== '') {
            $customer = Customer::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->when($this->shouldApplyCustomerWorkshopScope($tenantId, $activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                    $query->where('workshop_id', $activeWorkshopId);
                })
                ->where('id', $customerId)
                ->first();

            if (! $customer) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Pelanggan tidak ditemukan.',
                ]);
            }

            return $customer;
        }

        $customerName = trim((string) ($validated['customer_name'] ?? ''));
        if ($customerName === '') {
            throw ValidationException::withMessages([
                'customer_name' => 'Nama pelanggan wajib diisi.',
            ]);
        }

        $customerPhone = $this->normalizeNullableString($validated['customer_phone'] ?? null);
        $customerEmail = $this->normalizeNullableString($validated['customer_email'] ?? null);
        $customerAddress = $this->normalizeNullableString($validated['customer_address'] ?? null);

        $existingCustomer = Customer::query()
            ->where('tenant_id', $tenantId)
            ->when($this->shouldApplyCustomerWorkshopScope($tenantId, $activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                $query->where('workshop_id', $activeWorkshopId);
            })
            ->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower($customerName)])
            ->first();

        if ($existingCustomer) {
            $didChange = false;

            if ($customerPhone !== null && trim((string) ($existingCustomer->phone ?? '')) === '') {
                $existingCustomer->phone = $customerPhone;
                $didChange = true;
            }

            if ($customerEmail !== null && trim((string) ($existingCustomer->email ?? '')) === '') {
                $existingCustomer->email = $customerEmail;
                $didChange = true;
            }

            if ($customerAddress !== null && trim((string) ($existingCustomer->address ?? '')) === '') {
                $existingCustomer->address = $customerAddress;
                $didChange = true;
            }

            if ($didChange) {
                $existingCustomer->save();
            }

            return $existingCustomer;
        }

        $customerPayload = [
            'tenant_id' => $tenantId,
            'name' => $customerName,
            'phone' => $customerPhone,
            'email' => $customerEmail,
            'address' => $customerAddress,
            'notes' => null,
            'is_active' => true,
        ];

        if ($this->shouldApplyCustomerWorkshopScope($tenantId, $activeWorkshopId)) {
            $customerPayload['workshop_id'] = $activeWorkshopId;
        }

        return Customer::query()->create($customerPayload);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveVehicle(string $tenantId, string $customerId, array $validated): CustomerVehicle
    {
        $vehicleId = trim((string) ($validated['vehicle_id'] ?? ''));
        if ($vehicleId !== '') {
            $vehicle = CustomerVehicle::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $vehicleId)
                ->where('is_active', true)
                ->first();

            if (! $vehicle || (string) $vehicle->customer_id !== $customerId) {
                throw ValidationException::withMessages([
                    'vehicle_id' => 'Kendaraan tidak ditemukan atau bukan milik pelanggan terpilih.',
                ]);
            }

            return $vehicle;
        }

        [$vehicleType, $brand, $model] = $this->resolveVehicleIdentityFromTenantMaster(
            $tenantId,
            $validated,
        );
        $plateNumber = $this->normalizePlateNumber($validated['vehicle_plate_number'] ?? null);

        if ($brand === '' || $model === '' || $plateNumber === '') {
            throw ValidationException::withMessages([
                'vehicle_master_id' => 'Model kendaraan wajib dipilih dari master kendaraan.',
            ]);
        }

        $variant = $this->normalizeNullableString($validated['vehicle_variant'] ?? null);
        $year = $this->normalizeNullableInteger($validated['vehicle_year'] ?? null);
        $notes = $this->normalizeNullableString($validated['vehicle_notes'] ?? null);

        $existingVehicle = CustomerVehicle::query()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->where('vehicle_type', $vehicleType)
            ->whereRaw('LOWER(TRIM(brand)) = ?', [Str::lower($brand)])
            ->whereRaw('LOWER(TRIM(model)) = ?', [Str::lower($model)])
            ->whereRaw("UPPER(REPLACE(TRIM(COALESCE(plate_number, '')), ' ', '')) = ?", [$plateNumber])
            ->first();

        if ($existingVehicle) {
            $attributesToUpdate = [
                'vehicle_type' => $vehicleType,
                'variant' => $variant,
                'year' => $year,
                'notes' => $notes,
                'is_active' => true,
            ];

            $existingVehicle->forceFill($attributesToUpdate)->save();

            return $existingVehicle;
        }

        $attributesToCreate = [
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'vehicle_type' => $vehicleType,
            'brand' => $brand,
            'model' => $model,
            'variant' => $variant,
            'plate_number' => $plateNumber,
            'year' => $year,
            'notes' => $notes,
            'is_active' => true,
        ];

        return CustomerVehicle::query()->create($attributesToCreate);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: string, 1: string, 2: string}
     */
    private function resolveVehicleIdentityFromTenantMaster(string $tenantId, array $validated): array
    {
        $vehicleType = $this->normalizeVehicleType((string) ($validated['vehicle_type'] ?? ''));
        $brand = trim((string) ($validated['vehicle_brand'] ?? ''));
        $model = trim((string) ($validated['vehicle_model'] ?? ''));

        $vehicleMasterId = trim((string) ($validated['vehicle_master_id'] ?? ''));
        if ($vehicleMasterId !== '') {
            $master = TenantVehicleMaster::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $vehicleMasterId)
                ->where('is_active', true)
                ->first();

            if (! $master) {
                throw ValidationException::withMessages([
                    'vehicle_master_id' => 'Master kendaraan tidak ditemukan atau sudah nonaktif.',
                ]);
            }

            $vehicleType = $this->normalizeVehicleType((string) $master->vehicle_type);
            $brand = trim((string) $master->brand);
            $model = trim((string) $master->model);
        }

        return [$vehicleType, $brand, $model];
    }

    private function generateServiceOrderCode(): string
    {
        $prefix = 'SO-'.now()->format('Ymd');

        for ($sequence = 1; $sequence <= 999; $sequence++) {
            $candidateCode = $prefix.'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

            $exists = ServiceOrder::query()
                ->withoutGlobalScopes()
                ->where('code', $candidateCode)
                ->exists();

            if (! $exists) {
                return $candidateCode;
            }
        }

        return $prefix.'-'.Str::upper(Str::random(4));
    }

    private function isDuplicateServiceOrderCodeViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        if ($sqlState !== '23000') {
            return false;
        }

        $message = Str::lower($exception->getMessage());

        return str_contains($message, 'service_orders_code_unique')
            || str_contains($message, 'duplicate entry')
            || str_contains($message, 'unique constraint failed');
    }

    private function resolveActorUserId(?Authenticatable $actor): ?string
    {
        if (! $actor) {
            return null;
        }

        $actorId = trim((string) $actor->getAuthIdentifier());

        return $actorId !== '' ? $actorId : null;
    }

    private function assertTablesReady(): void
    {
        if (
            ! Schema::hasTable('service_orders')
            || ! Schema::hasTable('customers')
            || ! Schema::hasTable('customer_vehicles')
            || ! Schema::hasColumn('service_orders', 'vehicle_condition')
            || ! Schema::hasColumn('service_orders', 'estimated_days')
            || ! Schema::hasColumn('service_orders', 'estimated_finish_date')
        ) {
            throw ValidationException::withMessages([
                'create_service_order' => 'Tabel servis belum siap.',
            ]);
        }
    }

    private function assertServiceOrdersTableReady(string $errorKey, string $message): void
    {
        if (! Schema::hasTable('service_orders')) {
            throw ValidationException::withMessages([
                $errorKey => $message,
            ]);
        }
    }

    private function findTenantServiceOrderOrFail(
        string $tenantId,
        string $activeWorkshopId,
        string $orderId,
        string $errorKey,
        bool $lockForUpdate = false,
    ): ServiceOrder {
        $query = ServiceOrder::query()
            ->with(['customer:id,workshop_id'])
            ->where('tenant_id', $tenantId)
            ->where('id', $orderId)
            ->when($this->shouldApplyCustomerWorkshopScope($tenantId, $activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                $query->whereHas('customer', function (Builder $customerQuery) use ($activeWorkshopId): void {
                    $customerQuery->where('workshop_id', $activeWorkshopId);
                });
            });

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $order = $query->first();

        if (! $order) {
            throw ValidationException::withMessages([
                $errorKey => 'Data servis tidak ditemukan.',
            ]);
        }

        return $order;
    }

    private function isAllowedStatusTransition(string $fromStatus, string $toStatus): bool
    {
        $allowedTransitions = [
            'open' => ['in_progress', 'cancelled'],
            'in_progress' => ['done', 'cancelled'],
            'done' => [],
            'cancelled' => [],
        ];

        $availableTargets = $allowedTransitions[$fromStatus] ?? [];

        return in_array($toStatus, $availableTargets, true);
    }

    private function resolveStatusLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            'in_progress' => 'Proses',
            'done' => 'Selesai',
            'cancelled' => 'Batal',
            default => 'Open',
        };
    }

    private function resolveEstimateStatusLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            'pending_approval' => 'Menunggu Approval',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'expired' => 'Kadaluarsa',
            default => 'Draft',
        };
    }

    private function resolveWarehouseStockForServiceCompletion(
        string $tenantId,
        string $activeWorkshopId,
        string $sparePartId,
        int $qty,
        ?string $warehouseId = null,
    ): ?WarehouseSparePartStock {
        if ($qty < 1) {
            return null;
        }

        if ($warehouseId !== null && $warehouseId !== '') {
            return WarehouseSparePartStock::query()
                ->where('tenant_id', $tenantId)
                ->when($this->shouldApplyWarehouseStockWorkshopScope($tenantId, $activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                    $query->where('workshop_id', $activeWorkshopId);
                })
                ->where('spare_part_id', $sparePartId)
                ->where('warehouse_id', $warehouseId)
                ->where('stock', '>=', $qty)
                ->orderByDesc('stock')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();
        }

        return WarehouseSparePartStock::query()
            ->where('tenant_id', $tenantId)
            ->when($this->shouldApplyWarehouseStockWorkshopScope($tenantId, $activeWorkshopId), function (Builder $query) use ($activeWorkshopId): void {
                $query->where('workshop_id', $activeWorkshopId);
            })
            ->where('spare_part_id', $sparePartId)
            ->where('stock', '>=', $qty)
            ->orderByDesc('stock')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }

    private function syncSparePartTotalStock(string $tenantId, SparePart $sparePart): void
    {
        if (! Schema::hasTable('warehouse_spare_part_stocks')) {
            return;
        }

        $aggregate = WarehouseSparePartStock::query()
            ->where('tenant_id', $tenantId)
            ->where('spare_part_id', (string) $sparePart->id)
            ->selectRaw('COUNT(*) as stock_row_count')
            ->selectRaw('COALESCE(SUM(stock), 0) as total_stock')
            ->selectRaw('COALESCE(SUM(minimum_stock), 0) as total_minimum_stock')
            ->first();

        if ((int) ($aggregate?->getAttribute('stock_row_count') ?? 0) < 1) {
            return;
        }

        $lockedSparePart = SparePart::query()
            ->where('tenant_id', $tenantId)
            ->where('id', (string) $sparePart->id)
            ->lockForUpdate()
            ->first();

        if (! $lockedSparePart) {
            return;
        }

        $lockedSparePart->forceFill([
            'stock' => (int) ($aggregate?->getAttribute('total_stock') ?? 0),
            'minimum_stock' => (int) ($aggregate?->getAttribute('total_minimum_stock') ?? 0),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveTargetWorkshopId(
        string $tenantId,
        string $activeWorkshopId,
        array $validated,
        string $errorKey,
    ): string {
        $requestedWorkshopId = trim((string) ($validated['workshop_id'] ?? ''));
        $hasActiveWorkshops = $this->hasActiveWorkshops($tenantId);
        if ($requestedWorkshopId === '' && $this->shouldApplyCustomerWorkshopScope($tenantId, $activeWorkshopId)) {
            $requestedWorkshopId = trim($activeWorkshopId);
        }

        if ($requestedWorkshopId === '') {
            if (! $hasActiveWorkshops) {
                return '';
            }

            throw ValidationException::withMessages([
                $errorKey => 'Pilih bengkel tujuan terlebih dahulu.',
            ]);
        }

        if (! Schema::hasTable('workshops') || ! $hasActiveWorkshops) {
            return $requestedWorkshopId;
        }

        $exists = Workshop::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $requestedWorkshopId)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                $errorKey => 'Bengkel tujuan tidak valid atau tidak aktif.',
            ]);
        }

        return $requestedWorkshopId;
    }

    private function resolveOrderScopedWorkshopId(
        string $tenantId,
        string $activeWorkshopId,
        ServiceOrder $order,
    ): string {
        if ($this->shouldApplyCustomerWorkshopScope($tenantId, $activeWorkshopId)) {
            return $activeWorkshopId;
        }

        $orderWorkshopId = trim((string) ($order->customer?->workshop_id ?? ''));
        if ($orderWorkshopId === '' && Schema::hasTable('customers') && Schema::hasColumn('customers', 'workshop_id')) {
            $orderWorkshopId = trim((string) Customer::query()
                ->where('tenant_id', $tenantId)
                ->where('id', (string) $order->customer_id)
                ->value('workshop_id'));
        }

        return $orderWorkshopId;
    }

    private function syncBookingStatusForCancelledServiceOrder(string $tenantId, ServiceOrder $order): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        $orderWorkshopId = trim((string) ($order->customer?->workshop_id ?? ''));
        $orderCustomerVehicleId = trim((string) ($order->customer_vehicle_id ?? ''));
        $orderCustomerName = trim((string) ($order->customer?->name ?? ''));
        $orderCustomerPhone = $this->normalizePhoneForLookup($order->customer?->phone);
        $orderComplaint = trim((string) ($order->complaint ?? ''));
        $orderServiceDate = $order->service_date instanceof \DateTimeInterface
            ? $order->service_date->format('Y-m-d')
            : null;

        $bookingQuery = Booking::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'in_service')
            ->when($orderWorkshopId !== '', function (Builder $query) use ($orderWorkshopId): void {
                $query->where('workshop_id', $orderWorkshopId);
            });

        if ($orderCustomerVehicleId !== '' && Schema::hasColumn('bookings', 'customer_vehicle_id')) {
            $bookingQuery->where('customer_vehicle_id', $orderCustomerVehicleId);
        } else {
            if ($orderCustomerName !== '') {
                $bookingQuery->whereRaw('LOWER(TRIM(customer_name)) = ?', [Str::lower($orderCustomerName)]);
            }

            if ($orderCustomerPhone !== '') {
                $bookingQuery->whereRaw(
                    "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(customer_phone, ''), ' ', ''), '-', ''), '.', ''), '(', ''), ')', ''), '+', '') = ?",
                    [$orderCustomerPhone],
                );
            }
        }

        if ($orderComplaint !== '') {
            $bookingQuery->whereRaw('LOWER(TRIM(complaint)) = ?', [Str::lower($orderComplaint)]);
        }

        if ($orderServiceDate !== null) {
            $bookingQuery->whereDate('booking_date', '<=', $orderServiceDate);
        }

        $matchedBooking = $bookingQuery
            ->orderByDesc('booking_date')
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->first();

        if (! $matchedBooking) {
            return;
        }

        $matchedBooking->status = 'cancelled';
        $matchedBooking->save();
    }

    private function shouldApplyCustomerWorkshopScope(string $tenantId, string $activeWorkshopId): bool
    {
        return $this->hasCustomerWorkshopScope()
            && $this->hasActiveWorkshops($tenantId)
            && $activeWorkshopId !== ''
            && ! OwnerWorkshopSwitcherService::isAllWorkshopsId($activeWorkshopId);
    }

    private function shouldApplyUserWorkshopScope(string $tenantId, string $activeWorkshopId): bool
    {
        return $this->hasUserWorkshopScope()
            && $this->hasActiveWorkshops($tenantId)
            && $activeWorkshopId !== ''
            && ! OwnerWorkshopSwitcherService::isAllWorkshopsId($activeWorkshopId);
    }

    private function shouldApplyWarehouseStockWorkshopScope(string $tenantId, string $activeWorkshopId): bool
    {
        return $this->hasWarehouseStockWorkshopScope()
            && $this->hasActiveWorkshops($tenantId)
            && $activeWorkshopId !== ''
            && ! OwnerWorkshopSwitcherService::isAllWorkshopsId($activeWorkshopId);
    }

    private function hasCustomerWorkshopScope(): bool
    {
        return Schema::hasTable('customers')
            && Schema::hasColumn('customers', 'workshop_id');
    }

    private function hasUserWorkshopScope(): bool
    {
        return Schema::hasTable('users')
            && Schema::hasColumn('users', 'workshop_id');
    }

    private function hasWarehouseStockWorkshopScope(): bool
    {
        return Schema::hasTable('warehouse_spare_part_stocks')
            && Schema::hasColumn('warehouse_spare_part_stocks', 'workshop_id');
    }

    private function hasActiveWorkshops(string $tenantId): bool
    {
        if ($tenantId === '' || ! Schema::hasTable('workshops')) {
            return false;
        }

        return Workshop::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeLatestDiagnosisPayload(?ServiceOrderEstimateAiLog $aiLog): ?array
    {
        if (! $aiLog) {
            return null;
        }

        $outputPayload = is_array($aiLog->output_payload)
            ? $aiLog->output_payload
            : [];

        $summary = trim((string) ($outputPayload['summary'] ?? ''));
        $possibleCauses = collect($outputPayload['possible_causes'] ?? [])
            ->filter(fn ($cause): bool => is_array($cause))
            ->map(function (array $cause): ?array {
                $label = trim((string) ($cause['label'] ?? ''));
                if ($label === '') {
                    return null;
                }

                $severity = strtolower(trim((string) ($cause['severity'] ?? 'medium')));
                if (! in_array($severity, ['high', 'medium', 'low'], true)) {
                    $severity = 'medium';
                }

                return [
                    'label' => $label,
                    'confidence' => max(0, min(100, (int) ($cause['confidence'] ?? 0))),
                    'severity' => $severity,
                    'reason' => $this->normalizeNullableString($cause['reason'] ?? null),
                ];
            })
            ->filter(fn ($cause): bool => is_array($cause))
            ->values()
            ->take(3)
            ->all();

        $warnings = collect($outputPayload['warnings'] ?? [])
            ->map(fn ($warning): string => trim((string) $warning))
            ->filter(fn (string $warning): bool => $warning !== '')
            ->unique()
            ->values()
            ->take(6)
            ->all();

        $customerAdvice = collect($outputPayload['customer_advice'] ?? [])
            ->map(fn ($advice): string => trim((string) $advice))
            ->filter(fn (string $advice): bool => $advice !== '')
            ->unique()
            ->values()
            ->take(6)
            ->all();

        $disclaimer = $this->normalizeNullableString($outputPayload['disclaimer'] ?? null);
        $symptoms = collect($outputPayload['symptoms'] ?? [])
            ->map(fn ($symptom): string => trim((string) $symptom))
            ->filter(fn (string $symptom): bool => $symptom !== '')
            ->unique()
            ->values()
            ->take(10)
            ->all();

        if ($summary === '' && count($possibleCauses) < 1 && count($warnings) < 1) {
            return null;
        }

        return [
            'log_id' => (string) $aiLog->id,
            'summary' => $summary,
            'confidence_level' => max(0, min(100, (int) ($outputPayload['confidence_level'] ?? 0))),
            'possible_causes' => $possibleCauses,
            'warnings' => $warnings,
            'customer_advice' => $customerAdvice,
            'disclaimer' => $disclaimer,
            'symptoms' => $symptoms,
            'generated_at' => $aiLog->created_at?->toIso8601String(),
        ];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        $normalized = trim((string) $value);
        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return (int) $normalized;
    }

    private function normalizePlateNumber(mixed $value): string
    {
        $normalized = Str::upper(trim((string) $value));
        $normalized = str_replace(' ', '', $normalized);
        $normalized = preg_replace('/[^A-Z0-9]/', '', $normalized);

        return is_string($normalized) ? $normalized : '';
    }

    private function normalizeVehicleType(string $value): string
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, ['motor', 'mobil'], true) ? $normalized : 'motor';
    }

    private function normalizePhoneForLookup(mixed $value): string
    {
        $normalized = preg_replace('/\D+/', '', (string) $value);

        return is_string($normalized) ? $normalized : '';
    }

    private function resolveEstimatedFinishDate(string $serviceDate, ?int $estimatedDays): ?string
    {
        if ($estimatedDays === null) {
            return null;
        }

        try {
            $startDate = Carbon::parse($serviceDate)->startOfDay();
            $durationDays = max(1, $estimatedDays);

            return $startDate->copy()->addDays($durationDays - 1)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveCurrentUri(Request $request): string
    {
        $path = '/'.ltrim((string) $request->path(), '/');
        $queryString = trim((string) $request->getQueryString());

        return $queryString !== '' ? $path.'?'.$queryString : $path;
    }

    private function resolveSortBy(string $sortBy): string
    {
        return in_array($sortBy, ['code', 'service_date', 'status', 'created_at'], true)
            ? $sortBy
            : 'service_date';
    }

    private function resolveSortDirection(string $sortDirection): string
    {
        return strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';
    }

    private function resolvePerPage(int $perPage): int
    {
        return in_array($perPage, [10, 20, 50], true) ? $perPage : 10;
    }

    private function resolveCompletionSparePartPerPage(int $perPage): int
    {
        return in_array($perPage, [20, 50], true) ? $perPage : 20;
    }

    private function cursorPaginateCompletionSparePartsWithFallback(
        Builder $query,
        int $perPage,
        array $columns,
        string $cursor,
    ): CursorPaginator {
        $cursorValue = $cursor !== '' ? $cursor : null;

        try {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, 'completion_sparepart_cursor', $cursorValue)
                ->withQueryString();
        } catch (\Throwable) {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, 'completion_sparepart_cursor_fallback', null)
                ->withQueryString();
        }
    }

    private function cursorPaginateWithFallback(
        Builder $query,
        int $perPage,
        array $columns,
        string $cursor,
    ): CursorPaginator {
        $cursorValue = $cursor !== '' ? $cursor : null;

        try {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, 'order_cursor', $cursorValue)
                ->withQueryString();
        } catch (\Throwable) {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, 'order_cursor_fallback', null)
                ->withQueryString();
        }
    }
}
