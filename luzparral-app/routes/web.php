<?php

use App\Http\Controllers\ContingencyMapController;
use App\Http\Controllers\ContingencyDetailController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OperationalSearchController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ContingencyReportController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware(['auth', 'verified', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/contingencias/mapa', ContingencyMapController::class)->name('contingencies.map');
    Route::get('/buscador-operacional', OperationalSearchController::class)->name('contingencies.search');
    Route::get('/contingencias/{contingency}', ContingencyDetailController::class)->name('contingencies.show');

    Route::middleware('role:admin,supervisor')->group(function () {
        Route::get('/informes', [ContingencyReportController::class, 'index'])
            ->name('reports.index');
        Route::get('/informes/contingencias.csv', [ContingencyReportController::class, 'exportCsv'])
            ->name('reports.csv');
        Route::get('/informes/contingencias.pdf', [ContingencyReportController::class, 'exportPdf'])
            ->name('reports.pdf');
    });
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
