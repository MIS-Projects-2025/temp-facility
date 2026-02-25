<?php

namespace App\Http\Controllers;

use App\Constants\RunningHours;
use App\Repositories\CheckItemsResultRepository;
use App\Services\AssetsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Checklist;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $vacuumChecklistId = Checklist::where('slug', 'vacuum_pump')->value('id');
        $airCompressorChecklistId = Checklist::where('slug', 'revised_air_compressor_unit')->value('id');

        $CheckItemsResultsRepo = new CheckItemsResultRepository();

        $vacuumLatestRunningHours = $CheckItemsResultsRepo->latestRunningHoursByChecklist($vacuumChecklistId);
        $assetsDue = (new AssetsService())->countDues();
        $airCompressorLatestRunningHours = $CheckItemsResultsRepo->latestRunningHoursByChecklist($airCompressorChecklistId);

        return Inertia::render('Dashboard', [
            'vacuum_latest_running_hours' => $vacuumLatestRunningHours,
            'air_compressor_latest_running_hours' => $airCompressorLatestRunningHours,
            'vacuum_running_hours_ok' => RunningHours::VACUUM_RUNNING_HOURS_OK,
            'assets_due' => $assetsDue,
            'vacuum_running_hours_warning' => RunningHours::VACUUM_RUNNING_HOURS_WARNING,
            'vacuum_running_hours_danger' => RunningHours::VACUUM_RUNNING_HOURS_DANGER,
            'air_compressor_running_hours_ok' => RunningHours::AIR_COMPRESSOR_RUNNING_HOURS_OK,
            'air_compressor_running_hours_warning' => RunningHours::AIR_COMPRESSOR_RUNNING_HOURS_WARNING,
            'air_compressor_running_hours_danger' => RunningHours::AIR_COMPRESSOR_RUNNING_HOURS_DANGER
        ]);
    }
}
