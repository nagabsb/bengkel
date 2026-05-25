<?php

namespace App\Http\Requests\Owner\BookingPageBuilder;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOwnerBookingPageBuilderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('bookings.manage');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', Rule::in(['tech', 'dark'])],
            'primary_color' => ['required', 'string', 'regex:/^#[A-F0-9]{6}$/'],
            'font_preset' => ['required', 'string', Rule::in(['modern', 'elegant', 'playful', 'minimal', 'bold'])],
            'radius_preset' => ['required', 'string', Rule::in(['sharp', 'subtle', 'medium', 'rounded', 'pill'])],
            'subheadline' => ['required', 'string', 'max:180'],
            'cta_label' => ['required', 'string', 'max:60'],
            'cta_size' => ['required', 'string', Rule::in(['small', 'medium', 'large'])],
            'trust_badge' => ['required', 'string', 'max:140'],
            'existing_gallery_paths' => ['nullable', 'array', 'max:4'],
            'existing_gallery_paths.*' => ['string', 'max:255'],
            'gallery_images' => ['nullable', 'array', 'max:4'],
            'gallery_images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mode.required' => 'Mode tampilan wajib dipilih.',
            'mode.in' => 'Mode tampilan tidak valid.',
            'primary_color.required' => 'Warna utama wajib dipilih.',
            'primary_color.regex' => 'Format warna utama harus HEX (contoh: #0F766E).',
            'font_preset.required' => 'Preset font wajib dipilih.',
            'font_preset.in' => 'Preset font tidak valid.',
            'radius_preset.required' => 'Preset radius wajib dipilih.',
            'radius_preset.in' => 'Preset radius tidak valid.',
            'subheadline.required' => 'Subjudul wajib diisi.',
            'cta_label.required' => 'Label tombol CTA wajib diisi.',
            'cta_size.required' => 'Ukuran tombol CTA wajib dipilih.',
            'cta_size.in' => 'Ukuran tombol CTA tidak valid.',
            'trust_badge.required' => 'Teks badge trust wajib diisi.',
            'gallery_images.max' => 'Maksimal upload 4 gambar galeri.',
            'gallery_images.*.image' => 'File galeri harus berupa gambar.',
            'gallery_images.*.mimes' => 'Format gambar galeri harus JPG, JPEG, PNG, atau WEBP.',
            'gallery_images.*.max' => 'Ukuran setiap gambar galeri maksimal 10 MB.',
            'is_active.required' => 'Status aktivasi wajib dipilih.',
            'is_active.boolean' => 'Status aktivasi tidak valid.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $existingGalleryPaths = $this->input('existing_gallery_paths', []);
            $newGalleryImages = $this->file('gallery_images', []);

            $existingCount = is_array($existingGalleryPaths) ? count($existingGalleryPaths) : 0;
            $newCount = is_array($newGalleryImages) ? count($newGalleryImages) : 0;

            if (($existingCount + $newCount) > 4) {
                $validator->errors()->add('gallery_images', 'Total gambar galeri maksimal 4 file.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $normalizedColor = strtoupper(trim((string) $this->input('primary_color', '')));
        if ($normalizedColor !== '' && ! str_starts_with($normalizedColor, '#')) {
            $normalizedColor = '#'.$normalizedColor;
        }

        $existingGalleryPaths = $this->input('existing_gallery_paths', []);
        if (! is_array($existingGalleryPaths)) {
            $existingGalleryPaths = [];
        }

        $existingGalleryPaths = array_values(array_filter(array_map(
            static fn ($path): string => trim((string) $path),
            $existingGalleryPaths,
        ), static fn (string $path): bool => $path !== ''));

        $this->merge([
            'mode' => strtolower(trim((string) $this->input('mode', 'tech'))),
            'primary_color' => $normalizedColor,
            'font_preset' => strtolower(trim((string) $this->input('font_preset', 'modern'))),
            'radius_preset' => strtolower(trim((string) $this->input('radius_preset', 'medium'))),
            'subheadline' => trim((string) $this->input('subheadline', '')),
            'cta_label' => trim((string) $this->input('cta_label', '')),
            'cta_size' => strtolower(trim((string) $this->input('cta_size', 'medium'))),
            'trust_badge' => trim((string) $this->input('trust_badge', '')),
            'existing_gallery_paths' => $existingGalleryPaths,
            'is_active' => filter_var($this->input('is_active', true), FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}
