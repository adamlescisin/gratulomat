<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_codes', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();
            $t->unsignedTinyInteger('percent');
            $t->unsignedInteger('max_uses')->nullable();
            $t->unsignedInteger('used_count')->default(0);
            $t->date('valid_until')->nullable();
            $t->boolean('active')->default(true);
            $t->string('note', 200)->nullable();
            $t->timestamps();
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->string('discount_code', 40)->nullable()->after('vat_halere');
            $t->unsignedTinyInteger('discount_percent')->nullable()->after('discount_code');
            $t->unsignedInteger('discount_halere')->default(0)->after('discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['discount_code', 'discount_percent', 'discount_halere']));
        Schema::dropIfExists('discount_codes');
    }
};
