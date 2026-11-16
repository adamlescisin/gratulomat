<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->unsignedInteger('refunded_halere')->default(0)->after('discount_halere');
            $t->timestamp('refunded_at')->nullable()->after('paid_at');
            $t->json('history')->nullable();
            $t->text('note')->nullable();
            $t->text('payment_url')->nullable();
        });
        Schema::table('invoices', function (Blueprint $t) {
            $t->integer('amount_halere')->nullable()->after('kind');
            $t->foreignId('source_invoice_id')->nullable()->after('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['refunded_halere', 'refunded_at', 'history', 'note', 'payment_url']));
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn(['amount_halere', 'source_invoice_id']));
    }
};
