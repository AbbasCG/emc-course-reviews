<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_campaigns', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('is_active');
            $table->string('theme', 50)->default('default')->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('review_campaigns', function (Blueprint $table) {
            $table->dropColumn(['expires_at', 'theme']);
        });
    }
};
