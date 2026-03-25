<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_approvers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('instance_id');
            $table->string('user_id', 50);
            $table->unsignedTinyInteger('level');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('decided_at')->nullable();
            $table->string('remarks', 500)->nullable();

            $table->unique(['instance_id', 'user_id'], 'uq_instance_user');
            $table->index(['instance_id', 'level'], 'idx_instance_level');

            $table->foreign('instance_id')
                ->references('id')
                ->on('checklist_instances')
                ->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE checklist_approvers ADD CONSTRAINT chk_level CHECK (level BETWEEN 1 AND 3)');
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_approvers');
    }
};
