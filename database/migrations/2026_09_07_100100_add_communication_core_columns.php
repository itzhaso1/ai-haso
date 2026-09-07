<?php

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

        Schema::table('conversations', function (Blueprint $table): void {
            if (! Schema::hasColumn('conversations', 'channel_connection_id')) {
                $table->foreignId('channel_connection_id')
                    ->nullable()
                    ->after('channel')
                    ->constrained('channel_connections')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('conversations', 'identity_key')) {
                $table->string('identity_key', 64)->nullable()->after('external_id');
            }
            if (! Schema::hasColumn('conversations', 'priority')) {
                $table->string('priority', 16)->default('normal')->after('status');
            }
            if (! Schema::hasColumn('conversations', 'assigned_team_id')) {
                $table->foreignId('assigned_team_id')
                    ->nullable()
                    ->after('priority')
                    ->constrained('communication_teams')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('conversations', 'assigned_user_id')) {
                $table->foreignId('assigned_user_id')
                    ->nullable()
                    ->after('assigned_team_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('conversations', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('assigned_user_id');
            }
        });

        $this->widenConversationChannelColumn();

        Schema::table('conversations', function (Blueprint $table): void {
            if (! $this->indexExists('conversations', 'conv_ws_assigned_idx')) {
                $table->index(['workspace_id', 'assigned_user_id', 'status'], 'conv_ws_assigned_idx');
            }
            if (! $this->indexExists('conversations', 'conv_ws_team_idx')) {
                $table->index(['workspace_id', 'assigned_team_id', 'status'], 'conv_ws_team_idx');
            }
            if (! $this->indexExists('conversations', 'conv_ws_conn_idx')) {
                $table->index(['workspace_id', 'channel_connection_id', 'last_message_at'], 'conv_ws_conn_idx');
            }
        });

        if (Schema::hasTable('messages') && ! Schema::hasColumn('messages', 'delivery_status')) {
            Schema::table('messages', function (Blueprint $table): void {
                $table->string('delivery_status', 16)->nullable()->after('ai_generated');
                $table->text('delivery_error')->nullable()->after('delivery_status');
                $table->timestamp('delivered_at')->nullable()->after('delivery_error');
                $table->foreignId('channel_connection_id')
                    ->nullable()
                    ->after('conversation_id')
                    ->constrained('channel_connections')
                    ->nullOnDelete();
                $table->index(['workspace_id', 'delivery_status'], 'msg_ws_delivery_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table): void {
                if ($this->indexExists('messages', 'msg_ws_delivery_idx')) {
                    $table->dropIndex('msg_ws_delivery_idx');
                }
                if (Schema::hasColumn('messages', 'channel_connection_id')) {
                    $table->dropConstrainedForeignId('channel_connection_id');
                }
                foreach (['delivered_at', 'delivery_error', 'delivery_status'] as $column) {
                    if (Schema::hasColumn('messages', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (! Schema::hasTable('conversations')) {
            return;
        }

        Schema::table('conversations', function (Blueprint $table): void {
            foreach (['conv_ws_assigned_idx', 'conv_ws_team_idx', 'conv_ws_conn_idx'] as $index) {
                if ($this->indexExists('conversations', $index)) {
                    $table->dropIndex($index);
                }
            }
            if (Schema::hasColumn('conversations', 'assigned_user_id')) {
                $table->dropConstrainedForeignId('assigned_user_id');
            }
            if (Schema::hasColumn('conversations', 'assigned_team_id')) {
                $table->dropConstrainedForeignId('assigned_team_id');
            }
            if (Schema::hasColumn('conversations', 'channel_connection_id')) {
                $table->dropConstrainedForeignId('channel_connection_id');
            }
            foreach (['assigned_at', 'priority', 'identity_key'] as $column) {
                if (Schema::hasColumn('conversations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function widenConversationChannelColumn(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE conversations MODIFY channel VARCHAR(32) NOT NULL DEFAULT 'manual'");

            return;
        }

        try {
            Schema::table('conversations', function (Blueprint $table): void {
                $table->string('channel', 32)->default('manual')->change();
            });
        } catch (Throwable) {
            // SQLite stores enum values as strings.
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");
            foreach ($indexes as $index) {
                if (($index->name ?? '') === $indexName) {
                    return true;
                }
            }

            return false;
        }

        if ($driver === 'mysql') {
            $rows = DB::select('SHOW INDEX FROM '.$table.' WHERE Key_name = ?', [$indexName]);

            return $rows !== [];
        }

        return false;
    }
};
