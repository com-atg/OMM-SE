<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('batch', 32)->nullable()->after('cohort_start_year');
            $table->boolean('is_active')->default(true)->after('batch');

            $table->index('batch');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['batch']);
            $table->dropColumn(['is_active', 'batch']);
        });
    }
};
