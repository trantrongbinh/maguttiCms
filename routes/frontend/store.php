<?php

use App\maguttiCms\Domain\Store\Controllers\MainStoreController;
use App\maguttiCms\Website\Controllers\Store\CartController;
use App\maguttiCms\Website\Controllers\Store\OrderSendController;

use App\maguttiCms\Website\Controllers\Store\OrderManageController;
use App\maguttiCms\Website\Controllers\Store\OrderPaymentController;
use App\maguttiCms\Website\Controllers\Store\OrderResultController;

use App\maguttiCms\Domain\Store\Controllers\AddressStepController;
use App\maguttiCms\Domain\Store\Controllers\ConfirmStepController;
use App\maguttiCms\Domain\Store\Notifications\NewOrderNotification;
use App\maguttiCms\Domain\Store\Controllers\PaymentMethodStepController;

Route::get('/cart/', CartController::class)->middleware('storeenabled')->name('cart');
Route::get('/cart/address', [AddressStepController::class, 'view'])->middleware('storeenabled', 'auth')->name('cart.address');
Route::post('/cart/address', [AddressStepController::class, 'store'])->middleware('storeenabled');
Route::get('/cart/payment', [PaymentMethodStepController::class, 'view'])->middleware('storeenabled')->name('cart.payment');
Route::post('/cart/payment', [PaymentMethodStepController::class, 'store'])->middleware('storeenabled');
Route::get('/cart/resume', [ConfirmStepController::class, 'view'])->middleware('storeenabled')->name('cart.resume');
Route::post('/cart/resume', [ConfirmStepController::class, 'view'])->middleware('storeenabled');

Route::get('/order-send/', [OrderManageController::class, 'handle'])->middleware(['storeenabled', 'auth'])->name('order.send');
Route::get('/order-confirm/{token}', [OrderResultController::class, 'view'])->middleware(['storeenabled', 'auth'])->name('order.confirm.success');

Route::get('/order-payment-confirm/{token?}', [OrderPaymentController::class, 'orderPaymentConfirm'])->middleware(['storeenabled', 'auth'])->name('order.payment.cancel');
Route::get('/order-payment-cancel/{cart:token}', [OrderPaymentController::class, 'cancel'])->middleware(['storeenabled', 'auth'])->name('order.payment.cancel');


Route::get('/order/mailable/{order:token}', function (\App\Order $order) {
    return (new NewOrderNotification($order))
        ->toMail($order->user);
});


Route::get('/order-login/', [MainStoreController::class,'orderLogin'])->name('pino')->middleware(['storeenabled']);
Route::get('/order-submit/', [MainStoreController::class,'orderSubmit'])->middleware(['storeenabled'])->name('cart.detail');

Route::get('/order-review/{token}', '\App\maguttiCms\Website\Controllers\StoreController@orderReview')->middleware(['storeenabled', 'auth']);
Route::post('/order-payment/', '\App\maguttiCms\Website\Controllers\StoreController@orderPayment')->middleware(['storeenabled', 'auth']);
Route::get('/order-payment-result/{token}', '\App\maguttiCms\Website\Controllers\StoreController@orderResult')->middleware(['storeenabled', 'auth']);
