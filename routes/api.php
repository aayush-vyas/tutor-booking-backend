<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BookingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Health Check Route
|--------------------------------------------------------------------------
*/
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Tutor Booking API is running',
        'timestamp' => now()->toDateTimeString(),
    ]);
});

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes (accessible by all authenticated users)
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
    });

    /*
    |--------------------------------------------------------------------------
    | Booking Routes (Student & Tutor)
    |--------------------------------------------------------------------------
    */
    Route::prefix('bookings')->group(function () {
        // Both students and tutors can view bookings
        Route::get('/', [BookingController::class, 'index']);
        Route::get('/{booking}', [BookingController::class, 'show']);

        // Only students can create bookings
        Route::middleware('role:student')->group(function () {
            Route::post('/', [BookingController::class, 'store']);
        });

        // Only tutors can confirm and complete bookings
        Route::middleware('role:tutor')->group(function () {
            Route::post('/{booking}/confirm', [BookingController::class, 'confirm']);
            Route::post('/{booking}/complete', [BookingController::class, 'complete']);
        });

        // Both students and tutors can cancel and reschedule their bookings
        Route::post('/{booking}/cancel', [BookingController::class, 'cancel']);
        Route::post('/{booking}/reschedule', [BookingController::class, 'reschedule']);
    });

    // Get available slots for a tutor (accessible by all authenticated users)
    Route::get('/tutors/{tutorId}/available-slots', [BookingController::class, 'availableSlots']);

    /*
    |--------------------------------------------------------------------------
    | Availability Routes (Tutor Only)
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:tutor')->prefix('availabilities')->group(function () {
        Route::post('/', [AvailabilityController::class, 'store']); // Create availability
        Route::put('/{availability}', [AvailabilityController::class, 'update']); // Update availability
        Route::delete('/{availability}', [AvailabilityController::class, 'destroy']); // Delete availability
    });

    // View availabilities (accessible by all authenticated users)
    Route::prefix('availabilities')->group(function () {
        Route::get('/', [AvailabilityController::class, 'index']); // List availabilities
        Route::get('/{availability}', [AvailabilityController::class, 'show']); // Show availability
    });

    // Get tutor schedule (accessible by all authenticated users)
    Route::get('/tutors/{tutorId}/schedule', [AvailabilityController::class, 'tutorSchedule']);
});
