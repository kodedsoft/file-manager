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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->integer('unique_key');
            $table->text('title');
            $table->text('description')->nullable();
            $table->float('piece_price');
            $table->string('size')->nullable();
            $table->string('style')->nullable();
            $table->string('color_name')->nullable();
            $table->string("sanmar_mainframe_color")->nullable();
            $table->tinyInteger("status")->default(1);
            $table->timestamps();

            $table->index('unique_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
