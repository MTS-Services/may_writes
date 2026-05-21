<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trello_tasks', function (Blueprint $table) {
            $table->string('workflow_status')->default('initialized')->after('description');
            $table->string('trello_list_id')->nullable()->after('trello_board_id');
            $table->string('content_fingerprint', 64)->nullable()->after('workflow_status');
            $table->foreignId('latest_version_id')->nullable()->after('content_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('trello_tasks', function (Blueprint $table) {
            $table->dropColumn([
                'workflow_status',
                'trello_list_id',
                'content_fingerprint',
                'latest_version_id',
            ]);
        });
    }
};
