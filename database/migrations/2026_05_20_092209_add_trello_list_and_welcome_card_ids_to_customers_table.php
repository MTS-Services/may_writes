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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('trello_writing_requests_list_id')->nullable()->after('trello_webhook_id');
            $table->string('trello_in_progress_list_id')->nullable()->after('trello_writing_requests_list_id');
            $table->string('trello_completed_list_id')->nullable()->after('trello_in_progress_list_id');
            $table->string('trello_welcome_card_id')->nullable()->after('trello_completed_list_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'trello_writing_requests_list_id',
                'trello_in_progress_list_id',
                'trello_completed_list_id',
                'trello_welcome_card_id',
            ]);
        });
    }
};
