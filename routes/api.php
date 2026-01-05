<?php

use App\Http\Controllers\ChemicalsController;
use App\Http\Controllers\ChemicalSDSController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\ApiAuthMiddleware;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\UtilityTrashController;
use App\Http\Controllers\ChecklistItemsController;
use App\Http\Controllers\CheckItemsController;
use App\Http\Controllers\AssetsController;
use App\Http\Controllers\HazardousWasteTurnOverLogSheetController;

Route::middleware([ApiAuthMiddleware::class])
  ->name('api.')
  ->group(function () {

    Route::prefix('location')->name('location.')->group(function () {
      Route::get('/trend', [LocationController::class, 'getAllLocation'])
        ->name('index');
    });

    Route::prefix('utility-trash')->name('utility-trash.')->group(function () {
      Route::post('/perform', [UtilityTrashController::class, 'perform'])
        ->name('perform');
      Route::patch('/verify', [UtilityTrashController::class, 'bulkVerify'])
        ->name('verify');
    });

    Route::prefix('hazardous-log-sheet')->name('hazardous-log-sheet.')->group(function () {
      Route::post('/add', [HazardousWasteTurnOverLogSheetController::class, 'store'])
        ->name('add');
      Route::delete('/{reference_no}/delete', [HazardousWasteTurnOverLogSheetController::class, 'destroy'])
        ->name('delete');
      Route::patch('/{reference_no}/update', [HazardousWasteTurnOverLogSheetController::class, 'update'])
        ->name('update');
    });

    Route::prefix('chemicals')->name('chemicals.')->group(function () {
      Route::post('/add', [ChemicalsController::class, 'store'])
        ->name('add');
      Route::delete('/{id}/delete', [ChemicalsController::class, 'destroy'])
        ->name('delete');
      Route::patch('/{id}/update', [ChemicalsController::class, 'update'])
        ->name('update');
    });

    Route::prefix('chemicals-sds')->name('chemicals-sds.')->group(function () {
      Route::post('/add', [ChemicalSDSController::class, 'store'])
        ->name('add');
      Route::delete('/{id}/delete', [ChemicalSDSController::class, 'destroy'])
        ->name('delete');
      Route::patch('/{id}/update', [ChemicalSDSController::class, 'update'])
        ->name('update');
    });

    Route::prefix('checklist-items')->name('checklist-items.')->group(function () {
      Route::get('/', [ChecklistItemsController::class, 'index'])
        ->name('index');
      Route::post('/add', [ChecklistItemsController::class, 'store'])
        ->name('add');
      Route::delete('/{id}/delete', [ChecklistItemsController::class, 'destroy'])
        ->name('delete');
      Route::patch('/{id}/update', [ChecklistItemsController::class, 'update'])
        ->name('update');
    });

    Route::prefix('check-items')->name('check-items.')->group(function () {
      Route::get('/', [CheckItemsController::class, 'index'])
        ->name('index');
      Route::post('/add', [CheckItemsController::class, 'store'])
        ->name('add');
      Route::delete('/{id}/delete', [CheckItemsController::class, 'destroy'])
        ->name('delete');
      Route::patch('/{id}/update', [CheckItemsController::class, 'update'])
        ->name('update');
    });

    Route::prefix('assets')->name('assets.')->group(function () {
      Route::get('/', [AssetsController::class, 'index'])
        ->name('index');
      Route::post('/add', [AssetsController::class, 'store'])
        ->name('add');
      Route::delete('/{id}/delete', [AssetsController::class, 'destroy'])
        ->name('delete');
      Route::patch('/{id}/update', [AssetsController::class, 'update'])
        ->name('update');
    });
  });
