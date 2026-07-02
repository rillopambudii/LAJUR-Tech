<?php

use App\Http\Controllers\Admin\AssistantController;
use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\CalendarController;
use App\Http\Controllers\Admin\CarController as AdminCarController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DriverController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Driver\DriverDashboardController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\TestimonialController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
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
| Guest (auth) routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.attempt');
});

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

        // Analytics & reports
        Route::get('reports', [ReportController::class, 'index'])->name('reports');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');

        // AI business assistant
        Route::get('assistant', [AssistantController::class, 'index'])->name('assistant');
        Route::post('assistant', [AssistantController::class, 'ask'])->middleware('throttle:20,1')->name('assistant.ask');

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
| Driver area (auth + role:driver)
|--------------------------------------------------------------------------
*/
Route::prefix('driver')
    ->name('driver.')
    ->middleware(['auth', 'role:driver'])
    ->group(function () {
        Route::get('/', [DriverDashboardController::class, 'index'])->name('dashboard');
    });
