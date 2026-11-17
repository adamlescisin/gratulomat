<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Chyba posledního pokusu o e-mail hostovi (pozvánka/připomínka) a počet pokusů – ať pozvánka nevisí „odesílá se“ navěky.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $t) {
            $t->string('mail_error', 500)->nullable();
            $t->unsignedTinyInteger('mail_attempts')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('households', fn (Blueprint $t) => $t->dropColumn(['mail_error', 'mail_attempts']));
    }
};
