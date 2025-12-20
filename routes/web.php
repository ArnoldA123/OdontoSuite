<?php

use Illuminate\Support\Facades\Route;

// Ruta raíz
Route::get('/', function () {
    return view('app');
});

// Rutas específicas para la aplicación Vue.js
Route::get('/login', function () {
    return view('app');
});

Route::get('/dashboard', function () {
    return view('app');
});

Route::get('/calendar', function () {
    return view('app');
});

Route::get('/patients', function () {
    return view('app');
});

Route::get('/professionals', function () {
    return view('app');
});

Route::get('/environments', function () {
    return view('app');
});

Route::get('/appointment-types', function () {
    return view('app');
});

// Catch-all para otras rutas (excluyendo API)
Route::get('/{any}', function () {
    return view('app');
})->where('any', '^(?!api).*');
