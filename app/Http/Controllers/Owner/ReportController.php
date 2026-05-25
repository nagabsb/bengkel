<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\Report\ExportOwnerSalesReportRequest;
use App\Services\Owner\OwnerPrintSettingService;
use App\Services\Owner\OwnerReportService;
use App\Support\Billing\TenantPlanResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function sales(
        Request $request,
        string $tenant,
        OwnerReportService $service,
        OwnerPrintSettingService $ownerPrintSettingService,
        TenantPlanResolver $planResolver,
    ): Response {
        return $this->renderReport(
            request: $request,
            tenant: $tenant,
            reportType: 'sales',
            service: $service,
            ownerPrintSettingService: $ownerPrintSettingService,
            planResolver: $planResolver,
        );
    }

    public function spareparts(
        Request $request,
        string $tenant,
        OwnerReportService $service,
        OwnerPrintSettingService $ownerPrintSettingService,
        TenantPlanResolver $planResolver,
    ): Response {
        return $this->renderReport(
            request: $request,
            tenant: $tenant,
            reportType: 'spareparts',
            service: $service,
            ownerPrintSettingService: $ownerPrintSettingService,
            planResolver: $planResolver,
        );
    }

    public function expenses(
        Request $request,
        string $tenant,
        OwnerReportService $service,
        OwnerPrintSettingService $ownerPrintSettingService,
        TenantPlanResolver $planResolver,
    ): Response {
        return $this->renderReport(
            request: $request,
            tenant: $tenant,
            reportType: 'expenses',
            service: $service,
            ownerPrintSettingService: $ownerPrintSettingService,
            planResolver: $planResolver,
        );
    }

    public function customers(
        Request $request,
        string $tenant,
        OwnerReportService $service,
        OwnerPrintSettingService $ownerPrintSettingService,
        TenantPlanResolver $planResolver,
    ): Response {
        return $this->renderReport(
            request: $request,
            tenant: $tenant,
            reportType: 'customers',
            service: $service,
            ownerPrintSettingService: $ownerPrintSettingService,
            planResolver: $planResolver,
        );
    }

    public function profitLoss(
        Request $request,
        string $tenant,
        OwnerReportService $service,
        OwnerPrintSettingService $ownerPrintSettingService,
        TenantPlanResolver $planResolver,
    ): Response {
        return $this->renderReport(
            request: $request,
            tenant: $tenant,
            reportType: 'profit_loss',
            service: $service,
            ownerPrintSettingService: $ownerPrintSettingService,
            planResolver: $planResolver,
        );
    }

    public function aiMonthly(
        Request $request,
        string $tenant,
        OwnerReportService $service,
        OwnerPrintSettingService $ownerPrintSettingService,
        TenantPlanResolver $planResolver,
    ): Response {
        return $this->renderReport(
            request: $request,
            tenant: $tenant,
            reportType: 'ai_monthly',
            service: $service,
            ownerPrintSettingService: $ownerPrintSettingService,
            planResolver: $planResolver,
        );
    }

    public function exportSales(
        ExportOwnerSalesReportRequest $request,
        string $tenant,
        OwnerReportService $service,
    ): StreamedResponse {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);
        $filters = $service->normalizeSalesFilters($request->validated());
        $rows = $service->buildSalesExportRows($tenantId, $filters);
        $spreadsheet = $service->buildSalesExportSpreadsheet($rows);
        $generatedAt = now()->format('Ymd_His');
        $filename = "laporan_servis_{$generatedAt}.xlsx";

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function renderReport(
        Request $request,
        string $tenant,
        string $reportType,
        OwnerReportService $service,
        OwnerPrintSettingService $ownerPrintSettingService,
        TenantPlanResolver $planResolver,
    ): Response {
        $tenantId = (string) $request->attributes->get('tenant_id', $tenant);

        return Inertia::render('Owner/Reports/Index', [
            'user' => $request->user()?->only('name', 'email'),
            ...$service->buildPageData(
                request: $request,
                tenantId: $tenantId,
                reportType: $reportType,
                planResolver: $planResolver,
                user: $request->user(),
            ),
            ...$ownerPrintSettingService->buildPageData($tenantId),
        ]);
    }
}
