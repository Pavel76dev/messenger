<?php

use Illuminate\Support\Facades\Route;

/*
| SPA shell: React mounts in resources/views/app.blade.php.
| API routes live in routes/api.php (prefix /api) and are not caught here.
*/
Route::view('/{any?}', 'app')->where('any', '.*');
