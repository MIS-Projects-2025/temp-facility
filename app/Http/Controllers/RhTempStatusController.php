<?php

namespace App\Http\Controllers;

use App\Constants\RunningHours;
use App\Models\Checklist;
use App\Models\Device;
use App\Repositories\CheckItemsResultRepository;
use App\Services\RunningHoursService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RhTempStatusController extends Controller
{
    private function runningHoursBlock(string $checklistSlug, string $itemSlug = 'running_hours'): array
    {
        $checklist = Checklist::where('slug', $checklistSlug)->first();

        if (!$checklist) {
            return ['name' => null, 'results' => collect()];
        }

        $raw = (new CheckItemsResultRepository())->getLastAndFirstSinceLastPmByChecklist($checklist->id);

        return [
            'name' => $checklist->items()->where('slug', $itemSlug)->value('name'),
            'results' => RunningHoursService::enrichWithRunningHours($raw, $itemSlug),
        ];
    }

    public function index()
    {
        $vacuum = $this->runningHoursBlock('vacuum_pump');
        $airCompressor = $this->runningHoursBlock('revised_air_compressor_unit');
        $genset = $this->runningHoursBlock('generator_test_run_monitoring_checklist', 'running_hours_(w/_load)');

        return Inertia::render('RhTempDashboard', [
            'vacuum_latest_results' => $vacuum['results'],
            'air_compressor_latest_result' => $airCompressor['results'],
            'genset_latest_result' => $genset['results'],
            'genset_running_hours_name' => $genset['name'],
            'vaccum_running_hours_name' => $vacuum['name'],
            'air_compressor_running_hours_name' => $airCompressor['name'],
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

        $device = Device::with('thresholdProfile')->findOrFail($request->device_id);
        $profile = $device->thresholdProfile;

        if (!$profile) {
            return response()->json(['out_of_range' => false, 'saved' => false]);
        }

        $tempBreached = is_numeric($request->temp) && $profile->isTempBreached((float) $request->temp);
        $rhBreached   = is_numeric($request->rh) && $profile->isRhBreached((float) $request->rh);
        $outOfRange   = $tempBreached || $rhBreached;

        if ($outOfRange) {
            $device->statuses()->create([
                'temp'         => $request->temp,
                'rh'           => $request->rh,
                'is_recording' => strtoupper($request->is_recording) === 'ON',
            ]);
        }

        return response()->json([
            'out_of_range' => $outOfRange,
            'saved'        => $outOfRange,
        ]);
    }
}
