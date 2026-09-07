<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conversations')) {
            Schema::table('conversations', function (Blueprint $table): void {
                if (! $this->indexExists('conversations', 'conv_ws_channel_last_idx')) {
                    $table->index(['workspace_id', 'channel', 'last_message_at'], 'conv_ws_channel_last_idx');
                }
                if (! $this->indexExists('conversations', 'conv_ws_priority_last_idx')) {
                    $table->index(['workspace_id', 'priority', 'last_message_at'], 'conv_ws_priority_last_idx');
                }
            });
        }

        if (Schema::hasTable('messages') && ! $this->indexExists('messages', 'msg_conv_dir_id_idx')) {
            Schema::table('messages', function (Blueprint $table): void {
                $table->index(['conversation_id', 'direction', 'id'], 'msg_conv_dir_id_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('conversations')) {
            Schema::table('conversations', function (Blueprint $table): void {
                foreach (['conv_ws_channel_last_idx', 'conv_ws_priority_last_idx'] as $index) {
                    if ($this->indexExists('conversations', $index)) {
                        $table->dropIndex($index);
                    }
                }
            });
        }

        if (Schema::hasTable('messages') && $this->indexExists('messages', 'msg_conv_dir_id_idx')) {
            Schema::table('messages', function (Blueprint $table): void {
                $table->dropIndex('msg_conv_dir_id_idx');
            });
        }
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
