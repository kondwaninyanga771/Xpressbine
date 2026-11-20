<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
| The React screens are Inertia pages. They read and write through the
| existing JSON API (Sanctum bearer token). These routes only name the
| page and pass ids that already appear in the URL.
*/

Route::get('/', fn () => Inertia::render('Feed'))->name('home');
Route::get('/discover', fn () => Inertia::render('Discover'))->name('discover');
Route::get('/map', fn () => Inertia::render('Map'))->name('map');
Route::get('/login', fn () => Inertia::render('Login'))->name('login');
Route::get('/register', fn () => Inertia::render('Register'))->name('register');
Route::get('/account', fn () => Inertia::render('Account'))->name('account');
Route::get('/messages', fn () => Inertia::render('Messages'))->name('messages');
Route::get('/dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');
Route::get('/ops', fn () => Inertia::render('Ops'))->name('ops');
Route::get('/wallet', fn () => Inertia::render('Wallet'))->name('wallet');

Route::get('/providers/{provider}', function (int $provider) {
    return Inertia::render('Provider', [
        'providerId' => $provider,
    ]);
})->whereNumber('provider')->name('providers.show');

Route::get('/bookings', fn () => Inertia::render('Bookings'))->name('bookings');

Route::get('/bookings/{booking}', function (int $booking) {
    return Inertia::render('Booking', [
        'bookingId' => $booking,
    ]);
})->whereNumber('booking')->name('bookings.show');

Route::get('/messages/{peer}', function (int $peer) {
    return Inertia::render('Chat', [
        'peerId' => $peer,
    ]);
})->whereNumber('peer')->name('messages.show');
