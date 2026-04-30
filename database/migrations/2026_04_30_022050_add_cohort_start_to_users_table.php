<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('cohort_start_term', ['Spring', 'Fall'])->nullable()->after('redcap_record_id');
            $table->unsignedSmallInteger('cohort_start_year')->nullable()->after('cohort_start_term');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['cohort_start_term', 'cohort_start_year']);
        });
    }
};
