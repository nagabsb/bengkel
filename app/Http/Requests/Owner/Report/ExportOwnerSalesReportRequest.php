<?php

namespace App\Http\Requests\Owner\Report;

use Illuminate\Foundation\Http\FormRequest;

class ExportOwnerSalesReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'sales_report_search' => ['nullable', 'string', 'max:150'],
            'sales_report_sort_by' => ['nullable', 'string', 'in:code,service_date,total_amount,created_at'],
            'sales_report_sort_dir' => ['nullable', 'string', 'in:asc,desc'],
            'sales_report_per_page' => ['nullable', 'integer', 'in:10,20,50'],
        ];
    }
}
