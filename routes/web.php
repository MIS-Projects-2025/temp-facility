<?php

use App\Http\Controllers\ChemicalsController;
use App\Http\Controllers\ChemicalSDSController;
use App\Http\Controllers\DemoController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\General\AdminController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UtilityTrashController;
use App\Http\Controllers\General\ProfileController;
use App\Http\Controllers\HazardousWasteTurnOverLogSheetController;
use Inertia\Inertia;

$app_name = env('APP_NAME', '');

// Authentication routes
require __DIR__ . '/auth.php';

Route::get("/demo", [DemoController::class, 'index'])->name('demo');

Route::get("/admin", [AdminController::class, 'index'])->name('admin');
Route::get("/new-admin", [AdminController::class, 'index_addAdmin'])->name('index_addAdmin');
Route::post("/add-admin", [AdminController::class, 'addAdmin'])->name('addAdmin');
Route::post("/remove-admin", [AdminController::class, 'removeAdmin'])->name('removeAdmin');
Route::patch("/change-admin-role", [AdminController::class, 'changeAdminRole'])->name('changeAdminRole');


Route::middleware(AuthMiddleware::class . ':dashboard')->group(function () {
    Route::get("/", [DashboardController::class, 'index'])->name('dashboard');
});

Route::middleware(AuthMiddleware::class . ':utility-trash')->group(function () {
    Route::get("/utility-trash-list", [UtilityTrashController::class, 'index'])->name('utility-trash');
});

Route::prefix('hazardous')->name('hazardous-log-sheet.')->group(
    function () {
        Route::middleware([])->group(function () {
            Route::get("/", [HazardousWasteTurnOverLogSheetController::class, 'index'])->name('index');
        });
        Route::middleware([])->group(function () {
            Route::get("/create", [HazardousWasteTurnOverLogSheetController::class, 'upsert'])->name('create');
        });
        Route::middleware([])->group(function () {
            Route::get("/{reference_no}/edit", [HazardousWasteTurnOverLogSheetController::class, 'upsert'])->name('edit');
        });
    }
);

Route::prefix('chemicals')->name('chemicals.')->group(
    function () {
        Route::middleware([])->group(function () {
            Route::get("/", [ChemicalsController::class, 'index'])->name('index');
        });
        Route::middleware([])->group(function () {
            Route::get("/create", [ChemicalsController::class, 'upsert'])->name('create');
        });
        Route::middleware([])->group(function () {
            Route::get("/{id}/edit", [ChemicalsController::class, 'upsert'])->name('edit');
        });
    }
);

Route::prefix('chemicals-sds')->name('chemicals-sds.')->group(
    function () {
        Route::middleware([])->group(function () {
            Route::get("/", [ChemicalSDSController::class, 'index'])->name('index');
        });
        Route::middleware([])->group(function () {
            Route::get("/create", [ChemicalSDSController::class, 'upsert'])->name('create');
        });
        Route::middleware([])->group(function () {
            Route::get("/{id}/edit", [ChemicalSDSController::class, 'upsert'])->name('edit');
        });
    }
);


Route::get("/profile", [ProfileController::class, 'index'])->name('profile.index');
Route::post("/change-password", [ProfileController::class, 'changePassword'])->name('changePassword');

Route::fallback(function () {
    return Inertia::render('404');
})->name('404');
