<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trello_task_versions', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('pipeline_status');
        });
    }

    public function down(): void
    {
        Schema::table('trello_task_versions', function (Blueprint $table) {
            $table->dropColumn('submitted_at');
        });
    }
};
