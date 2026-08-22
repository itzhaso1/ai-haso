<?php

namespace Tests\Feature\Feature\Email;

use App\Jobs\SendEmailMessageJob;
use App\Jobs\SyncEmailInboxJob;
use App\Models\EmailAccount;
use App\Models\EmailAttachment;
use App\Models\EmailMessage;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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

    public function test_workspace_can_update_email_account_settings(): void
    {
        [$user, $workspace] = $this->createWorkspaceOwner('company');

        Storage::fake('public');
        Storage::disk('public')->put('workspaces/'.$workspace->id.'/email-logos/old-logo.png', 'old');

        $account = EmailAccount::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Primary Account',
            'email' => 'old@company.test',
            'password' => 'old-password',
            'imap_host' => 'imap.old.test',
            'imap_port' => 993,
            'smtp_host' => 'smtp.old.test',
            'smtp_port' => 587,
            'logo_path' => 'workspaces/'.$workspace->id.'/email-logos/old-logo.png',
            'brand_color' => '#06C2A4',
            'aliases' => ['Old Alias'],
        ]);

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->put(route('workspace.emails.accounts.update', $account), [
                'name' => 'Updated Account',
                'email' => 'new@company.test',
                'password' => 'new-password',
                'imap_host' => 'imap.new.test',
                'imap_port' => 995,
                'smtp_host' => 'smtp.new.test',
                'smtp_port' => 465,
                'brand_color' => '#112233',
                'aliases' => "Support Team\nSales Team",
                'remove_logo' => 1,
            ])
            ->assertRedirect();

        $account->refresh();
        $this->assertSame('Updated Account', $account->name);
        $this->assertSame('new@company.test', $account->email);
        $this->assertSame('imap.new.test', $account->imap_host);
        $this->assertSame(995, $account->imap_port);
        $this->assertSame('smtp.new.test', $account->smtp_host);
        $this->assertSame(465, $account->smtp_port);
        $this->assertSame('#112233', $account->brand_color);
        $this->assertSame(['Support Team', 'Sales Team'], $account->aliases);
        $this->assertNull($account->logo_path);
        Storage::disk('public')->assertMissing('workspaces/'.$workspace->id.'/email-logos/old-logo.png');
    }

    public function test_workspace_can_delete_message_and_attachment_files(): void
    {
        [$user, $workspace] = $this->createWorkspaceOwner('company');
        Storage::fake('public');

        $account = EmailAccount::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Company Mail',
            'email' => 'mail@company.test',
            'password' => 'secret',
            'imap_host' => 'imap.company.test',
            'imap_port' => 993,
            'smtp_host' => 'smtp.company.test',
            'smtp_port' => 587,
            'brand_color' => '#06C2A4',
            'aliases' => ['Support'],
        ]);

        $message = EmailMessage::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'email_account_id' => $account->id,
            'sender' => 'agent@company.test',
            'recipient' => 'customer@example.com',
            'subject' => 'Message for delete',
            'body' => 'Body text.',
            'type' => 'inbound',
            'message_id' => 'msg-delete-1',
            'thread_key' => 'msg-delete-1',
        ]);

        $attachmentPath = 'workspaces/'.$workspace->id.'/emails/attachments/sample.txt';
        Storage::disk('public')->put($attachmentPath, 'attachment-content');
        EmailAttachment::query()->create([
            'message_id' => $message->id,
            'file_path' => $attachmentPath,
            'file_type' => 'text/plain',
            'file_size' => 18,
        ]);

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->delete(route('workspace.emails.messages.destroy', $message), [
                'account_id' => $account->id,
                'folder' => 'inbound',
            ])
            ->assertRedirect(route('workspace.emails.index', [
                'account_id' => $account->id,
                'folder' => 'inbound',
            ]));

        $this->assertDatabaseMissing('email_messages', ['id' => $message->id]);
        $this->assertDatabaseMissing('email_attachments', ['message_id' => $message->id]);
        Storage::disk('public')->assertMissing($attachmentPath);
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
