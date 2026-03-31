<?php

use App\Http\Controllers\ChemicalsController;
use App\Http\Controllers\ChemicalSDSMonitoringInstanceController;
use App\Http\Controllers\RestroomMonitoringInstanceController;
use App\Http\Controllers\DemoController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\General\AdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RhTempStatusController;
use App\Http\Controllers\UtilityTrashController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\General\ProfileController;
use App\Http\Controllers\ChecklistsController;
use App\Http\Controllers\CheckItemsController;
use App\Http\Controllers\SchedulesController;
use App\Http\Controllers\ChecklistApprovalConfigController;
use App\Http\Controllers\AssetsController;
use App\Http\Controllers\GlobalPmController;
use App\Http\Controllers\GlobalPmSchedulesController;
use App\Http\Controllers\PmHistoryController;
use App\Http\Controllers\ChecklistAssetsController;
use App\Http\Controllers\PerformChecklistController;
use App\Http\Controllers\PerformSDSMonitoringController;
use App\Http\Controllers\PerformRestroomMonitoringController;
use App\Http\Controllers\ChecklistInstanceController;
use App\Http\Controllers\AssetPmSchedulesController;
use App\Http\Controllers\ChecklistItemsController;
use App\Http\Controllers\AssetHealthBoardController;
use App\Http\Controllers\HazardousWasteTurnOverLogSheetController;
use App\Http\Controllers\ChemicalSDSController;
use App\Http\Middleware\ApiAuthMiddleware;
use App\Http\Controllers\ChecklistItemSchedulesController;
use App\Http\Controllers\CheckItemsResultController;
use App\Http\Middleware\ApiPermissionMiddleware;
use Inertia\Inertia;

$app_name = env('APP_NAME', '');

// Authentication routes
require __DIR__ . '/auth.php';

