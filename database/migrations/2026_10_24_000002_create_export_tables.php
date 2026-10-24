<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->json('options');
            $table->timestamps();
        });

        Schema::create('export_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->string('report_type', 30);
            $table->string('format', 10);
            $table->string('status', 20)->default('pending');
            $table->json('options');
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->unsignedInteger('record_count')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'expires_at']);
        });

        // Speeds up date-range filtering on submission date.
        Schema::table('course_reviews', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('course_reviews', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
        Schema::dropIfExists('export_logs');
        Schema::dropIfExists('export_templates');
    }
};
