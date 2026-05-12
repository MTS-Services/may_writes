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
        Schema::create('trello_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('trello_card_id')->unique();
            $table->string('trello_board_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('raw_payload');
            $table->string('status')->default('received');
            $table->text('ai_summary')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_filename')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('failed_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trello_tasks');
    }
};