Route::middleware([ApiAuthMiddleware::class])
    ->prefix('api')
    ->name('api.')
    ->group(function () {

        Route::prefix('locations')->name('locations.')->group(function () {
            Route::get('/', [LocationController::class, 'index'])
                ->name('index');
            Route::patch('/bulk-update', [LocationController::class, 'bulkUpdate'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('bulkUpdate');
            Route::post('/add', [LocationController::class, 'store'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('store');
            Route::delete('/bulk-delete', [LocationController::class, 'massGenocide'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('massGenocide');
        });

        Route::prefix('utility-trash')->name('utility-trash.')->group(function () {
            Route::post('/perform', [UtilityTrashController::class, 'perform'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('perform');
            Route::patch('/verify', [UtilityTrashController::class, 'bulkVerify'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('verify');
        });

        Route::prefix('hazardous-log-sheet')->name('hazardous-log-sheet.')->group(function () {
            Route::post('/add', [HazardousWasteTurnOverLogSheetController::class, 'store'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('add');
            Route::patch('/bulk-update', [HazardousWasteTurnOverLogSheetController::class, 'bulkUpdate'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('bulkUpdate');
            Route::delete('/{reference_no}/delete', [HazardousWasteTurnOverLogSheetController::class, 'destroy'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('delete');
            Route::patch('/{reference_no}/update', [HazardousWasteTurnOverLogSheetController::class, 'update'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('update');
        });

        Route::prefix('checklist-approval-config')->name('checklist-approval-config.')->group(function () {
            Route::patch('/bulk-update', [ChecklistApprovalConfigController::class, 'bulkUpdate'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('bulkUpdate');
            Route::delete('/mass-genocide', [ChecklistApprovalConfigController::class, 'massGenocide'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('massGenocide');
        });

        Route::prefix('chemicals')->name('chemicals.')->group(function () {
            Route::post('/add', [ChemicalsController::class, 'store'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('add');
            Route::delete('/{id}/delete', [ChemicalsController::class, 'destroy'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('delete');
            Route::patch('/{id}/update', [ChemicalsController::class, 'update'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('update');
        });

        Route::prefix('chemicals-sds')->name('chemicals-sds.')->group(function () {
            Route::post('/add', [ChemicalSDSController::class, 'store'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('add');
            Route::delete('/{id}/delete', [ChemicalSDSController::class, 'destroy'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('delete');
            Route::patch('/{id}/update', [ChemicalSDSController::class, 'update'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('update');
        });

        Route::prefix('checklist-items')->name('checklist-items.')->group(function () {
            Route::get('/', [ChecklistItemsController::class, 'index'])
                ->name('index');
            Route::post('/add', [ChecklistItemsController::class, 'store'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('add');
            Route::delete('/{id}/delete', [ChecklistItemsController::class, 'destroy'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('delete');
            Route::patch('/{id}/update', [ChecklistItemsController::class, 'update'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('update');
            Route::get('/all-check-items', [ChecklistItemsController::class, 'getAllCheckItems'])
                ->name('all-check-items');
            Route::patch('/bulk-update', [ChecklistItemsController::class, 'bulkUpdate'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('bulkUpdate');
            Route::delete('/bulk-delete', [ChecklistItemsController::class, 'massGenocide'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('massGenocide');
            Route::get('/scheduled-check-items', [ChecklistItemsController::class, 'getScheduledCheckItems'])
                ->name('scheduled-check-items');
            Route::get('/overdue', [ChecklistItemsController::class, 'getOverdueCheckItems'])
                ->name('overdue');
        });

        Route::prefix('check-items')->name('check-items.')->group(function () {
            Route::get('/', [CheckItemsController::class, 'index'])
                ->name('index');
            Route::post('/add', [CheckItemsController::class, 'store'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('add');
            Route::delete('/{id}/delete', [CheckItemsController::class, 'destroy'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('delete');
            Route::patch('/{id}/update', [CheckItemsController::class, 'update'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('update');
            Route::patch('/bulk-update', [CheckItemsController::class, 'bulkUpdate'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('bulkUpdate');
        });

        Route::prefix('assets')->name('assets.')->group(function () {
            Route::get('/', [AssetsController::class, 'index'])
                ->name('index');
            Route::post('/add', [AssetsController::class, 'store'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('add');
            Route::delete('/{id}/delete', [AssetsController::class, 'destroy'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('delete');
            Route::patch('/{id}/update', [AssetsController::class, 'update'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('update');
            Route::get('/all-assets', [AssetsController::class, 'getAllAssets'])
                ->name('all-assets');
            Route::patch('/bulk-update', [AssetsController::class, 'bulkUpdate'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('bulkUpdate');
            Route::delete('/bulk-delete', [AssetsController::class, 'massGenocide'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('massGenocide');
            Route::prefix('assets/{assetId}')->group(function () {
                Route::post('record-done-date', [PmHistoryController::class, 'recordAssetPmDoneDate'])
                    ->middleware(ApiPermissionMiddleware::class)
                    ->name('recordDoneDate');
            });
        });

        Route::prefix('checklist-item-result')->name('checklist-item-result.')->group(function () {
            Route::post('/record', [CheckItemsResultController::class, 'recordResult'])
                ->name('recordResult');
        });

        Route::prefix('chemical-sds-result')->name('chemical-sds-result.')->group(function () {
            Route::post('/record', [PerformSDSMonitoringController::class, 'recordResult'])
                ->name('recordResult');
        });

        Route::prefix('restroom-monitoring-result')->name('restroom-monitoring-result.')->group(function () {
            Route::post('/record', [PerformRestroomMonitoringController::class, 'recordResult'])
                ->name('recordResult');
        });

        Route::prefix('checklist-assets')->name('checklist-assets.')->group(function () {
            Route::get('/', [ChecklistAssetsController::class, 'index'])
                ->name('index');
            Route::get('/due', [AssetsController::class, 'getDueAssets'])
                ->name('due-assets');
            Route::get('/due-with-items', [AssetsController::class, 'getDueAssetsWithItems'])
                ->name('due-assets-with-items');
            Route::post('/add', [ChecklistAssetsController::class, 'store'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('add');
            Route::delete('/{id}/delete', [ChecklistAssetsController::class, 'destroy'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('delete');
            Route::patch('/{id}/update', [ChecklistAssetsController::class, 'update'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('update');
            Route::patch('/bulk-update', [ChecklistAssetsController::class, 'bulkUpdate'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('bulkUpdate');
            Route::delete('/bulk-delete', [ChecklistAssetsController::class, 'massGenocide'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('massGenocide');
        });

        Route::prefix('checklist-instance')->name('checklist-instance.')->group(function () {
            Route::patch('/verify', [ChecklistInstanceController::class, 'verify'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('verify');
            Route::patch('/{id}/approve', [ChecklistInstanceController::class, 'approve'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('approve');
        });

        Route::prefix('restroom-monitoring-instance')->name('restroom-monitoring-instance.')->group(function () {
            Route::patch('/verify', [RestroomMonitoringInstanceController::class, 'verify'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('verify');
        });

        Route::prefix('schedules')->name('schedules.')->group(function () {
            Route::get('/', [SchedulesController::class, 'index'])
                ->name('index');
            Route::post('/add', [SchedulesController::class, 'store'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('add');
            Route::delete('/{id}/delete', [SchedulesController::class, 'destroy'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('delete');
            Route::patch('/{id}/update', [SchedulesController::class, 'update'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('update');
        });

        Route::prefix('asset-pm-schedules')->name('asset-pm-schedules.')->group(function () {
            Route::get('/', [AssetPmSchedulesController::class, 'index'])
                ->name('index');
            Route::post('/add', [AssetPmSchedulesController::class, 'store'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('add');
            Route::delete('/{id}/delete', [AssetPmSchedulesController::class, 'destroy'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('delete');
            Route::patch('/{id}/update', [AssetPmSchedulesController::class, 'update'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('update');
            Route::patch('/bulk-update', [AssetPmSchedulesController::class, 'bulkUpdate'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('bulkUpdate');
            Route::delete('/bulk-delete', [AssetPmSchedulesController::class, 'massGenocide'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('massGenocide');
        });

        Route::prefix('checklist_item_schedules')->name('checklist_item_schedules.')->group(function () {
            Route::get('/', [ChecklistItemSchedulesController::class, 'index'])
                ->name('index');
            Route::post('/add', [ChecklistItemSchedulesController::class, 'store'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('add');
            Route::delete('/{id}/delete', [ChecklistItemSchedulesController::class, 'destroy'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('delete');
            Route::patch('/{id}/update', [ChecklistItemSchedulesController::class, 'update'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('update');
        });

        Route::prefix('global-pm')->name('global-pm.')->group(function () {
            Route::patch('/bulk-update', [GlobalPmController::class, 'bulkUpdate'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('bulkUpdate');
            Route::get('/', [GlobalPmController::class, 'index'])
                ->name('index');

            Route::prefix('schedules')->name('schedules.')->group(function () {
                Route::get('/', [GlobalPmSchedulesController::class, 'index'])
                    ->name('index');
                Route::post('/add', [GlobalPmSchedulesController::class, 'store'])
                    ->middleware(ApiPermissionMiddleware::class)
                    ->name('add');
                Route::delete('/{id}/delete', [GlobalPmSchedulesController::class, 'destroy'])
                    ->middleware(ApiPermissionMiddleware::class)
                    ->name('delete');
                Route::patch('/{id}/update', [GlobalPmSchedulesController::class, 'update'])
                    ->middleware(ApiPermissionMiddleware::class)
                    ->name('update');
                Route::patch('/bulk-update', [GlobalPmSchedulesController::class, 'bulkUpdate'])
                    ->middleware(ApiPermissionMiddleware::class)
                    ->name('bulkUpdate');
                Route::delete('/bulk-delete', [GlobalPmSchedulesController::class, 'massGenocide'])
                    ->middleware(ApiPermissionMiddleware::class)
                    ->name('massGenocide');
                Route::prefix('globalPmId/{globalPmId}')->group(function () {
                    Route::post('record-done-date', [PmHistoryController::class, 'recordGlobalPmDoneDate'])
                        ->middleware(ApiPermissionMiddleware::class)
                        ->name('recordDoneDate');
                });
            });
        });

        Route::prefix('checklists')->name('checklists.')->group(function () {
            Route::get('/', [ChecklistsController::class, 'getAllChecklistsWithDueAssets'])
                ->name('index');
            Route::post('/add', [ChecklistsController::class, 'store'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('add');
            Route::delete('/{id}/delete', [ChecklistsController::class, 'destroy'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('delete');
            Route::patch('/{id}/update', [ChecklistsController::class, 'update'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('update');
            Route::patch('/bulk-update', [ChecklistsController::class, 'bulkUpdate'])
                ->middleware(ApiPermissionMiddleware::class)
                ->name('bulkUpdate');
        });
    });

Route::get("/demo", [DemoController::class, 'index'])->name('demo');

Route::get("/admin", [AdminController::class, 'index'])->name('admin');
Route::get("/new-admin", [AdminController::class, 'index_addAdmin'])->name('index_addAdmin');
Route::post("/add-admin", [AdminController::class, 'addAdmin'])->name('addAdmin');
Route::post("/remove-admin", [AdminController::class, 'removeAdmin'])->name('removeAdmin');
Route::patch("/change-admin-role", [AdminController::class, 'changeAdminRole'])->name('changeAdminRole');

Route::get("/", [DashboardController::class, 'index'])->name('dashboard');
Route::get("/rh-temp", [RhTempStatusController::class, 'index'])->name('rh-temp-dashboard');
Route::get("/asset-health", [AssetHealthBoardController::class, 'index'])->name('asset-health');
Route::get("/utility-trash-list", [UtilityTrashController::class, 'index'])->name('utility-trash');

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

Route::prefix('locations')->name('locations.')->group(
    function () {
        Route::middleware([])->group(function () {
            Route::get("/", [LocationController::class, 'index'])->name('index');
        });
        Route::middleware([])->group(function () {
            Route::get("/create", [LocationController::class, 'upsert'])->name('create');
        });
        Route::middleware([])->group(function () {
            Route::get("/{id}/edit", [LocationController::class, 'upsert'])->name('edit');
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

Route::prefix('chemicals-sds-instances')->name('chemicals-sds-instances.')->group(
    function () {
        Route::get("/", [ChemicalSDSMonitoringInstanceController::class, 'index'])->name('index');
    }
);

Route::prefix('restroom-monitoring-instances')->name('restroom-monitoring-instances.')->group(
    function () {
        Route::get("/", [RestroomMonitoringInstanceController::class, 'index'])->name('index');
    }
);

Route::prefix('checklist')->name('checklist.')->group(
    function () {
        Route::middleware([])->group(function () {
            Route::get("/list", [ChecklistsController::class, 'index'])->name('index');
        });
    }
);

Route::prefix('checklist-approval-config')->name('checklist-approval-config.')->group(
    function () {
        Route::middleware([])->group(function () {
            Route::get("/list", [ChecklistApprovalConfigController::class, 'index'])->name('index');
        });
    }
);

Route::prefix('checklist-items')->name('checklist-items.')->group(
    function () {
        Route::middleware([])->group(function () {
            Route::get("/", [ChecklistItemsController::class, 'index'])->name('index');
        });
        Route::middleware([])->group(function () {
            Route::get("/create", [ChecklistsController::class, 'upsert'])->name('create');
        });
        Route::middleware([])->group(function () {
            Route::get("/{id}/edit", [ChecklistsController::class, 'upsert'])->name('edit');
        });
    }
);

Route::prefix('check-items')->name('check-items.')->group(
    function () {
        Route::middleware([])->group(function () {
            Route::get("/", [CheckItemsController::class, 'index'])->name('index');
        });
        Route::middleware([])->group(function () {
            Route::get("/create", [CheckItemsController::class, 'upsert'])->name('create');
        });
        Route::middleware([])->group(function () {
            Route::get("/{id}/edit", [CheckItemsController::class, 'upsert'])->name('edit');
        });
    }
);

Route::prefix('schedules')->name('schedules.')->group(
    function () {
        Route::middleware([])->group(function () {
            Route::get("/", [SchedulesController::class, 'index'])->name('index');
        });
        Route::middleware([])->group(function () {
            Route::get("/create", [SchedulesController::class, 'upsert'])->name('create');
        });
        Route::middleware([])->group(function () {
            Route::get("/{id}/edit", [SchedulesController::class, 'upsert'])->name('edit');
        });
    }
);

Route::prefix('pm')->name('pm.')->group(
    function () {
        Route::prefix('history')->name('history.')->group(function () {
            Route::get('/', [PmHistoryController::class, 'index'])->name('index');
        });
        Route::prefix('schedule')->name('schedule.')->group(function () {
            Route::get('/', [PmHistoryController::class, 'getAllSchedule'])->name('getAllSchedule');
        });
    }
);

Route::prefix('assets')->name('assets.')->group(
    function () {
        Route::middleware([])->group(function () {
            Route::get("/", [AssetsController::class, 'index'])->name('index');
        });
        Route::middleware([])->group(function () {
            Route::get("/create", [AssetsController::class, 'upsert'])->name('create');
        });
        Route::middleware([])->group(function () {
            Route::get("/{id}/edit", [AssetsController::class, 'upsert'])->name('edit');
        });
    }
);

Route::prefix('global-pm')->name('global-pm.')->group(
    function () {
        Route::middleware([])->group(function () {
            Route::get("/", [GlobalPmController::class, 'index'])->name('index');
        });

        Route::prefix('schedules')->name('schedules.')->group(function () {
            Route::get('/', [GlobalPmSchedulesController::class, 'index'])
                ->name('index');
        });
    }

);

Route::prefix('checklist-assets')->name('checklist-assets.')->group(
    function () {
        Route::middleware([])->group(function () {
            Route::get("/due", [AssetsController::class, 'getDueAssets'])->name('due-assets');
        });
        Route::middleware([])->group(function () {
            Route::get("/", [ChecklistAssetsController::class, 'index'])->name('index');
        });
        Route::middleware([])->group(function () {
            Route::get("/create", [ChecklistAssetsController::class, 'upsert'])->name('create');
        });
        Route::middleware([])->group(function () {
            Route::get("/{id}/edit", [ChecklistAssetsController::class, 'upsert'])->name('edit');
        });
    }
);

Route::prefix('perform')->name('perform.')->group(function () {
    Route::prefix('checklist')->name('checklist.')->group(function () {
        Route::get('/', [PerformChecklistController::class, 'index'])->name('index');
    });
    Route::prefix('sds-monitoring')->name('sds-monitoring.')->group(function () {
        Route::get('/', [PerformSDSMonitoringController::class, 'index'])->name('index');
    });
    Route::prefix('restroom-monitoring')->name('restroom-monitoring.')->group(function () {
        Route::get('/', [PerformRestroomMonitoringController::class, 'index'])->name('index');
    });
});

Route::prefix('checklist-instance')->name('checklist-instance.')->group(
    function () {
        Route::middleware([])->group(function () {
            Route::get("/", [ChecklistInstanceController::class, 'index'])->name('index');
        });
        // Route::middleware([])->group(function () {
        //     Route::get("/create", [PerformChecklistController::class, 'upsert'])->name('create');
        // });
        // Route::middleware([])->group(function () {
        //     Route::get("/{id}/edit", [PerformChecklistController::class, 'upsert'])->name('edit');
        // });
    }
);

Route::prefix('asset-pm-schedule')->name('asset-pm-schedule.')->group(
    function () {
        Route::middleware([])->group(function () {
            Route::get("/", [AssetPmSchedulesController::class, 'index'])->name('index');
        });
        Route::middleware([])->group(function () {
            Route::get("/create", [AssetPmSchedulesController::class, 'upsert'])->name('create');
        });
        Route::middleware([])->group(function () {
            Route::get("/{id}/edit", [AssetPmSchedulesController::class, 'upsert'])->name('edit');
        });
    }
);


Route::get("/profile", [ProfileController::class, 'index'])->name('profile.index');
Route::post("/change-password", [ProfileController::class, 'changePassword'])->name('changePassword');

Route::fallback(function () {
    return Inertia::render('404');
})->name('404');
