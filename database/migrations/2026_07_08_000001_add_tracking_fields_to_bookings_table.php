<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            // Public, unguessable code for the customer tracking page (e.g. LJR-8F3K2A).
            // Kept separate from the numeric id so the database id is never exposed in a
            // public URL and codes can't be enumerated by incrementing a number.
            $table->string('booking_code', 20)->nullable()->unique()->after('id');

            // Physical delivery/pick-up stage of the car. Deliberately independent of
            // `status` (pending/confirmed/completed/cancelled), which is the transaction
            // state — a booking can be status=confirmed while trip_status=preparing.
            $table->string('trip_status')->default('not_started')->after('status');

            // ETA typed in by the admin (free text, e.g. "±45 menit lagi"). No automatic
            // calculation in this phase — the admin stays fully flexible.
            $table->string('eta_manual_note')->nullable()->after('trip_status');

            // NOTE: live GPS position + device mapping intentionally NOT stored here.
            // The fleet already tracks GPS per car via `vehicle_positions` +
            // `cars.traccar_device_id` (Traccar integration, Phase 7). A booking's live
            // position derives from its car's latest position — see Booking::hasLiveGps.

            $table->index('trip_status');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex(['trip_status']);
            $table->dropColumn(['booking_code', 'trip_status', 'eta_manual_note']);
        });
    }
};
