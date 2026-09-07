<?php

use App\Services\Communication\Setup\CommunicationBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversations')) {
            return;
        }

        app(CommunicationBackfill::class)->verifyOrThrow();

        if (! $this->indexExists('conversations', 'conv_ws_identity_key_uniq')) {
            throw new RuntimeException(
                'Refusing to drop legacy conversation uniqueness: conv_ws_identity_key_uniq is missing.'
            );
        }

        if (! $this->indexExists('conversations', 'conversations_workspace_id_external_id_unique')) {
            return;
        }

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropUnique('conversations_workspace_id_external_id_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('conversations')) {
            return;
        }

        if ($this->indexExists('conversations', 'conversations_workspace_id_external_id_unique')) {
            return;
        }

        Schema::table('conversations', function (Blueprint $table): void {
            $table->unique(['workspace_id', 'external_id'], 'conversations_workspace_id_external_id_unique');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            foreach (DB::select("PRAGMA index_list('{$table}')") as $index) {
                if (($index->name ?? '') === $indexName) {
                    return true;
                }
            }

            return false;
        }

        if ($driver === 'mysql') {
            return DB::select('SHOW INDEX FROM '.$table.' WHERE Key_name = ?', [$indexName]) !== [];
        }

        return false;
    }
};
