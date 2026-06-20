<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            //新增影片路徑欄位
            $table->string('video_path')->nullable()->after('image_path');
             //新增媒體類型欄位 (用來區分 'image' 或 'video')
             $table->string('media_type',10)->nullable()->after('video_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('video_path', 'mdedia_type');
        });
    }
};
