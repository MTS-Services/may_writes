<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('trello_onboarding_status', 32)->nullable()->after('trello_offboarded_at');
            $table->text('trello_onboarding_last_error')->nullable()->after('trello_onboarding_status');
        });

        DB::table('customers')
            ->whereNotNull('trello_onboarded_at')
            ->update(['trello_onboarding_status' => 'completed']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['trello_onboarding_status', 'trello_onboarding_last_error']);
        });
    }
};
