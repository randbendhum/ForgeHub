<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_version_mod_version', function (Blueprint $table) {
            $table->foreignId('game_version_id')->constrained('game_versions')->cascadeOnDelete();
            $table->foreignId('mod_version_id')->constrained('mod_versions')->cascadeOnDelete();
            $table->primary(['game_version_id', 'mod_version_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_version_mod_version');
    }
};

