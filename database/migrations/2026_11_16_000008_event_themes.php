<?php

use App\Domain\Guest\Themes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Staré názvy vzhledů → nové šablony (Adamových 50 „kino“ = Exkluzivní tmavá). */
    public function up(): void
    {
        Schema::table('events', fn (Blueprint $t) => $t->string('theme', 30)->default('neutralni')->change());

        foreach (DB::table('events')->get(['id', 'theme', 'settings']) as $e) {
            if (! isset(Themes::LEGACY[$e->theme])) {
                continue;
            }
            [$theme, $mode] = Themes::LEGACY[$e->theme];
            $settings = json_decode($e->settings ?? '{}', true) ?: [];
            if ($mode && empty($settings['mode'])) {
                $settings['mode'] = $mode;
            }
            DB::table('events')->where('id', $e->id)->update(['theme' => $theme, 'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE)]);
        }
    }

    public function down(): void {}
};
