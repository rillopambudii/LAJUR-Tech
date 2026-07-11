<?php

namespace App\Http\Requests;

use App\Models\Car;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize input before validation:
     * - strip thousand separators from price (NFR-19)
     * - coerce checkbox values to booleans
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('price_per_day')) {
            $merge['price_per_day'] = preg_replace('/\D/', '', (string) $this->input('price_per_day'));
        }

        $merge['is_available'] = $this->boolean('is_available');
        $merge['is_featured'] = $this->boolean('is_featured');

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'plate_number' => ['nullable', 'string', 'max:20'],
            'brand' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(Car::TYPES)],
            'transmission' => ['required', Rule::in(Car::TRANSMISSIONS)],
            'fuel_type' => ['required', Rule::in(Car::FUEL_TYPES)],
            'tank_capacity_liters' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'fuel_baseline_km_per_l' => ['nullable', 'numeric', 'min:0.1', 'max:100'],
            'seats' => ['required', 'integer', 'min:1', 'max:20'],
            'price_per_day' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'description' => ['nullable', 'string', 'max:5000'],
            'tax_due_date' => ['nullable', 'date'],
            'service_due_date' => ['nullable', 'date'],
            'is_available' => ['boolean'],
            'is_featured' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama mobil',
            'brand' => 'merek',
            'type' => 'tipe',
            'transmission' => 'transmisi',
            'fuel_type' => 'bahan bakar',
            'seats' => 'jumlah kursi',
            'price_per_day' => 'harga per hari',
            'tank_capacity_liters' => 'kapasitas tangki',
            'fuel_baseline_km_per_l' => 'baseline konsumsi',
            'image' => 'foto mobil',
            'image_url' => 'URL gambar',
            'sort_order' => 'urutan tampil',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama mobil wajib diisi.',
            'brand.required' => 'Merek wajib diisi.',
            'type.required' => 'Tipe wajib dipilih.',
            'type.in' => 'Tipe yang dipilih tidak valid.',
            'transmission.in' => 'Transmisi yang dipilih tidak valid.',
            'fuel_type.in' => 'Bahan bakar yang dipilih tidak valid.',
            'seats.required' => 'Jumlah kursi wajib diisi.',
            'price_per_day.required' => 'Harga per hari wajib diisi.',
            'price_per_day.integer' => 'Harga per hari harus berupa angka.',
            'image.image' => 'Berkas harus berupa gambar.',
            'image.mimes' => 'Gambar harus berformat JPEG, JPG, PNG, atau WEBP.',
            'image.max' => 'Ukuran gambar maksimal 2 MB.',
            'image_url.url' => 'URL gambar tidak valid.',
        ];
    }
}
