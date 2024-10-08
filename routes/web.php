<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\HomeController;
use App\Http\Livewire\Customer;
use App\Http\Livewire\DeliveryPeople;
use App\Http\Livewire\DirectoryLivewire;
use App\Http\Livewire\Map;
use App\Http\Livewire\Menu;
use App\Http\Livewire\Monolith\LoginLivewire;
use App\Http\Livewire\Promocodes;
use App\Http\Livewire\RegisterLivewire;
use App\Http\Livewire\Restaurant;
use App\Http\Livewire\Settings;
use App\Http\Livewire\Users;
use Illuminate\Support\Facades\Route;

Route::middleware(['guest'])->group(function () {
    Route::get('login-admin', [LoginController::class, 'showLoginForm']);
    Route::post('login-admin', [LoginController::class, 'login'])->name('login-admin');
    Route::get('logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('login', LoginLivewire::class)->name('login');
    Route::get('register', RegisterLivewire::class)->name('register');
});

Route::middleware(['auth:web', 'role:admin'])
    ->group(function () {
        Route::get('/', [HomeController::class, 'index'])->name('home');
        Route::get('/restaurant', Restaurant::class)->name('restaurant');
        Route::get('/restaurant/menu', Menu::class)->name('menu');
        Route::get('/customer', Customer::class)->name('customer');
        Route::get('/delivery-people', DeliveryPeople::class)->name('delivery-people');
        Route::get('/promocodes', Promocodes::class)->name('promocodes');
        Route::get('/settings', Settings::class)->name('settings');
        Route::get('/users', Users::class)->name('users');
        Route::get('/map', Map::class)->name('map');
    });

Route::middleware(['auth:web', 'role:customer'])
    ->group(function () {
        Route::get('/directory', DirectoryLivewire::class)->name('directory');
    });
