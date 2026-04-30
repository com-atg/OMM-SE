<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_mappings', function (Blueprint $table) {
            $table->dropIndex(['deleted_at', 'graduation_year']);
            $table->dropIndex(['graduation_year']);
            $table->dropColumn('graduation_year');
        });
    }

    public function down(): void
    {
        Schema::table('project_mappings', function (Blueprint $table) {
            $table->unsignedSmallInteger('graduation_year')->nullable()->after('academic_year');
            $table->index('graduation_year');
            $table->index(['deleted_at', 'graduation_year']);
        });
    }
};
