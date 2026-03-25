<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('checklist_approval_config', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 50);
            $table->unsignedTinyInteger('level');
            $table->timestamps();

            $table->unique(['user_id'], 'uq_config_user');
        });

        // Run AFTER the table is created
        DB::statement('ALTER TABLE checklist_approval_config ADD CONSTRAINT chk_config_level CHECK (level BETWEEN 1 AND 3)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklist_approval_config');
    }
};
