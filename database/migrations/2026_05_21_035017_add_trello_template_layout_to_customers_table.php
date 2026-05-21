<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('trello_draft_review_list_id')->nullable()->after('trello_in_progress_list_id');
            $table->string('trello_revisions_list_id')->nullable()->after('trello_draft_review_list_id');
            $table->string('trello_delivered_list_id')->nullable()->after('trello_revisions_list_id');
            $table->json('trello_instruction_card_ids')->nullable()->after('trello_welcome_card_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'trello_draft_review_list_id',
                'trello_revisions_list_id',
                'trello_delivered_list_id',
                'trello_instruction_card_ids',
            ]);
        });
    }
};
