<?php

use Illuminate\Support\Facades\Route;

Route::get('/login', function() { return redirect('/'); })->name('login');
Route::get('/register', function() { return redirect('/'); })->name('register');

Route::get('/', function () {
    return view('welcome');
});
