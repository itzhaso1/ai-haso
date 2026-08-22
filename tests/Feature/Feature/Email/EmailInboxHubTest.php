<?php

namespace Tests\Feature\Feature\Email;

use App\Jobs\SendEmailMessageJob;
use App\Jobs\SyncEmailInboxJob;
use App\Models\EmailAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailInboxHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_can_create_email_account_with_isolation(): void
    {
        [$userA, $workspaceA] = $this->createWorkspaceOwner('company');
        [, $workspaceB] = $this->createWorkspaceOwner('store');

        EmailAccount::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceB->id,
            'name' => 'Other Workspace Mail',
            'email' => 'other@workspace-b.test',
            'password' => 'secret',
            'imap_host' => 'imap.other.test',
            'imap_port' => 993,
            'smtp_host' => 'smtp.other.test',
            'smtp_port' => 587,
            'brand_color' => '#000000',
            'aliases' => ['Other'],
        ]);

        $this->actingAs($userA)
            ->withSession(['current_workspace_id' => $workspaceA->id])
            ->post(route('workspace.emails.accounts.store'), [
                'name' => 'Support Mail',
                'email' => 'support@workspace-a.test',
                'password' => 'secret',
                'imap_host' => 'imap.workspace-a.test',
                'imap_port' => 993,
                'smtp_host' => 'smtp.workspace-a.test',
                'smtp_port' => 587,
                'brand_color' => '#06C2A4',
                'aliases' => "Support\nSales",
            ])
            ->assertRedirect(route('workspace.emails.index'));

        $this->assertDatabaseHas('email_accounts', [
            'workspace_id' => $workspaceA->id,
            'email' => 'support@workspace-a.test',
        ]);

        $otherAccount = EmailAccount::withoutGlobalScopes()
            ->where('workspace_id', $workspaceB->id)
            ->firstOrFail();

        // A member of workspace A cannot trigger sync for account in workspace B.
        $this->actingAs($userA)
            ->withSession(['current_workspace_id' => $workspaceA->id])
            ->post(route('workspace.emails.accounts.sync', $otherAccount))
            ->assertNotFound();
    }

    public function test_sending_email_dispatches_background_job(): void
    {
        Queue::fake();

        [$user, $workspace] = $this->createWorkspaceOwner('company');
        $account = EmailAccount::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Company Mail',
            'email' => 'company@mail.test',
            'password' => 'secret',
            'imap_host' => 'imap.mail.test',
            'imap_port' => 993,
            'smtp_host' => 'smtp.mail.test',
            'smtp_port' => 587,
            'brand_color' => '#06C2A4',
            'aliases' => ['Support'],
        ]);

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.emails.messages.send'), [
                'email_account_id' => $account->id,
                'recipient' => 'customer@example.com',
                'subject' => 'Welcome',
                'body' => 'Hello from workspace mail.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('email_messages', [
            'workspace_id' => $workspace->id,
            'email_account_id' => $account->id,
            'type' => 'outbound',
            'recipient' => 'customer@example.com',
        ]);

        Queue::assertPushed(SendEmailMessageJob::class);
        Queue::assertNotPushed(SyncEmailInboxJob::class);
    }

    /**
     * @return array{0: User, 1: Workspace}
     */
    private function createWorkspaceOwner(string $workspaceType): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $user->id,
            'type' => $workspaceType,
        ]);

        $workspace->users()->attach($user->id, [
            'membership_role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return [$user, $workspace];
    }
}
