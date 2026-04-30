<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_mappings', function (Blueprint $table) {
            $table->boolean('is_active')->default(false)->after('redcap_token');
            $table->index('is_active');
            $table->string('academic_year', 9)->nullable()->change();
            $table->unsignedSmallInteger('graduation_year')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('project_mappings', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropColumn('is_active');
            $table->string('academic_year', 9)->nullable(false)->change();
            $table->unsignedSmallInteger('graduation_year')->nullable(false)->change();
        });
    }
};
