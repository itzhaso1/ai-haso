<?php

namespace Tests\Feature\Feature\Communication;

use App\Jobs\ProcessAIResponse;
use App\Models\Communication\ChannelConnection;
use App\Models\Communication\CommunicationBackfillIssue;
use App\Models\Communication\CommunicationTeam;
use App\Models\Communication\CustomerChannelIdentity;
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
use App\Services\Communication\Setup\CommunicationBackfill;
use App\Services\Communication\UnreadService;
use App\Services\WhatsApp\WhatsAppService;
use App\Support\Communication\ConversationIdentityKey;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
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

        $outsider = User::factory()->create();
        $workspaceA->users()->attach($outsider->id, [
            'membership_role' => 'agent',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        try {
            app(AssignmentService::class)->assign($conversation, $team->id, $outsider->id, null, $ownerA);
            $this->fail('Non-member of the team must not be assigned with the team.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('not a member of the selected team', $exception->getMessage());
        }

        $reassigned = app(AssignmentService::class)->assign($conversation, null, $outsider->id, 'urgent', $ownerA);
        $this->assertSame($outsider->id, $reassigned->assigned_user_id);
        $this->assertNull($reassigned->assigned_team_id);
        $this->assertSame('urgent', $reassigned->priority);

        $this->assertTrue($ownerA->can('assign', $conversation));
        $this->assertTrue($ownerA->can('reply', $conversation));

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
        $this->assertSame(2, Message::withoutGlobalScopes()->where('conversation_id', $conversation->id)->count());
    }

    public function test_whatsapp_limit_and_entitlement_failures_persist_as_failed(): void
    {
        config()->set('services.whatsapp.token', 'test-wa-token');
        config()->set('whatsapp.api_version', 'v20.0');
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'should-not-send']]], 200),
        ]);

        [$workspace, $owner] = $this->workspaceWithWhatsApp(
            'wa-limit@example.com',
            'waba_limit',
            ['whatsapp_messages' => 0],
            ['whatsapp', 'conversations'],
            ['whatsapp_messages' => 'hard_block'],
        );
        app(WorkspaceContext::class)->set($workspace);
        $phone = $this->createPhone($workspace, 'pnid_limit', 'waba_limit');
        $connection = app(ChannelConnectionService::class)->syncWhatsAppPhone($phone);

        $conversation = Conversation::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'channel' => 'whatsapp',
            'channel_connection_id' => $connection->id,
            'external_id' => '966500000088',
            'status' => 'open',
            'metadata' => ['phone_number_id' => 'pnid_limit', 'channel_source' => 'whatsapp'],
        ]);

        $limited = app(MessageService::class)->recordOutbound($conversation, [
            'content' => 'حد الاستخدام',
            'message_type' => 'text',
        ], $owner);

        $this->assertSame(Message::DELIVERY_FAILED, $limited->delivery_status);
        $this->assertNotSame(Message::DELIVERY_SENT, $limited->delivery_status);
        $this->assertNotNull($limited->delivery_error);
        $this->assertDatabaseHas('messages', [
            'id' => $limited->id,
            'delivery_status' => Message::DELIVERY_FAILED,
        ]);
        $this->assertSame(0, Message::withoutGlobalScopes()->where('conversation_id', $conversation->id)->where('delivery_status', Message::DELIVERY_SENT)->count());

        app(WorkspaceContext::class)->clear();

        [$blockedWorkspace, $blockedOwner] = $this->workspaceWithWhatsApp(
            'wa-entitlement@example.com',
            'waba_entitlement',
            ['whatsapp_messages' => 100],
            ['conversations'],
        );
        app(WorkspaceContext::class)->set($blockedWorkspace);
        $blockedPhone = $this->createPhone($blockedWorkspace, 'pnid_ent', 'waba_entitlement');
        $blockedConnection = app(ChannelConnectionService::class)->syncWhatsAppPhone($blockedPhone);
        $blockedConversation = Conversation::withoutGlobalScopes()->create([
            'workspace_id' => $blockedWorkspace->id,
            'channel' => 'whatsapp',
            'channel_connection_id' => $blockedConnection->id,
            'external_id' => '966500000089',
            'status' => 'open',
            'metadata' => ['phone_number_id' => 'pnid_ent', 'channel_source' => 'whatsapp'],
        ]);

        $blocked = app(MessageService::class)->recordOutbound($blockedConversation, [
            'content' => 'بدون ميزة واتساب',
            'message_type' => 'text',
        ], $blockedOwner);

        $this->assertSame(Message::DELIVERY_FAILED, $blocked->delivery_status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'communication.message.outbound',
            'entity_id' => $blocked->id,
        ]);
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

    public function test_same_connection_and_external_id_cannot_duplicate(): void
    {
        [$workspace] = $this->workspaceWithWhatsApp('uniq@example.com', 'waba_uniq');
        $phone = $this->createPhone($workspace, 'pnid_uniq', 'waba_uniq');
        $connection = app(ChannelConnectionService::class)->syncWhatsAppPhone($phone);

        Conversation::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'channel' => 'whatsapp',
            'channel_connection_id' => $connection->id,
            'external_id' => '966500000077',
            'status' => 'open',
        ]);

        try {
            Conversation::withoutGlobalScopes()->create([
                'workspace_id' => $workspace->id,
                'channel' => 'whatsapp',
                'channel_connection_id' => $connection->id,
                'external_id' => '966500000077',
                'status' => 'open',
            ]);
            $this->fail('Duplicate identity_key should be rejected.');
        } catch (QueryException) {
            $this->assertSame(
                1,
                Conversation::withoutGlobalScopes()
                    ->where('workspace_id', $workspace->id)
                    ->where('channel_connection_id', $connection->id)
                    ->where('external_id', '966500000077')
                    ->count(),
            );
        }
    }

    public function test_null_external_id_allows_multiple_conversations(): void
    {
        [$workspace] = $this->workspaceWithWhatsApp('null-ext@example.com', 'waba_null_ext');

        $one = Conversation::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'channel' => 'manual',
            'status' => 'open',
        ]);
        $two = Conversation::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'channel' => 'manual',
            'status' => 'open',
        ]);

        $this->assertNull($one->identity_key);
        $this->assertNull($two->identity_key);
        $this->assertNotSame($one->id, $two->id);
    }

    public function test_customer_can_own_multiple_identities_without_merge(): void
    {
        [$workspace] = $this->workspaceWithWhatsApp('identities@example.com', 'waba_ids');
        app(WorkspaceContext::class)->set($workspace);

        $customer = Customer::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Multi Channel',
            'phone' => '966511100001',
            'email' => 'multi@example.com',
        ]);

        $identities = app(IdentityService::class);
        $whatsapp = $identities->resolve($workspace->id, 'whatsapp', '966511100001');
        $email = $identities->resolve($workspace->id, 'email', 'multi@example.com');
        $instagram = $identities->linkToCustomer($customer, 'instagram', 'ig_multi_1');

        $this->assertSame($customer->id, $whatsapp['customer']->id);
        $this->assertSame($customer->id, $email['customer']->id);
        $this->assertSame($customer->id, $instagram->customer_id);
        $this->assertSame(3, CustomerChannelIdentity::withoutGlobalScopes()->where('customer_id', $customer->id)->count());
        $this->assertSame(1, Customer::withoutGlobalScopes()->where('workspace_id', $workspace->id)->count());
    }

    public function test_backfill_records_identity_and_connection_issues_without_merging(): void
    {
        [$workspace] = $this->workspaceWithWhatsApp('backfill@example.com', 'waba_bf');
        $firstPhone = $this->createPhone($workspace, 'pnid_bf_a', 'waba_bf_a');
        $this->createPhone($workspace, 'pnid_bf_b', 'waba_bf_b');

        Customer::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Dup A',
            'phone' => '+966 50 333 3333',
        ]);
        Customer::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Dup B',
            'phone' => '966503333333',
        ]);

        $ambiguous = Conversation::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'channel' => 'whatsapp',
            'external_id' => '966500000055',
            'status' => 'open',
            'metadata' => ['channel_source' => 'whatsapp'],
        ]);
        $missing = Conversation::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'channel' => 'whatsapp',
            'external_id' => '966500000056',
            'status' => 'open',
            'metadata' => ['channel_source' => 'whatsapp', 'phone_number_id' => 'pnid_missing_xyz'],
        ]);

        $customerCountBefore = Customer::withoutGlobalScopes()->where('workspace_id', $workspace->id)->count();
        $conversationIds = [$ambiguous->id, $missing->id];

        app(CommunicationBackfill::class)->run();

        $this->assertSame($customerCountBefore, Customer::withoutGlobalScopes()->where('workspace_id', $workspace->id)->count());
        $this->assertSame($conversationIds, Conversation::withoutGlobalScopes()->whereIn('id', $conversationIds)->orderBy('id')->pluck('id')->all());
        $this->assertNull($ambiguous->fresh()->channel_connection_id);
        $this->assertNull($missing->fresh()->channel_connection_id);
        $this->assertSame($ambiguous->external_id, $ambiguous->fresh()->external_id);

        $issues = CommunicationBackfillIssue::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->pluck('issue_type')
            ->all();

        $this->assertContains('identity_collision', $issues);
        $this->assertContains('ambiguous_connection', $issues);
        $this->assertContains('missing_connection', $issues);
        $this->assertNotNull($firstPhone->id);
    }

    public function test_whatsapp_webhook_inbound_is_idempotent_and_uses_core(): void
    {
        Bus::fake([ProcessAIResponse::class]);
        config()->set('whatsapp.app_secret', 'meta_secret_core');

        [$workspace] = $this->workspaceWithWhatsApp('hook@example.com', 'waba_hook');
        $phone = $this->createPhone($workspace, 'pnid_hook', 'waba_hook');
        app(ChannelConnectionService::class)->syncWhatsAppPhone($phone);

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'waba_event_core_1',
                'changes' => [[
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '966500000000',
                            'phone_number_id' => 'pnid_hook',
                        ],
                        'messages' => [[
                            'from' => '966511122244',
                            'id' => 'wamid.hook.core.1',
                            'timestamp' => (string) time(),
                            'type' => 'text',
                            'text' => ['body' => 'webhook inbound'],
                        ]],
                    ],
                    'field' => 'messages',
                ]],
            ]],
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $signature = 'sha256='.hash_hmac('sha256', $encoded ?: '{}', 'meta_secret_core');

        $this->withHeader('X-Hub-Signature-256', $signature)
            ->postJson('/whatsapp-webhook', $payload)
            ->assertStatus(202);

        $this->withHeader('X-Hub-Signature-256', $signature)
            ->postJson('/whatsapp-webhook', $payload)
            ->assertStatus(202);

        $this->assertSame(1, Message::withoutGlobalScopes()->where('external_message_id', 'wamid.hook.core.1')->count());
        $conversation = Conversation::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->where('external_id', '966511122244')
            ->first();
        $this->assertNotNull($conversation?->channel_connection_id);
        $this->assertSame('whatsapp', $conversation->channel);
        $this->assertDatabaseHas('customer_channel_identities', [
            'workspace_id' => $workspace->id,
            'channel' => 'whatsapp',
            'identifier' => '966511122244',
        ]);
        Bus::assertDispatched(ProcessAIResponse::class);
    }

    public function test_tenant_isolation_blocks_cross_workspace_reads_and_writes(): void
    {
        [$workspaceA, $ownerA] = $this->workspaceWithWhatsApp('iso-a@example.com', 'waba_iso_full_a');
        [$workspaceB, $ownerB] = $this->workspaceWithWhatsApp('iso-b@example.com', 'waba_iso_full_b');

        $phone = $this->createPhone($workspaceA, 'pnid_iso_full', 'waba_iso_full');
        $connection = app(ChannelConnectionService::class)->syncWhatsAppPhone($phone);
        $team = CommunicationTeam::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceA->id,
            'name' => 'A Team',
        ]);
        $customer = Customer::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceA->id,
            'name' => 'Iso Customer',
            'phone' => '966500000066',
        ]);
        $identity = app(IdentityService::class)->linkToCustomer($customer, 'whatsapp', '966500000066', $connection->id);
        $conversation = Conversation::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceA->id,
            'customer_id' => $customer->id,
            'channel' => 'whatsapp',
            'channel_connection_id' => $connection->id,
            'external_id' => '966500000066',
            'status' => 'open',
        ]);
        $message = Message::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceA->id,
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'message_type' => 'text',
            'content' => 'secret',
            'delivery_status' => Message::DELIVERY_RECEIVED,
        ]);

        app(WorkspaceContext::class)->set($workspaceB);

        $this->assertNull(Conversation::query()->find($conversation->id));
        $this->assertNull(Message::query()->find($message->id));
        $this->assertNull(ChannelConnection::query()->find($connection->id));
        $this->assertNull(CustomerChannelIdentity::query()->find($identity->id));
        $this->assertNull(CommunicationTeam::query()->find($team->id));
        $this->assertFalse($ownerB->can('view', $conversation));
        $this->assertFalse($ownerB->can('reply', $conversation));
        $this->assertFalse($ownerB->can('assign', $conversation));
        $this->assertTrue($ownerA->can('view', $conversation));

        $this->expectException(\RuntimeException::class);
        Conversation::query()->create([
            'workspace_id' => $workspaceA->id,
            'channel' => 'manual',
            'status' => 'open',
        ]);
    }

    /**
     * @return array{0:Workspace,1:User}
     */
    private function workspaceWithWhatsApp(
        string $email = 'wa-core@example.com',
        string $waba = 'waba_core',
        array $limits = ['whatsapp_messages' => 100],
        array $features = ['whatsapp', 'ai', 'conversations'],
        array $overageRules = [],
    ): array {
        app(WorkspaceContext::class)->clear();

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
            'features' => $features,
            'limits' => $limits,
            'overage_rules' => $overageRules,
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
