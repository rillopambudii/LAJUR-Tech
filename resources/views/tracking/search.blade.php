@extends('layouts.public')

@section('title', 'Lacak Pesanan — Lajur')

@section('content')
<section class="section" id="lacak">
    <div class="container" style="max-width:520px">

        <div class="section-head reveal" style="text-align:left;margin-bottom:22px">
            <span class="eyebrow">Lacak Pesanan</span>
            <h1 class="section-title" style="font-size:1.7rem;margin-bottom:6px">Cari status pesananmu</h1>
            <p class="section-sub" style="margin:0">Masukkan kode booking dan nomor HP yang kamu pakai saat memesan.</p>
        </div>

        @if (session('tracking_error'))
            <div class="alert alert-error" role="alert">
                <x-icon name="alert" /> <span>{{ session('tracking_error') }}</span>
            </div>
        @endif

        <div class="panel reveal">
            <div class="panel-body">
                <form action="{{ route('tracking.find') }}" method="POST">
                    @csrf
                    <div class="field">
                        <label for="booking_code">Kode Booking <span class="req">*</span></label>
                        <input type="text" id="booking_code" name="booking_code" value="{{ old('booking_code') }}"
                               class="input mono @error('booking_code') has-error @enderror"
                               placeholder="cth: LJR-8F3K2A" autocomplete="off" required>
                        @error('booking_code')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="field">
                        <label for="customer_phone">Nomor HP <span class="req">*</span></label>
                        <input type="tel" id="customer_phone" name="customer_phone" value="{{ old('customer_phone') }}"
                               class="input @error('customer_phone') has-error @enderror"
                               placeholder="cth: 0812xxxxxxxx" required>
                        @error('customer_phone')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <x-icon name="search" /> Lacak Pesanan
                    </button>
                </form>
            </div>
        </div>

    </div>
</section>
@endsection
