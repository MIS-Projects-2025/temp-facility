<?php

namespace App\Http\Controllers;

use App\Constants\RunningHours;
use App\Models\Checklist;
use App\Models\ChecklistInstance;
use App\Repositories\CheckItemsResultRepository;
use App\Services\ChecklistsService;
use App\Services\RunningHoursService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
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

    public function getOverallChecklistState($assetDetails = null)
    {
        $assetDetails ??= (new ChecklistsService())->getDueAssetRows();

        // resolve category per checklist_id in one query, no N+1
        $checklistIds = $assetDetails->pluck('checklist_id')->unique();

        $checklistNames = DB::table('checklists')
            ->whereIn('id', $checklistIds)
            ->pluck('name', 'id');

        $categoryByChecklist = DB::table('checklist_items')
            ->join('entity_checklist_item_schedules as ecs', 'ecs.checklist_item_id', '=', 'checklist_items.id')
            ->join('schedules as s', 's.id', '=', 'ecs.schedule_id')
            ->whereIn('checklist_items.checklist_id', $checklistIds)
            ->select('checklist_items.checklist_id', 's.category')
            ->groupBy('checklist_items.checklist_id', 's.category')
            ->pluck('category', 'checklist_id'); // [checklist_id => category]

        // attach category to each asset
        $assetDetails = $assetDetails->map(function ($asset) use ($categoryByChecklist) {
            $asset->schedule_category = $categoryByChecklist[$asset->checklist_id] ?? 'other';
            return $asset;
        });

        return $assetDetails
            ->groupBy('schedule_category')
            ->map(function ($assets, $scheduleName) use ($checklistNames) {
                $byChecklist = $assets->groupBy('checklist_id')->map(function ($checklistAssets, $checklistId) use ($checklistNames) {
                    return [
                        'checklist_id'       => $checklistId,
                        'checklist_name'     => $checklistNames[$checklistId] ?? 'Unknown',
                        'total_assets'       => $checklistAssets->count(),
                        'assets_complete'    => $checklistAssets->filter(fn($a) => $a->due_items == 0 && $a->done_items > 0)->values(),
                        'assets_partial'     => $checklistAssets->filter(fn($a) => $a->due_items > 0 && $a->done_items > 0)->values(),
                        'assets_not_started' => $checklistAssets->filter(fn($a) => $a->due_items > 0 && $a->done_items == 0)->values(),
                        'assets_idle'        => $checklistAssets->filter(fn($a) => $a->due_items == 0 && $a->done_items == 0)->values(),
                        'assets_overdue'     => $checklistAssets->filter(fn($a) => $a->overdue_items > 0)->values(),
                    ];
                })->values();

                return [
                    'schedule_category' => $scheduleName,
                    'total_assets'      => $assets->count(),
                    'checklists'        => $byChecklist,
                ];
            })
            ->values();
    }

    public function index(Request $request)
    {
        $vacuum        = $this->runningHoursBlock('vacuum_pump');
        $airCompressor = $this->runningHoursBlock('revised_air_compressor_unit');
        $genset        = $this->runningHoursBlock('generator_test_run_monitoring_checklist', 'running_hours_(w/_load)');

        $checkItemsResultsRepo = new CheckItemsResultRepository();
        $checklistService      = new ChecklistsService();

        // built once, shared by both overviews
        $dueAssetRows       = $checklistService->getDueAssetRows();
        $assetsOverview     = $this->getOverallChecklistState($dueAssetRows);
        $checklistsOverview = $checklistService->getAllChecklistsWithDueAssets($dueAssetRows);

        $allLatestStatusResults = $checkItemsResultsRepo->getAllStatusResults();

        // punctual: waiting for verification. late: waiting for final approval.
        $pending = fn() => ChecklistInstance::query()->where(function ($q) {
            $q->where(fn($q) => $q->where('submission_type', 'late')->whereNull('approved_at'))
                ->orWhere(
                    fn($q) => $q
                        ->where(fn($q) => $q->where('submission_type', '!=', 'late')->orWhereNull('submission_type'))
                        ->whereNull('verified_at')
                );
        });

        $unverifiedTotal = $pending()->count();
        $unverifiedToday = $pending()->whereDate('created_at', today())->count();

        return Inertia::render('Dashboard', [
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

            'assets_due' => $assetsOverview,
            'checklists_overview' => $checklistsOverview,
            'unverified_today' => $unverifiedToday,
            'unverified_total' => $unverifiedTotal,

            'all_latest_status_results' => $allLatestStatusResults,
        ]);
    }
}
