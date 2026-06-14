<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_closure', function (Blueprint $table) {
            // ancestor 是祖先，descendant 是後代
            $table->foreignId('ancestor')->constrained('messages')->onDelete('cascade');
            $table->foreignId('descendant')->constrained('messages')->onDelete('cascade');
            $table->primary(['ancestor', 'descendant']); // 確保不重複的關係鏈
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_closure');
    }
};



  

