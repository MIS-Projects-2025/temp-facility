<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'assets',
            'check_items',
            'checklist_assets',
            'checklist_item_results',
            'checklist_items',
            'checklists',
            'chemical_sds_monitoring',
            'chemicals',
            'hazardous_waste_material_turn_over_logsheet',
            'utility_trash_collection',
        ];

        foreach ($tables as $table) {
            DB::statement("
                ALTER TABLE `{$table}`
                MODIFY COLUMN `modified_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ");
        }
    }

    public function down(): void
    {
        $tables = [
            'assets',
            'check_items',
            'checklist_assets',
            'checklist_item_results',
            'checklist_items',
            'checklists',
            'chemicals',
            'hazardous_waste_material_turn_over_logsheet',
            'utility_trash_collection',
        ];

        foreach ($tables as $table) {
            DB::statement("
                ALTER TABLE `{$table}`
                MODIFY COLUMN `modified_at` TIMESTAMP NULL DEFAULT NULL
            ");
        }
    }
};
