<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mod_tag', function (Blueprint $table) {
            $table->foreignId('mod_id')->constrained('mods')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->primary(['mod_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mod_tag');
    }
};

