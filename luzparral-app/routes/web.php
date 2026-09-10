<?php

use App\Http\Controllers\ContingencyMapController;
use App\Http\Controllers\ContingencyDetailController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OperationalSearchController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ContingencyReportController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/contingencias/mapa', ContingencyMapController::class)
    ->middleware(['auth', 'verified'])
    ->name('contingencies.map');

Route::get('/buscador-operacional', OperationalSearchController::class)
    ->middleware(['auth', 'verified'])
    ->name('contingencies.search');

Route::get('/informes', [ContingencyReportController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('reports.index');

Route::get('/informes/contingencias.csv', [ContingencyReportController::class, 'exportCsv'])
    ->middleware(['auth', 'verified'])
    ->name('reports.csv');

Route::get('/informes/contingencias.pdf', [ContingencyReportController::class, 'exportPdf'])
    ->middleware(['auth', 'verified'])
    ->name('reports.pdf');

Route::get('/contingencias/{contingency}', ContingencyDetailController::class)
    ->middleware(['auth', 'verified'])
    ->name('contingencies.show');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
