<?php

use App\Http\Controllers\Admin\AssistantController;
use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\CalendarController;
use App\Http\Controllers\Admin\CarController as AdminCarController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DriverController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\FuelController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\TrackingController;
use App\Http\Controllers\SuperAdmin\PlanController as SuperAdminPlanController;
use App\Http\Controllers\Driver\DriverDashboardController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\TestimonialController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TrackingController as PublicTrackingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'index'])->name('home');

Route::post('/booking', [BookingController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('booking.store');

Route::post('/kontak', [ContactController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('contact.store');

/*
|--------------------------------------------------------------------------
| Order tracking (public — via unguessable booking code, no login)
|--------------------------------------------------------------------------
*/
Route::get('/lacak', [PublicTrackingController::class, 'search'])->name('tracking.search');
Route::post('/lacak', [PublicTrackingController::class, 'find'])
    ->middleware('throttle:10,1') // slow down brute-forcing booking codes
    ->name('tracking.find');
Route::get('/lacak/{bookingCode}', [PublicTrackingController::class, 'show'])->name('tracking.show');
Route::get('/pantau/{bookingCode}', [PublicTrackingController::class, 'watch'])->name('tracking.watch');

/*
|--------------------------------------------------------------------------
| Payment (gateway callback + customer return page)
|--------------------------------------------------------------------------
*/
Route::post('/payment/midtrans/webhook', [PaymentController::class, 'webhook'])->name('payment.webhook');
Route::get('/payment/finish', [PaymentController::class, 'finish'])->name('payment.finish');

/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
| No `guest` middleware: it would bounce an already-authenticated user to `/`
| (landing). LoginController::show() instead redirects them to their own
| dashboard based on role (see homeFor()).
*/
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('login.attempt');

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Admin dashboard (auth + admin)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'admin'])
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Fleet availability calendar
        Route::get('calendar', [CalendarController::class, 'index'])->name('calendar');

        // Unit tracking (GPS + map)
        Route::get('tracking', [TrackingController::class, 'index'])->name('tracking');
        Route::get('tracking/live', [TrackingController::class, 'live'])->middleware('throttle:60,1')->name('tracking.live');
        Route::get('tracking/history', [TrackingController::class, 'history'])->middleware('throttle:60,1')->name('tracking.history');

        // Fuel (BBM/solar) logs & leak indicators
        Route::get('fuel', [FuelController::class, 'index'])->name('fuel.index');
        Route::get('fuel/create', [FuelController::class, 'create'])->name('fuel.create');
        Route::post('fuel', [FuelController::class, 'store'])->name('fuel.store');
        Route::delete('fuel/{fuelLog}', [FuelController::class, 'destroy'])->name('fuel.destroy');

        // Operational data export (PDF/Excel)
        Route::get('export/{dataset}/{format}', [ExportController::class, 'download'])
            ->where('format', 'xlsx|pdf')
            ->name('export.download');

        // Analytics & reports
        Route::get('reports', [ReportController::class, 'index'])->name('reports');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');

        // AI business assistant
        Route::get('assistant', [AssistantController::class, 'index'])->name('assistant');
        Route::post('assistant', [AssistantController::class, 'ask'])->middleware('throttle:20,1')->name('assistant.ask');
        Route::get('assistant/insight', [AssistantController::class, 'insight'])->middleware('throttle:30,1')->name('assistant.insight');

        // Cars CRUD
        Route::resource('cars', AdminCarController::class)->except('show');

        // Drivers CRUD
        Route::resource('drivers', DriverController::class)->except('show');

        // Testimonials CRUD
        Route::resource('testimonials', TestimonialController::class)->except('show');

        // Bookings
        Route::get('bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
        Route::get('bookings/{booking}', [AdminBookingController::class, 'show'])->name('bookings.show');
        Route::patch('bookings/{booking}/status', [AdminBookingController::class, 'updateStatus'])->name('bookings.status');
        Route::patch('bookings/{booking}/trip-status', [AdminBookingController::class, 'updateTripStatus'])->name('bookings.trip-status');
        Route::get('bookings/{booking}/replay', [AdminBookingController::class, 'replay'])->name('bookings.replay');
        Route::patch('bookings/{booking}/driver', [AdminBookingController::class, 'assignDriver'])->name('bookings.driver');
        Route::get('bookings/{booking}/invoice', [AdminBookingController::class, 'invoice'])->name('bookings.invoice');
        Route::post('bookings/{booking}/email', [AdminBookingController::class, 'emailInvoice'])->name('bookings.email');
        Route::delete('bookings/{booking}', [AdminBookingController::class, 'destroy'])->name('bookings.destroy');

        // Messages
        Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
        Route::get('messages/{message}', [MessageController::class, 'show'])->name('messages.show');
        Route::patch('messages/{message}/toggle', [MessageController::class, 'toggle'])->name('messages.toggle');
        Route::delete('messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');
    });

/*
|--------------------------------------------------------------------------
| Super admin (platform owner — auth + role:super_admin)
|--------------------------------------------------------------------------
*/
Route::prefix('superadmin')
    ->name('superadmin.')
    ->middleware(['auth', 'role:super_admin'])
    ->group(function () {
        Route::get('plans', [SuperAdminPlanController::class, 'index'])->name('plans.index');
        Route::patch('plans/{plan}', [SuperAdminPlanController::class, 'update'])->name('plans.update');
        Route::patch('plans/{plan}/features', [SuperAdminPlanController::class, 'updateFeatures'])->name('plans.features');
    });

/*
|--------------------------------------------------------------------------
| Driver area (auth + role:driver)
|--------------------------------------------------------------------------
*/
Route::prefix('driver')
    ->name('driver.')
    ->middleware(['auth', 'role:driver'])
    ->group(function () {
        Route::get('/', [DriverDashboardController::class, 'index'])->name('dashboard');
    });
