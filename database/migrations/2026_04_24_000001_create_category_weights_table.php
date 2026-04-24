<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_weights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_mapping_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20);
            $table->decimal('weight', 5, 2);
            $table->timestamps();

            $table->unique(['project_mapping_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_weights');
    }
};
