<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'Xpressbine',
        'description' => 'Lusaka in-person services marketplace API',
        'currency' => config('xpressbine.currency'),
        'health' => '/up',
        'api' => '/api',
    ]);
});
