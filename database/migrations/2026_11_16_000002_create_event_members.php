<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Organizátoři se přihlašují odkazem z e-mailu, heslo nemají.
        Schema::table('users', function (Blueprint $t) {
            $t->string('password')->nullable()->change();
            $t->string('locale', 5)->default('cs');
            $t->timestamp('last_login_at')->nullable();
        });

        Schema::create('event_members', function (Blueprint $t) {
            $t->id();
            $t->foreignUlid('event_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role', 20)->default('owner'); // owner | co-owner
            $t->timestamps();
            $t->unique(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_members');
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['locale', 'last_login_at']);
        });
    }
};
