
<?php

namespace App\Http\Controllers;

use App\Constants\RunningHours;
use App\Models\Checklist;
use App\Models\ChecklistInstance;
use App\Repositories\CheckItemsResultRepository;
use App\Services\AssetsService;
use App\Services\ChecklistsService;
use App\Services\RunningHoursService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function getOverallChecklistState()
    {
        $sub = (new AssetsService())->getDueAssetsQuery();

        $assetsCollapsed = DB::table(DB::raw("({$sub->toSql()}) as sub"))
            ->mergeBindings($sub->getQuery())
            ->select([
                'sub.id',
                'sub.code',
                'sub.location_name',
                'sub.checklist_id',
                DB::raw('SUM(sub.due_items) as due_items'),
                DB::raw('SUM(sub.done_items) as done_items'),
                DB::raw('SUM(sub.overdue_items) as overdue_items'),
            ])
            ->groupBy(
                'sub.id',
                'sub.code',
                'sub.location_name',
                'sub.checklist_id'
            );

        $assetDetails = $assetsCollapsed->get();

        // Resolve checklist names in one query
        $checklistIds = $assetDetails->pluck('checklist_id')->unique();

        $checklistNames = DB::table('checklists')
            ->whereIn('id', $checklistIds)
            ->pluck('name', 'id');

        // Resolve schedule category per checklist
        $categoryByChecklist = DB::table('checklist_items')
            ->join(
                'entity_checklist_item_schedules as ecs',
                'ecs.checklist_item_id',
                '=',
                'checklist_items.id'
            )
            ->join(
                'schedules as s',
                's.id',
                '=',
                'ecs.schedule_id'
            )
            ->whereIn('checklist_items.checklist_id', $checklistIds)
            ->select(
                'checklist_items.checklist_id',
                's.category'
            )
            ->groupBy(
                'checklist_items.checklist_id',
                's.category'
            )
            ->pluck('category', 'checklist_id');

        // Attach schedule category to each asset
        $assetDetails = $assetDetails->map(function ($asset) use ($categoryByChecklist) {
            $asset->schedule_category =
                $categoryByChecklist[$asset->checklist_id] ?? 'other';

            return $asset;
        });

        // Group assets by schedule category and checklist
        $summary = $assetDetails
            ->groupBy('schedule_category')
            ->map(function ($assets, $scheduleName) use ($checklistNames) {

                $byChecklist = $assets
                    ->groupBy('checklist_id')
                    ->map(function ($checklistAssets, $checklistId) use ($checklistNames) {

                        return [
                            'checklist_id' => $checklistId,

                            'checklist_name' =>
                            $checklistNames[$checklistId] ?? 'Unknown',

                            'total_assets' => $checklistAssets->count(),

                            'assets_complete' => $checklistAssets
                                ->filter(
                                    fn($a) =>
                                    $a->due_items == 0 &&
                                        $a->done_items > 0
                                )
                                ->values(),

                            'assets_partial' => $checklistAssets
                                ->filter(
                                    fn($a) =>
                                    $a->due_items > 0 &&
                                        $a->done_items > 0
                                )
                                ->values(),

                            'assets_not_started' => $checklistAssets
                                ->filter(
                                    fn($a) =>
                                    $a->due_items > 0 &&
                                        $a->done_items == 0
                                )
                                ->values(),

                            'assets_idle' => $checklistAssets
                                ->filter(
                                    fn($a) =>
                                    $a->due_items == 0 &&
                                        $a->done_items == 0
                                )
                                ->values(),

                            'assets_overdue' => $checklistAssets
                                ->filter(
                                    fn($a) =>
                                    $a->overdue_items > 0
                                )
                                ->values(),
                        ];
                    })
                    ->values();

                return [
                    'schedule_category' => $scheduleName,
                    'total_assets' => $assets->count(),
                    'checklists' => $byChecklist,
                ];
            })
            ->values();

        return $summary;
    }

    public function index(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Running Hours
        |--------------------------------------------------------------------------
        | RunningHoursService::forChecklist() handles:
        | - Checklist lookup
        | - Running-hours item name lookup
        | - Latest and first results retrieval
        | - Running-hours computation
        */

        $vacuumRunningHours = RunningHoursService::forChecklist(
            'vacuum_pump'
        );

        $airCompressorRunningHours = RunningHoursService::forChecklist(
            'revised_air_compressor_unit'
        );

        $gensetRunningHours = RunningHoursService::forChecklist(
            'generator_test_run_monitoring_checklist',
            'running_hours_(w/_load)'
        );

        /*
        |--------------------------------------------------------------------------
        | Other Dashboard Data
        |--------------------------------------------------------------------------
        */

        $checkItemsResultsRepo = new CheckItemsResultRepository();

        $allLatestStatusResults = $checkItemsResultsRepo
            ->getAllStatusResults();

        $assetsOverview = self::getOverallChecklistState();

        $checklistsOverview = (new ChecklistsService())
            ->getAllChecklistsWithDueAssets();

        $pending = ChecklistInstance::query()->where(function ($q) {
            $q->where(fn($q) => $q->where('submission_type', 'late')->whereNull('approved_at'))
                ->orWhere(fn($q) => $q->where(fn($q) => $q->where('submission_type', '!=', 'late')->orWhereNull('submission_type'))
                    ->whereNull('verified_at'));
        });

        $unverifiedTotal = (clone $pending)->count();
        $unverifiedToday = (clone $pending)->whereDate('created_at', today())->count();

        /*
        |--------------------------------------------------------------------------
        | Dashboard Response
        |--------------------------------------------------------------------------
        */

        return Inertia::render('Dashboard', [

            // Vacuum Pump
            'vacuum_latest_results' =>
            $vacuumRunningHours['results'],

            'vaccum_running_hours_name' =>
            $vacuumRunningHours['name'],

            'vacuum_running_hours_ok' =>
            RunningHours::VACUUM_RUNNING_HOURS_OK,

            'vacuum_running_hours_warning' =>
            RunningHours::VACUUM_RUNNING_HOURS_WARNING,

            'vacuum_running_hours_danger' =>
            RunningHours::VACUUM_RUNNING_HOURS_DANGER,

            // Air Compressor
            'air_compressor_latest_result' =>
            $airCompressorRunningHours['results'],

            'air_compressor_running_hours_name' =>
            $airCompressorRunningHours['name'],

            'air_compressor_running_hours_ok' =>
            RunningHours::AIR_COMPRESSOR_RUNNING_HOURS_OK,

            'air_compressor_running_hours_warning' =>
            RunningHours::AIR_COMPRESSOR_RUNNING_HOURS_WARNING,

            'air_compressor_running_hours_danger' =>
            RunningHours::AIR_COMPRESSOR_RUNNING_HOURS_DANGER,

            // Genset
            'genset_latest_result' =>
            $gensetRunningHours['results'],

            'genset_running_hours_name' =>
            $gensetRunningHours['name'],

            // Checklist Overview
            'assets_due' =>
            $assetsOverview,

            'checklists_overview' =>
            $checklistsOverview,

            // Unverified Checklist Instances
            'unverified_today' =>
            $unverifiedToday,

            'unverified_total' =>
            $unverifiedTotal,

            // Latest Status Results
            'all_latest_status_results' =>
            $allLatestStatusResults,
        ]);
    }
}
