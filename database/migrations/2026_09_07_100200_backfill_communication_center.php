<?php

use App\Services\Communication\Setup\CommunicationBackfill;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(CommunicationBackfill::class)->run();
    }

    public function down(): void
    {
        // Backfill is additive. Rolling back columns/tables is handled by earlier migrations.
    }
};
