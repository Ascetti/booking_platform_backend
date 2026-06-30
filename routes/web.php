<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

Route::get('/preview-mail', function () {
    $booking = \App\Models\Booking::with([
        'hotel', 'category', 'plan', 'guests', 'services'
    ])->latest()->first();
    
    return new \App\Mail\BookingConfirmation($booking);
});

require __DIR__.'/auth.php';
