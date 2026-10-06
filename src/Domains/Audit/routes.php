<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use JayI\Keen\Domains\Audit\Http\Controllers\AuditController;

Route::get('entries', [AuditController::class, 'index'])->name('entries.index');
Route::post('entries', [AuditController::class, 'store'])->name('entries.store');
Route::get('entries/{entry}', [AuditController::class, 'show'])->whereNumber('entry')->name('entries.show');
