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
        Schema::create('project_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year', 9)->index();
            $table->unsignedSmallInteger('graduation_year')->index();
            $table->unsignedInteger('redcap_pid')->index();
            $table->text('redcap_token');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['deleted_at', 'graduation_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_mappings');
    }
};
