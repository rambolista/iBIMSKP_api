<?php

use App\Http\Controllers\Api\BarangayId\BarangayIdController;
use App\Http\Controllers\Api\BarangayServices\ServiceRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/service-request-verification/{verificationCode}', [ServiceRequestController::class, 'verifyDocumentPage']);
Route::get('/barangay-id-verification/{verificationCode}', [BarangayIdController::class, 'verifyDocumentPage']);
