<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('priority_keywords', function (Blueprint $table) {
            $table->id();
            $table->string('keyword')->unique();
            $table->enum('sentiment', ['bullish', 'bearish'])->index();
            $table->integer('score')->comment('Positive for bullish, negative for bearish');
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            // Index for fast sentiment queries
            $table->index(['sentiment', 'score', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('priority_keywords');
    }
};
