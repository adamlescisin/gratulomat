<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Knihovna hudebních podkresů pro videoprezentaci (spravuje provozovatel, vybírá organizátor)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('music_tracks', function (Blueprint $t) {
            $t->id();
            $t->string('title', 140);
            $t->string('artist', 140)->nullable();
            $t->string('license', 500)->nullable();   // zdroj a licence (např. Pixabay Content License)
            $t->json('tags')->nullable();
            $t->unsignedInteger('duration')->nullable(); // sekundy
            $t->string('file', 80);                       // název v storage/app/music
            $t->unsignedInteger('bytes')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::table('show_settings', function (Blueprint $t) {
            $t->foreignId('music_track_id')->nullable()->after('music')->constrained('music_tracks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('show_settings', function (Blueprint $t) {
            $t->dropConstrainedForeignId('music_track_id');
        });
        Schema::dropIfExists('music_tracks');
    }
};
