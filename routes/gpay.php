<?php

use App\Http\Controllers\CustomerPackageController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\CustomerPackagePaymentController;
use App\Http\Controllers\ManualPaymentMethodController;
use App\Http\Controllers\SellerPackageController;
use App\Http\Controllers\SellerPackagePaymentController;
use App\Http\Controllers\GpayController;
/*
|--------------------------------------------------------------------------
| Offline Payment Routes
|--------------------------------------------------------------------------
|
| Here is where you can register admin routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/process-payment',[GpayController::class,'processPayment']);

//Admin
Route::group(['prefix' =>'admin', 'middleware' => ['auth', 'admin']], function(){
    Route::resource('gpay', ManualPaymentMethodController::class);
});

