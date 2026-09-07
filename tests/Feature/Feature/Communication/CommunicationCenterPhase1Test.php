<?php

namespace Tests\Feature\Feature\Communication;

use App\Models\Communication\ChannelConnection;
use App\Models\Communication\CommunicationTeam;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Models\Workspace;
use App\Services\Communication\AssignmentService;
use App\Services\Communication\ChannelConnectionService;
use App\Services\Communication\IdentityService;
use App\Services\Communication\MessageService;
use App\Services\Communication\UnreadService;
use App\Services\WhatsApp\WhatsAppService;
use App\Support\Communication\ConversationIdentityKey;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CommunicationCenterPhase1Test extends TestCase
{
    use RefreshDatabase;

    public function test_identity_key_is_scoped_to_connection_not_workspace_external_id(): void
    {
        [$workspace] = $this->workspaceWithWhatsApp();

        $first = $this->createPhone($workspace, 'pnid_a', 'waba_a');
        $second = $this->createPhone($workspace, 'pnid_b', 'waba_b');

        $connections = app(ChannelConnectionService::class);
        $connA = $connections->syncWhatsAppPhone($first);
        $connB = $connections->syncWhatsAppPhone($second);

        $this->assertNotSame($connA->id, $connB->id);

        $customer = Customer::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Same Person',
            'phone' => '966500000001',
        ]);

        $one = Conversation::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'customer_id' => $customer->id,
            'channel' => 'whatsapp',
            'channel_connection_id' => $connA->id,
            'external_id' => '966500000001',
            'status' => 'open',
        ]);
        $two = Conversation::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'customer_id' => $customer->id,
            'channel' => 'whatsapp',
            'channel_connection_id' => $connB->id,
            'external_id' => '966500000001',
            'status' => 'open',
        ]);

        $this->assertNotSame($one->identity_key, $two->identity_key);
        $this->assertSame(
            ConversationIdentityKey::make($connA->id, 'whatsapp', '966500000001'),
            $one->identity_key,
        );
        $this->assertFalse($this->legacyExternalIdUniqueExists());
        $this->assertTrue($this->identityKeyUniqueExists());
    }

    public function test_identity_service_does_not_merge_customers(): void
    {
        [$workspace] = $this->workspaceWithWhatsApp();
        app(WorkspaceContext::class)->set($workspace);

        $first = Customer::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'One',
            'phone' => '0501111111',
        ]);

        $resolved = app(IdentityService::class)->resolve($workspace->id, 'whatsapp', '0501111111');
        $this->assertSame($first->id, $resolved['customer']->id);

        $second = Customer::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Two',
            'phone' => '0502222222',
        ]);

        $again = app(IdentityService::class)->resolve($workspace->id, 'whatsapp', '0501111111');
        $this->assertSame($first->id, $again['customer']->id);
        $this->assertNotSame($second->id, $again['customer']->id);
    }

    public function test_unread_is_per_user_and_archive_does_not_close_conversation(): void
    {
        [$workspace, $owner] = $this->workspaceWithWhatsApp();
        $agent = User::factory()->create();
        $workspace->users()->attach($agent->id, [
            'membership_role' => 'agent',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $conversation = Conversation::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'channel' => 'manual',
            'status' => 'open',
            'external_id' => 'thread-1',
        ]);
        Message::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'message_type' => 'text',
            'content' => 'hi',
            'delivery_status' => 'received',
        ]);

        $unread = app(UnreadService::class);
        $this->assertSame(1, $unread->unreadCountForConversation($conversation, $owner));
        $this->assertSame(1, $unread->unreadCountForConversation($conversation, $agent));

        $unread->markRead($conversation, $owner);
        $this->assertSame(0, $unread->unreadCountForConversation($conversation, $owner));
        $this->assertSame(1, $unread->unreadCountForConversation($conversation, $agent));

        $unread->archive($conversation, $owner);
        $this->assertSame('open', $conversation->fresh()->status);
        $this->assertNotNull($unread->archive($conversation, $owner)->archived_at);
    }

    public function test_assignment_uses_workspace_users_and_rejects_foreign_agent(): void
    {
        [$workspaceA, $ownerA] = $this->workspaceWithWhatsApp();
        [$workspaceB, $ownerB] = $this->workspaceWithWhatsApp('b@example.com', 'waba_foreign');

        $conversation = Conversation::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceA->id,
            'channel' => 'manual',
            'status' => 'open',
        ]);

        $team = CommunicationTeam::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceA->id,
            'name' => 'Support',
        ]);
        $team->members()->sync([$ownerA->id => ['workspace_id' => $workspaceA->id]]);

        $assigned = app(AssignmentService::class)->assign($conversation, $team->id, $ownerA->id, 'high', $ownerA);
        $this->assertSame($ownerA->id, $assigned->assigned_user_id);
        $this->assertSame('high', $assigned->priority);

        $this->expectException(\InvalidArgumentException::class);
        app(AssignmentService::class)->assign($conversation, null, $ownerB->id, null, $ownerA);
    }

    public function test_whatsapp_inbound_uses_connection_and_identity(): void
    {
        [$workspace] = $this->workspaceWithWhatsApp();
        $phone = $this->createPhone($workspace, 'pnid_in', 'waba_in');
        app(ChannelConnectionService::class)->syncWhatsAppPhone($phone);

        $message = app(WhatsAppService::class)->storeIncomingMessage($workspace->id, [
            'from' => '966511122233',
            'id' => 'wamid.in.1',
            'text' => ['body' => 'مرحبا وارد'],
        ], 'pnid_in');

        $conversation = Conversation::withoutGlobalScopes()->find($message->conversation_id);
        $this->assertNotNull($conversation?->channel_connection_id);
        $this->assertSame('whatsapp', $conversation->channel);
        $this->assertSame(Message::DELIVERY_RECEIVED, $message->delivery_status);
        $this->assertDatabaseHas('customer_channel_identities', [
            'workspace_id' => $workspace->id,
            'channel' => 'whatsapp',
            'identifier' => '966511122233',
        ]);
    }

    public function test_agent_whatsapp_reply_is_pending_then_sent_or_failed(): void
    {
        config()->set('services.whatsapp.token', 'test-wa-token');
        config()->set('whatsapp.api_version', 'v20.0');

        [$workspace, $owner] = $this->workspaceWithWhatsApp();
        app(WorkspaceContext::class)->set($workspace);
        $phone = $this->createPhone($workspace, 'pnid_out', 'waba_out');
        $connection = app(ChannelConnectionService::class)->syncWhatsAppPhone($phone);

        $customer = Customer::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'عميل',
            'phone' => '966500000010',
        ]);
        $conversation = Conversation::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'customer_id' => $customer->id,
            'channel' => 'whatsapp',
            'channel_connection_id' => $connection->id,
            'external_id' => '966500000010',
            'status' => 'open',
            'metadata' => ['phone_number_id' => 'pnid_out', 'channel_source' => 'whatsapp'],
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.OUT.1']],
            ], 200),
        ]);

        $sent = app(MessageService::class)->recordOutbound($conversation, [
            'content' => 'رد موظف',
            'message_type' => 'text',
        ], $owner);

        $this->assertSame(Message::DELIVERY_SENT, $sent->delivery_status);
        $this->assertSame('wamid.OUT.1', $sent->external_message_id);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => 'Graph boom'],
            ], 400),
        ]);

        $failed = app(MessageService::class)->recordOutbound($conversation, [
            'content' => 'رد فاشل',
            'message_type' => 'text',
        ], $owner);

        $this->assertSame(Message::DELIVERY_FAILED, $failed->delivery_status);
        $this->assertNotSame(Message::DELIVERY_SENT, $failed->delivery_status);
        $this->assertNotNull($failed->delivery_error);
    }

    public function test_coming_soon_channels_are_not_connected(): void
    {
        [$workspace, $owner] = $this->workspaceWithWhatsApp();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.channels.index'))
            ->assertOk()
            ->assertSee('Coming Soon')
            ->assertSee('Instagram')
            ->assertSee('Facebook Messenger')
            ->assertDontSee('workspace.settings');

        $instagram = collect(app(ChannelConnectionService::class)->catalog($workspace))
            ->firstWhere('key', 'instagram');
        $this->assertSame(ChannelConnection::STATUS_COMING_SOON, $instagram['status']);
        $this->assertFalse($instagram['connected']);
    }

    public function test_tenant_isolation_on_identities_and_connections(): void
    {
        [$workspaceA] = $this->workspaceWithWhatsApp('a@iso.test', 'waba_iso_a');
        [$workspaceB, $ownerB] = $this->workspaceWithWhatsApp('b@iso.test', 'waba_iso_b');

        $phone = $this->createPhone($workspaceA, 'pnid_iso', 'waba_iso_phone');
        $connection = app(ChannelConnectionService::class)->syncWhatsAppPhone($phone);

        app(WorkspaceContext::class)->set($workspaceB);
        $this->assertNull(ChannelConnection::query()->find($connection->id));

        $this->actingAs($ownerB)
            ->withSession(['current_workspace_id' => $workspaceB->id])
            ->get(route('workspace.conversations.index', ['conversation' => 999999]))
            ->assertOk();
    }

    /**
     * @return array{0:Workspace,1:User}
     */
    private function workspaceWithWhatsApp(string $email = 'wa-core@example.com', string $waba = 'waba_core'): array
    {
        $user = User::factory()->create(['email' => $email]);
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $user->id,
            'type' => 'company',
        ]);
        $workspace->users()->attach($user->id, [
            'membership_role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $plan = Plan::query()->create([
            'code' => 'comm_core_'.substr(sha1($email.$waba), 0, 8),
            'name' => 'Comm Core',
            'workspace_type' => 'company',
            'billing_period' => 'monthly',
            'currency' => 'SAR',
            'price' => 0,
            'is_active' => true,
            'features' => ['whatsapp', 'ai', 'conversations'],
            'limits' => ['whatsapp_messages' => 100],
        ]);

        Subscription::query()->create([
            'workspace_id' => $workspace->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
        ]);

        return [$workspace, $user];
    }

    private function createPhone(Workspace $workspace, string $phoneNumberId, string $waba): WhatsAppPhoneNumber
    {
        $account = WhatsAppAccount::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'business_account_id' => $waba,
            'display_name' => 'WA '.$waba,
            'status' => 'connected',
            'metadata' => [],
        ]);

        return WhatsAppPhoneNumber::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'whats_app_account_id' => $account->id,
            'phone_number_id' => $phoneNumberId,
            'display_phone_number' => '+966500000000',
            'verified_name' => 'Number',
            'status' => 'connected',
        ]);
    }

    private function identityKeyUniqueExists(): bool
    {
        return $this->sqliteIndexExists('conversations', 'conv_ws_identity_key_uniq')
            || $this->mysqlIndexExists('conversations', 'conv_ws_identity_key_uniq');
    }

    private function legacyExternalIdUniqueExists(): bool
    {
        return $this->sqliteIndexExists('conversations', 'conversations_workspace_id_external_id_unique')
            || $this->mysqlIndexExists('conversations', 'conversations_workspace_id_external_id_unique');
    }

    private function sqliteIndexExists(string $table, string $indexName): bool
    {
        if (DB::getDriverName() !== 'sqlite') {
            return false;
        }
        foreach (DB::select("PRAGMA index_list('{$table}')") as $index) {
            if (($index->name ?? '') === $indexName) {
                return true;
            }
        }

        return false;
    }

    private function mysqlIndexExists(string $table, string $indexName): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        return DB::select('SHOW INDEX FROM '.$table.' WHERE Key_name = ?', [$indexName]) !== [];
    }
}
