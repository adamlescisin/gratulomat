<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Ruční objednávky provozovatele nemají cenu ani položky (dřív se ukládala vypočtená cena). */
    public function up(): void
    {
        DB::table('orders')->where('kind', 'manual')->update([
            'lines' => '[]', 'total_halere' => 0, 'vat_halere' => 0, 'discount_code' => null, 'discount_percent' => null, 'discount_halere' => 0,
        ]);
    }

    public function down(): void {}
};
