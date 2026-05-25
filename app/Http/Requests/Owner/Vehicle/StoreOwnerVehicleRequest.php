<?php

namespace App\Http\Requests\Owner\Vehicle;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOwnerVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('service_orders.manage');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'vehicle_type' => ['required', 'string', Rule::in(['motor', 'mobil'])],
            'brand' => ['required', 'string', 'max:120'],
            'model' => ['required', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vehicle_type.required' => 'Jenis kendaraan wajib dipilih.',
            'brand.required' => 'Merek kendaraan wajib diisi.',
            'model.required' => 'Model kendaraan wajib diisi.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $vehicleType = strtolower(trim((string) $this->input('vehicle_type', 'motor')));
        if (! in_array($vehicleType, ['motor', 'mobil'], true)) {
            $vehicleType = 'motor';
        }

        $this->merge([
            'vehicle_type' => $vehicleType,
            'brand' => trim((string) $this->input('brand', '')),
            'model' => trim((string) $this->input('model', '')),
            'is_active' => $this->has('is_active') ? (bool) $this->input('is_active') : true,
        ]);
    }
}

