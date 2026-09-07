<?php

namespace Tests\Feature\Mobile;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileConversationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_list_send_read_and_idempotent_message(): void
    {
        $this->seed(FoundationSeeder::class);
        [$user, $workspace, $token] = $this->authMember();

        $customer = Customer::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'عميل تجريبي',
            'phone' => '+966500000099',
        ]);

        $conversation = Conversation::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'customer_id' => $customer->id,
            'channel' => 'whatsapp',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        Message::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'direction' => 'inbound',
            'message_type' => 'text',
            'content' => 'مرحبا',
        ]);

        $list = $this->withToken($token)
            ->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->getJson('/api/mobile/v1/conversations');
        $this->assertTrue($list->isSuccessful(), (string) $list->getContent());
        $list->assertJsonPath('success', true);

        $send = $this->withToken($token)
            ->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->withHeader('Idempotency-Key', 'msg-key-1')
            ->postJson("/api/mobile/v1/conversations/{$conversation->id}/messages", [
                'content' => 'رد من حاسم',
                'idempotency_key' => 'msg-key-1',
            ])
            ->assertCreated();

        $messageId = $send->json('data.id');

        $dup = $this->withToken($token)
            ->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->withHeader('Idempotency-Key', 'msg-key-1')
            ->postJson("/api/mobile/v1/conversations/{$conversation->id}/messages", [
                'content' => 'رد من حاسم',
                'idempotency_key' => 'msg-key-1',
            ])
            ->assertCreated();

        $this->assertSame($messageId, $dup->json('data.id'));

        $this->withToken($token)
            ->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->postJson("/api/mobile/v1/conversations/{$conversation->id}/read")
            ->assertOk();

        $this->withToken($token)
            ->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->postJson("/api/mobile/v1/conversations/{$conversation->id}/mute", ['muted' => true])
            ->assertOk();

        $this->withToken($token)
            ->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->getJson("/api/mobile/v1/conversations/{$conversation->id}/messages")
            ->assertOk();

        $suggest = $this->withToken($token)
            ->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->postJson('/api/mobile/v1/ai/suggest-reply', [
                'conversation_id' => $conversation->id,
                'content' => 'مرحبا',
            ]);
        $this->assertNotSame(404, $suggest->status());

        $summarize = $this->withToken($token)
            ->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->postJson('/api/mobile/v1/ai/summarize-conversation', [
                'conversation_id' => $conversation->id,
            ]);
        $this->assertNotSame(404, $summarize->status());

        $this->withToken($token)
            ->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->getJson('/api/mobile/v1/unread')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withToken($token)
            ->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->getJson('/api/mobile/v1/home')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_tenant_isolation_blocks_foreign_conversation(): void
    {
        $this->seed(FoundationSeeder::class);
        [$userA, $workspaceA, $tokenA] = $this->authMember('a@example.com');
        [$userB, $workspaceB] = $this->makeMember('b@example.com');

        $conversationB = Conversation::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceB->id,
            'channel' => 'web',
            'status' => 'open',
        ]);

        $response = $this->withToken($tokenA)
            ->withHeader('X-Workspace-Id', (string) $workspaceA->id)
            ->getJson("/api/mobile/v1/conversations/{$conversationB->id}");

        $this->assertContains($response->status(), [403, 404]);
        $this->assertNotEquals(200, $response->status());
    }

    public function test_archive_is_per_user_and_does_not_change_conversation_status(): void
    {
        $this->seed(FoundationSeeder::class);
        [$userA, $workspace, $tokenA] = $this->authMember('arch-a@example.com');
        $userB = User::factory()->create([
            'email' => 'arch-b@example.com',
            'password' => 'password',
        ]);
        $workspace->users()->attach($userB->id, [
            'membership_role' => 'agent',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->flushHeaders();
        $tokenB = $this->postJson('/api/mobile/v1/auth/login', [
            'email' => $userB->email,
            'password' => 'password',
            'workspace_id' => $workspace->id,
        ])->assertOk()->json('data.token');
        $this->assertNotEmpty($tokenB);

        $conversation = Conversation::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'channel' => 'whatsapp',
            'status' => 'open',
            'last_message_at' => now(),
        ]);
        Message::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'message_type' => 'text',
            'content' => 'archive me',
            'delivery_status' => 'received',
        ]);

        $this->withToken($tokenA)
            ->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->postJson("/api/mobile/v1/conversations/{$conversation->id}/archive", [
                'archived' => true,
            ])
            ->assertOk();

        $this->assertSame('open', $conversation->fresh()->status);
        $this->assertDatabaseHas('conversation_user_states', [
            'conversation_id' => $conversation->id,
            'user_id' => $userA->id,
        ]);
        $this->assertDatabaseMissing('conversation_user_states', [
            'conversation_id' => $conversation->id,
            'user_id' => $userB->id,
        ]);

        app(\App\Support\Tenancy\WorkspaceContext::class)->set($workspace);
        $inbox = app(\App\Services\Mobile\ConversationInboxService::class);
        $forB = collect($inbox->listForUser($userB, $workspace, ['filter' => 'all'])->items())->pluck('id')->all();
        $forA = collect($inbox->listForUser($userA, $workspace, ['filter' => 'all'])->items())->pluck('id')->all();
        $archivedForA = collect($inbox->listForUser($userA, $workspace, ['filter' => 'archived'])->items())->pluck('id')->all();

        $this->assertNotContains($conversation->id, $forA);
        $this->assertContains($conversation->id, $forB);
        $this->assertContains($conversation->id, $archivedForA);

        $this->flushHeaders();
        $httpA = $this->actingAs($userA, 'sanctum')
            ->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->getJson('/api/mobile/v1/conversations')
            ->assertOk();
        $this->flushHeaders();
        $httpB = $this->actingAs($userB, 'sanctum')
            ->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->getJson('/api/mobile/v1/conversations')
            ->assertOk();
        $this->flushHeaders();
        $httpArchivedA = $this->actingAs($userA, 'sanctum')
            ->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->getJson('/api/mobile/v1/conversations?filter=archived')
            ->assertOk();

        $idsA = collect($this->extractMobileList($httpA->json('data')))->pluck('id')->map(fn ($id) => (int) $id);
        $idsB = collect($this->extractMobileList($httpB->json('data')))->pluck('id')->map(fn ($id) => (int) $id);
        $archivedA = collect($this->extractMobileList($httpArchivedA->json('data')))->pluck('id')->map(fn ($id) => (int) $id);

        $this->assertFalse($idsA->contains($conversation->id), 'Archiving user still sees conversation in the default inbox.');
        $this->assertTrue($idsB->contains($conversation->id), 'Other workspace members must still see the conversation. ids='.$idsB->implode(','));
        $this->assertTrue($archivedA->contains($conversation->id), 'Archiving user must see the conversation in the archived filter. ids='.$archivedA->implode(','));
    }

    public function test_device_push_token_register_and_revoke(): void
    {
        $this->seed(FoundationSeeder::class);
        [$user, $workspace, $token] = $this->authMember('push@example.com');

        $created = $this->withToken($token)
            ->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->postJson('/api/mobile/v1/devices', [
                'token' => 'fcm-test-token-123',
                'provider' => 'fcm',
                'platform' => 'android',
                'device_name' => 'Pixel',
            ])
            ->assertCreated();

        $deviceId = $created->json('data.id');

        $this->withToken($token)
            ->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->deleteJson("/api/mobile/v1/devices/{$deviceId}")
            ->assertOk();
    }

    /**
     * @return array{0:User,1:Workspace,2:string}
     */
    private function authMember(string $email = 'conv@example.com'): array
    {
        [$user, $workspace] = $this->makeMember($email);
        $login = $this->postJson('/api/mobile/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'workspace_id' => $workspace->id,
        ])->assertOk();

        return [$user, $workspace, $login->json('data.token')];
    }

    /**
     * @return array{0:User,1:Workspace}
     */
    private function makeMember(string $email): array
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => 'password',
        ]);
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $user->id,
            'type' => 'company',
        ]);
        $workspace->users()->attach($user->id, [
            'membership_role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return [$user, $workspace];
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function mobileConversationIds(string $token, int $workspaceId, string $filter = 'all'): \Illuminate\Support\Collection
    {
        $response = $this->withToken($token)
            ->withHeader('X-Workspace-Id', (string) $workspaceId)
            ->getJson('/api/mobile/v1/conversations?filter='.$filter)
            ->assertOk();

        $payload = $response->json('data');
        $items = $this->extractMobileList($payload);

        return collect($items)->pluck('id')->map(fn ($id) => (int) $id);
    }

    /**
     * @param  mixed  $node
     * @return list<array<string, mixed>>
     */
    private function extractMobileList(mixed $node): array
    {
        if (! is_array($node)) {
            return [];
        }

        if ($node !== [] && array_is_list($node) && isset($node[0]) && is_array($node[0]) && array_key_exists('id', $node[0])) {
            return $node;
        }

        if (array_key_exists('data', $node)) {
            return $this->extractMobileList($node['data']);
        }

        return [];
    }
}
