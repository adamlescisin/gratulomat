<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $t) {
            $t->timestamp('invite_queued_at')->nullable();
            $t->timestamp('invited_at')->nullable();
            $t->timestamp('reminder_queued_at')->nullable();
            $t->timestamp('reminded_at')->nullable();
            $t->boolean('email_opt_out')->default(false);
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->json('todo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('households', fn (Blueprint $t) => $t->dropColumn(['invite_queued_at', 'invited_at', 'reminder_queued_at', 'reminded_at', 'email_opt_out']));
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('todo'));
    }
};
