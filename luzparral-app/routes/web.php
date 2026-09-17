<?php

use App\Http\Controllers\ContingencyDetailController;
use App\Http\Controllers\ContingencyImportController;
use App\Http\Controllers\ContingencyMapController;
use App\Http\Controllers\ContingencyReportController;
use App\Http\Controllers\ContingencyStatusController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FieldReportAttachmentController;
use App\Http\Controllers\FieldReportController;
use App\Http\Controllers\OperationalSearchController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WeatherForecastController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware(['auth', 'verified', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/contingencias/mapa', ContingencyMapController::class)->name('contingencies.map');
    Route::get('/contingencias/mapa/datos', [ContingencyMapController::class, 'data'])->name('contingencies.map.data');
    Route::get('/pronostico-meteorologico', WeatherForecastController::class)->name('weather.forecast');
    Route::get('/buscador-operacional', OperationalSearchController::class)->name('contingencies.search');
    Route::get('/contingencias/{contingency}', ContingencyDetailController::class)->name('contingencies.show');
    Route::patch('/contingencias/{contingency}/estado', [ContingencyStatusController::class, 'update'])
        ->middleware('role:admin,supervisor,operator')
        ->name('contingencies.status.update');
    Route::post('/contingencias/{contingency}/antecedentes-terreno', [FieldReportController::class, 'store'])
        ->middleware('role:admin,supervisor,operator')
        ->name('contingencies.field-reports.store');
    Route::get('/antecedentes-terreno/{attachment}/evidencia', [FieldReportAttachmentController::class, 'download'])
        ->name('field-reports.attachments.download');

    Route::middleware('role:admin,supervisor')->group(function () {
        Route::get('/importaciones', [ContingencyImportController::class, 'index'])->name('imports.index');
        Route::post('/importaciones/previsualizar', [ContingencyImportController::class, 'preview'])->name('imports.preview');
        Route::post('/importaciones', [ContingencyImportController::class, 'store'])->name('imports.store');
        Route::get('/importaciones/plantilla', [ContingencyImportController::class, 'template'])->name('imports.template');
    });

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
});

require __DIR__.'/auth.php';
