<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversations') || ! Schema::hasColumn('conversations', 'identity_key')) {
            return;
        }

        if ($this->indexExists('conversations', 'conv_ws_identity_key_uniq')) {
            return;
        }

        Schema::table('conversations', function (Blueprint $table): void {
            $table->unique(['workspace_id', 'identity_key'], 'conv_ws_identity_key_uniq');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('conversations') || ! $this->indexExists('conversations', 'conv_ws_identity_key_uniq')) {
            return;
        }

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropUnique('conv_ws_identity_key_uniq');
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
