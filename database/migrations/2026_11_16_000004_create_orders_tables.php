<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('number', 20)->unique();                 // G-2026-0001 (TEST: T-2026-0001)
            $t->string('kind', 12)->default('initial');         // initial | upgrade | addon | vip
            $t->string('status', 12)->default('pending');       // pending | paid | fulfilled | expired | cancelled | inquiry
            $t->string('email');
            $t->json('config');                                 // konfigurace z průvodce
            $t->json('company')->nullable();                    // IČO, DIČ, název, adresa
            $t->string('slug', 30)->nullable();
            $t->string('plan', 20);
            $t->json('lines');
            $t->unsignedInteger('total_halere');
            $t->unsignedInteger('vat_halere');
            $t->string('stripe_session_id')->nullable()->unique();
            $t->string('stripe_payment_intent')->nullable();
            $t->boolean('is_test')->default(false);
            $t->foreignUlid('event_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('expires_at')->nullable();            // rezervace adresy
            $t->timestamps();
            $t->index(['slug', 'status']);
        });

        Schema::create('worker_jobs', function (Blueprint $t) {
            $t->id();
            $t->string('type', 20);                             // invoice | credit_note | video | mp4
            $t->json('payload');
            $t->string('status', 12)->default('queued');        // queued | running | done | failed
            $t->json('result')->nullable();
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->text('error')->nullable();
            $t->timestamp('locked_until')->nullable();
            $t->nullableMorphs('subject');
            $t->timestamps();
            $t->index(['status', 'type']);
        });

        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 12)->default('invoice');         // invoice | credit_note
            $t->string('status', 12)->default('queued');        // queued | issued | sent | failed
            $t->unsignedBigInteger('myucto_id')->nullable();
            $t->string('number', 40)->nullable();
            $t->string('pdf_path')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('worker_jobs');
        Schema::dropIfExists('orders');
    }
};
