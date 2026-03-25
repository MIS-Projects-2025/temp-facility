<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_approvers', function (Blueprint $table) {
            $table->dropForeign(['instance_id']);
            $table->dropUnique('uq_instance_user');
            $table->dropIndex('idx_instance_level');

            $table->string('instance_type', 100)->after('instance_id')->default('checklist');

            $table->unique(['instance_id', 'instance_type', 'user_id'], 'uq_instance_user');
            $table->index(['instance_id', 'instance_type', 'level'], 'idx_instance_level');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_approvers', function (Blueprint $table) {
            $table->dropUnique('uq_instance_user');
            $table->dropIndex('idx_instance_level');
            $table->dropColumn('instance_type');

            $table->unique(['instance_id', 'user_id'], 'uq_instance_user');
            $table->index(['instance_id', 'level'], 'idx_instance_level');

            $table->foreign('instance_id')
                ->references('id')
                ->on('checklist_instances')
                ->cascadeOnDelete();
        });
    }
};
