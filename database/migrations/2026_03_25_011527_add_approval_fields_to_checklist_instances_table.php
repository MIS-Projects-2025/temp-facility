<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_instances', function (Blueprint $table) {
            $table->string('approved_by', 50)->nullable()->after('verified_by');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_instances', function (Blueprint $table) {
            $table->dropColumn(['approved_by', 'approved_at']);
        });
    }
};
