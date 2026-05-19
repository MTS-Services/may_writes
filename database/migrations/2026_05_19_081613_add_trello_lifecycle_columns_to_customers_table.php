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
            $table->string('stripe_subscription_id')->nullable()->after('stripe_id');
            $table->string('trello_webhook_id')->nullable()->after('trello_member_id');
            $table->timestamp('trello_onboarded_at')->nullable()->after('trello_invited_at');
            $table->timestamp('trello_offboarded_at')->nullable()->after('trello_onboarded_at');
            $table->timestamp('access_ends_at')->nullable()->after('trial_used_at');
            $table->boolean('cancel_at_period_end')->default(false)->after('access_ends_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_subscription_id',
                'trello_webhook_id',
                'trello_onboarded_at',
                'trello_offboarded_at',
                'access_ends_at',
                'cancel_at_period_end',
            ]);
        });
    }
};
