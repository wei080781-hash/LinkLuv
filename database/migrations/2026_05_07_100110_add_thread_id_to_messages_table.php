<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // 在 parent_id 欄位後新增 thread_id
            $table->unsignedBigInteger('thread_id')
            ->nullable()->index()->after('parent_id');
        });
    }

   
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('thread_id');
        });
    }
};
