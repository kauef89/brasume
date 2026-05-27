<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GoogleController;

// 0. Redireciona quem aceder à raiz diretamente para o Hub
Route::redirect("/", "/hub");
Route::redirect("/login", "/hub/login");

// 1. Rotas do Google
Route::get("/auth/google/redirect", [
    GoogleController::class,
    "redirectToGoogle",
])->name("google.redirect");
Route::get("/auth/google/callback", [
    GoogleController::class,
    "handleGoogleCallback",
])->name("google.callback");
Route::get("/auth/google/magic-login", [
    GoogleController::class,
    "magicLogin",
])->name("google.magic");

// (A rota do /pos foi removida daqui!)
