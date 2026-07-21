<?php

namespace App\Providers;

use App\Tenancy\Branding;
use App\Tenancy\TenantManager;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Holds the active tenant for the current request/process; read by the
        // BelongsToTenant global scope. One instance per request lifecycle.
        $this->app->singleton(TenantManager::class);

        // Select the payment driver from config. Defaults to manual/offline.
        $this->app->bind(\App\Payments\PaymentGateway::class, function ($app) {
            return match (config('services.payment.gateway')) {
                'midtrans' => $app->make(\App\Payments\MidtransGateway::class),
                default => $app->make(\App\Payments\ManualPaymentGateway::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Shared hosting (mis. InfinityFree) membatasi panjang key index ke
        // 1000 byte; utf8mb4 × 255 char = 1020 byte. Batasi default ke 191.
        Schema::defaultStringLength(191);

        // If a CA bundle is configured, use it for every outbound HTTPS request
        // (fixes local "cURL error 60" without depending on php.ini). No-op in
        // production where the system CA store is available.
        if ($ca = config('services.ca_bundle')) {
            Http::globalOptions(['verify' => $ca]);
        }

        // Use our vanilla-CSS pagination markup everywhere.
        Paginator::defaultView('pagination.lajur');

        // Localise dates (month names in charts, etc.).
        Carbon::setLocale('id');

        // Storefront branding: the public layout + home read tenant branding;
        // dashboards keep Lajur branding — layouts.admin & admin.site only get
        // it for the "Lihat Situs" link (Branding::siteUrl). NOTE: composer nama
        // view harus didaftarkan satu-satu — @extends('layouts.public') TIDAK
        // otomatis mewariskan composer parent-nya ke view anak.
        View::composer(['layouts.public', 'home', 'layouts.admin', 'admin.site', 'driver.public-profile'], function ($view) {
            $view->with('branding', new Branding(app(TenantManager::class)->current()));
        });

        // Email lupa-password default Laravel berbahasa Inggris & tak berbrand.
        // Titik kustomisasi resmi framework, berlaku utk semua Notifiable (owner/
        // admin/driver/superadmin sama-sama lewat form login bersama).
        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));
            $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject('Atur Ulang Kata Sandi — Lajur')
                ->greeting('Halo!')
                ->line('Kami menerima permintaan untuk mengatur ulang kata sandi akun Lajur Anda.')
                ->action('Atur Ulang Kata Sandi', $url)
                ->line("Tautan ini berlaku selama {$expire} menit.")
                ->line('Jika Anda tidak meminta ini, abaikan saja email ini — kata sandi Anda tidak akan berubah.')
                ->salutation('Salam, Tim Lajur');
        });
    }
}
