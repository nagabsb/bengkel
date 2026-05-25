<?php

namespace App\Services\Owner;

use DateTimeInterface;
use App\Models\AiRuntimeLog;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderEstimate;
use App\Models\ServiceOrderEstimateItem;
use App\Models\ServiceOrderSparePart;
use App\Models\SparePart;
use App\Models\Tenant;
use App\Models\WarehouseSparePartStock;
use App\Models\Workshop;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class OwnerReportService
{
    private const AI_REPORT_SOURCE_ALL = 'all_sources';

    private const AI_REPORT_SOURCE_OWNER_RUNTIME = 'owner_service_runtime';

    private const AI_REPORT_SOURCE_SUPERADMIN_PROMPT_TEST = 'platform_prompt_test';

    public function __construct(
        private readonly OwnerMenuService $ownerMenuService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildPageData(
        Request $request,
        string $tenantId,
        string $reportType,
        TenantPlanResolver $planResolver,
        ?Authenticatable $user,
    ): array {
        $normalizedReportType = $this->resolveReportType($reportType);
        $reportConfig = $this->resolveReportConfig($normalizedReportType);
        $salesFilters = $this->resolveSalesFilters($request);
        $activeWorkshopId = trim((string) $request->attributes->get('tenant_workshop_id', $tenantId));
        $expenseFilters = $this->resolveExpenseReportFilters($request, $tenantId, $activeWorkshopId);
        $customerFilters = $this->resolveCustomerReportFilters($request, $tenantId, $activeWorkshopId);
        $profitLossFilters = $this->resolveProfitLossFilters($request, $tenantId, $activeWorkshopId);
        $sparePartFilters = $this->resolveSparePartReportFilters($request, $tenantId, $activeWorkshopId);
        $aiMonthlyFilters = $this->resolveAiMonthlyFilters($request, $tenantId, $activeWorkshopId);

        $package = $planResolver->forTenantId($tenantId);
        $hasAiFeature = (bool) data_get($package, 'plan.has_ai_feature', false);
        $planId = data_get($package, 'plan.id');

        if (! $hasAiFeature && $normalizedReportType === 'ai_monthly') {
            $reportConfig['description'] = 'Fitur AI belum tersedia pada paket aktif. Upgrade paket untuk membuka laporan AI bulanan.';
        }

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

        $summary = match ($normalizedReportType) {
            'spareparts' => $this->buildSparePartSummary($tenantId, $sparePartFilters),
            'expenses' => $this->buildExpenseSummary($tenantId, $expenseFilters),
            'customers' => $this->buildCustomerSummary($tenantId, $customerFilters),
            'profit_loss' => $this->buildProfitLossSummary($tenantId, $profitLossFilters),
            'ai_monthly' => $hasAiFeature
                ? $this->buildAiMonthlySummary($tenantId, $aiMonthlyFilters)
                : $this->defaultSummary('Fitur AI belum tersedia pada paket aktif.'),
            default => $this->buildSalesSummary($tenantId),
        };

        $salesReportPayload = $this->defaultSalesReportPayload(
            perPage: $salesFilters['per_page'],
        );

        if ($normalizedReportType === 'sales') {
            $salesReportPayload = $this->buildSalesReportPayload($tenantId, $salesFilters);
        }

        $expenseReportPayload = $this->defaultExpenseReportPayload(
            perPage: $expenseFilters['per_page'],
        );
        $expenseCategoryOptions = [];

        if ($normalizedReportType === 'expenses') {
            $expenseReportPayload = $this->buildExpenseReportPayload($tenantId, $expenseFilters);
            $expenseCategoryOptions = $this->resolveExpenseCategoryOptions($tenantId, $expenseFilters);
        }

        $customerReportPayload = $this->defaultCustomerReportPayload(
            perPage: $customerFilters['per_page'],
        );

        if ($normalizedReportType === 'customers') {
            $customerReportPayload = $this->buildCustomerReportPayload($tenantId, $customerFilters);
        }

        $profitLossReportPayload = $this->defaultProfitLossReportPayload();

        if ($normalizedReportType === 'profit_loss') {
            $profitLossReportPayload = $this->buildProfitLossReportPayload($tenantId, $profitLossFilters);
        }

        $sparePartReportPayload = $this->defaultSparePartReportPayload(
            perPage: $sparePartFilters['per_page'],
        );
        $sparePartReorderInsights = $this->defaultSparePartReorderInsights();

        if ($normalizedReportType === 'spareparts') {
            $sparePartReportPayload = $this->buildSparePartReportPayload($tenantId, $sparePartFilters);
            $sparePartReorderInsights = $hasAiFeature
                ? $this->buildSparePartReorderInsights($tenantId, $sparePartFilters)
                : $this->defaultSparePartReorderInsights(
                    'Prediksi reorder AI belum tersedia pada paket aktif. Silakan upgrade paket untuk mengaktifkan fitur ini.',
                );
        }

        $aiMonthlyReportPayload = $this->defaultAiMonthlyReportPayload(
            perPage: $aiMonthlyFilters['per_page'],
        );
        $aiMonthlyInsights = $this->defaultAiMonthlyInsights();
        $aiMonthlyBusinessReport = $this->defaultAiMonthlyBusinessReport();

        if ($normalizedReportType === 'ai_monthly') {
            if ($hasAiFeature) {
                $aiMonthlyReportPayload = $this->buildAiMonthlyReportPayload($tenantId, $aiMonthlyFilters);
                $aiMonthlyInsights = $this->buildAiMonthlyInsights($tenantId, $aiMonthlyFilters);
                $aiMonthlyBusinessReport = $this->buildAiMonthlyBusinessReport($tenantId, $aiMonthlyFilters);
            } else {
                $aiMonthlyBusinessReport = $this->defaultAiMonthlyBusinessReport(
                    'Fitur AI belum tersedia pada paket aktif. Upgrade paket untuk membuka laporan AI bulanan.',
                );
            }
        }

        return [
            'tenantId' => $tenantId,
            'tenantProfile' => $this->resolveTenantProfile($tenantId),
            'package' => $package,
            'menuItems' => $menuItems,
            'reportType' => $normalizedReportType,
            'reportConfig' => $reportConfig,
            'aiFeatureEnabled' => $hasAiFeature,
            'reportSummary' => $summary,
            'reportGeneratedAt' => now()->toDateTimeString(),
            'salesReports' => $salesReportPayload,
            'salesReportFilters' => [
                'search' => $salesFilters['search'],
                'sort_by' => $salesFilters['sort_by'],
                'sort_dir' => $salesFilters['sort_dir'],
                'per_page' => $salesFilters['per_page'],
                'cursor' => $salesReportPayload['current_cursor'],
            ],
            'expenseReports' => $expenseReportPayload,
            'expenseReportFilters' => [
                'search' => $expenseFilters['search'],
                'workshop_id' => $expenseFilters['workshop_id'],
                'category' => $expenseFilters['category'],
                'sort_by' => $expenseFilters['sort_by'],
                'sort_dir' => $expenseFilters['sort_dir'],
                'per_page' => $expenseFilters['per_page'],
                'cursor' => $expenseReportPayload['current_cursor'],
            ],
            'expenseWorkshopOptions' => $this->resolveAiMonthlyWorkshopOptions($request, $tenantId, $activeWorkshopId),
            'expenseCategoryOptions' => $expenseCategoryOptions,
            'customerReports' => $customerReportPayload,
            'customerReportFilters' => [
                'search' => $customerFilters['search'],
                'workshop_id' => $customerFilters['workshop_id'],
                'status' => $customerFilters['status'],
                'contact' => $customerFilters['contact'],
                'sort_by' => $customerFilters['sort_by'],
                'sort_dir' => $customerFilters['sort_dir'],
                'per_page' => $customerFilters['per_page'],
                'cursor' => $customerReportPayload['current_cursor'],
            ],
            'customerWorkshopOptions' => $this->resolveAiMonthlyWorkshopOptions($request, $tenantId, $activeWorkshopId),
            'profitLossReport' => $profitLossReportPayload,
            'profitLossReportFilters' => [
                'workshop_id' => $profitLossFilters['workshop_id'],
            ],
            'profitLossWorkshopOptions' => $this->resolveAiMonthlyWorkshopOptions($request, $tenantId, $activeWorkshopId),
            'sparePartReports' => $sparePartReportPayload,
            'sparePartReportFilters' => [
                'search' => $sparePartFilters['search'],
                'workshop_id' => $sparePartFilters['workshop_id'],
                'sort_by' => $sparePartFilters['sort_by'],
                'sort_dir' => $sparePartFilters['sort_dir'],
                'per_page' => $sparePartFilters['per_page'],
                'cursor' => $sparePartReportPayload['current_cursor'],
            ],
            'sparePartWorkshopOptions' => $this->resolveAiMonthlyWorkshopOptions($request, $tenantId, $activeWorkshopId),
            'sparePartReorderInsights' => $sparePartReorderInsights,
            'aiMonthlyReports' => $aiMonthlyReportPayload,
            'aiMonthlyReportFilters' => [
                'search' => $aiMonthlyFilters['search'],
                'source' => $aiMonthlyFilters['source'],
                'workshop_id' => $aiMonthlyFilters['workshop_id'],
                'sort_by' => $aiMonthlyFilters['sort_by'],
                'sort_dir' => $aiMonthlyFilters['sort_dir'],
                'per_page' => $aiMonthlyFilters['per_page'],
                'cursor' => $aiMonthlyReportPayload['current_cursor'],
            ],
            'aiMonthlyReportSources' => $this->resolveAiMonthlySourceOptions(),
            'aiMonthlyWorkshopOptions' => $this->resolveAiMonthlyWorkshopOptions($request, $tenantId, $activeWorkshopId),
            'aiMonthlyInsights' => $aiMonthlyInsights,
            'aiMonthlyBusinessReport' => $aiMonthlyBusinessReport,
        ];
    }

    /**
     * @return array{name: string, phone: string, address: string}
     */
    private function resolveTenantProfile(string $tenantId): array
    {
        if ($tenantId === '' || ! Schema::hasTable('tenants')) {
            return [
                'name' => '',
                'phone' => '',
                'address' => '',
            ];
        }

        $columns = ['id', 'name'];
        if (Schema::hasColumn('tenants', 'phone')) {
            $columns[] = 'phone';
        }
        if (Schema::hasColumn('tenants', 'address')) {
            $columns[] = 'address';
        }

        $tenant = Tenant::query()
            ->where('id', $tenantId)
            ->first($columns);

        return [
            'name' => trim((string) ($tenant?->name ?? '')),
            'phone' => trim((string) ($tenant?->getAttribute('phone') ?? '')),
            'address' => trim((string) ($tenant?->getAttribute('address') ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{search: string, sort_by: string, sort_dir: string, per_page: int, cursor: string}
     */
    public function normalizeSalesFilters(array $payload): array
    {
        return [
            'search' => trim((string) ($payload['sales_report_search'] ?? '')),
            'sort_by' => $this->resolveSalesSortBy((string) ($payload['sales_report_sort_by'] ?? 'service_date')),
            'sort_dir' => $this->resolveSortDirection((string) ($payload['sales_report_sort_dir'] ?? 'desc')),
            'per_page' => $this->resolvePerPage((int) ($payload['sales_report_per_page'] ?? 10)),
            'cursor' => trim((string) ($payload['sales_report_cursor'] ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function buildSalesExportRows(string $tenantId, array $filters): LazyCollection
    {
        if (! Schema::hasTable('service_orders')) {
            return LazyCollection::make(static fn (): \Generator => yield from []);
        }

        $sortDir = (string) ($filters['sort_dir'] ?? 'desc');
        $sortableColumn = $this->resolveSalesSortableColumn((string) ($filters['sort_by'] ?? 'service_date'));

        return $this->buildSalesBaseQuery($tenantId, $filters, false)
            ->orderBy($sortableColumn, $sortDir)
            ->orderBy('service_orders.id', $sortDir)
            ->cursor()
            ->map(fn (ServiceOrder $serviceOrder): array => $this->mapSalesOrderPayload($serviceOrder, false));
    }

    public function buildSalesExportSpreadsheet(LazyCollection $rows): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Servis');

        $headers = [
            'A' => 'No',
            'B' => 'Kode Servis',
            'C' => 'Tanggal Servis',
            'D' => 'Pelanggan',
            'E' => 'Telepon',
            'F' => 'Email',
            'G' => 'Kendaraan',
            'H' => 'Nopol',
            'I' => 'Status Servis',
            'J' => 'Keluhan',
            'K' => 'Catatan Selesai',
            'L' => 'Total Servis',
            'M' => 'Kode Invoice',
            'N' => 'Status Invoice',
            'O' => 'Total Invoice',
            'P' => 'Terbayar',
            'Q' => 'Sisa',
            'R' => 'Dicatat',
        ];

        foreach ($headers as $column => $label) {
            $sheet->setCellValue($column.'1', $label);
        }

        $columnWidths = [
            'A' => 6,
            'B' => 18,
            'C' => 16,
            'D' => 24,
            'E' => 16,
            'F' => 24,
            'G' => 24,
            'H' => 14,
            'I' => 14,
            'J' => 36,
            'K' => 36,
            'L' => 16,
            'M' => 18,
            'N' => 16,
            'O' => 16,
            'P' => 16,
            'Q' => 16,
            'R' => 20,
        ];

        foreach ($columnWidths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:R1');
        $sheet->getRowDimension(1)->setRowHeight(24);

        $currentRow = 2;
        $rowNumber = 1;

        foreach ($rows as $row) {
            $invoice = is_array($row['invoice'] ?? null) ? $row['invoice'] : [];

            $sheet->setCellValue('A'.$currentRow, $rowNumber);
            $sheet->setCellValueExplicit('B'.$currentRow, $this->sanitizeExcelString($row['code'] ?? '-'), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C'.$currentRow, $this->formatExcelDateValue($row['service_date'] ?? null), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('D'.$currentRow, $this->sanitizeExcelString($row['customer_name'] ?? '-'), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('E'.$currentRow, $this->sanitizeExcelString($row['customer_phone'] ?? '-'), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('F'.$currentRow, $this->sanitizeExcelString($row['customer_email'] ?? '-'), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('G'.$currentRow, $this->sanitizeExcelString($row['vehicle_name'] ?? '-'), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('H'.$currentRow, $this->sanitizeExcelString($row['plate_number'] ?? '-'), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('I'.$currentRow, $this->sanitizeExcelString($row['status_label'] ?? 'Selesai'), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('J'.$currentRow, $this->sanitizeExcelString($row['complaint'] ?? '-'), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('K'.$currentRow, $this->sanitizeExcelString($row['completion_notes'] ?? '-'), DataType::TYPE_STRING);
            $sheet->setCellValue('L'.$currentRow, max((int) ($row['total_amount'] ?? 0), 0));
            $sheet->setCellValueExplicit('M'.$currentRow, $this->sanitizeExcelString($invoice['code'] ?? '-'), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('N'.$currentRow, $this->sanitizeExcelString($invoice['status_label'] ?? '-'), DataType::TYPE_STRING);
            $sheet->setCellValue('O'.$currentRow, max((int) ($invoice['total_amount'] ?? 0), 0));
            $sheet->setCellValue('P'.$currentRow, max((int) ($invoice['paid_amount'] ?? 0), 0));
            $sheet->setCellValue('Q'.$currentRow, max((int) ($invoice['remaining_amount'] ?? 0), 0));
            $sheet->setCellValueExplicit('R'.$currentRow, $this->formatExcelDateTimeValue($row['created_at'] ?? null), DataType::TYPE_STRING);

            $currentRow++;
            $rowNumber++;
        }

        $lastDataRow = max(1, $currentRow - 1);
        $sheet->getStyle('A1:R1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF0F766E'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF0B5E57'],
                ],
            ],
        ]);

        if ($lastDataRow >= 2) {
            $sheet->getStyle("A2:R{$lastDataRow}")->applyFromArray([
                'font' => [
                    'size' => 10,
                    'color' => ['argb' => 'FF1E293B'],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_TOP,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FFE2E8F0'],
                    ],
                ],
            ]);

            $sheet->getStyle("A2:A{$lastDataRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->getStyle("L2:L{$lastDataRow}")
                ->getNumberFormat()
                ->setFormatCode('"Rp" #,##0');

            $sheet->getStyle("O2:Q{$lastDataRow}")
                ->getNumberFormat()
                ->setFormatCode('"Rp" #,##0');

            $sheet->getStyle("L2:L{$lastDataRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("O2:Q{$lastDataRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $sheet->getStyle("J2:K{$lastDataRow}")
                ->getAlignment()
                ->setWrapText(true);

            for ($rowIndex = 2; $rowIndex <= $lastDataRow; $rowIndex++) {
                if ($rowIndex % 2 !== 0) {
                    continue;
                }

                $sheet->getStyle("A{$rowIndex}:R{$rowIndex}")
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB('FFF8FAFC');
            }
        }

        return $spreadsheet;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildSalesReportPayload(string $tenantId, array $filters): array
    {
        if (! Schema::hasTable('service_orders')) {
            return $this->defaultSalesReportPayload(perPage: (int) ($filters['per_page'] ?? 10));
        }

        $sortDir = (string) ($filters['sort_dir'] ?? 'desc');
        $perPage = (int) ($filters['per_page'] ?? 10);
        $cursor = (string) ($filters['cursor'] ?? '');
        $sortableColumn = $this->resolveSalesSortableColumn((string) ($filters['sort_by'] ?? 'service_date'));
        $baseQuery = $this->buildSalesBaseQuery($tenantId, $filters, true);

        $totalRows = (int) (clone $baseQuery)->count();

        $paginator = $this->cursorPaginateWithFallback(
            query: (clone $baseQuery)
                ->orderBy($sortableColumn, $sortDir)
                ->orderBy('service_orders.id', $sortDir),
            perPage: $perPage,
            columns: [
                'service_orders.id',
                'service_orders.code',
                'service_orders.customer_id',
                'service_orders.customer_vehicle_id',
                'service_orders.service_date',
                'service_orders.status',
                'service_orders.total_amount',
                'service_orders.complaint',
                'service_orders.completion_notes',
                'service_orders.odometer',
                'service_orders.service_fee',
                'service_orders.started_at',
                'service_orders.completed_at',
                'service_orders.created_at',
            ],
            cursor: $cursor,
        );

        $rows = collect($paginator->items())
            ->map(fn (ServiceOrder $serviceOrder): array => $this->mapSalesOrderPayload($serviceOrder, true))
            ->values();

        return [
            'mode' => 'cursor',
            'data' => $rows->all(),
            'per_page' => $paginator->perPage(),
            'total' => $totalRows,
            'from' => $rows->isEmpty() ? 0 : 1,
            'to' => $rows->count(),
            'current_cursor' => $paginator->cursor()?->encode(),
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'prev_cursor' => $paginator->previousCursor()?->encode(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function buildSalesBaseQuery(string $tenantId, array $filters, bool $includeReceiptDetails = false): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $hasInvoicesTable = Schema::hasTable('invoices');
        $hasWorkshopsTable = Schema::hasTable('workshops');
        $hasInvoiceWorkshopColumn = $hasInvoicesTable && Schema::hasColumn('invoices', 'workshop_id');
        $hasServiceOrderSparePartTable = $includeReceiptDetails
            && Schema::hasTable('service_order_spare_parts')
            && Schema::hasTable('spare_parts');
        $hasEstimateTables = $includeReceiptDetails
            && Schema::hasTable('service_order_estimates')
            && Schema::hasTable('service_order_estimate_items');

        $relations = [
            'customer:id,name,phone,email,address,workshop_id',
            'vehicle:id,brand,model,plate_number',
        ];

        if ($hasInvoicesTable) {
            $invoiceColumns = [
                'id',
                'service_order_id',
                'code',
                'status',
                'total_amount',
                'paid_amount',
                'remaining_amount',
                'invoice_date',
                'due_date',
            ];

            if ($hasInvoiceWorkshopColumn) {
                $invoiceColumns[] = 'workshop_id';
            }

            $relations[] = 'invoice:'.implode(',', $invoiceColumns);
        }

        if ($hasWorkshopsTable) {
            $workshopColumns = ['id', 'name', 'code'];
            if (Schema::hasColumn('workshops', 'phone')) {
                $workshopColumns[] = 'phone';
            }
            if (Schema::hasColumn('workshops', 'address')) {
                $workshopColumns[] = 'address';
            }

            $relations['customer.workshop'] = function ($query) use ($workshopColumns): void {
                $query->select($workshopColumns);
            };

            if ($hasInvoicesTable && $hasInvoiceWorkshopColumn) {
                $relations['invoice.workshop'] = function ($query) use ($workshopColumns): void {
                    $query->select($workshopColumns);
                };
            }
        }

        if ($hasServiceOrderSparePartTable) {
            $relations[] = 'spareParts:id,service_order_id,spare_part_id,qty,unit_price,subtotal,notes';
            $relations[] = 'spareParts.sparePart:id,name,unit';
        }

        if ($hasEstimateTables) {
            $relations[] = 'latestEstimate';
            $relations[] = 'latestEstimate.items:id,service_order_estimate_id,item_type,label,unit_label,description,qty,unit_price,subtotal';
        }

        $query = ServiceOrder::query()
            ->with($relations)
            ->where('tenant_id', $tenantId)
            ->where('status', 'done');

        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $nestedQuery) use ($search): void {
            $nestedQuery
                ->where('service_orders.code', 'like', "%{$search}%")
                ->orWhere('service_orders.complaint', 'like', "%{$search}%")
                ->orWhereHas('customer', function (Builder $customerQuery) use ($search): void {
                    $customerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhereHas('vehicle', function (Builder $vehicleQuery) use ($search): void {
                    $vehicleQuery
                        ->where('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('plate_number', 'like', "%{$search}%");
                });
        });
    }

    private function resolveSalesSortableColumn(string $sortBy): string
    {
        return [
            'code' => 'service_orders.code',
            'service_date' => 'service_orders.service_date',
            'total_amount' => 'service_orders.total_amount',
            'created_at' => 'service_orders.created_at',
        ][$sortBy] ?? 'service_orders.service_date';
    }

    /**
     * @return array<string, mixed>
     */
    private function mapSalesOrderPayload(ServiceOrder $serviceOrder, bool $includeReceiptDetails = false): array
    {
        $vehicleBrand = trim((string) ($serviceOrder->vehicle?->brand ?? ''));
        $vehicleModel = trim((string) ($serviceOrder->vehicle?->model ?? ''));
        $vehicleLabel = trim(implode(' ', array_filter([$vehicleBrand, $vehicleModel])));
        $customer = $serviceOrder->relationLoaded('customer')
            ? $serviceOrder->getRelation('customer')
            : $serviceOrder->customer;
        $invoice = $serviceOrder->relationLoaded('invoice')
            ? $serviceOrder->getRelation('invoice')
            : null;
        $customerWorkshop = $customer instanceof Customer && $customer->relationLoaded('workshop')
            ? $customer->getRelation('workshop')
            : null;
        $invoiceWorkshop = $invoice instanceof Invoice && $invoice->relationLoaded('workshop')
            ? $invoice->getRelation('workshop')
            : null;
        $workshop = $customerWorkshop instanceof Workshop
            ? $customerWorkshop
            : ($invoiceWorkshop instanceof Workshop ? $invoiceWorkshop : null);
        $serviceFeeAmount = max((int) ($serviceOrder->service_fee ?? 0), 0);
        $totalAmount = max((int) ($serviceOrder->total_amount ?? 0), 0);

        $invoicePayload = null;
        if ($invoice instanceof Invoice) {
            $invoiceStatus = strtolower(trim((string) ($invoice->status ?? 'unpaid')));
            $invoicePayload = [
                'id' => (string) $invoice->id,
                'code' => (string) ($invoice->code ?? ''),
                'status' => $invoiceStatus,
                'status_label' => $this->resolveInvoiceStatusLabel($invoiceStatus),
                'total_amount' => max((int) ($invoice->total_amount ?? 0), 0),
                'paid_amount' => max((int) ($invoice->paid_amount ?? 0), 0),
                'remaining_amount' => max((int) ($invoice->remaining_amount ?? 0), 0),
                'invoice_date' => $invoice->invoice_date,
                'due_date' => $invoice->due_date,
            ];
        }

        $serviceItems = [];
        $sparePartItems = [];
        $subtotalServiceAmount = $serviceFeeAmount;
        $subtotalSparePartAmount = 0;
        $subtotalAmount = $totalAmount;
        $discountAmount = 0;

        if ($includeReceiptDetails) {
            $estimateItems = $this->resolveEstimateItemsFromSalesOrder($serviceOrder);
            $serviceItems = $this->resolveSalesServiceItems($estimateItems, $serviceFeeAmount);
            $sparePartItems = $this->resolveSalesSparePartItems($serviceOrder, $estimateItems);

            $subtotalServiceAmount = max((int) collect($serviceItems)
                ->sum(fn (array $item): int => max((int) ($item['subtotal'] ?? 0), 0)), 0);
            if ($subtotalServiceAmount < 1) {
                $subtotalServiceAmount = $serviceFeeAmount;
            }

            $subtotalSparePartAmount = max((int) collect($sparePartItems)
                ->sum(fn (array $item): int => max((int) ($item['subtotal'] ?? 0), 0)), 0);

            $subtotalAmount = $subtotalServiceAmount + $subtotalSparePartAmount;
            if ($subtotalAmount < 1) {
                $subtotalAmount = $totalAmount;
            }

            if ($totalAmount > 0 && $subtotalAmount > $totalAmount) {
                $discountAmount = $subtotalAmount - $totalAmount;
            }
        }

        $grandTotalAmount = $totalAmount > 0
            ? $totalAmount
            : max($subtotalAmount - $discountAmount, 0);

        return [
            'id' => (string) $serviceOrder->id,
            'code' => (string) ($serviceOrder->code ?? '-'),
            'service_date' => $serviceOrder->service_date,
            'status' => (string) ($serviceOrder->status ?? 'done'),
            'status_label' => 'Selesai',
            'total_amount' => $totalAmount,
            'service_fee' => $serviceFeeAmount,
            'odometer' => $serviceOrder->odometer !== null ? max((int) $serviceOrder->odometer, 0) : null,
            'complaint' => trim((string) ($serviceOrder->complaint ?? '')),
            'completion_notes' => trim((string) ($serviceOrder->completion_notes ?? '')),
            'started_at' => $serviceOrder->started_at,
            'completed_at' => $serviceOrder->completed_at,
            'created_at' => $serviceOrder->created_at,

            'customer_name' => (string) ($customer?->name ?? '-'),
            'customer_phone' => (string) ($customer?->phone ?? ''),
            'customer_email' => (string) ($customer?->email ?? ''),
            'customer_address' => (string) ($customer?->address ?? ''),

            'vehicle_name' => $vehicleLabel !== '' ? $vehicleLabel : '-',
            'vehicle_brand' => $vehicleBrand,
            'vehicle_model' => $vehicleModel,
            'plate_number' => (string) ($serviceOrder->vehicle?->plate_number ?? ''),

            'workshop_id' => trim((string) ($workshop?->id ?? $customer?->workshop_id ?? $invoice?->workshop_id ?? '')),
            'workshop_name' => trim((string) ($workshop?->name ?? '')),
            'workshop_code' => trim((string) ($workshop?->code ?? '')),
            'workshop_phone' => trim((string) ($workshop?->getAttribute('phone') ?? '')),
            'workshop_address' => trim((string) ($workshop?->getAttribute('address') ?? '')),

            'service_items' => $serviceItems,
            'sparepart_items' => $sparePartItems,
            'subtotal_service_amount' => $subtotalServiceAmount,
            'subtotal_sparepart_amount' => $subtotalSparePartAmount,
            'subtotal_amount' => $subtotalAmount,
            'discount_amount' => $discountAmount,
            'grand_total_amount' => $grandTotalAmount,

            'invoice' => $invoicePayload,
        ];
    }

    /**
     * @return array<int, array{
     *  item_type: string,
     *  label: string,
     *  unit_label: string,
     *  description: string,
     *  qty: int,
     *  unit_price: int,
     *  subtotal: int,
     *  notes: string
     * }>
     */
    private function resolveEstimateItemsFromSalesOrder(ServiceOrder $serviceOrder): array
    {
        $latestEstimate = $serviceOrder->relationLoaded('latestEstimate')
            ? $serviceOrder->getRelation('latestEstimate')
            : null;

        if (! $latestEstimate instanceof ServiceOrderEstimate || ! $latestEstimate->relationLoaded('items')) {
            return [];
        }

        return $latestEstimate->items
            ->map(function (ServiceOrderEstimateItem $item): array {
                $itemType = strtolower(trim((string) ($item->item_type ?? 'service')));
                $normalizedType = $itemType === 'sparepart' ? 'sparepart' : 'service';
                $label = trim((string) ($item->label ?? ''));
                $unitLabel = trim((string) ($item->unit_label ?? ''));
                $qty = max((int) ($item->qty ?? 0), 1);
                $unitPrice = max((int) ($item->unit_price ?? 0), 0);
                $subtotal = max((int) ($item->subtotal ?? 0), 0);

                if ($subtotal < 1 && $unitPrice > 0) {
                    $subtotal = $qty * $unitPrice;
                }

                return [
                    'item_type' => $normalizedType,
                    'label' => $label,
                    'unit_label' => $unitLabel,
                    'description' => trim((string) ($item->description ?? '')),
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                    'notes' => '',
                ];
            })
            ->filter(fn (array $item): bool => trim((string) ($item['label'] ?? '')) !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $estimateItems
     * @return array<int, array<string, mixed>>
     */
    private function resolveSalesServiceItems(array $estimateItems, int $serviceFeeAmount): array
    {
        $serviceItems = collect($estimateItems)
            ->filter(fn (array $item): bool => (string) ($item['item_type'] ?? '') === 'service')
            ->values();

        if ($serviceItems->isNotEmpty()) {
            return $serviceItems->all();
        }

        if ($serviceFeeAmount < 1) {
            return [];
        }

        return [[
            'item_type' => 'service',
            'label' => 'Biaya Jasa Servis',
            'unit_label' => 'jasa',
            'description' => '',
            'qty' => 1,
            'unit_price' => $serviceFeeAmount,
            'subtotal' => $serviceFeeAmount,
            'notes' => '',
        ]];
    }

    /**
     * @param  array<int, array<string, mixed>>  $estimateItems
     * @return array<int, array<string, mixed>>
     */
    private function resolveSalesSparePartItems(ServiceOrder $serviceOrder, array $estimateItems): array
    {
        if ($serviceOrder->relationLoaded('spareParts')) {
            $sparePartItems = $serviceOrder->spareParts
                ->map(function (ServiceOrderSparePart $item): array {
                    $qty = max((int) ($item->qty ?? 0), 1);
                    $unitPrice = max((int) ($item->unit_price ?? 0), 0);
                    $subtotal = max((int) ($item->subtotal ?? 0), 0);

                    if ($subtotal < 1 && $unitPrice > 0) {
                        $subtotal = $qty * $unitPrice;
                    }

                    return [
                        'item_type' => 'sparepart',
                        'label' => trim((string) ($item->sparePart?->name ?? 'Sparepart')),
                        'unit_label' => trim((string) ($item->sparePart?->unit ?? 'pcs')),
                        'description' => '',
                        'qty' => $qty,
                        'unit_price' => $unitPrice,
                        'subtotal' => $subtotal,
                        'notes' => trim((string) ($item->notes ?? '')),
                    ];
                })
                ->filter(fn (array $item): bool => trim((string) ($item['label'] ?? '')) !== '')
                ->values();

            if ($sparePartItems->isNotEmpty()) {
                return $sparePartItems->all();
            }
        }

        return collect($estimateItems)
            ->filter(fn (array $item): bool => (string) ($item['item_type'] ?? '') === 'sparepart')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildExpenseReportPayload(string $tenantId, array $filters): array
    {
        if (! Schema::hasTable('expenses')) {
            return $this->defaultExpenseReportPayload(perPage: (int) ($filters['per_page'] ?? 10));
        }

        $sortDir = (string) ($filters['sort_dir'] ?? 'desc');
        $perPage = (int) ($filters['per_page'] ?? 10);
        $cursor = (string) ($filters['cursor'] ?? '');
        $sortableColumn = $this->resolveExpenseSortableColumn((string) ($filters['sort_by'] ?? 'expense_date'));
        $baseQuery = $this->buildExpenseReportBaseQuery($tenantId, $filters);

        $totalRows = (int) (clone $baseQuery)->count('expenses.id');

        $paginator = $this->cursorPaginateWithFallbackByKey(
            query: (clone $baseQuery)
                ->orderBy($sortableColumn, $sortDir)
                ->orderBy('expenses.id', $sortDir),
            perPage: $perPage,
            columns: [
                'expenses.id',
                'expenses.workshop_id',
                'expenses.expense_date',
                'expenses.category',
                'expenses.description',
                'expenses.reference_number',
                'expenses.notes',
                'expenses.amount',
                'expenses.created_at',
            ],
            cursor: $cursor,
            cursorName: 'expense_report_cursor',
            fallbackCursorName: 'expense_report_cursor_fallback',
        );

        $rows = collect($paginator->items())
            ->map(fn (Expense $expense): array => $this->mapExpenseReportPayload($expense))
            ->values();

        return [
            'mode' => 'cursor',
            'data' => $rows->all(),
            'per_page' => $paginator->perPage(),
            'total' => $totalRows,
            'from' => $rows->isEmpty() ? 0 : 1,
            'to' => $rows->count(),
            'current_cursor' => $paginator->cursor()?->encode(),
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'prev_cursor' => $paginator->previousCursor()?->encode(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function buildExpenseReportBaseQuery(string $tenantId, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $category = trim((string) ($filters['category'] ?? ''));
        $activeWorkshopId = trim((string) ($filters['active_workshop_id'] ?? $tenantId));
        $workshopFilterId = trim((string) ($filters['workshop_id'] ?? OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID));
        if ($workshopFilterId === '') {
            $workshopFilterId = OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID;
        }

        $query = Expense::query()
            ->with(['workshop:id,name,code'])
            ->where('expenses.tenant_id', $tenantId)
            ->when($category !== '', function (Builder $categoryQuery) use ($category): void {
                $categoryQuery->where('expenses.category', $category);
            })
            ->when($search !== '', function (Builder $searchQuery) use ($search): void {
                $searchQuery->where(function (Builder $nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('expenses.description', 'like', "%{$search}%")
                        ->orWhere('expenses.category', 'like', "%{$search}%")
                        ->orWhere('expenses.reference_number', 'like', "%{$search}%")
                        ->orWhere('expenses.notes', 'like', "%{$search}%");
                });
            });

        $this->applyAiBusinessDirectWorkshopScope(
            $query,
            $activeWorkshopId,
            $workshopFilterId,
            'expenses.workshop_id',
        );

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapExpenseReportPayload(Expense $expense): array
    {
        return [
            'id' => (string) $expense->id,
            'workshop_id' => (string) ($expense->workshop_id ?? ''),
            'workshop_name' => trim((string) ($expense->workshop?->name ?? '')),
            'workshop_code' => trim((string) ($expense->workshop?->code ?? '')),
            'expense_date' => $expense->expense_date,
            'category' => (string) ($expense->category ?? ''),
            'description' => (string) ($expense->description ?? ''),
            'reference_number' => (string) ($expense->reference_number ?? ''),
            'notes' => (string) ($expense->notes ?? ''),
            'amount' => max((int) ($expense->amount ?? 0), 0),
            'created_at' => $expense->created_at,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, string>
     */
    private function resolveExpenseCategoryOptions(string $tenantId, array $filters): array
    {
        if (! Schema::hasTable('expenses')) {
            return [];
        }

        $activeWorkshopId = trim((string) ($filters['active_workshop_id'] ?? $tenantId));
        $workshopFilterId = trim((string) ($filters['workshop_id'] ?? OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID));
        if ($workshopFilterId === '') {
            $workshopFilterId = OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID;
        }

        $query = Expense::query()
            ->where('tenant_id', $tenantId);

        $this->applyAiBusinessDirectWorkshopScope(
            $query,
            $activeWorkshopId,
            $workshopFilterId,
            'workshop_id',
        );

        return $query
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->limit(100)
            ->pluck('category')
            ->map(fn (mixed $category): string => trim((string) $category))
            ->filter(fn (string $category): bool => $category !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildCustomerReportPayload(string $tenantId, array $filters): array
    {
        if (! Schema::hasTable('customers')) {
            return $this->defaultCustomerReportPayload(perPage: (int) ($filters['per_page'] ?? 10));
        }

        $sortDir = (string) ($filters['sort_dir'] ?? 'desc');
        $perPage = (int) ($filters['per_page'] ?? 10);
        $cursor = (string) ($filters['cursor'] ?? '');
        $sortableColumn = $this->resolveCustomerSortableColumn((string) ($filters['sort_by'] ?? 'created_at'));
        $baseQuery = $this->buildCustomerReportBaseQuery($tenantId, $filters);

        $totalRows = (int) (clone $baseQuery)->count('customers.id');

        $paginator = $this->cursorPaginateWithFallbackByKey(
            query: (clone $baseQuery)
                ->orderBy($sortableColumn, $sortDir)
                ->orderBy('customers.id', $sortDir),
            perPage: $perPage,
            columns: [
                'customers.id',
                'customers.workshop_id',
                'customers.name',
                'customers.phone',
                'customers.email',
                'customers.address',
                'customers.notes',
                'customers.is_active',
                'customers.created_at',
                'customers.updated_at',
            ],
            cursor: $cursor,
            cursorName: 'customer_report_cursor',
            fallbackCursorName: 'customer_report_cursor_fallback',
        );

        $rows = collect($paginator->items())
            ->map(fn (Customer $customer): array => $this->mapCustomerReportPayload($customer))
            ->values();

        return [
            'mode' => 'cursor',
            'data' => $rows->all(),
            'per_page' => $paginator->perPage(),
            'total' => $totalRows,
            'from' => $rows->isEmpty() ? 0 : 1,
            'to' => $rows->count(),
            'current_cursor' => $paginator->cursor()?->encode(),
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'prev_cursor' => $paginator->previousCursor()?->encode(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function buildCustomerReportBaseQuery(string $tenantId, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $this->resolveCustomerReportStatus((string) ($filters['status'] ?? 'all'));
        $contact = $this->resolveCustomerReportContact((string) ($filters['contact'] ?? 'all'));
        $activeWorkshopId = trim((string) ($filters['active_workshop_id'] ?? $tenantId));
        $workshopFilterId = trim((string) ($filters['workshop_id'] ?? OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID));
        if ($workshopFilterId === '') {
            $workshopFilterId = OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID;
        }

        $query = Customer::query()
            ->with(['workshop:id,name,code'])
            ->where('customers.tenant_id', $tenantId)
            ->when($status === 'active', function (Builder $statusQuery): void {
                $statusQuery->where('customers.is_active', true);
            })
            ->when($status === 'inactive', function (Builder $statusQuery): void {
                $statusQuery->where('customers.is_active', false);
            })
            ->when($search !== '', function (Builder $searchQuery) use ($search): void {
                $searchQuery->where(function (Builder $nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('customers.name', 'like', "%{$search}%")
                        ->orWhere('customers.phone', 'like', "%{$search}%")
                        ->orWhere('customers.email', 'like', "%{$search}%")
                        ->orWhere('customers.address', 'like', "%{$search}%")
                        ->orWhere('customers.notes', 'like', "%{$search}%");
                });
            })
            ->when($contact === 'complete', function (Builder $contactQuery): void {
                $contactQuery
                    ->whereNotNull('customers.phone')
                    ->where('customers.phone', '!=', '')
                    ->whereNotNull('customers.email')
                    ->where('customers.email', '!=', '');
            })
            ->when($contact === 'phone_only', function (Builder $contactQuery): void {
                $contactQuery
                    ->whereNotNull('customers.phone')
                    ->where('customers.phone', '!=', '')
                    ->where(function (Builder $emailQuery): void {
                        $emailQuery
                            ->whereNull('customers.email')
                            ->orWhere('customers.email', '');
                    });
            })
            ->when($contact === 'email_only', function (Builder $contactQuery): void {
                $contactQuery
                    ->whereNotNull('customers.email')
                    ->where('customers.email', '!=', '')
                    ->where(function (Builder $phoneQuery): void {
                        $phoneQuery
                            ->whereNull('customers.phone')
                            ->orWhere('customers.phone', '');
                    });
            })
            ->when($contact === 'missing', function (Builder $contactQuery): void {
                $contactQuery->where(function (Builder $missingQuery): void {
                    $missingQuery
                        ->where(function (Builder $phoneQuery): void {
                            $phoneQuery
                                ->whereNull('customers.phone')
                                ->orWhere('customers.phone', '');
                        })
                        ->where(function (Builder $emailQuery): void {
                            $emailQuery
                                ->whereNull('customers.email')
                                ->orWhere('customers.email', '');
                        });
                });
            });

        $this->applyCustomerReportWorkshopScope($query, $activeWorkshopId, $workshopFilterId);

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCustomerReportPayload(Customer $customer): array
    {
        $hasPhone = trim((string) ($customer->phone ?? '')) !== '';
        $hasEmail = trim((string) ($customer->email ?? '')) !== '';
        $contactQuality = 'missing';
        $contactQualityLabel = 'Belum Lengkap';

        if ($hasPhone && $hasEmail) {
            $contactQuality = 'complete';
            $contactQualityLabel = 'Lengkap';
        } elseif ($hasPhone) {
            $contactQuality = 'phone_only';
            $contactQualityLabel = 'Hanya Telepon';
        } elseif ($hasEmail) {
            $contactQuality = 'email_only';
            $contactQualityLabel = 'Hanya Email';
        }

        return [
            'id' => (string) $customer->id,
            'workshop_id' => (string) ($customer->workshop_id ?? ''),
            'workshop_name' => trim((string) ($customer->workshop?->name ?? '')),
            'workshop_code' => trim((string) ($customer->workshop?->code ?? '')),
            'name' => (string) ($customer->name ?? ''),
            'phone' => (string) ($customer->phone ?? ''),
            'email' => (string) ($customer->email ?? ''),
            'address' => (string) ($customer->address ?? ''),
            'notes' => (string) ($customer->notes ?? ''),
            'is_active' => (bool) $customer->is_active,
            'status_label' => (bool) $customer->is_active ? 'Aktif' : 'Nonaktif',
            'contact_quality' => $contactQuality,
            'contact_quality_label' => $contactQualityLabel,
            'created_at' => $customer->created_at,
            'updated_at' => $customer->updated_at,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildProfitLossSummary(string $tenantId, array $filters = []): array
    {
        $metrics = $this->buildProfitLossMetrics($tenantId, $filters);

        return [
            'cards' => [
                ['key' => 'pl_total_revenue', 'label' => 'Total Pendapatan', 'value' => $metrics['total_revenue'], 'format' => 'currency'],
                ['key' => 'pl_total_expense', 'label' => 'Total Beban', 'value' => $metrics['total_expense'], 'format' => 'currency'],
                ['key' => 'pl_gross_profit', 'label' => 'Laba Kotor', 'value' => $metrics['gross_profit'], 'format' => 'currency'],
                ['key' => 'pl_net_profit', 'label' => 'Laba Bersih', 'value' => $metrics['net_profit'], 'format' => 'currency'],
            ],
            'highlights' => [
                "Periode laporan: {$metrics['period_label']} ({$metrics['scope_label']}).",
                'Pendapatan jasa berasal dari service_orders.service_fee (status done).',
                'Pendapatan sparepart berasal dari service_order_spare_parts.subtotal (order done).',
                'HPP sparepart dihitung dari qty x spare_parts.purchase_price.',
                'Beban operasional diambil dari tabel expenses (expense_date periode aktif).',
                'Margin bersih: '.number_format((float) $metrics['net_margin_pct'], 1, ',', '.').'%.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildProfitLossReportPayload(string $tenantId, array $filters = []): array
    {
        $metrics = $this->buildProfitLossMetrics($tenantId, $filters);

        return [
            'period_label' => $metrics['period_label'],
            'scope_label' => $metrics['scope_label'],
            'rows' => [
                [
                    'id' => 'revenue_service',
                    'group' => 'Pendapatan',
                    'label' => 'Pendapatan Jasa Servis',
                    'formula' => 'service_orders.service_fee (status done)',
                    'amount' => $metrics['service_revenue'],
                ],
                [
                    'id' => 'revenue_sparepart',
                    'group' => 'Pendapatan',
                    'label' => 'Pendapatan Sparepart',
                    'formula' => 'service_order_spare_parts.subtotal (order done)',
                    'amount' => $metrics['sparepart_revenue'],
                ],
                [
                    'id' => 'revenue_total',
                    'group' => 'Pendapatan',
                    'label' => 'Total Pendapatan',
                    'formula' => 'Pendapatan jasa + pendapatan sparepart',
                    'amount' => $metrics['total_revenue'],
                ],
                [
                    'id' => 'cogs_sparepart',
                    'group' => 'Beban',
                    'label' => 'HPP Sparepart Terjual',
                    'formula' => 'service_order_spare_parts.qty x spare_parts.purchase_price',
                    'amount' => $metrics['sparepart_cogs'],
                ],
                [
                    'id' => 'gross_profit',
                    'group' => 'Laba',
                    'label' => 'Laba Kotor',
                    'formula' => 'Total pendapatan - HPP sparepart',
                    'amount' => $metrics['gross_profit'],
                ],
                [
                    'id' => 'operational_expense',
                    'group' => 'Beban',
                    'label' => 'Beban Operasional',
                    'formula' => 'expenses.amount (sesuai periode & cabang)',
                    'amount' => $metrics['operational_expense'],
                ],
                [
                    'id' => 'net_profit',
                    'group' => 'Laba',
                    'label' => 'Laba Bersih',
                    'formula' => 'Laba kotor - beban operasional',
                    'amount' => $metrics['net_profit'],
                ],
            ],
            'summary' => [
                'total_revenue' => $metrics['total_revenue'],
                'total_expense' => $metrics['total_expense'],
                'gross_profit' => $metrics['gross_profit'],
                'net_profit' => $metrics['net_profit'],
                'net_margin_pct' => $metrics['net_margin_pct'],
                'completed_orders' => $metrics['completed_orders'],
                'avg_ticket' => $metrics['avg_ticket'],
                'service_revenue' => $metrics['service_revenue'],
                'sparepart_revenue' => $metrics['sparepart_revenue'],
                'sparepart_cogs' => $metrics['sparepart_cogs'],
                'operational_expense' => $metrics['operational_expense'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int|float|string>
     */
    private function buildProfitLossMetrics(string $tenantId, array $filters = []): array
    {
        $activeWorkshopId = trim((string) ($filters['active_workshop_id'] ?? $tenantId));
        $workshopFilterId = trim((string) ($filters['workshop_id'] ?? OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID));
        if ($workshopFilterId === '') {
            $workshopFilterId = OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID;
        }

        [$periodStart, $periodEnd] = $this->resolveAiMonthlyPeriodRange();
        $scopeLabel = $this->resolveAiBusinessScopeLabel($tenantId, $activeWorkshopId, $workshopFilterId);
        $periodLabel = sprintf('%s - %s', $periodStart->format('d/m/Y'), $periodEnd->format('d/m/Y'));

        $serviceRevenue = 0;
        $orderTotalRevenue = 0;
        $sparepartRevenue = 0;
        $sparepartCogs = 0;
        $completedOrders = 0;

        if (Schema::hasTable('service_orders')) {
            $completedOrdersQuery = ServiceOrder::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'done')
                ->whereDate('service_date', '>=', $periodStart->toDateString())
                ->whereDate('service_date', '<=', $periodEnd->toDateString());
            $this->applyAiBusinessOrderWorkshopScope($completedOrdersQuery, $activeWorkshopId, $workshopFilterId);

            $completedOrders = (int) (clone $completedOrdersQuery)->count();

            if (Schema::hasColumn('service_orders', 'service_fee')) {
                $serviceRevenue = max((int) ((clone $completedOrdersQuery)->sum('service_fee') ?? 0), 0);
            }

            if (Schema::hasColumn('service_orders', 'total_amount')) {
                $orderTotalRevenue = max((int) ((clone $completedOrdersQuery)->sum('total_amount') ?? 0), 0);
            }

            if (Schema::hasTable('service_order_spare_parts')) {
                $sparepartQuery = ServiceOrderSparePart::query()
                    ->where('service_order_spare_parts.tenant_id', $tenantId)
                    ->whereHas('serviceOrder', function (Builder $query) use ($periodStart, $periodEnd): void {
                        $query
                            ->where('status', 'done')
                            ->whereDate('service_date', '>=', $periodStart->toDateString())
                            ->whereDate('service_date', '<=', $periodEnd->toDateString());
                    });
                $this->applyAiBusinessDirectWorkshopScope(
                    $sparepartQuery,
                    $activeWorkshopId,
                    $workshopFilterId,
                    'service_order_spare_parts.workshop_id',
                );

                $sparepartRevenue = max((int) ((clone $sparepartQuery)->sum('subtotal') ?? 0), 0);

                if (Schema::hasTable('spare_parts')) {
                    $sparepartCogs = max((int) ((clone $sparepartQuery)
                        ->leftJoin('spare_parts', function ($join): void {
                            $join->on('spare_parts.id', '=', 'service_order_spare_parts.spare_part_id')
                                ->on('spare_parts.tenant_id', '=', 'service_order_spare_parts.tenant_id');
                        })
                        ->selectRaw('SUM(service_order_spare_parts.qty * COALESCE(spare_parts.purchase_price, 0)) as total_cogs')
                        ->value('total_cogs') ?? 0), 0);
                }
            }
        }

        if ($serviceRevenue <= 0 && $orderTotalRevenue > 0) {
            $serviceRevenue = max($orderTotalRevenue - $sparepartRevenue, 0);
        }

        $operationalExpense = 0;
        if (Schema::hasTable('expenses')) {
            $expenseQuery = Expense::query()
                ->where('tenant_id', $tenantId)
                ->whereDate('expense_date', '>=', $periodStart->toDateString())
                ->whereDate('expense_date', '<=', $periodEnd->toDateString());
            $this->applyAiBusinessDirectWorkshopScope(
                $expenseQuery,
                $activeWorkshopId,
                $workshopFilterId,
                'workshop_id',
            );

            $operationalExpense = max((int) ($expenseQuery->sum('amount') ?? 0), 0);
        }

        $totalRevenue = max($serviceRevenue + $sparepartRevenue, $orderTotalRevenue, 0);
        $totalExpense = max($sparepartCogs + $operationalExpense, 0);
        $grossProfit = $totalRevenue - $sparepartCogs;
        $netProfit = $grossProfit - $operationalExpense;
        $avgTicket = $completedOrders > 0
            ? (int) floor($totalRevenue / $completedOrders)
            : 0;
        $netMarginPct = $totalRevenue > 0
            ? round(($netProfit / $totalRevenue) * 100, 1)
            : 0.0;

        return [
            'period_label' => $periodLabel,
            'scope_label' => $scopeLabel,
            'service_revenue' => $serviceRevenue,
            'sparepart_revenue' => $sparepartRevenue,
            'total_revenue' => $totalRevenue,
            'sparepart_cogs' => $sparepartCogs,
            'operational_expense' => $operationalExpense,
            'total_expense' => $totalExpense,
            'gross_profit' => $grossProfit,
            'net_profit' => $netProfit,
            'net_margin_pct' => $netMarginPct,
            'completed_orders' => $completedOrders,
            'avg_ticket' => $avgTicket,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildSparePartReportPayload(string $tenantId, array $filters): array
    {
        if (! Schema::hasTable('spare_parts')) {
            return $this->defaultSparePartReportPayload(perPage: (int) ($filters['per_page'] ?? 10));
        }

        $sortDir = (string) ($filters['sort_dir'] ?? 'desc');
        $perPage = (int) ($filters['per_page'] ?? 10);
        $cursor = (string) ($filters['cursor'] ?? '');
        $sortableColumn = $this->resolveSparePartSortableColumn((string) ($filters['sort_by'] ?? 'created_at'));
        $baseQuery = $this->buildSparePartReportBaseQuery($tenantId, $filters);

        $totalRows = (int) (clone $baseQuery)->count('spare_parts.id');

        $paginator = $this->cursorPaginateWithFallbackByKey(
            query: (clone $baseQuery)
                ->orderBy($sortableColumn, $sortDir)
                ->orderBy('spare_parts.id', $sortDir),
            perPage: $perPage,
            columns: ['*'],
            cursor: $cursor,
            cursorName: 'sparepart_report_cursor',
            fallbackCursorName: 'sparepart_report_cursor_fallback',
        );

        $rows = collect($paginator->items())
            ->map(fn (SparePart $sparePart): array => $this->mapSparePartReportPayload($sparePart))
            ->values();

        return [
            'mode' => 'cursor',
            'data' => $rows->all(),
            'per_page' => $paginator->perPage(),
            'total' => $totalRows,
            'from' => $rows->isEmpty() ? 0 : 1,
            'to' => $rows->count(),
            'current_cursor' => $paginator->cursor()?->encode(),
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'prev_cursor' => $paginator->previousCursor()?->encode(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function buildSparePartReportBaseQuery(string $tenantId, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $activeWorkshopId = trim((string) ($filters['active_workshop_id'] ?? $tenantId));
        $workshopFilterId = trim((string) ($filters['workshop_id'] ?? OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID));
        if ($workshopFilterId === '') {
            $workshopFilterId = OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID;
        }

        $baseQuery = SparePart::query()
            ->with(['supplier:id,name'])
            ->where('spare_parts.tenant_id', $tenantId)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('spare_parts.name', 'like', "%{$search}%")
                        ->orWhere('spare_parts.sku', 'like', "%{$search}%")
                        ->orWhere('spare_parts.category', 'like', "%{$search}%")
                        ->orWhere('spare_parts.unit', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function (Builder $supplierQuery) use ($search): void {
                            $supplierQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->select([
                'spare_parts.id',
                'spare_parts.supplier_id',
                'spare_parts.name',
                'spare_parts.sku',
                'spare_parts.category',
                'spare_parts.unit',
                'spare_parts.purchase_price',
                'spare_parts.selling_price',
                'spare_parts.is_active',
                'spare_parts.created_at',
            ]);

        $stockExpression = 'COALESCE(spare_parts.stock, 0)';
        $minimumStockExpression = 'COALESCE(spare_parts.minimum_stock, 0)';

        if (Schema::hasTable('warehouse_spare_part_stocks')) {
            $stockAggregateQuery = $this->buildSparePartStockAggregateQuery(
                $tenantId,
                $activeWorkshopId,
                $workshopFilterId,
            );

            $baseQuery
                ->leftJoinSub($stockAggregateQuery, 'sparepart_stock_agg', function ($join): void {
                    $join->on('sparepart_stock_agg.spare_part_id', '=', 'spare_parts.id');
                })
                ->selectRaw('COALESCE(sparepart_stock_agg.total_stock, spare_parts.stock, 0) as stock_total')
                ->selectRaw('COALESCE(sparepart_stock_agg.total_minimum_stock, spare_parts.minimum_stock, 0) as minimum_stock_total');

            $stockExpression = 'COALESCE(sparepart_stock_agg.total_stock, spare_parts.stock, 0)';
            $minimumStockExpression = 'COALESCE(sparepart_stock_agg.total_minimum_stock, spare_parts.minimum_stock, 0)';
        } else {
            $baseQuery
                ->selectRaw('COALESCE(spare_parts.stock, 0) as stock_total')
                ->selectRaw('COALESCE(spare_parts.minimum_stock, 0) as minimum_stock_total');
        }

        if (Schema::hasTable('service_order_spare_parts') && Schema::hasTable('service_orders')) {
            $usageAggregateQuery = $this->buildSparePartUsageAggregateQuery(
                $tenantId,
                $activeWorkshopId,
                $workshopFilterId,
            );

            $baseQuery
                ->leftJoinSub($usageAggregateQuery, 'sparepart_usage_agg', function ($join): void {
                    $join->on('sparepart_usage_agg.spare_part_id', '=', 'spare_parts.id');
                })
                ->selectRaw('COALESCE(sparepart_usage_agg.used_qty, 0) as used_qty')
                ->selectRaw('COALESCE(sparepart_usage_agg.usage_revenue, 0) as usage_revenue')
                ->selectRaw('COALESCE(sparepart_usage_agg.service_order_count, 0) as service_order_count');
        } else {
            $baseQuery
                ->selectRaw('0 as used_qty')
                ->selectRaw('0 as usage_revenue')
                ->selectRaw('0 as service_order_count');
        }

        $baseQuery->selectRaw(
            "CASE
                WHEN spare_parts.is_active = 0 THEN 0
                WHEN {$minimumStockExpression} > 0 AND {$stockExpression} <= {$minimumStockExpression} THEN 1
                ELSE 2
            END as stock_status_rank",
        );

        return $baseQuery;
    }

    private function buildSparePartUsageAggregateQuery(
        string $tenantId,
        string $activeWorkshopId,
        string $workshopFilterId,
    ): Builder {
        $usageQuery = ServiceOrderSparePart::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('serviceOrder', function (Builder $serviceOrderQuery): void {
                $serviceOrderQuery->where('status', 'done');
            });

        $this->applyAiBusinessDirectWorkshopScope(
            $usageQuery,
            $activeWorkshopId,
            $workshopFilterId,
            'workshop_id',
        );

        return $usageQuery
            ->selectRaw('spare_part_id')
            ->selectRaw('COALESCE(SUM(qty), 0) as used_qty')
            ->selectRaw('COALESCE(SUM(subtotal), 0) as usage_revenue')
            ->selectRaw('COUNT(DISTINCT service_order_id) as service_order_count')
            ->groupBy('spare_part_id');
    }

    private function buildSparePartStockAggregateQuery(
        string $tenantId,
        string $activeWorkshopId,
        string $workshopFilterId,
    ): Builder {
        $stockQuery = WarehouseSparePartStock::query()
            ->where('tenant_id', $tenantId);

        $this->applyAiBusinessDirectWorkshopScope(
            $stockQuery,
            $activeWorkshopId,
            $workshopFilterId,
            'workshop_id',
        );

        return $stockQuery
            ->selectRaw('spare_part_id')
            ->selectRaw('COALESCE(SUM(stock), 0) as total_stock')
            ->selectRaw('COALESCE(SUM(minimum_stock), 0) as total_minimum_stock')
            ->groupBy('spare_part_id');
    }

    /**
     * @return array<string, mixed>
     */
    private function mapSparePartReportPayload(SparePart $sparePart): array
    {
        $stockTotal = max((int) ($sparePart->getAttribute('stock_total') ?? 0), 0);
        $minimumStockTotal = max((int) ($sparePart->getAttribute('minimum_stock_total') ?? 0), 0);
        $isActive = (bool) $sparePart->is_active;
        $isLowStock = $isActive && $minimumStockTotal > 0 && $stockTotal <= $minimumStockTotal;
        $stockStatus = ! $isActive
            ? 'inactive'
            : ($isLowStock ? 'low' : 'healthy');

        return [
            'id' => (string) $sparePart->id,
            'name' => (string) ($sparePart->name ?? '-'),
            'sku' => (string) ($sparePart->sku ?? ''),
            'category' => (string) ($sparePart->category ?? ''),
            'unit' => (string) ($sparePart->unit ?? ''),
            'supplier_name' => (string) ($sparePart->supplier?->name ?? ''),
            'purchase_price' => $sparePart->purchase_price !== null ? max((int) $sparePart->purchase_price, 0) : null,
            'selling_price' => $sparePart->selling_price !== null ? max((int) $sparePart->selling_price, 0) : null,
            'stock_total' => $stockTotal,
            'minimum_stock_total' => $minimumStockTotal,
            'used_qty' => max((int) ($sparePart->getAttribute('used_qty') ?? 0), 0),
            'usage_revenue' => max((int) ($sparePart->getAttribute('usage_revenue') ?? 0), 0),
            'service_order_count' => max((int) ($sparePart->getAttribute('service_order_count') ?? 0), 0),
            'stock_status' => $stockStatus,
            'stock_status_label' => match ($stockStatus) {
                'inactive' => 'Nonaktif',
                'low' => 'Menipis',
                default => 'Aman',
            },
            'stock_status_rank' => (int) ($sparePart->getAttribute('stock_status_rank') ?? 2),
            'is_active' => $isActive,
            'created_at' => $sparePart->created_at,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildSparePartReorderInsights(string $tenantId, array $filters = []): array
    {
        if (! Schema::hasTable('spare_parts')) {
            return $this->defaultSparePartReorderInsights('Data sparepart belum tersedia untuk prediksi reorder.');
        }

        $activeWorkshopId = trim((string) ($filters['active_workshop_id'] ?? $tenantId));
        $workshopFilterId = trim((string) ($filters['workshop_id'] ?? OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID));
        if ($workshopFilterId === '') {
            $workshopFilterId = OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID;
        }

        $scopeLabel = $this->resolveAiBusinessScopeLabel($tenantId, $activeWorkshopId, $workshopFilterId);
        $usageWindowDays = 60;
        $leadTimeDays = 14;
        $windowStartDate = now()->copy()->subDays($usageWindowDays)->toDateString();

        $baseQuery = SparePart::query()
            ->with(['supplier:id,name'])
            ->where('spare_parts.tenant_id', $tenantId);

        $stockExpression = 'COALESCE(spare_parts.stock, 0)';
        $minimumStockExpression = 'COALESCE(spare_parts.minimum_stock, 0)';

        if (Schema::hasTable('warehouse_spare_part_stocks')) {
            $stockAggregateQuery = $this->buildSparePartStockAggregateQuery(
                $tenantId,
                $activeWorkshopId,
                $workshopFilterId,
            );

            $baseQuery->leftJoinSub($stockAggregateQuery, 'sparepart_reorder_stock_agg', function ($join): void {
                $join->on('sparepart_reorder_stock_agg.spare_part_id', '=', 'spare_parts.id');
            });

            $stockExpression = 'COALESCE(sparepart_reorder_stock_agg.total_stock, spare_parts.stock, 0)';
            $minimumStockExpression = 'COALESCE(sparepart_reorder_stock_agg.total_minimum_stock, spare_parts.minimum_stock, 0)';
        }

        $usageExpression = '0';
        $orderCountExpression = '0';

        if (Schema::hasTable('service_order_spare_parts') && Schema::hasTable('service_orders')) {
            $usageAggregateQuery = $this->buildSparePartReorderUsageAggregateQuery(
                $tenantId,
                $activeWorkshopId,
                $workshopFilterId,
                $windowStartDate,
            );

            $baseQuery->leftJoinSub($usageAggregateQuery, 'sparepart_reorder_usage_agg', function ($join): void {
                $join->on('sparepart_reorder_usage_agg.spare_part_id', '=', 'spare_parts.id');
            });

            $usageExpression = 'COALESCE(sparepart_reorder_usage_agg.used_qty_window, 0)';
            $orderCountExpression = 'COALESCE(sparepart_reorder_usage_agg.service_order_count_window, 0)';
        }

        $averageDailyUsageExpression = "({$usageExpression} / {$usageWindowDays})";
        $reorderPointExpression = "({$minimumStockExpression} + CEIL({$averageDailyUsageExpression} * {$leadTimeDays}))";
        $recommendedQtyExpression = "GREATEST(({$reorderPointExpression} - {$stockExpression}), 0)";
        $stockoutDaysExpression = "CASE WHEN {$averageDailyUsageExpression} > 0 THEN FLOOR({$stockExpression} / {$averageDailyUsageExpression}) ELSE NULL END";
        $estimatedCostExpression = "({$recommendedQtyExpression} * COALESCE(spare_parts.purchase_price, 0))";

        $summary = (clone $baseQuery)
            ->selectRaw('COUNT(*) as total_items')
            ->selectRaw('SUM(CASE WHEN spare_parts.is_active = 1 THEN 1 ELSE 0 END) as active_items')
            ->selectRaw("SUM(CASE WHEN spare_parts.is_active = 1 AND {$recommendedQtyExpression} > 0 THEN 1 ELSE 0 END) as items_need_reorder")
            ->selectRaw("SUM(CASE WHEN spare_parts.is_active = 1 AND {$stockoutDaysExpression} IS NOT NULL AND {$stockoutDaysExpression} <= 7 THEN 1 ELSE 0 END) as critical_items")
            ->selectRaw("SUM(CASE WHEN spare_parts.is_active = 1 AND {$recommendedQtyExpression} > 0 THEN {$estimatedCostExpression} ELSE 0 END) as estimated_reorder_cost")
            ->first();

        $activeItems = max((int) ($summary?->getAttribute('active_items') ?? 0), 0);
        $itemsNeedReorder = max((int) ($summary?->getAttribute('items_need_reorder') ?? 0), 0);
        $criticalItems = max((int) ($summary?->getAttribute('critical_items') ?? 0), 0);
        $estimatedReorderCost = max((int) ($summary?->getAttribute('estimated_reorder_cost') ?? 0), 0);

        $rows = (clone $baseQuery)
            ->select([
                'spare_parts.id',
                'spare_parts.name',
                'spare_parts.sku',
                'spare_parts.category',
                'spare_parts.unit',
                'spare_parts.purchase_price',
                'spare_parts.selling_price',
                'spare_parts.is_active',
            ])
            ->selectRaw("{$stockExpression} as stock_total")
            ->selectRaw("{$minimumStockExpression} as minimum_stock_total")
            ->selectRaw("{$usageExpression} as used_qty_window")
            ->selectRaw("{$orderCountExpression} as service_order_count_window")
            ->selectRaw("ROUND({$averageDailyUsageExpression}, 2) as avg_daily_usage")
            ->selectRaw("{$reorderPointExpression} as reorder_point_qty")
            ->selectRaw("{$recommendedQtyExpression} as recommended_qty")
            ->selectRaw("{$stockoutDaysExpression} as estimated_stockout_days")
            ->selectRaw("{$estimatedCostExpression} as estimated_reorder_cost")
            ->where('spare_parts.is_active', true)
            ->whereRaw("{$recommendedQtyExpression} > 0")
            ->orderByRaw("CASE WHEN {$stockoutDaysExpression} IS NOT NULL AND {$stockoutDaysExpression} <= 7 THEN 0 ELSE 1 END")
            ->orderByRaw("{$recommendedQtyExpression} DESC")
            ->orderBy('spare_parts.name')
            ->limit(20)
            ->get()
            ->map(function (SparePart $sparePart): array {
                $stockTotal = max((int) ($sparePart->getAttribute('stock_total') ?? 0), 0);
                $minimumStockTotal = max((int) ($sparePart->getAttribute('minimum_stock_total') ?? 0), 0);
                $usedQtyWindow = max((int) ($sparePart->getAttribute('used_qty_window') ?? 0), 0);
                $serviceOrderCountWindow = max((int) ($sparePart->getAttribute('service_order_count_window') ?? 0), 0);
                $avgDailyUsage = max((float) ($sparePart->getAttribute('avg_daily_usage') ?? 0), 0);
                $reorderPointQty = max((int) ($sparePart->getAttribute('reorder_point_qty') ?? 0), 0);
                $recommendedQty = max((int) ($sparePart->getAttribute('recommended_qty') ?? 0), 0);

                $estimatedStockoutDaysRaw = $sparePart->getAttribute('estimated_stockout_days');
                $estimatedStockoutDays = is_numeric($estimatedStockoutDaysRaw)
                    ? max((int) $estimatedStockoutDaysRaw, 0)
                    : null;

                $priority = 'medium';
                $priorityLabel = 'Menengah';

                if ($estimatedStockoutDays !== null && $estimatedStockoutDays <= 7) {
                    $priority = 'critical';
                    $priorityLabel = 'Kritis';
                } elseif ($stockTotal <= $minimumStockTotal) {
                    $priority = 'high';
                    $priorityLabel = 'Tinggi';
                }

                return [
                    'id' => (string) $sparePart->id,
                    'name' => (string) ($sparePart->name ?? '-'),
                    'sku' => (string) ($sparePart->sku ?? ''),
                    'category' => (string) ($sparePart->category ?? ''),
                    'unit' => (string) ($sparePart->unit ?? ''),
                    'supplier_name' => (string) ($sparePart->supplier?->name ?? ''),
                    'stock_total' => $stockTotal,
                    'minimum_stock_total' => $minimumStockTotal,
                    'used_qty_window' => $usedQtyWindow,
                    'service_order_count_window' => $serviceOrderCountWindow,
                    'avg_daily_usage' => $avgDailyUsage,
                    'reorder_point_qty' => $reorderPointQty,
                    'recommended_qty' => $recommendedQty,
                    'estimated_stockout_days' => $estimatedStockoutDays,
                    'estimated_reorder_cost' => max((int) ($sparePart->getAttribute('estimated_reorder_cost') ?? 0), 0),
                    'priority' => $priority,
                    'priority_label' => $priorityLabel,
                ];
            })
            ->values();

        $emptyMessage = '';
        if ($activeItems < 1) {
            $emptyMessage = 'Belum ada sparepart aktif untuk dianalisa.';
        } elseif ($itemsNeedReorder < 1) {
            $emptyMessage = 'Belum ada item yang perlu reorder berdasarkan parameter saat ini.';
        }

        return [
            'is_available' => $activeItems > 0,
            'scope_label' => $scopeLabel,
            'usage_window_days' => $usageWindowDays,
            'lead_time_days' => $leadTimeDays,
            'summary' => [
                'items_need_reorder' => $itemsNeedReorder,
                'critical_items' => $criticalItems,
                'estimated_reorder_cost' => $estimatedReorderCost,
            ],
            'rows' => $rows->all(),
            'disclaimer' => 'Prediksi reorder berbasis pemakaian '.$usageWindowDays.' hari terakhir dan lead time '.$leadTimeDays.' hari.',
            'empty_message' => $emptyMessage,
        ];
    }

    private function buildSparePartReorderUsageAggregateQuery(
        string $tenantId,
        string $activeWorkshopId,
        string $workshopFilterId,
        string $windowStartDate,
    ): Builder {
        $usageQuery = ServiceOrderSparePart::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('serviceOrder', function (Builder $serviceOrderQuery) use ($windowStartDate): void {
                $serviceOrderQuery
                    ->where('status', 'done')
                    ->whereDate('service_date', '>=', $windowStartDate);
            });

        $this->applyAiBusinessDirectWorkshopScope(
            $usageQuery,
            $activeWorkshopId,
            $workshopFilterId,
            'workshop_id',
        );

        return $usageQuery
            ->selectRaw('spare_part_id')
            ->selectRaw('COALESCE(SUM(qty), 0) as used_qty_window')
            ->selectRaw('COUNT(DISTINCT service_order_id) as service_order_count_window')
            ->groupBy('spare_part_id');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSalesSummary(string $tenantId): array
    {
        if (! Schema::hasTable('service_orders')) {
            return $this->defaultSummary('Tabel service order belum tersedia.');
        }

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $baseQuery = ServiceOrder::query()->where('tenant_id', $tenantId);

        $totalOrders = (int) (clone $baseQuery)->count();
        $completedOrders = (int) (clone $baseQuery)->where('status', 'done')->count();
        $ongoingOrders = (int) (clone $baseQuery)->whereIn('status', ['open', 'in_progress'])->count();
        $cancelledOrders = (int) (clone $baseQuery)->where('status', 'cancelled')->count();
        $totalRevenue = (int) (clone $baseQuery)->where('status', 'done')->sum('total_amount');
        $monthRevenue = (int) (clone $baseQuery)
            ->where('status', 'done')
            ->whereBetween('service_date', [$monthStart, $monthEnd])
            ->sum('total_amount');
        $avgTicket = $completedOrders > 0 ? (int) floor($totalRevenue / $completedOrders) : 0;

        return [
            'cards' => [
                ['key' => 'total_orders', 'label' => 'Total Servis', 'value' => $totalOrders, 'format' => 'number'],
                ['key' => 'completed_orders', 'label' => 'Servis Selesai', 'value' => $completedOrders, 'format' => 'number'],
                ['key' => 'month_revenue', 'label' => 'Omzet Bulan Ini', 'value' => $monthRevenue, 'format' => 'currency'],
                ['key' => 'avg_ticket', 'label' => 'Rata-Rata per Servis', 'value' => $avgTicket, 'format' => 'currency'],
            ],
            'highlights' => [
                "Order proses: {$ongoingOrders}",
                "Order batal: {$cancelledOrders}",
                'Omzet total selesai: '.(string) $totalRevenue,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSparePartSummary(string $tenantId, array $filters = []): array
    {
        if (! Schema::hasTable('spare_parts')) {
            return $this->defaultSummary('Tabel sparepart belum tersedia.');
        }

        $activeWorkshopId = trim((string) ($filters['active_workshop_id'] ?? $tenantId));
        $workshopFilterId = trim((string) ($filters['workshop_id'] ?? OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID));
        if ($workshopFilterId === '') {
            $workshopFilterId = OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID;
        }

        $scopeLabel = $this->resolveAiBusinessScopeLabel($tenantId, $activeWorkshopId, $workshopFilterId);

        $baseQuery = SparePart::query()->where('spare_parts.tenant_id', $tenantId);
        $stockExpression = 'COALESCE(spare_parts.stock, 0)';
        $minimumStockExpression = 'COALESCE(spare_parts.minimum_stock, 0)';

        if (Schema::hasTable('warehouse_spare_part_stocks')) {
            $stockAggregateQuery = $this->buildSparePartStockAggregateQuery(
                $tenantId,
                $activeWorkshopId,
                $workshopFilterId,
            );

            $baseQuery
                ->leftJoinSub($stockAggregateQuery, 'sparepart_stock_agg_summary', function ($join): void {
                    $join->on('sparepart_stock_agg_summary.spare_part_id', '=', 'spare_parts.id');
                });

            $stockExpression = 'COALESCE(sparepart_stock_agg_summary.total_stock, spare_parts.stock, 0)';
            $minimumStockExpression = 'COALESCE(sparepart_stock_agg_summary.total_minimum_stock, spare_parts.minimum_stock, 0)';
        }

        $totalSpareParts = (int) (clone $baseQuery)->count('spare_parts.id');
        $activeSpareParts = (int) (clone $baseQuery)
            ->where('spare_parts.is_active', true)
            ->count('spare_parts.id');
        $lowStockCount = (int) (clone $baseQuery)
            ->whereRaw("{$minimumStockExpression} > 0 AND {$stockExpression} <= {$minimumStockExpression}")
            ->count('spare_parts.id');
        $totalStockUnits = (int) (clone $baseQuery)
            ->sum(DB::raw($stockExpression));

        return [
            'cards' => [
                ['key' => 'total_spareparts', 'label' => 'Total Sparepart', 'value' => $totalSpareParts, 'format' => 'number'],
                ['key' => 'active_spareparts', 'label' => 'Sparepart Aktif', 'value' => $activeSpareParts, 'format' => 'number'],
                ['key' => 'low_stock', 'label' => 'Stok Menipis', 'value' => $lowStockCount, 'format' => 'number'],
                ['key' => 'stock_units', 'label' => 'Total Unit Stok', 'value' => $totalStockUnits, 'format' => 'number'],
            ],
            'highlights' => [
                "Analisa stok pada lingkup: {$scopeLabel}.",
                'Pantau item dengan stok menipis agar tidak menghambat servis.',
                'Pastikan minimum stock setiap sparepart sudah terisi konsisten.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildExpenseSummary(string $tenantId, array $filters = []): array
    {
        if (! Schema::hasTable('expenses')) {
            return $this->defaultSummary('Tabel pengeluaran belum tersedia.');
        }

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $activeWorkshopId = trim((string) ($filters['active_workshop_id'] ?? $tenantId));
        $workshopFilterId = trim((string) ($filters['workshop_id'] ?? OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID));
        if ($workshopFilterId === '') {
            $workshopFilterId = OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID;
        }
        $categoryFilter = trim((string) ($filters['category'] ?? ''));
        $scopeLabel = $this->resolveAiBusinessScopeLabel($tenantId, $activeWorkshopId, $workshopFilterId);

        $baseQuery = Expense::query()
            ->where('tenant_id', $tenantId)
            ->when($categoryFilter !== '', function (Builder $query) use ($categoryFilter): void {
                $query->where('category', $categoryFilter);
            });
        $this->applyAiBusinessDirectWorkshopScope(
            $baseQuery,
            $activeWorkshopId,
            $workshopFilterId,
            'workshop_id',
        );

        $totalTransactions = (int) (clone $baseQuery)->count();
        $totalAmount = (int) (clone $baseQuery)->sum('amount');
        $monthAmount = (int) (clone $baseQuery)
            ->whereBetween('expense_date', [$monthStart, $monthEnd])
            ->sum('amount');
        $largestAmount = (int) (clone $baseQuery)->max('amount');
        $averageAmount = $totalTransactions > 0 ? (int) floor($totalAmount / $totalTransactions) : 0;

        return [
            'cards' => [
                ['key' => 'expense_transactions', 'label' => 'Transaksi Pengeluaran', 'value' => $totalTransactions, 'format' => 'number'],
                ['key' => 'expense_total', 'label' => 'Total Pengeluaran', 'value' => $totalAmount, 'format' => 'currency'],
                ['key' => 'expense_month', 'label' => 'Pengeluaran Bulan Ini', 'value' => $monthAmount, 'format' => 'currency'],
                ['key' => 'expense_average', 'label' => 'Rata-Rata per Transaksi', 'value' => $averageAmount, 'format' => 'currency'],
            ],
            'highlights' => [
                "Lingkup data: {$scopeLabel}",
                $categoryFilter !== '' ? "Kategori aktif: {$categoryFilter}" : 'Kategori aktif: Semua kategori',
                'Nominal transaksi terbesar: '.(string) $largestAmount,
                'Pisahkan pengeluaran rutin dan non-rutin agar evaluasi lebih mudah.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCustomerSummary(string $tenantId, array $filters = []): array
    {
        if (! Schema::hasTable('customers')) {
            return $this->defaultSummary('Tabel pelanggan belum tersedia.');
        }

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $activeWorkshopId = trim((string) ($filters['active_workshop_id'] ?? $tenantId));
        $workshopFilterId = trim((string) ($filters['workshop_id'] ?? OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID));
        if ($workshopFilterId === '') {
            $workshopFilterId = OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID;
        }
        $scopeLabel = $this->resolveAiBusinessScopeLabel($tenantId, $activeWorkshopId, $workshopFilterId);

        $baseQuery = Customer::query()->where('tenant_id', $tenantId);
        $this->applyCustomerReportWorkshopScope($baseQuery, $activeWorkshopId, $workshopFilterId);

        $totalCustomers = (int) (clone $baseQuery)->count();
        $activeCustomers = (int) (clone $baseQuery)->where('is_active', true)->count();
        $customersWithPhone = (int) (clone $baseQuery)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->count();
        $customersWithEmail = (int) (clone $baseQuery)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->count();
        $newCustomersThisMonth = (int) (clone $baseQuery)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();

        return [
            'cards' => [
                ['key' => 'total_customers', 'label' => 'Total Pelanggan', 'value' => $totalCustomers, 'format' => 'number'],
                ['key' => 'active_customers', 'label' => 'Pelanggan Aktif', 'value' => $activeCustomers, 'format' => 'number'],
                ['key' => 'new_customers', 'label' => 'Pelanggan Baru Bulan Ini', 'value' => $newCustomersThisMonth, 'format' => 'number'],
                ['key' => 'customers_with_phone', 'label' => 'Kontak Telepon Tersedia', 'value' => $customersWithPhone, 'format' => 'number'],
            ],
            'highlights' => [
                "Lingkup data: {$scopeLabel}",
                "Kontak email tersedia: {$customersWithEmail}",
                'Lengkapi data kontak untuk memudahkan follow-up servis berkala.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAiMonthlySummary(string $tenantId, array $filters = []): array
    {
        if (! Schema::hasTable('ai_runtime_logs')) {
            return [
                'cards' => [
                    ['key' => 'ai_total_requests', 'label' => 'Request AI Bulan Ini', 'value' => 0, 'format' => 'number'],
                    ['key' => 'ai_success_rate_pct', 'label' => 'Success Rate (%)', 'value' => 0, 'format' => 'number'],
                    ['key' => 'ai_total_tokens', 'label' => 'Total Token AI', 'value' => 0, 'format' => 'number'],
                    ['key' => 'ai_avg_latency_ms', 'label' => 'Rata-Rata Latensi (ms)', 'value' => 0, 'format' => 'number'],
                ],
                'highlights' => [
                    'Log AI belum tersedia.',
                ],
            ];
        }

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $monthlyQuery = AiRuntimeLog::query()
            ->whereBetween('created_at', [$monthStart, $monthEnd]);
        $this->applyAiMonthlySourceScope($monthlyQuery, $tenantId, $filters);

        $totalRequests = (int) (clone $monthlyQuery)->count();
        $successRequests = (int) (clone $monthlyQuery)->where('status', 'success')->count();
        $failedRequests = (int) (clone $monthlyQuery)->where('status', 'failed')->count();
        $estimateRequests = (int) (clone $monthlyQuery)->where('feature_key', 'service_estimate_v1')->count();
        $diagnosisRequests = (int) (clone $monthlyQuery)->where('feature_key', 'symptom_diagnosis_v1')->count();
        $totalTokens = (int) (clone $monthlyQuery)->sum('total_tokens');
        $avgLatencyMs = (int) round((float) ((clone $monthlyQuery)
            ->whereNotNull('latency_ms')
            ->avg('latency_ms') ?? 0));

        $successRate = $totalRequests > 0
            ? (int) floor(($successRequests / $totalRequests) * 100)
            : 0;

        return [
            'cards' => [
                ['key' => 'ai_total_requests', 'label' => 'Request AI Bulan Ini', 'value' => $totalRequests, 'format' => 'number'],
                ['key' => 'ai_success_rate_pct', 'label' => 'Success Rate (%)', 'value' => $successRate, 'format' => 'number'],
                ['key' => 'ai_total_tokens', 'label' => 'Total Token AI', 'value' => $totalTokens, 'format' => 'number'],
                ['key' => 'ai_avg_latency_ms', 'label' => 'Rata-Rata Latensi (ms)', 'value' => $avgLatencyMs, 'format' => 'number'],
            ],
            'highlights' => [
                "Estimasi AI bulan ini: {$estimateRequests}",
                "Diagnosa AI bulan ini: {$diagnosisRequests}",
                "Request gagal bulan ini: {$failedRequests}",
                $totalRequests === 0 ? 'Belum ada aktivitas AI di bulan ini.' : "Request sukses bulan ini: {$successRequests}",
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildAiMonthlyReportPayload(string $tenantId, array $filters): array
    {
        if (! Schema::hasTable('ai_runtime_logs')) {
            return $this->defaultAiMonthlyReportPayload(perPage: (int) ($filters['per_page'] ?? 10));
        }

        $sortDir = (string) ($filters['sort_dir'] ?? 'desc');
        $perPage = (int) ($filters['per_page'] ?? 10);
        $cursor = (string) ($filters['cursor'] ?? '');
        $sortableColumn = $this->resolveAiMonthlySortableColumn((string) ($filters['sort_by'] ?? 'created_at'));
        $baseQuery = $this->buildAiMonthlyBaseQuery($tenantId, $filters);

        $totalRows = (int) (clone $baseQuery)->count();

        $paginator = $this->cursorPaginateWithFallbackByKey(
            query: (clone $baseQuery)
                ->orderBy($sortableColumn, $sortDir)
                ->orderBy('ai_runtime_logs.id', $sortDir),
            perPage: $perPage,
            columns: [
                'ai_runtime_logs.id',
                'ai_runtime_logs.source',
                'ai_runtime_logs.service_order_id',
                'ai_runtime_logs.feature_key',
                'ai_runtime_logs.status',
                'ai_runtime_logs.requester_user_id',
                'ai_runtime_logs.total_tokens',
                'ai_runtime_logs.latency_ms',
                'ai_runtime_logs.error_message',
                'ai_runtime_logs.created_at',
            ],
            cursor: $cursor,
            cursorName: 'ai_report_cursor',
            fallbackCursorName: 'ai_report_cursor_fallback',
        );

        $rows = collect($paginator->items())
            ->map(fn (AiRuntimeLog $aiLog): array => $this->mapAiMonthlyLogPayload($aiLog))
            ->values();

        return [
            'mode' => 'cursor',
            'data' => $rows->all(),
            'per_page' => $paginator->perPage(),
            'total' => $totalRows,
            'from' => $rows->isEmpty() ? 0 : 1,
            'to' => $rows->count(),
            'current_cursor' => $paginator->cursor()?->encode(),
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'prev_cursor' => $paginator->previousCursor()?->encode(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function buildAiMonthlyBaseQuery(string $tenantId, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $query = AiRuntimeLog::query()
            ->with([
                'serviceOrder:id,code',
                'requester:id,name',
            ])
            ->whereBetween('created_at', [$monthStart, $monthEnd]);

        $this->applyAiMonthlySourceScope($query, $tenantId, $filters);

        return $query
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('ai_runtime_logs.feature_key', 'like', "%{$search}%")
                        ->orWhere('ai_runtime_logs.status', 'like', "%{$search}%")
                        ->orWhere('ai_runtime_logs.source', 'like', "%{$search}%")
                        ->orWhereHas('serviceOrder', function (Builder $serviceOrderQuery) use ($search): void {
                            $serviceOrderQuery->where('code', 'like', "%{$search}%");
                        })
                        ->orWhereHas('requester', function (Builder $generatorQuery) use ($search): void {
                            $generatorQuery->where('name', 'like', "%{$search}%");
                        });
                });
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAiMonthlyLogPayload(AiRuntimeLog $aiLog): array
    {
        $featureKey = trim((string) ($aiLog->feature_key ?? ''));
        $status = strtolower(trim((string) ($aiLog->status ?? 'failed')));
        $source = strtolower(trim((string) ($aiLog->source ?? self::AI_REPORT_SOURCE_OWNER_RUNTIME)));

        return [
            'id' => (string) $aiLog->id,
            'service_order_id' => (string) ($aiLog->service_order_id ?? ''),
            'service_order_code' => (string) ($aiLog->serviceOrder?->code ?? '-'),
            'source' => $source,
            'source_label' => $this->resolveAiReportSourceLabel($source),
            'feature_key' => $featureKey,
            'feature_label' => $this->resolveAiFeatureLabel($featureKey),
            'status' => $status,
            'status_label' => $this->resolveAiStatusLabel($status),
            'total_tokens' => max((int) ($aiLog->total_tokens ?? 0), 0),
            'latency_ms' => $aiLog->latency_ms !== null ? max((int) $aiLog->latency_ms, 0) : null,
            'generated_by_name' => (string) ($aiLog->requester?->name ?? '-'),
            'error_message' => trim((string) ($aiLog->error_message ?? '')),
            'created_at' => $aiLog->created_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAiMonthlyInsights(string $tenantId, array $filters = []): array
    {
        if (! Schema::hasTable('ai_runtime_logs')) {
            return $this->defaultAiMonthlyInsights();
        }

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $featureQuery = AiRuntimeLog::query()
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->select([
                'feature_key',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_total"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_total"),
                DB::raw('SUM(total_tokens) as total_tokens'),
                DB::raw('AVG(latency_ms) as avg_latency_ms'),
            ])
            ->groupBy('feature_key')
            ->orderByDesc('total');
        $this->applyAiMonthlySourceScope($featureQuery, $tenantId, $filters);
        $featureRows = $featureQuery->get();

        $featureBreakdown = $featureRows
            ->map(function (AiRuntimeLog $row): array {
                $featureKey = trim((string) ($row->getAttribute('feature_key') ?? ''));
                $total = max((int) ($row->getAttribute('total') ?? 0), 0);
                $successTotal = max((int) ($row->getAttribute('success_total') ?? 0), 0);
                $failedTotal = max((int) ($row->getAttribute('failed_total') ?? 0), 0);
                $totalTokens = max((int) ($row->getAttribute('total_tokens') ?? 0), 0);
                $avgLatencyMs = (int) round((float) ($row->getAttribute('avg_latency_ms') ?? 0));
                $successRate = $total > 0 ? (int) floor(($successTotal / $total) * 100) : 0;

                return [
                    'feature_key' => $featureKey,
                    'feature_label' => $this->resolveAiFeatureLabel($featureKey),
                    'total' => $total,
                    'success_total' => $successTotal,
                    'failed_total' => $failedTotal,
                    'total_tokens' => $totalTokens,
                    'avg_latency_ms' => max($avgLatencyMs, 0),
                    'success_rate' => $successRate,
                ];
            })
            ->values()
            ->all();

        $statusQuery = AiRuntimeLog::query()
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->select([
                'status',
                DB::raw('COUNT(*) as total'),
            ])
            ->groupBy('status')
            ->orderByDesc('total');
        $this->applyAiMonthlySourceScope($statusQuery, $tenantId, $filters);
        $statusRows = $statusQuery->get();

        $statusBreakdown = $statusRows
            ->map(function (AiRuntimeLog $row): array {
                $status = strtolower(trim((string) ($row->getAttribute('status') ?? 'failed')));
                $total = max((int) ($row->getAttribute('total') ?? 0), 0);

                return [
                    'status' => $status,
                    'status_label' => $this->resolveAiStatusLabel($status),
                    'total' => $total,
                ];
            })
            ->values()
            ->all();

        $trendStart = now()->copy()->subDays(13)->startOfDay();
        $trendEnd = now()->copy()->endOfDay();

        $dailyQuery = AiRuntimeLog::query()
            ->whereBetween('created_at', [$trendStart, $trendEnd])
            ->select([
                DB::raw('DATE(created_at) as report_date'),
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_total"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_total"),
            ])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'));
        $this->applyAiMonthlySourceScope($dailyQuery, $tenantId, $filters);
        $dailyRows = $dailyQuery->get();

        $dailyMap = $dailyRows
            ->mapWithKeys(function (AiRuntimeLog $row): array {
                $date = (string) ($row->getAttribute('report_date') ?? '');
                if ($date === '') {
                    return [];
                }

                return [
                    $date => [
                        'total' => max((int) ($row->getAttribute('total') ?? 0), 0),
                        'success_total' => max((int) ($row->getAttribute('success_total') ?? 0), 0),
                        'failed_total' => max((int) ($row->getAttribute('failed_total') ?? 0), 0),
                    ],
                ];
            });

        $dailyTrend = [];
        for ($offset = 13; $offset >= 0; $offset--) {
            $date = now()->copy()->subDays($offset);
            $dateKey = $date->toDateString();
            $daily = $dailyMap->get($dateKey, [
                'total' => 0,
                'success_total' => 0,
                'failed_total' => 0,
            ]);

            $dailyTrend[] = [
                'date' => $dateKey,
                'label' => $date->format('d M'),
                'total' => (int) ($daily['total'] ?? 0),
                'success_total' => (int) ($daily['success_total'] ?? 0),
                'failed_total' => (int) ($daily['failed_total'] ?? 0),
            ];
        }

        return [
            'feature_breakdown' => $featureBreakdown,
            'status_breakdown' => $statusBreakdown,
            'daily_trend' => $dailyTrend,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultAiMonthlyInsights(): array
    {
        return [
            'feature_breakdown' => [],
            'status_breakdown' => [],
            'daily_trend' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildAiMonthlyBusinessReport(string $tenantId, array $filters = []): array
    {
        if (! Schema::hasTable('service_orders')) {
            return $this->defaultAiMonthlyBusinessReport('Data servis belum tersedia untuk menyusun laporan.');
        }

        $activeWorkshopId = trim((string) ($filters['active_workshop_id'] ?? $tenantId));
        $workshopFilterId = trim((string) ($filters['workshop_id'] ?? OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID));
        if ($workshopFilterId === '') {
            $workshopFilterId = OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID;
        }

        [$monthStart, $monthEnd] = $this->resolveAiMonthlyPeriodRange();
        $periodLabel = $monthStart->format('Y-m');
        $scopeLabel = $this->resolveAiBusinessScopeLabel($tenantId, $activeWorkshopId, $workshopFilterId);

        $ordersQuery = ServiceOrder::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('service_date', '>=', $monthStart->toDateString())
            ->whereDate('service_date', '<=', $monthEnd->toDateString());
        $this->applyAiBusinessOrderWorkshopScope($ordersQuery, $activeWorkshopId, $workshopFilterId);

        $completedOrdersQuery = (clone $ordersQuery)->where('status', 'done');
        $totalOrders = (int) (clone $ordersQuery)->count();
        $completedOrders = (int) (clone $completedOrdersQuery)->count();
        $pendingOrders = (int) (clone $ordersQuery)
            ->whereIn('status', ['open', 'in_progress'])
            ->count();

        $hasServiceFeeColumn = Schema::hasColumn('service_orders', 'service_fee');
        $hasTotalAmountColumn = Schema::hasColumn('service_orders', 'total_amount');

        $serviceRevenue = $hasServiceFeeColumn
            ? max((int) ((clone $completedOrdersQuery)->sum('service_fee') ?? 0), 0)
            : 0;
        $totalRevenue = $hasTotalAmountColumn
            ? max((int) ((clone $completedOrdersQuery)->sum('total_amount') ?? 0), 0)
            : $serviceRevenue;

        $sparepartRevenue = 0;
        if (Schema::hasTable('service_order_spare_parts')) {
            $sparepartQuery = ServiceOrderSparePart::query()
                ->where('tenant_id', $tenantId)
                ->whereHas('serviceOrder', function (Builder $query) use ($monthStart, $monthEnd): void {
                    $query
                        ->whereDate('service_date', '>=', $monthStart->toDateString())
                        ->whereDate('service_date', '<=', $monthEnd->toDateString())
                        ->where('status', 'done');
                });
            $this->applyAiBusinessDirectWorkshopScope(
                $sparepartQuery,
                $activeWorkshopId,
                $workshopFilterId,
                'workshop_id',
            );
            $sparepartRevenue = max((int) ($sparepartQuery->sum('subtotal') ?? 0), 0);
        }

        if ($sparepartRevenue < 1 && $totalRevenue > $serviceRevenue) {
            $sparepartRevenue = max($totalRevenue - $serviceRevenue, 0);
        }

        $newCustomers = 0;
        if (Schema::hasTable('customers')) {
            $newCustomersQuery = Customer::query()
                ->where('tenant_id', $tenantId)
                ->whereDate('created_at', '>=', $monthStart->toDateString())
                ->whereDate('created_at', '<=', $monthEnd->toDateString());
            $this->applyAiBusinessDirectWorkshopScope(
                $newCustomersQuery,
                $activeWorkshopId,
                $workshopFilterId,
                'workshop_id',
            );
            $newCustomers = max((int) $newCustomersQuery->count(), 0);
        }

        $totalExpenses = 0;
        if (Schema::hasTable('expenses')) {
            $expenseQuery = Expense::query()
                ->where('tenant_id', $tenantId)
                ->whereDate('expense_date', '>=', $monthStart->toDateString())
                ->whereDate('expense_date', '<=', $monthEnd->toDateString());
            $this->applyAiBusinessDirectWorkshopScope(
                $expenseQuery,
                $activeWorkshopId,
                $workshopFilterId,
                'workshop_id',
            );
            $totalExpenses = max((int) ($expenseQuery->sum('amount') ?? 0), 0);
        }

        $grossProfitEstimate = max($totalRevenue - $totalExpenses, 0);
        $completionRate = $totalOrders > 0
            ? round(($completedOrders / $totalOrders) * 100, 1)
            : 0.0;
        $marginRate = $totalRevenue > 0
            ? round(($grossProfitEstimate / $totalRevenue) * 100, 1)
            : 0.0;
        $serviceShare = $totalRevenue > 0
            ? round(($serviceRevenue / $totalRevenue) * 100, 1)
            : 0.0;
        $sparepartShare = $totalRevenue > 0
            ? round(($sparepartRevenue / $totalRevenue) * 100, 1)
            : 0.0;

        $executiveSummary = $this->buildAiBusinessExecutiveSummary(
            $periodLabel,
            $scopeLabel,
            $serviceShare,
            $newCustomers,
            $pendingOrders,
        );

        return [
            'is_available' => true,
            'generated_at' => now()->toIso8601String(),
            'period_label' => $periodLabel,
            'scope_label' => $scopeLabel,
            'total_revenue' => $totalRevenue,
            'service_revenue' => $serviceRevenue,
            'sparepart_revenue' => $sparepartRevenue,
            'gross_profit_estimate' => $grossProfitEstimate,
            'total_orders' => $totalOrders,
            'completed_orders' => $completedOrders,
            'new_customers' => $newCustomers,
            'order_completion_text' => $totalOrders > 0
                ? sprintf('%d / %d', $completedOrders, $totalOrders)
                : '0 / 0',
            'executive_summary' => $executiveSummary,
            'highlights' => $this->buildAiBusinessHighlights(
                $serviceRevenue,
                $sparepartRevenue,
                $completionRate,
                $pendingOrders,
                $newCustomers,
                $grossProfitEstimate,
                $marginRate,
            ),
            'risks' => $this->buildAiBusinessRisks($pendingOrders, $completionRate, $marginRate, $sparepartShare),
            'recommendations' => $this->buildAiBusinessRecommendations($pendingOrders, $newCustomers, $marginRate),
            'next_month_focus' => $this->buildAiBusinessNextMonthFocus($pendingOrders, $sparepartShare, $completionRate),
            'disclaimer' => 'Laporan AI adalah ringkasan awal, validasi akhir tetap oleh owner/manager bengkel.',
            'empty_message' => '',
        ];
    }

    private function buildAiBusinessExecutiveSummary(
        string $periodLabel,
        string $scopeLabel,
        float $serviceShare,
        int $newCustomers,
        int $pendingOrders,
    ): string
    {
        if ($pendingOrders > 0) {
            return sprintf(
                'Kinerja periode %s untuk %s menunjukkan dominasi omzet jasa (%.1f%%), didukung penambahan %d pelanggan baru. Namun masih ada %d order pending yang perlu dituntaskan agar momentum pertumbuhan tetap terjaga.',
                $periodLabel,
                $scopeLabel,
                $serviceShare,
                $newCustomers,
                $pendingOrders,
            );
        }

        return sprintf(
            'Kinerja periode %s untuk %s menunjukkan performa operasional yang stabil dengan dominasi omzet jasa sebesar %.1f%%. Akuisisi pelanggan baru sebanyak %d turut mendukung pertumbuhan pendapatan bengkel.',
            $periodLabel,
            $scopeLabel,
            $serviceShare,
            $newCustomers,
        );
    }

    /**
     * @return array<int, string>
     */
    private function buildAiBusinessHighlights(
        int $serviceRevenue,
        int $sparepartRevenue,
        float $completionRate,
        int $pendingOrders,
        int $newCustomers,
        int $grossProfitEstimate,
        float $marginRate,
    ): array
    {
        return [
            sprintf(
                'Omzet jasa mencapai Rp %s, %s omzet sparepart (Rp %s).',
                number_format($serviceRevenue, 0, ',', '.'),
                $serviceRevenue >= $sparepartRevenue ? 'melampaui' : 'di bawah',
                number_format($sparepartRevenue, 0, ',', '.'),
            ),
            sprintf(
                'Efisiensi penyelesaian order mencapai %.1f%%, dengan %d order pending yang perlu diprioritaskan.',
                $completionRate,
                $pendingOrders,
            ),
            sprintf(
                'Pelanggan baru periode ini tercatat %d, menandakan akuisisi pelanggan tetap berjalan.',
                $newCustomers,
            ),
            sprintf(
                'Estimasi laba kotor berada di Rp %s (sekitar %.1f%% dari total revenue).',
                number_format($grossProfitEstimate, 0, ',', '.'),
                $marginRate,
            ),
            $pendingOrders > 0
                ? sprintf('Backlog %d order perlu dijaga agar SLA servis tetap sehat.', $pendingOrders)
                : 'Tidak ada backlog order signifikan pada periode ini.',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function buildAiBusinessRisks(
        int $pendingOrders,
        float $completionRate,
        float $marginRate,
        float $sparepartShare,
    ): array {
        $risks = [];

        if ($pendingOrders > 0) {
            $risks[] = sprintf(
                'Potensi penurunan kepuasan pelanggan jika %d order pending tidak segera diselesaikan.',
                $pendingOrders,
            );
        }

        if ($completionRate < 85) {
            $risks[] = 'Tingkat completion rate masih rendah dan berisiko menambah antrean kerja.';
        }

        if ($marginRate < 20) {
            $risks[] = 'Margin laba kotor tipis, perlu kontrol biaya operasional dan efisiensi proses.';
        }

        if ($sparepartShare < 30) {
            $risks[] = 'Kontribusi omzet sparepart masih rendah, berisiko menahan potensi profit tambahan.';
        }

        if ($risks === []) {
            $risks[] = 'Tidak ada risiko mayor terdeteksi, tetap monitor stok fast-moving dan SLA servis.';
            $risks[] = 'Jaga konsistensi kualitas layanan untuk menghindari kenaikan komplain customer.';
        }

        return array_slice($risks, 0, 5);
    }

    /**
     * @return array<int, string>
     */
    private function buildAiBusinessRecommendations(int $pendingOrders, int $newCustomers, float $marginRate): array
    {
        $recommendations = [];

        if ($pendingOrders > 0) {
            $recommendations[] = sprintf(
                'Prioritaskan penyelesaian %d order pending pada minggu pertama periode berikutnya.',
                $pendingOrders,
            );
        }

        if ($newCustomers > 0) {
            $recommendations[] = sprintf(
                'Jalankan program retensi untuk %d pelanggan baru agar repeat order meningkat.',
                $newCustomers,
            );
        }

        if ($marginRate < 25) {
            $recommendations[] = 'Evaluasi komposisi biaya dan harga jual untuk memperbaiki margin laba kotor.';
        }

        $recommendations[] = 'Pantau item sparepart fast-moving dan siapkan safety stock agar servis tidak tertunda.';

        return array_slice(array_values(array_unique($recommendations)), 0, 5);
    }

    /**
     * @return array<int, string>
     */
    private function buildAiBusinessNextMonthFocus(
        int $pendingOrders,
        float $sparepartShare,
        float $completionRate,
    ): array {
        $focus = [];

        if ($pendingOrders > 0) {
            $focus[] = 'Penuntasan backlog order dan penjagaan target SLA harian tiap stall.';
        }

        if ($sparepartShare < 35) {
            $focus[] = 'Perbaikan konversi penjualan sparepart pendukung servis untuk menaikkan margin.';
        }

        if ($completionRate < 90) {
            $focus[] = 'Optimasi workflow teknisi dan penjadwalan servis untuk menaikkan completion rate.';
        }

        $focus[] = 'Monitoring mingguan KPI omzet, order selesai, dan pelanggan baru agar keputusan lebih cepat.';

        return array_slice(array_values(array_unique($focus)), 0, 5);
    }

    /**
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    private function resolveAiMonthlyPeriodRange(): array
    {
        return [
            now()->copy()->startOfMonth(),
            now()->copy()->endOfMonth(),
        ];
    }

    private function resolveAiBusinessScopeLabel(string $tenantId, string $activeWorkshopId, string $workshopFilterId): string
    {
        if (
            OwnerWorkshopSwitcherService::isAllWorkshopsId($workshopFilterId)
            || trim($workshopFilterId) === ''
        ) {
            return 'Semua Cabang';
        }

        if (! Schema::hasTable('workshops')) {
            return $this->shouldApplyWorkshopScope($activeWorkshopId)
                ? 'Cabang Aktif'
                : 'Semua Cabang';
        }

        $workshop = Workshop::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $workshopFilterId)
            ->first(['name']);

        return trim((string) ($workshop?->name ?? '')) ?: 'Cabang';
    }

    private function applyAiBusinessOrderWorkshopScope(
        Builder $query,
        string $activeWorkshopId,
        string $workshopFilterId,
    ): void {
        if ($this->shouldApplyWorkshopScope($activeWorkshopId)) {
            $query->whereHas('customer', function (Builder $customerQuery) use ($activeWorkshopId): void {
                $customerQuery->where('workshop_id', $activeWorkshopId);
            });

            return;
        }

        if (OwnerWorkshopSwitcherService::isAllWorkshopsId($workshopFilterId) || trim($workshopFilterId) === '') {
            return;
        }

        $query->whereHas('customer', function (Builder $customerQuery) use ($workshopFilterId): void {
            $customerQuery->where('workshop_id', $workshopFilterId);
        });
    }

    private function applyAiBusinessDirectWorkshopScope(
        Builder $query,
        string $activeWorkshopId,
        string $workshopFilterId,
        string $workshopColumn = 'workshop_id',
    ): void {
        if ($this->shouldApplyWorkshopScope($activeWorkshopId)) {
            $query->where($workshopColumn, $activeWorkshopId);

            return;
        }

        if (OwnerWorkshopSwitcherService::isAllWorkshopsId($workshopFilterId) || trim($workshopFilterId) === '') {
            return;
        }

        $query->where($workshopColumn, $workshopFilterId);
    }

    private function applyCustomerReportWorkshopScope(
        Builder $query,
        string $activeWorkshopId,
        string $workshopFilterId,
    ): void {
        if ($this->shouldApplyWorkshopScope($activeWorkshopId)) {
            $query->where(function (Builder $scopedQuery) use ($activeWorkshopId): void {
                $scopedQuery
                    ->where('customers.workshop_id', $activeWorkshopId)
                    ->orWhereNull('customers.workshop_id');
            });

            return;
        }

        if (OwnerWorkshopSwitcherService::isAllWorkshopsId($workshopFilterId) || trim($workshopFilterId) === '') {
            return;
        }

        $query->where('customers.workshop_id', $workshopFilterId);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultAiMonthlyBusinessReport(string $message = 'Belum ada output laporan AI bulanan.'): array
    {
        return [
            'is_available' => false,
            'generated_at' => '',
            'period_label' => '-',
            'scope_label' => 'Semua Cabang',
            'total_revenue' => 0,
            'service_revenue' => 0,
            'sparepart_revenue' => 0,
            'gross_profit_estimate' => 0,
            'total_orders' => 0,
            'completed_orders' => 0,
            'new_customers' => 0,
            'order_completion_text' => '0 / 0',
            'executive_summary' => '',
            'highlights' => [],
            'risks' => [],
            'recommendations' => [],
            'next_month_focus' => [],
            'disclaimer' => 'Laporan AI adalah ringkasan awal, validasi akhir tetap oleh owner/manager bengkel.',
            'empty_message' => $message,
        ];
    }

    private function resolveAiFeatureLabel(string $featureKey): string
    {
        return match ($featureKey) {
            'service_estimate_v1' => 'Estimasi Servis',
            'symptom_diagnosis_v1' => 'Diagnosa Gejala',
            'monthly_business_report_v1' => 'Laporan Bisnis Bulanan',
            'connection_test_v1' => 'Test Koneksi AI Agent',
            default => $featureKey !== '' ? $featureKey : 'Fitur AI',
        };
    }

    private function resolveAiReportSourceLabel(string $source): string
    {
        return match (strtolower(trim($source))) {
            self::AI_REPORT_SOURCE_OWNER_RUNTIME => 'Penggunaan Owner',
            self::AI_REPORT_SOURCE_SUPERADMIN_PROMPT_TEST => 'Test Output Superadmin',
            'platform_connection_test' => 'Test Koneksi Superadmin',
            'runtime_general' => 'Runtime Lainnya',
            default => 'Sumber Lainnya',
        };
    }

    private function resolveAiStatusLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            'success' => 'Sukses',
            'failed' => 'Gagal',
            default => 'Lainnya',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultSummary(string $message): array
    {
        return [
            'cards' => [],
            'highlights' => [$message],
        ];
    }

    private function resolveInvoiceStatusLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            'paid' => 'Lunas',
            'partial' => 'Sebagian',
            default => 'Belum Lunas',
        };
    }

    private function sanitizeExcelString(mixed $value): string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '-';
        }

        $firstChar = substr($normalized, 0, 1);
        if (in_array($firstChar, ['=', '+', '-', '@'], true)) {
            return "'".$normalized;
        }

        return $normalized;
    }

    private function formatExcelDateValue(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : '-';
    }

    private function formatExcelDateTimeValue(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('d/m/Y H:i');
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : '-';
    }

    /**
     * @return array<string, string>
     */
    private function resolveReportConfig(string $reportType): array
    {
        return match ($reportType) {
            'spareparts' => [
                'title' => 'Laporan Sparepart',
                'description' => 'Pantau stok, item aktif, dan area prioritas restock.',
            ],
            'expenses' => [
                'title' => 'Laporan Pengeluaran',
                'description' => 'Ringkasan pengeluaran operasional bengkel per tenant.',
            ],
            'customers' => [
                'title' => 'Laporan Pelanggan',
                'description' => 'Lihat pertumbuhan pelanggan dan kelengkapan data kontak.',
            ],
            'profit_loss' => [
                'title' => 'Laporan Laba Rugi',
                'description' => 'Ringkasan pendapatan, beban, dan laba bersih periode berjalan.',
            ],
            'ai_monthly' => [
                'title' => 'Laporan AI Bulanan',
                'description' => 'Analisa performa bisnis bulanan per cabang atau seluruh cabang.',
            ],
            default => [
                'title' => 'Laporan Servis',
                'description' => 'Ringkasan performa servis dan omzet.',
            ],
        };
    }

    private function resolveReportType(string $reportType): string
    {
        $normalized = strtolower(trim($reportType));

        return in_array($normalized, ['sales', 'spareparts', 'expenses', 'customers', 'profit_loss', 'ai_monthly'], true)
            ? $normalized
            : 'sales';
    }

    private function resolveCurrentUri(Request $request): string
    {
        $path = '/'.ltrim((string) $request->path(), '/');
        $queryString = trim((string) $request->getQueryString());

        return $queryString !== '' ? $path.'?'.$queryString : $path;
    }

    /**
     * @return array{search: string, sort_by: string, sort_dir: string, per_page: int, cursor: string}
     */
    private function resolveSalesFilters(Request $request): array
    {
        return $this->normalizeSalesFilters($request->query());
    }

    /**
     * @return array{
     *  search: string,
     *  workshop_id: string,
     *  active_workshop_id: string,
     *  category: string,
     *  sort_by: string,
     *  sort_dir: string,
     *  per_page: int,
     *  cursor: string
     * }
     */
    private function resolveExpenseReportFilters(Request $request, string $tenantId, string $activeWorkshopId): array
    {
        $query = $request->query();
        $workshopFilterId = $this->resolveAiReportWorkshopFilter(
            $tenantId,
            $activeWorkshopId,
            (string) ($query['expense_report_workshop_id'] ?? OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID),
        );

        return [
            'search' => trim((string) ($query['expense_report_search'] ?? '')),
            'workshop_id' => $workshopFilterId,
            'active_workshop_id' => $activeWorkshopId,
            'category' => trim((string) ($query['expense_report_category'] ?? '')),
            'sort_by' => $this->resolveExpenseReportSortBy((string) ($query['expense_report_sort_by'] ?? 'expense_date')),
            'sort_dir' => $this->resolveSortDirection((string) ($query['expense_report_sort_dir'] ?? 'desc')),
            'per_page' => $this->resolvePerPage((int) ($query['expense_report_per_page'] ?? 10)),
            'cursor' => trim((string) ($query['expense_report_cursor'] ?? '')),
        ];
    }

    /**
     * @return array{
     *  search: string,
     *  workshop_id: string,
     *  active_workshop_id: string,
     *  status: string,
     *  contact: string,
     *  sort_by: string,
     *  sort_dir: string,
     *  per_page: int,
     *  cursor: string
     * }
     */
    private function resolveCustomerReportFilters(Request $request, string $tenantId, string $activeWorkshopId): array
    {
        $query = $request->query();
        $workshopFilterId = $this->resolveAiReportWorkshopFilter(
            $tenantId,
            $activeWorkshopId,
            (string) ($query['customer_report_workshop_id'] ?? OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID),
        );

        return [
            'search' => trim((string) ($query['customer_report_search'] ?? '')),
            'workshop_id' => $workshopFilterId,
            'active_workshop_id' => $activeWorkshopId,
            'status' => $this->resolveCustomerReportStatus((string) ($query['customer_report_status'] ?? 'all')),
            'contact' => $this->resolveCustomerReportContact((string) ($query['customer_report_contact'] ?? 'all')),
            'sort_by' => $this->resolveCustomerReportSortBy((string) ($query['customer_report_sort_by'] ?? 'created_at')),
            'sort_dir' => $this->resolveSortDirection((string) ($query['customer_report_sort_dir'] ?? 'desc')),
            'per_page' => $this->resolvePerPage((int) ($query['customer_report_per_page'] ?? 10)),
            'cursor' => trim((string) ($query['customer_report_cursor'] ?? '')),
        ];
    }

    /**
     * @return array{
     *  workshop_id: string,
     *  active_workshop_id: string
     * }
     */
    private function resolveProfitLossFilters(Request $request, string $tenantId, string $activeWorkshopId): array
    {
        $query = $request->query();
        $workshopFilterId = $this->resolveAiReportWorkshopFilter(
            $tenantId,
            $activeWorkshopId,
            (string) ($query['profit_loss_workshop_id'] ?? OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID),
        );

        return [
            'workshop_id' => $workshopFilterId,
            'active_workshop_id' => $activeWorkshopId,
        ];
    }

    /**
     * @return array{
     *  search: string,
     *  workshop_id: string,
     *  active_workshop_id: string,
     *  sort_by: string,
     *  sort_dir: string,
     *  per_page: int,
     *  cursor: string
     * }
     */
    private function resolveSparePartReportFilters(Request $request, string $tenantId, string $activeWorkshopId): array
    {
        $query = $request->query();
        $workshopFilterId = $this->resolveAiReportWorkshopFilter(
            $tenantId,
            $activeWorkshopId,
            (string) ($query['sparepart_report_workshop_id'] ?? OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID),
        );

        return [
            'search' => trim((string) ($query['sparepart_report_search'] ?? '')),
            'workshop_id' => $workshopFilterId,
            'active_workshop_id' => $activeWorkshopId,
            'sort_by' => $this->resolveSparePartReportSortBy((string) ($query['sparepart_report_sort_by'] ?? 'created_at')),
            'sort_dir' => $this->resolveSortDirection((string) ($query['sparepart_report_sort_dir'] ?? 'desc')),
            'per_page' => $this->resolvePerPage((int) ($query['sparepart_report_per_page'] ?? 10)),
            'cursor' => trim((string) ($query['sparepart_report_cursor'] ?? '')),
        ];
    }

    /**
     * @return array{
     *  search: string,
     *  source: string,
     *  workshop_id: string,
     *  active_workshop_id: string,
     *  sort_by: string,
     *  sort_dir: string,
     *  per_page: int,
     *  cursor: string
     * }
     */
    private function resolveAiMonthlyFilters(Request $request, string $tenantId, string $activeWorkshopId): array
    {
        $query = $request->query();
        $workshopFilterId = $this->resolveAiReportWorkshopFilter(
            $tenantId,
            $activeWorkshopId,
            (string) ($query['ai_report_workshop_id'] ?? OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID),
        );

        return [
            'search' => trim((string) ($query['ai_report_search'] ?? '')),
            'source' => $this->resolveAiReportSource((string) ($query['ai_report_source'] ?? self::AI_REPORT_SOURCE_ALL)),
            'workshop_id' => $workshopFilterId,
            'active_workshop_id' => $activeWorkshopId,
            'sort_by' => $this->resolveAiMonthlySortBy((string) ($query['ai_report_sort_by'] ?? 'created_at')),
            'sort_dir' => $this->resolveSortDirection((string) ($query['ai_report_sort_dir'] ?? 'desc')),
            'per_page' => $this->resolvePerPage((int) ($query['ai_report_per_page'] ?? 10)),
            'cursor' => trim((string) ($query['ai_report_cursor'] ?? '')),
        ];
    }

    private function resolveSalesSortBy(string $sortBy): string
    {
        return in_array($sortBy, ['code', 'service_date', 'total_amount', 'created_at'], true)
            ? $sortBy
            : 'service_date';
    }

    private function resolveExpenseReportSortBy(string $sortBy): string
    {
        return in_array($sortBy, ['expense_date', 'category', 'amount', 'created_at'], true)
            ? $sortBy
            : 'expense_date';
    }

    private function resolveCustomerReportSortBy(string $sortBy): string
    {
        $normalized = strtolower(trim($sortBy));

        return in_array($normalized, ['name', 'phone', 'email', 'is_active', 'created_at'], true)
            ? $normalized
            : 'created_at';
    }

    private function resolveCustomerReportStatus(string $status): string
    {
        $normalized = strtolower(trim($status));

        return in_array($normalized, ['all', 'active', 'inactive'], true)
            ? $normalized
            : 'all';
    }

    private function resolveCustomerReportContact(string $contact): string
    {
        $normalized = strtolower(trim($contact));

        return in_array($normalized, ['all', 'complete', 'phone_only', 'email_only', 'missing'], true)
            ? $normalized
            : 'all';
    }

    private function resolveExpenseSortableColumn(string $sortBy): string
    {
        return [
            'expense_date' => 'expenses.expense_date',
            'category' => 'expenses.category',
            'amount' => 'expenses.amount',
            'created_at' => 'expenses.created_at',
        ][$sortBy] ?? 'expenses.expense_date';
    }

    private function resolveCustomerSortableColumn(string $sortBy): string
    {
        return [
            'name' => 'customers.name',
            'phone' => 'customers.phone',
            'email' => 'customers.email',
            'is_active' => 'customers.is_active',
            'created_at' => 'customers.created_at',
        ][$sortBy] ?? 'customers.created_at';
    }

    private function resolveSparePartReportSortBy(string $sortBy): string
    {
        return in_array($sortBy, [
            'name',
            'sku',
            'stock_total',
            'minimum_stock_total',
            'used_qty',
            'usage_revenue',
            'stock_status_rank',
            'selling_price',
            'created_at',
        ], true)
            ? $sortBy
            : 'created_at';
    }

    private function resolveSparePartSortableColumn(string $sortBy): string
    {
        return [
            'name' => 'spare_parts.name',
            'sku' => 'spare_parts.sku',
            'stock_total' => 'stock_total',
            'minimum_stock_total' => 'minimum_stock_total',
            'used_qty' => 'used_qty',
            'usage_revenue' => 'usage_revenue',
            'stock_status_rank' => 'stock_status_rank',
            'selling_price' => 'spare_parts.selling_price',
            'created_at' => 'spare_parts.created_at',
        ][$sortBy] ?? 'spare_parts.created_at';
    }

    private function resolveAiMonthlySortBy(string $sortBy): string
    {
        return in_array($sortBy, ['source', 'feature_key', 'status', 'total_tokens', 'latency_ms', 'created_at'], true)
            ? $sortBy
            : 'created_at';
    }

    private function resolveAiMonthlySortableColumn(string $sortBy): string
    {
        return [
            'source' => 'ai_runtime_logs.source',
            'feature_key' => 'ai_runtime_logs.feature_key',
            'status' => 'ai_runtime_logs.status',
            'total_tokens' => 'ai_runtime_logs.total_tokens',
            'latency_ms' => 'ai_runtime_logs.latency_ms',
            'created_at' => 'ai_runtime_logs.created_at',
        ][$sortBy] ?? 'ai_runtime_logs.created_at';
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyAiMonthlySourceScope(Builder $query, string $tenantId, array $filters): void
    {
        $source = $this->resolveAiReportSource((string) ($filters['source'] ?? self::AI_REPORT_SOURCE_OWNER_RUNTIME));

        if ($source === self::AI_REPORT_SOURCE_OWNER_RUNTIME) {
            $query
                ->where('source', self::AI_REPORT_SOURCE_OWNER_RUNTIME)
                ->where('tenant_id', $tenantId);

            return;
        }

        if ($source === self::AI_REPORT_SOURCE_SUPERADMIN_PROMPT_TEST) {
            $query
                ->where('source', self::AI_REPORT_SOURCE_SUPERADMIN_PROMPT_TEST)
                ->where(function (Builder $nestedQuery) use ($tenantId): void {
                    $nestedQuery
                        ->where('tenant_id', $tenantId)
                        ->orWhereNull('tenant_id');
                });

            return;
        }

        $query->where(function (Builder $nestedQuery) use ($tenantId): void {
            $nestedQuery
                ->where(function (Builder $ownerRuntimeQuery) use ($tenantId): void {
                    $ownerRuntimeQuery
                        ->where('source', self::AI_REPORT_SOURCE_OWNER_RUNTIME)
                        ->where('tenant_id', $tenantId);
                })
                ->orWhere(function (Builder $promptTestQuery) use ($tenantId): void {
                    $promptTestQuery
                        ->where('source', self::AI_REPORT_SOURCE_SUPERADMIN_PROMPT_TEST)
                        ->where(function (Builder $tenantScopeQuery) use ($tenantId): void {
                            $tenantScopeQuery
                                ->where('tenant_id', $tenantId)
                                ->orWhereNull('tenant_id');
                        });
                });
        });
    }

    private function resolveAiReportSource(string $source): string
    {
        $normalized = strtolower(trim($source));

        return in_array($normalized, [
            self::AI_REPORT_SOURCE_ALL,
            self::AI_REPORT_SOURCE_OWNER_RUNTIME,
            self::AI_REPORT_SOURCE_SUPERADMIN_PROMPT_TEST,
        ], true)
            ? $normalized
            : self::AI_REPORT_SOURCE_ALL;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function resolveAiMonthlySourceOptions(): array
    {
        return [
            [
                'value' => self::AI_REPORT_SOURCE_ALL,
                'label' => 'Semua Sumber',
            ],
            [
                'value' => self::AI_REPORT_SOURCE_OWNER_RUNTIME,
                'label' => 'Penggunaan Owner',
            ],
            [
                'value' => self::AI_REPORT_SOURCE_SUPERADMIN_PROMPT_TEST,
                'label' => 'Test Output Superadmin',
            ],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function resolveAiMonthlyWorkshopOptions(Request $request, string $tenantId, string $activeWorkshopId): array
    {
        $switcher = $request->attributes->get('owner_workshop_switcher');
        $rawWorkshops = is_array($switcher) && is_array($switcher['workshops'] ?? null)
            ? $switcher['workshops']
            : [];

        $options = collect($rawWorkshops)
            ->map(function (mixed $item): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $id = trim((string) ($item['id'] ?? ''));
                $name = trim((string) ($item['name'] ?? ''));
                $code = trim((string) ($item['code'] ?? ''));

                if ($id === '' || $name === '') {
                    return null;
                }

                return [
                    'value' => $id,
                    'label' => $code !== '' && $code !== '-'
                        ? "{$name} - {$code}"
                        : $name,
                ];
            })
            ->filter()
            ->values();

        if ($options->isEmpty() && Schema::hasTable('workshops')) {
            $options = Workshop::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(function (Workshop $workshop): array {
                    $name = trim((string) $workshop->name);
                    $code = trim((string) $workshop->code);

                    return [
                        'value' => (string) $workshop->id,
                        'label' => $code !== '' ? "{$name} - {$code}" : $name,
                    ];
                })
                ->values();
        }

        if ($this->shouldApplyWorkshopScope($activeWorkshopId)) {
            $activeOption = $options->first(
                fn (array $option): bool => trim((string) ($option['value'] ?? '')) === $activeWorkshopId,
            );

            if (is_array($activeOption)) {
                return [$activeOption];
            }

            return [[
                'value' => $activeWorkshopId,
                'label' => 'Cabang Aktif',
            ]];
        }

        if (! $options->contains(fn (array $option): bool => OwnerWorkshopSwitcherService::isAllWorkshopsId((string) ($option['value'] ?? '')))) {
            $options = collect([[
                'value' => OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID,
                'label' => 'Semua Cabang',
            ]])->concat($options)->values();
        }

        return $options->all();
    }

    private function resolveAiReportWorkshopFilter(
        string $tenantId,
        string $activeWorkshopId,
        string $requestedWorkshopId,
    ): string {
        if ($this->shouldApplyWorkshopScope($activeWorkshopId)) {
            return $activeWorkshopId;
        }

        $normalizedWorkshopId = trim($requestedWorkshopId);
        if (
            $normalizedWorkshopId === ''
            || OwnerWorkshopSwitcherService::isAllWorkshopsId($normalizedWorkshopId)
        ) {
            return OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID;
        }

        if (! Schema::hasTable('workshops')) {
            return OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID;
        }

        $exists = Workshop::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $normalizedWorkshopId)
            ->where('is_active', true)
            ->exists();

        return $exists ? $normalizedWorkshopId : OwnerWorkshopSwitcherService::ALL_WORKSHOPS_ID;
    }

    private function shouldApplyWorkshopScope(string $activeWorkshopId): bool
    {
        return ! OwnerWorkshopSwitcherService::isAllWorkshopsId($activeWorkshopId);
    }

    private function resolveSortDirection(string $sortDirection): string
    {
        return strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';
    }

    private function resolvePerPage(int $perPage): int
    {
        return in_array($perPage, [10, 20, 50], true) ? $perPage : 10;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultSalesReportPayload(int $perPage): array
    {
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

    /**
     * @return array<string, mixed>
     */
    private function defaultExpenseReportPayload(int $perPage): array
    {
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

    /**
     * @return array<string, mixed>
     */
    private function defaultCustomerReportPayload(int $perPage): array
    {
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

    /**
     * @return array<string, mixed>
     */
    private function defaultProfitLossReportPayload(): array
    {
        return [
            'period_label' => '-',
            'scope_label' => 'Semua Cabang',
            'rows' => [],
            'summary' => [
                'total_revenue' => 0,
                'total_expense' => 0,
                'gross_profit' => 0,
                'net_profit' => 0,
                'net_margin_pct' => 0,
                'completed_orders' => 0,
                'avg_ticket' => 0,
                'service_revenue' => 0,
                'sparepart_revenue' => 0,
                'sparepart_cogs' => 0,
                'operational_expense' => 0,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultSparePartReportPayload(int $perPage): array
    {
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

    /**
     * @return array<string, mixed>
     */
    private function defaultSparePartReorderInsights(string $message = 'Prediksi reorder belum tersedia.'): array
    {
        return [
            'is_available' => false,
            'scope_label' => 'Semua Cabang',
            'usage_window_days' => 60,
            'lead_time_days' => 14,
            'summary' => [
                'items_need_reorder' => 0,
                'critical_items' => 0,
                'estimated_reorder_cost' => 0,
            ],
            'rows' => [],
            'disclaimer' => 'Prediksi reorder berbasis pemakaian historis dan parameter stok minimum.',
            'empty_message' => $message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultAiMonthlyReportPayload(int $perPage): array
    {
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

    private function cursorPaginateWithFallback(
        Builder $query,
        int $perPage,
        array $columns,
        string $cursor,
    ): CursorPaginator {
        $cursorValue = $cursor !== '' ? $cursor : null;

        try {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, 'sales_report_cursor', $cursorValue)
                ->withQueryString();
        } catch (\Throwable) {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, 'sales_report_cursor_fallback', null)
                ->withQueryString();
        }
    }

    private function cursorPaginateWithFallbackByKey(
        Builder $query,
        int $perPage,
        array $columns,
        string $cursor,
        string $cursorName,
        string $fallbackCursorName,
    ): CursorPaginator {
        $cursorValue = $cursor !== '' ? $cursor : null;

        try {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, $cursorName, $cursorValue)
                ->withQueryString();
        } catch (\Throwable) {
            return (clone $query)
                ->cursorPaginate($perPage, $columns, $fallbackCursorName, null)
                ->withQueryString();
        }
    }
}
