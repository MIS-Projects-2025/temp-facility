<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restroom_monitoring', function (Blueprint $table) {
            $table->unsignedBigInteger('fixture_id')->nullable()->after('restroom_id');
            $table->index('fixture_id');
        });
    }

    public function down(): void
    {
        Schema::table('restroom_monitoring', function (Blueprint $table) {
            $table->dropIndex(['fixture_id']);
            $table->dropColumn('fixture_id');
        });
    }
};
