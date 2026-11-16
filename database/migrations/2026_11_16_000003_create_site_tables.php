<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $t) {
            $t->string('key', 80)->primary();
            $t->json('value')->nullable();
            $t->timestamps();
        });

        Schema::create('waitlist', function (Blueprint $t) {
            $t->id();
            $t->string('email')->unique();
            $t->timestamp('consent_at');
            $t->string('source', 40)->default('coming_soon');
            $t->string('ip', 45)->nullable();
            $t->timestamp('invited_at')->nullable();
            $t->timestamps();
        });

        Schema::create('tester_codes', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();
            $t->string('label', 120)->nullable();
            $t->date('valid_until')->nullable();
            $t->unsignedInteger('uses')->default(0);
            $t->unsignedInteger('max_uses')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tester_codes');
        Schema::dropIfExists('waitlist');
        Schema::dropIfExists('settings');
    }
};
