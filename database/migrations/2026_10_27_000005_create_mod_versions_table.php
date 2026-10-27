<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mod_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mod_id')->constrained('mods')->cascadeOnDelete();
            $table->string('version', 50)->nullable();
            $table->string('author', 180)->nullable();
            $table->enum('status', ['validated', 'warning', 'invalid'])->index();
            $table->enum('manifest_type', ['fs_moddesc', 'fabric_json', 'forge_mcmod'])->nullable();
            $table->string('disk', 30)->default('mods');
            $table->string('file_path');
            $table->string('original_filename');
            $table->unsignedBigInteger('file_size');
            $table->char('sha256', 64);
            $table->json('metadata')->nullable();
            $table->text('changelog')->nullable();
            $table->unsignedInteger('downloads_count')->default(0);
            $table->timestamps();

            $table->unique(['mod_id', 'version']);
            $table->unique(['mod_id', 'sha256']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mod_versions');
    }
};

