<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('review_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('course_name');
            $table->string('instructor_name')->nullable();
            $table->date('course_date')->nullable();
            $table->text('description')->nullable();
            $table->string('token')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_campaigns');
    }
};
