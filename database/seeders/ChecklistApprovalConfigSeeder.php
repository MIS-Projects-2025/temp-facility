<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChecklistApprovalConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $approvers = [
            // Level 1
            ['user_id' => '1161', 'level' => 1, 'instance_type' => null],
            ['user_id' => '1698', 'level' => 1, 'instance_type' => null],
            ['user_id' => '1708', 'level' => 1, 'instance_type' => null],
            // Level 2
            ['user_id' => '1693', 'level' => 2, 'instance_type' => null],
            // Level 3
            ['user_id' => '1694', 'level' => 3, 'instance_type' => null],
        ];

        DB::table('checklist_approval_config')->insert($approvers);
    }
}
