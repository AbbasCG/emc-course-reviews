<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_campaign_id')->constrained('review_campaigns')->onDelete('cascade');
            $table->string('student_name');
            $table->string('email')->nullable();
            $table->integer('overall_rating'); // 1-5
            $table->integer('course_content_rating'); // 1-5
            $table->integer('instructor_rating'); // 1-5
            $table->integer('organization_rating'); // 1-5
            $table->string('difficulty_level'); // سهل, متوسط, صعب
            $table->text('what_did_you_like');
            $table->text('what_can_be_improved');
            $table->boolean('would_recommend')->default(true);
            $table->text('additional_notes')->nullable();
            $table->timestamps();

            // Unique constraint: one review per email per campaign
            $table->unique(['review_campaign_id', 'email'], 'unique_review_per_email_campaign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_reviews');
    }
};
