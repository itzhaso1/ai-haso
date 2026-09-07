<?php

namespace Tests\Feature\Feature\Communication;

use App\Models\Communication\CommunicationTeam;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\EmailAccount;
use App\Models\Message;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Models\Workspace;
use App\Services\Communication\ChannelConnectionService;
use App\Services\Communication\InboxQueryService;
use App\Services\Communication\UnreadService;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CommunicationCenterPhase2Test extends TestCase
{
    use RefreshDatabase;

    public function test_inbox_lists_conversations_with_customer_channel_and_preview(): void
    {
        [$workspace, $owner] = $this->workspaceWithWhatsApp();
        $conversation = $this->makeConversation($workspace, [
            'name' => 'نورة العميل',
            'phone' => '0501112233',
            'email' => 'noura@example.test',
            'channel' => 'whatsapp',
            'external_id' => '966501112233',
            'content' => 'آخر رسالة واردة',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.communication.inbox'))
            ->assertOk()
            ->assertSee('Communication Center')
            ->assertSee('نورة العميل')
            ->assertSee('آخر رسالة واردة')
            ->assertSee('WhatsApp')
            ->assertSee('Assigned to me')
            ->assertSee('All Channels');

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.conversations.index'))
            ->assertOk()
            ->assertSee('نورة العميل');

        $this->assertSame($conversation->id, Conversation::query()->where('external_id', '966501112233')->value('id'));
    }

    public function test_inbox_filters_search_and_pagination_use_backend_queries(): void
    {
        [$workspace, $owner] = $this->workspaceWithWhatsApp();
        $agent = $this->attachAgent($workspace, 'agent-b@example.test');
        $team = CommunicationTeam::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Support Desk',
        ]);
        $team->members()->sync([$owner->id => ['workspace_id' => $workspace->id]]);

        $mine = $this->makeConversation($workspace, [
            'name' => 'Mine Customer',
            'phone' => '0551000001',
            'email' => 'mine@example.test',
            'assigned_user_id' => $owner->id,
            'assigned_team_id' => $team->id,
            'priority' => 'urgent',
            'content' => 'assigned to owner',
        ]);
        $unread = $this->makeConversation($workspace, [
            'name' => 'Unread Customer',
            'phone' => '0551000002',
            'email' => 'unread@example.test',
            'content' => 'still unread',
        ]);
        $closed = $this->makeConversation($workspace, [
            'name' => 'Closed Customer',
            'phone' => '0551000003',
            'email' => 'closed@example.test',
            'status' => 'closed',
            'content' => 'closed thread',
        ]);
        $this->makeConversation($workspace, [
            'name' => 'Instagram Customer',
            'phone' => '0551000004',
            'channel' => 'manual',
            'metadata' => ['channel_source' => 'instagram'],
            'content' => 'Hello from Instagram',
        ]);

        for ($i = 0; $i < 18; $i++) {
            $this->makeConversation($workspace, [
                'name' => 'Paged Customer '.$i,
                'phone' => '05900000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'content' => 'page filler '.$i,
                'last_message_at' => now()->subDays(10 + $i),
            ]);
        }

        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($owner)->withSession($session)
            ->get(route('workspace.communication.inbox', ['filter' => 'assigned_to_me']))
            ->assertOk()
            ->assertSee('Mine Customer')
            ->assertDontSee('Unread Customer');

        $this->actingAs($owner)->withSession($session)
            ->get(route('workspace.communication.inbox', ['filter' => 'unassigned']))
            ->assertOk()
            ->assertSee('Unread Customer')
            ->assertDontSee('Mine Customer');

        $this->actingAs($owner)->withSession($session)
            ->get(route('workspace.communication.inbox', ['filter' => 'my_team']))
            ->assertOk()
            ->assertSee('Mine Customer');

        $this->actingAs($owner)->withSession($session)
            ->get(route('workspace.communication.inbox', ['filter' => 'high_priority']))
            ->assertOk()
            ->assertSee('Mine Customer')
            ->assertDontSee('Closed Customer');

        $this->actingAs($owner)->withSession($session)
            ->get(route('workspace.communication.inbox', ['filter' => 'closed']))
            ->assertOk()
            ->assertSee('Closed Customer')
            ->assertDontSee('Mine Customer');

        $this->actingAs($owner)->withSession($session)
            ->get(route('workspace.communication.inbox', ['filter' => 'open']))
            ->assertOk()
            ->assertSee('Mine Customer')
            ->assertDontSee('Closed Customer');

        $this->actingAs($owner)->withSession($session)
            ->get(route('workspace.communication.inbox', ['filter' => 'unread']))
            ->assertOk()
            ->assertSee('Unread Customer');

        $this->actingAs($owner)->withSession($session)
            ->get(route('workspace.communication.inbox', ['channel' => 'instagram']))
            ->assertOk()
            ->assertSee('Instagram Customer')
            ->assertSee('Instagram')
            ->assertDontSee('Mine Customer');

        $this->actingAs($owner)->withSession($session)
            ->get(route('workspace.communication.inbox', ['search' => 'Mine Customer']))
            ->assertOk()
            ->assertSee('Mine Customer')
            ->assertDontSee('Unread Customer');

        $this->actingAs($owner)->withSession($session)
            ->get(route('workspace.communication.inbox', ['search' => '0551000002']))
            ->assertOk()
            ->assertSee('Unread Customer');

        $this->actingAs($owner)->withSession($session)
            ->get(route('workspace.communication.inbox', ['search' => 'closed@example.test']))
            ->assertOk()
            ->assertSee('Closed Customer');

        $this->actingAs($owner)->withSession($session)
            ->get(route('workspace.communication.inbox', ['search' => (string) $mine->id]))
            ->assertOk()
            ->assertSee('Mine Customer');

        $this->actingAs($owner)->withSession($session)
            ->get(route('workspace.communication.inbox', ['search' => $mine->external_id]))
            ->assertOk()
            ->assertSee('Mine Customer');

        $pageOne = $this->actingAs($owner)->withSession($session)
            ->get(route('workspace.communication.inbox'));
        $pageOne->assertOk();
        $this->assertSame(InboxQueryService::PAGE_SIZE, $pageOne->viewData('conversations')->count());
        $this->assertGreaterThan(InboxQueryService::PAGE_SIZE, $pageOne->viewData('conversations')->total());

        $this->actingAs($agent)->withSession($session)
            ->get(route('workspace.communication.inbox', ['filter' => 'assigned_to_me']))
            ->assertOk()
            ->assertDontSee('Mine Customer');
    }

    public function test_opening_conversation_shows_thread_and_marks_unread_for_current_user_only(): void
    {
        [$workspace, $owner] = $this->workspaceWithWhatsApp();
        $agent = $this->attachAgent($workspace, 'reader-b@example.test');
        $conversation = $this->makeConversation($workspace, [
            'name' => 'Thread Customer',
            'content' => 'رسالة للفتح',
        ]);

        $unread = app(UnreadService::class);
        $this->assertSame(1, $unread->unreadCountForConversation($conversation, $owner));
        $this->assertSame(1, $unread->unreadCountForConversation($conversation, $agent));

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.communication.inbox', ['conversation' => $conversation->id]))
            ->assertOk()
            ->assertSee('Thread Customer')
            ->assertSee('رسالة للفتح')
            ->assertSee('Customer')
            ->assertSee('Channel identities')
            ->assertSee('Assignment');

        $this->assertSame(0, $unread->unreadCountForConversation($conversation->fresh(), $owner));
        $this->assertSame(1, $unread->unreadCountForConversation($conversation->fresh(), $agent));
    }

    public function test_whatsapp_send_failed_send_retry_and_internal_notes(): void
    {
        config()->set('services.whatsapp.token', 'test-wa-token');
        config()->set('whatsapp.api_version', 'v20.0');

        [$workspace, $owner] = $this->workspaceWithWhatsApp();
        $this->enableWorkspaceFeature($workspace, 'whatsapp');
        $this->enableWorkspaceFeature($workspace, 'conversations');
        app(WorkspaceContext::class)->set($workspace);
        $phone = $this->createPhone($workspace, 'pnid_inbox', 'waba_inbox');
        $connection = app(ChannelConnectionService::class)->syncWhatsAppPhone($phone);
        $conversation = $this->makeConversation($workspace, [
            'name' => 'WA Customer',
            'phone' => '966500000010',
            'channel' => 'whatsapp',
            'channel_connection_id' => $connection->id,
            'external_id' => '966500000010',
            'metadata' => ['phone_number_id' => 'pnid_inbox', 'channel_source' => 'whatsapp'],
            'content' => 'وارد واتساب',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push(['messages' => [['id' => 'wamid.INBOX.1']]], 200)
                ->push(['error' => ['message' => 'Graph boom']], 400)
                ->push(['messages' => [['id' => 'wamid.RETRY.1']]], 200),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.communication.inbox.messages.store', $conversation), [
                'direction' => 'outbound',
                'content' => 'رد واتساب',
            ])
            ->assertRedirect();

        $sent = Message::withoutGlobalScopes()
            ->where('conversation_id', $conversation->id)
            ->where('content', 'رد واتساب')
            ->first();
        $this->assertNotNull($sent);
        $this->assertSame(Message::DELIVERY_SENT, $sent->delivery_status);
        $this->assertNotSame(Message::DELIVERY_FAILED, $sent->delivery_status);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.communication.inbox.messages.store', $conversation), [
                'direction' => 'outbound',
                'content' => 'رد فاشل',
            ])
            ->assertRedirect();

        $failed = Message::withoutGlobalScopes()
            ->where('conversation_id', $conversation->id)
            ->where('content', 'رد فاشل')
            ->first();
        $this->assertSame(Message::DELIVERY_FAILED, $failed->delivery_status);
        $this->assertNotSame(Message::DELIVERY_SENT, $failed->delivery_status);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.communication.inbox', ['conversation' => $conversation->id]))
            ->assertOk()
            ->assertSee('Failed')
            ->assertSee('Retry');

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.communication.inbox.messages.retry', [$conversation, $failed]))
            ->assertRedirect();

        $retried = Message::withoutGlobalScopes()
            ->where('conversation_id', $conversation->id)
            ->where('content', 'رد فاشل')
            ->orderByDesc('id')
            ->first();
        $this->assertSame(Message::DELIVERY_SENT, $retried->delivery_status);
        $this->assertSame(Message::DELIVERY_FAILED, $failed->fresh()->delivery_status);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.communication.inbox.messages.store', $conversation), [
                'direction' => 'internal_note',
                'content' => 'ملاحظة داخلية فقط',
            ])
            ->assertRedirect();

        $note = Message::withoutGlobalScopes()
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'internal_note')
            ->first();
        $this->assertNotNull($note);
        $this->assertSame(Message::DELIVERY_NA, $note->delivery_status);
        $this->assertSame('ملاحظة داخلية فقط', $note->content);
    }

    public function test_coming_soon_and_email_channels_cannot_send(): void
    {
        [$workspace, $owner] = $this->workspaceWithWhatsApp();
        $instagram = $this->makeConversation($workspace, [
            'name' => 'IG Customer',
            'channel' => 'instagram',
            'content' => 'ig inbound',
        ]);
        $email = $this->makeConversation($workspace, [
            'name' => 'Email Customer',
            'channel' => 'email',
            'content' => 'email inbound',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.communication.inbox', ['conversation' => $instagram->id]))
            ->assertOk()
            ->assertSee('Coming Soon')
            ->assertDontSee('رسالة للعميل');

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.communication.inbox.messages.store', $instagram), [
                'direction' => 'outbound',
                'content' => 'should not send',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('messages', [
            'conversation_id' => $instagram->id,
            'content' => 'should not send',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.communication.inbox', ['conversation' => $email->id]))
            ->assertOk()
            ->assertSee('Email integration coming soon');

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.communication.inbox.messages.store', $email), [
                'direction' => 'outbound',
                'content' => 'fake email send',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('messages', [
            'conversation_id' => $email->id,
            'content' => 'fake email send',
        ]);
    }

    public function test_assignment_priority_permissions_and_tenant_isolation(): void
    {
        [$workspaceA, $ownerA] = $this->workspaceWithWhatsApp('phase2-a@example.test', 'waba_p2a');
        [$workspaceB, $ownerB] = $this->workspaceWithWhatsApp('phase2-b@example.test', 'waba_p2b');
        $member = $this->attachAgent($workspaceA, 'viewer@example.test', 'member');
        $agent = $this->attachAgent($workspaceA, 'assignee@example.test');

        $team = CommunicationTeam::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceA->id,
            'name' => 'Alpha Team',
        ]);
        $team->members()->sync([
            $ownerA->id => ['workspace_id' => $workspaceA->id],
            $agent->id => ['workspace_id' => $workspaceA->id],
        ]);

        $conversation = $this->makeConversation($workspaceA, [
            'name' => 'Iso Customer A',
            'content' => 'secret from A',
        ]);

        $this->actingAs($ownerA)
            ->withSession(['current_workspace_id' => $workspaceA->id])
            ->post(route('workspace.communication.inbox.assign', $conversation), [
                'assigned_team_id' => $team->id,
                'assigned_user_id' => $agent->id,
            ])
            ->assertRedirect();

        $this->assertSame($agent->id, $conversation->fresh()->assigned_user_id);
        $this->assertSame($team->id, $conversation->fresh()->assigned_team_id);

        $this->actingAs($ownerA)
            ->withSession(['current_workspace_id' => $workspaceA->id])
            ->post(route('workspace.communication.inbox.assign', $conversation), [
                'assigned_team_id' => $team->id,
                'assigned_user_id' => $ownerA->id,
            ])
            ->assertRedirect();
        $this->assertSame($ownerA->id, $conversation->fresh()->assigned_user_id);

        $this->actingAs($ownerA)
            ->withSession(['current_workspace_id' => $workspaceA->id])
            ->put(route('workspace.communication.inbox.priority', $conversation), [
                'priority' => 'urgent',
            ])
            ->assertRedirect();
        $this->assertSame('urgent', $conversation->fresh()->priority);

        $this->actingAs($ownerA)
            ->withSession(['current_workspace_id' => $workspaceA->id])
            ->post(route('workspace.communication.inbox.unassign', $conversation))
            ->assertRedirect();
        $this->assertNull($conversation->fresh()->assigned_user_id);
        $this->assertNull($conversation->fresh()->assigned_team_id);

        $this->actingAs($member)
            ->withSession(['current_workspace_id' => $workspaceA->id])
            ->get(route('workspace.communication.inbox', ['conversation' => $conversation->id]))
            ->assertOk()
            ->assertDontSee('Assignment');

        $this->actingAs($member)
            ->withSession(['current_workspace_id' => $workspaceA->id])
            ->post(route('workspace.communication.inbox.assign', $conversation), [
                'assigned_user_id' => $agent->id,
            ])
            ->assertForbidden();

        $this->actingAs($member)
            ->withSession(['current_workspace_id' => $workspaceA->id])
            ->post(route('workspace.communication.inbox.messages.store', $conversation), [
                'direction' => 'internal_note',
                'content' => 'member note',
            ])
            ->assertForbidden();

        $this->actingAs($ownerB)
            ->withSession(['current_workspace_id' => $workspaceB->id])
            ->get(route('workspace.communication.inbox', ['conversation' => $conversation->id]))
            ->assertOk()
            ->assertDontSee('Iso Customer A')
            ->assertDontSee('secret from A');

        $this->actingAs($ownerB)
            ->withSession(['current_workspace_id' => $workspaceB->id])
            ->post(route('workspace.communication.inbox.assign', $conversation), [
                'assigned_user_id' => $ownerB->id,
            ])
            ->assertNotFound();

        $this->actingAs($ownerB)
            ->withSession(['current_workspace_id' => $workspaceB->id])
            ->post(route('workspace.communication.inbox.messages.store', $conversation), [
                'direction' => 'outbound',
                'content' => 'cross tenant',
            ])
            ->assertNotFound();
    }

    public function test_teams_and_connections_screens(): void
    {
        [$workspace, $owner] = $this->workspaceWithWhatsApp();
        $agent = $this->attachAgent($workspace, 'team-member@example.test');
        $phone = $this->createPhone($workspace, 'pnid_conn', 'waba_conn');
        app(ChannelConnectionService::class)->syncWhatsAppPhone($phone);
        EmailAccount::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Support Mail',
            'email' => 'support@example.test',
            'password' => 'secret',
            'imap_host' => 'imap.example.test',
            'imap_port' => 993,
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => 587,
            'brand_color' => '#06C2A4',
            'aliases' => ['Support'],
        ]);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.communication.teams.index'))
            ->assertOk()
            ->assertSee('No team');

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.communication.teams.store'), [
                'name' => 'Night Shift',
                'user_ids' => [$owner->id],
            ])
            ->assertRedirect();

        $team = CommunicationTeam::withoutGlobalScopes()->where('name', 'Night Shift')->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.communication.teams.members.store', $team), [
                'user_id' => $agent->id,
            ])
            ->assertRedirect();
        $this->assertTrue($team->fresh()->members->contains('id', $agent->id));

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->delete(route('workspace.communication.teams.members.destroy', [$team, $agent->id]))
            ->assertRedirect();
        $this->assertFalse($team->fresh()->members->contains('id', $agent->id));

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.communication.teams.index'))
            ->assertOk()
            ->assertSee('Night Shift')
            ->assertSee('active conversations');

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.communication.connections.index'))
            ->assertOk()
            ->assertSee('WhatsApp')
            ->assertSee('Coming Soon')
            ->assertSee('Instagram')
            ->assertSee('Messenger')
            ->assertSee('Web Chat')
            ->assertSee('Not wired');

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.communication.templates.index'))
            ->assertOk()
            ->assertSee('Canned Replies');
    }

    /**
     * @return array{0: Workspace, 1: User}
     */
    private function workspaceWithWhatsApp(
        string $email = 'phase2@example.com',
        string $waba = 'waba_phase2',
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
            'code' => 'comm_p2_'.substr(sha1($email.$waba), 0, 8),
            'name' => 'Comm Phase 2',
            'workspace_type' => 'company',
            'billing_period' => 'monthly',
            'currency' => 'SAR',
            'price' => 0,
            'is_active' => true,
            'features' => ['whatsapp', 'ai', 'conversations'],
            'limits' => ['whatsapp_messages' => 100],
            'overage_rules' => [],
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

    private function attachAgent(Workspace $workspace, string $email, string $role = 'agent'): User
    {
        $user = User::factory()->create(['email' => $email]);
        $workspace->users()->attach($user->id, [
            'membership_role' => $role,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $user;
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function makeConversation(Workspace $workspace, array $data): Conversation
    {
        $customer = Customer::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => $data['name'] ?? 'Customer',
            'phone' => $data['phone'] ?? ('050'.substr(str_pad((string) abs(crc32((string) ($data['name'] ?? uniqid()))), 7, '0', STR_PAD_LEFT), 0, 7)),
            'email' => $data['email'] ?? null,
        ]);

        $conversation = Conversation::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'customer_id' => $customer->id,
            'channel' => $data['channel'] ?? 'manual',
            'channel_connection_id' => $data['channel_connection_id'] ?? null,
            'external_id' => $data['external_id'] ?? 'ext-'.$customer->id,
            'status' => $data['status'] ?? 'open',
            'priority' => $data['priority'] ?? 'normal',
            'assigned_team_id' => $data['assigned_team_id'] ?? null,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'last_message_at' => $data['last_message_at'] ?? now(),
            'metadata' => $data['metadata'] ?? [],
        ]);

        Message::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'direction' => 'inbound',
            'message_type' => 'text',
            'content' => $data['content'] ?? 'hello',
            'delivery_status' => Message::DELIVERY_RECEIVED,
        ]);

        return $conversation;
    }
}
