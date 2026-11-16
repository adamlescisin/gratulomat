<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('slug', 30)->unique();
            $t->string('type', 30)->default('narozeniny');
            $t->string('title', 140);
            $t->string('locale', 5)->default('cs');            // primární jazyk
            $t->string('recipient_type', 20)->default('osoba'); // osoba | par | tym | firma | zesnuly
            $t->string('recipient_name', 120);
            $t->json('recipient_forms')->nullable();            // pády a tvary jména pro texty (dat, acc, voc, poss…)
            $t->unsignedSmallInteger('start_year')->nullable(); // rok narození / svatby / založení
            $t->dateTime('event_date');
            $t->string('event_place', 160)->nullable();
            $t->dateTime('collect_until')->nullable();
            $t->date('archive_until')->nullable();
            $t->string('status', 20)->default('collecting');    // draft | collecting | closed | archived | paused
            $t->string('plan', 20)->default('ochutnavka');
            $t->json('limits')->nullable();                     // zmražené limity tarifu
            $t->json('settings')->nullable();                   // tags, hero, wall_before_event…
            $t->string('theme', 30)->default('kino');
            $t->boolean('is_test')->default(false);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('households', function (Blueprint $t) {
            $t->id();
            $t->foreignUlid('event_id')->constrained()->cascadeOnDelete();
            $t->string('token', 32)->unique();
            $t->string('label', 160);
            $t->json('names');
            $t->string('relation', 160)->nullable();
            $t->string('email')->nullable();
            $t->string('preferred_locale', 5)->nullable();
            $t->string('source', 20)->default('manual');        // manual | import | anonymous
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();
        });

        Schema::create('memories', function (Blueprint $t) {
            $t->uuid('id')->primary();                          // zároveň název složky s médii
            $t->foreignUlid('event_id')->constrained()->cascadeOnDelete();
            $t->foreignId('household_id')->constrained()->cascadeOnDelete();
            $t->json('authors');
            $t->string('title', 140)->nullable();
            $t->text('body')->nullable();
            $t->unsignedSmallInteger('year')->nullable();
            $t->json('tags');
            $t->text('wish_text')->nullable();
            $t->boolean('wish_public')->default(false);
            $t->string('status', 10)->default('visible');       // visible | hidden
            $t->timestamps();
            $t->index(['event_id', 'status']);
        });

        Schema::create('media', function (Blueprint $t) {
            $t->id();
            $t->uuid('memory_id');
            $t->foreign('memory_id')->references('id')->on('memories')->cascadeOnDelete();
            $t->string('kind', 10);                             // photo | video | audio
            $t->string('file', 80);
            $t->string('thumb', 80)->nullable();
            $t->string('mime', 60);
            $t->unsignedInteger('width')->nullable();
            $t->unsignedInteger('height')->nullable();
            $t->float('duration')->nullable();
            $t->unsignedBigInteger('bytes');
            $t->unsignedInteger('sort')->default(0);
            $t->string('processing_status', 12)->default('ready'); // ready | queued | failed
            $t->timestamps();
            $t->index('memory_id');
        });

        Schema::create('show_settings', function (Blueprint $t) {
            $t->foreignUlid('event_id')->primary()->constrained()->cascadeOnDelete();
            $t->dateTime('public_from')->nullable();            // zpřístupnění hostům – vždy rozhoduje organizátor
            $t->string('music')->nullable();
            $t->json('order')->nullable();
            $t->boolean('game_enabled')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('show_settings');
        Schema::dropIfExists('media');
        Schema::dropIfExists('memories');
        Schema::dropIfExists('households');
        Schema::dropIfExists('events');
    }
};
