<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_campaigns', function (Blueprint $table) {
            $table->string('activity_type', 100)->default('course')->after('instructor_name');
            $table->index('activity_type');
        });
    }

    public function down(): void
    {
        Schema::table('review_campaigns', function (Blueprint $table) {
            $table->dropIndex(['activity_type']);
            $table->dropColumn('activity_type');
        });
    }
};
