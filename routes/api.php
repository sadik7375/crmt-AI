<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\LabReportController;
use App\Http\Controllers\HealthAnalyticsController;
use App\Http\Controllers\AIAssistantController;
use App\Http\Controllers\PrescriptionController;

/*
|--------------------------------------------------------------------------
| CareMate AI REST API Routes
|--------------------------------------------------------------------------
*/

// 🔓 Public Auth Routes
Route::prefix('auth')->group(function () {
    Route::post('/register',        [AuthController::class, 'register']);
    Route::post('/login',           [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/verify-otp',      [AuthController::class, 'verifyOtp']);
    Route::get('/verify-email-link', [AuthController::class, 'verifyEmailLink']);
    Route::post('/resend-verification', [AuthController::class, 'resendVerification']);
    Route::post('/reset-password',  [AuthController::class, 'resetPassword']);
    Route::post('/google',          [AuthController::class, 'googleLogin']);
});

// 🔐 Protected Routes (Require Token / Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    // Profile
    Route::get('/profile',               [ProfileController::class, 'getProfile']);
    Route::put('/profile/update',        [ProfileController::class, 'updateProfile']);
    Route::post('/profile/password',     [ProfileController::class, 'changePassword']);
    Route::delete('/profile/account',    [ProfileController::class, 'deleteAccount']);

    // Medicines & Reminders
    Route::get('/medicines',             [MedicineController::class, 'index']);
    Route::get('/medicines/history',     [MedicineController::class, 'history']);
    Route::post('/medicines/{id}/toggle',[MedicineController::class, 'toggleTaken']);
    Route::post('/medicines/manual',     [MedicineController::class, 'storeManual']);
    Route::post('/medicines/update-slot-times', [MedicineController::class, 'updateSlotTimes']);
    Route::put('/medicines/{id}',        [MedicineController::class, 'update']);
    Route::delete('/medicines/{id}',     [MedicineController::class, 'destroy']);

    // Prescriptions
    Route::get('/prescriptions',         [PrescriptionController::class, 'index']);
    Route::post('/prescriptions/scan',   [PrescriptionController::class, 'scan']);
    Route::post('/prescriptions/{id}/activate-reminders', [PrescriptionController::class, 'activateReminders']);

    // Appointments
    Route::get('/appointments',          [AppointmentController::class, 'index']);
    Route::post('/appointments',         [AppointmentController::class, 'store']);
    Route::post('/appointments/parse-voice', [AppointmentController::class, 'parseVoice']);

    // Lab Reports
    Route::get('/lab-reports',          [LabReportController::class, 'index']);
    Route::get('/lab-reports/{id}',      [LabReportController::class, 'show']);
    Route::post('/lab-reports/scan',     [LabReportController::class, 'scan']);
    Route::post('/lab-reports',          [LabReportController::class, 'store']);

    // Health Analytics & Danger Zone Alerts
    Route::get('/health-analytics',      [HealthAnalyticsController::class, 'getAnalytics']);

    // AI Health Assistant Chatbot
    Route::post('/ai-assistant/chat',    [AIAssistantController::class, 'chat']);
});
