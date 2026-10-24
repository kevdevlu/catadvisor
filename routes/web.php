<?php

use Illuminate\Support\Facades\Route;

// Every address outside /api returns the React app, and React Router picks the
// page. For example, /login and /students/9b1f0c2e-... both get the same shell.
Route::view('/{path?}', 'app')->where('path', '(?!api(?:/|$)).*');
