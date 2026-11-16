<?php

use App\Http\Controllers\Organizer\EventAdminController;
use App\Models\Event;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Uložené, ale neupravené výchozí pozvánky (se skloňováním jmen) nahradí nový výchozí text. */
    public function up(): void
    {
        foreach (Event::all() as $event) {
            $saved = $event->setting('invite_text');
            if ($saved && trim($saved) === trim(EventAdminController::defaultInviteFor($event, 'invite_v1'))) {
                $event->settings = [...$event->settings, 'invite_text' => null];
                $event->save();
            }
        }
    }

    public function down(): void {}
};
