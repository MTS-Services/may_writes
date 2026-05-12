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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('stripe_id')->nullable()->index();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('trello_board_id')->nullable();
            $table->string('trello_board_url')->nullable();
            $table->string('trello_member_id')->nullable();
            $table->timestamp('trello_invited_at')->nullable();
            $table->timestamp('welcome_email_sent_at')->nullable();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
