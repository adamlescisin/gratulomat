<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Datum události je nepovinné; uzávěrka sběru příspěvků je povinná (starým událostem bez ní se doplní datum události).
return new class extends Migration
{
    public function up(): void
    {
        DB::table('events')->whereNull('collect_until')->update(['collect_until' => DB::raw('event_date')]);
        Schema::table('events', function (Blueprint $t) {
            $t->dateTime('event_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('events')->whereNull('event_date')->update(['event_date' => DB::raw('collect_until')]);
        Schema::table('events', function (Blueprint $t) {
            $t->dateTime('event_date')->nullable(false)->change();
        });
    }
};
