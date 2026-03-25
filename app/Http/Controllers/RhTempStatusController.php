<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\RunningHoursService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Repositories\CheckItemsResultRepository;
use App\Models\Checklist;
use App\Constants\RunningHours;

class RhTempStatusController extends Controller
{
    public function index()
    {
        $vacuumChecklistId = Checklist::where('slug', 'vacuum_pump')->value('id');
        $airCompressorChecklistId = Checklist::where('slug', 'revised_air_compressor_unit')->value('id');
        $gensetTestRunChecklistId = Checklist::where('slug', 'generator_test_run_monitoring_checklist')->value('id');

        $gensetRunningHoursSlug = 'running_hours_(w/_load)';

        $checkItemsResultsRepo = new CheckItemsResultRepository();

        $vaccumRunningHoursName = Checklist::find($vacuumChecklistId)
            ->items()
            ->where('slug', 'running_hours')
            ->value('name');

        $airCompressorRunningHoursName = Checklist::find($airCompressorChecklistId)
            ->items()
            ->where('slug', 'running_hours')
            ->value('name');

        $gensetRunningHoursName = Checklist::find($gensetTestRunChecklistId)
            ->items()
            ->where('slug', $gensetRunningHoursSlug)
            ->value('name');

        $vacuumLatestResults = $checkItemsResultsRepo->getLastAndFirstSinceLastPmByChecklist($vacuumChecklistId);
        $vacuumLatestResults = RunningHoursService::enrichWithRunningHours($vacuumLatestResults);

        $airCompressorLatestResults = $checkItemsResultsRepo->getLastAndFirstSinceLastPmByChecklist($airCompressorChecklistId);
        $airCompressorLatestResults = RunningHoursService::enrichWithRunningHours($airCompressorLatestResults);

        $gensetLatestResults = $checkItemsResultsRepo->getLastAndFirstSinceLastPmByChecklist($gensetTestRunChecklistId);
        $gensetLatestResults = RunningHoursService::enrichWithRunningHours($gensetLatestResults, $gensetRunningHoursSlug);

        return Inertia::render('RhTempDashboard', [
            'vacuum_latest_results' => $vacuumLatestResults,
            'air_compressor_latest_result' => $airCompressorLatestResults,
            'genset_latest_result' => $gensetLatestResults,
            'genset_running_hours_name' => $gensetRunningHoursName,
            'vaccum_running_hours_name' => $vaccumRunningHoursName,
            'air_compressor_running_hours_name' => $airCompressorRunningHoursName,
            'vacuum_running_hours_ok' => RunningHours::VACUUM_RUNNING_HOURS_OK,
            'vacuum_running_hours_warning' => RunningHours::VACUUM_RUNNING_HOURS_WARNING,
            'vacuum_running_hours_danger' => RunningHours::VACUUM_RUNNING_HOURS_DANGER,
            'air_compressor_running_hours_ok' => RunningHours::AIR_COMPRESSOR_RUNNING_HOURS_OK,
            'air_compressor_running_hours_warning' => RunningHours::AIR_COMPRESSOR_RUNNING_HOURS_WARNING,
            'air_compressor_running_hours_danger' => RunningHours::AIR_COMPRESSOR_RUNNING_HOURS_DANGER,
            'devices' => Device::with('thresholdProfile')->orderBy('location')->get(),
        ]);
    }

    /**
     * Evaluate a reading and save to DB if out of range.
     */
    public function log(Request $request)
    {
        $request->validate([
            'device_id'    => 'required|exists:devices,id',
            'temp'         => 'required|string',
            'rh'           => 'required|string',
            'is_recording' => 'required|string',
        ]);

        $device = Device::with('thresholdProfile')->find($request->device_id);
        $outOfRange = $device->isTempBreached($request->temp) || $device->isRhBreached($request->rh);

        if ($outOfRange) {
            $device->statuses()->create([
                'temp'         => $request->temp,
                'rh'           => $request->rh,
                'is_recording' => $request->is_recording === 'ON',
            ]);
        }

        return response()->json([
            'out_of_range' => $outOfRange,
            'saved'        => $outOfRange,
        ]);
    }
}
