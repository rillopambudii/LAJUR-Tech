<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class BookingRequest extends FormRequest
{
    /** Keep booking errors in their own bag so only the modal shows them. */
    protected $errorBag = 'booking';

    /** Sewa lebih panjang dari ini hampir pasti salah input / penyalahgunaan. */
    public const MAX_RENTAL_DAYS = 90;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'car_id' => ['required', 'integer', 'exists:cars,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30', 'regex:/^[0-9\+\-\s\(\)]{6,30}$/'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** Batasi durasi sewa agar satu booking tak bisa mengunci mobil bertahun-tahun. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->hasAny(['start_date', 'end_date'])) {
                return;
            }

            $start = Carbon::parse($this->input('start_date'));
            $end = Carbon::parse($this->input('end_date'));

            if ($start->diffInDays($end) > self::MAX_RENTAL_DAYS) {
                $v->errors()->add('end_date', 'Durasi sewa maksimal '.self::MAX_RENTAL_DAYS.' hari. Silakan hubungi kami untuk sewa lebih lama.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_name' => 'nama',
            'customer_email' => 'email',
            'customer_phone' => 'nomor HP',
            'start_date' => 'tanggal mulai',
            'end_date' => 'tanggal selesai',
            'notes' => 'catatan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'car_id.required' => 'Silakan pilih mobil terlebih dahulu.',
            'car_id.exists' => 'Mobil yang dipilih tidak ditemukan.',
            'customer_name.required' => 'Nama wajib diisi.',
            'customer_email.required' => 'Email wajib diisi.',
            'customer_email.email' => 'Format email tidak valid.',
            'customer_phone.required' => 'Nomor HP wajib diisi.',
            'customer_phone.regex' => 'Nomor HP hanya boleh berisi angka dan tanda + - ( ).',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'start_date.after_or_equal' => 'Tanggal mulai tidak boleh sebelum hari ini.',
            'end_date.required' => 'Tanggal selesai wajib diisi.',
            'end_date.after' => 'Tanggal selesai harus setelah tanggal mulai.',
        ];
    }
}
