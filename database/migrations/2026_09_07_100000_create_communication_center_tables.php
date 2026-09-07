<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('communication_teams')) {
            Schema::create('communication_teams', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->boolean('is_default')->default(false);
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['workspace_id', 'name'], 'comm_teams_ws_name_idx');
            });
        }

        if (! Schema::hasTable('communication_team_members')) {
            Schema::create('communication_team_members', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('team_id')->constrained('communication_teams')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['team_id', 'user_id'], 'ctm_team_user_uniq');
                $table->index(['workspace_id', 'user_id'], 'ctm_ws_user_idx');
            });
        }

        if (! Schema::hasTable('channel_connections')) {
            Schema::create('channel_connections', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('channel', 32);
                $table->string('display_name');
                $table->string('status', 32)->default('disconnected');
                $table->string('provider', 32)->nullable();
                $table->string('external_account_id')->nullable();
                $table->string('credentials_ref')->nullable();
                $table->json('capabilities')->nullable();
                $table->string('source_type', 64)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->timestamp('connected_at')->nullable();
                $table->text('last_error')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['workspace_id', 'channel', 'status'], 'cc_ws_channel_status_idx');
                $table->unique(['workspace_id', 'source_type', 'source_id'], 'cc_ws_source_uniq');
            });
        }

        if (! Schema::hasTable('customer_channel_identities')) {
            Schema::create('customer_channel_identities', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->string('channel', 32);
                $table->string('identifier', 191);
                $table->string('identifier_raw', 191)->nullable();
                $table->foreignId('channel_connection_id')->nullable()->constrained('channel_connections')->nullOnDelete();
                $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
                $table->string('match_rule', 64);
                $table->timestamp('matched_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['workspace_id', 'channel', 'identifier'], 'cci_ws_channel_ident_uniq');
                $table->index(['workspace_id', 'customer_id'], 'cci_ws_customer_idx');
            });
        }

        if (! Schema::hasTable('communication_backfill_issues')) {
            Schema::create('communication_backfill_issues', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
                $table->string('issue_type', 64);
                $table->string('entity_type', 64)->nullable();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();

                $table->index(['workspace_id', 'issue_type'], 'cbi_ws_type_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_backfill_issues');
        Schema::dropIfExists('customer_channel_identities');
        Schema::dropIfExists('channel_connections');
        Schema::dropIfExists('communication_team_members');
        Schema::dropIfExists('communication_teams');
    }
};
