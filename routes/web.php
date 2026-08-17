<?php

use App\Http\Controllers\Api\BarangayServices\ServiceRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/service-request-verification/{verificationCode}', [ServiceRequestController::class, 'verifyDocumentPage']);
