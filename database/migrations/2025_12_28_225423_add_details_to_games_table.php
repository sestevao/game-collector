<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->integer('metascore')->nullable();
            $table->date('released_at')->nullable();
            $table->string('genres')->nullable(); // JSON or comma-separated string
            $table->string('chart_ranking')->nullable(); // e.g., "#1 Top 2022"
            $table->enum('status', ['uncategorized', 'currently_playing', 'completed', 'played', 'not_played'])->default('uncategorized');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['metascore', 'released_at', 'genres', 'chart_ranking', 'status']);
        });
    }
};
