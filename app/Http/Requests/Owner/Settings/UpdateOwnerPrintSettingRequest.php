<?php

namespace App\Http\Requests\Owner\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOwnerPrintSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('users.manage');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'printer_name' => ['nullable', 'string', 'max:120'],
            'print_type' => ['required', 'string', Rule::in(['thermal'])],
            'paper_size' => ['required', 'string', Rule::in(['58mm', '80mm'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'print_type.required' => 'Tipe cetak wajib diisi.',
            'print_type.in' => 'Tipe cetak yang dipilih tidak didukung.',
            'paper_size.required' => 'Ukuran kertas wajib diisi.',
            'paper_size.in' => 'Ukuran kertas thermal harus 58mm atau 80mm.',
            'printer_name.max' => 'Nama printer maksimal 120 karakter.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $printerName = trim(strip_tags((string) $this->input('printer_name', '')));
        $printType = strtolower(trim((string) $this->input('print_type', 'thermal')));
        $paperSize = strtolower(trim((string) $this->input('paper_size', '80mm')));

        $this->merge([
            'printer_name' => $printerName,
            'print_type' => $printType,
            'paper_size' => $paperSize,
        ]);
    }
}
