<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\InteractsWithWorkspace;
use App\Jobs\SendEmailMessageJob;
use App\Jobs\SyncEmailInboxJob;
use App\Models\EmailAccount;
use App\Models\EmailAttachment;
use App\Models\EmailMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EmailController extends Controller
{
    use InteractsWithWorkspace;

    public function index(Request $request): View
    {
        $accounts = EmailAccount::query()->latest('id')->get();
        $currentAccount = $this->resolveCurrentAccount($request, $accounts);
        $folder = $this->resolveFolder((string) $request->string('folder', 'inbound'));
        $search = trim((string) $request->string('search', ''));

        $messagesQuery = EmailMessage::query()
            ->with('account')
            ->latest('created_at');
        if ($currentAccount) {
            $messagesQuery->where('email_account_id', $currentAccount->id);
        }
        if ($folder !== 'all') {
            $messagesQuery->where('type', $folder);
        }
        if ($search !== '') {
            $messagesQuery->where(function ($query) use ($search): void {
                $query
                    ->where('subject', 'like', '%'.$search.'%')
                    ->orWhere('sender', 'like', '%'.$search.'%')
                    ->orWhere('recipient', 'like', '%'.$search.'%')
                    ->orWhere('body', 'like', '%'.$search.'%');
            });
        }
        $messages = $messagesQuery->paginate(20)->withQueryString();

        $selectedMessage = null;
        $threadMessages = collect();
        if ($request->filled('message')) {
            $candidate = EmailMessage::query()
                ->with(['account', 'attachments'])
                ->find($request->integer('message'));

            if ($candidate && (! $currentAccount || $candidate->email_account_id === $currentAccount->id)) {
                $selectedMessage = $candidate;
            }

            if ($selectedMessage) {
                $threadKey = $selectedMessage->thread_key ?: ($selectedMessage->in_reply_to ?: $selectedMessage->message_id ?: (string) $selectedMessage->id);
                $threadMessages = EmailMessage::query()
                    ->with(['attachments', 'account'])
                    ->where('email_account_id', $selectedMessage->email_account_id)
                    ->when($threadKey, function ($query) use ($threadKey, $selectedMessage): void {
                        $query->where(function ($threadQuery) use ($threadKey, $selectedMessage): void {
                            $threadQuery
                                ->where('thread_key', $threadKey)
                                ->orWhere('message_id', $threadKey)
                                ->orWhere('in_reply_to', $threadKey)
                                ->orWhere('id', $selectedMessage->id);
                        });
                    })
                    ->orderBy('created_at')
                    ->get();
            }
        }

        return view('workspace.emails.hub', [
            'accounts' => $accounts,
            'currentAccount' => $currentAccount,
            'folder' => $folder,
            'search' => $search,
            'messages' => $messages,
            'selectedMessage' => $selectedMessage,
            'threadMessages' => $threadMessages,
        ]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $workspace = $this->currentWorkspace();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('email_accounts', 'email')->where(fn ($query) => $query->where('workspace_id', $workspace->id)),
            ],
            'password' => ['required', 'string', 'max:255'],
            'imap_host' => ['required', 'string', 'max:255'],
            'imap_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'brand_color' => ['nullable', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'logo' => ['nullable', 'image', 'max:4096'],
            'aliases' => ['nullable', 'string'],
        ]);

        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('workspaces/'.$workspace->id.'/email-logos', 'public');
        }

        EmailAccount::query()->create([
            'workspace_id' => $workspace->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'imap_host' => $validated['imap_host'],
            'imap_port' => $validated['imap_port'],
            'smtp_host' => $validated['smtp_host'],
            'smtp_port' => $validated['smtp_port'],
            'logo_path' => $logoPath,
            'brand_color' => $validated['brand_color'] ?: '#06C2A4',
            'aliases' => $this->parseAliases((string) ($validated['aliases'] ?? '')),
        ]);

        return redirect()->route('workspace.emails.index')->with('success', 'تم حفظ حساب البريد بنجاح.');
    }

    public function updateAccount(Request $request, EmailAccount $emailAccount): RedirectResponse
    {
        $workspace = $this->currentWorkspace();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('email_accounts', 'email')
                    ->where(fn ($query) => $query->where('workspace_id', $workspace->id))
                    ->ignore($emailAccount->id),
            ],
            'password' => ['nullable', 'string', 'max:255'],
            'imap_host' => ['required', 'string', 'max:255'],
            'imap_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'brand_color' => ['nullable', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'logo' => ['nullable', 'image', 'max:4096'],
            'remove_logo' => ['nullable', 'boolean'],
            'aliases' => ['nullable', 'string'],
        ]);

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'imap_host' => $validated['imap_host'],
            'imap_port' => $validated['imap_port'],
            'smtp_host' => $validated['smtp_host'],
            'smtp_port' => $validated['smtp_port'],
            'brand_color' => $validated['brand_color'] ?: '#06C2A4',
            'aliases' => $this->parseAliases((string) ($validated['aliases'] ?? '')),
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = $validated['password'];
        }

        if ($request->boolean('remove_logo') && $emailAccount->logo_path) {
            Storage::disk('public')->delete($emailAccount->logo_path);
            $payload['logo_path'] = null;
        }

        if ($request->hasFile('logo')) {
            if ($emailAccount->logo_path) {
                Storage::disk('public')->delete($emailAccount->logo_path);
            }
            $payload['logo_path'] = $request->file('logo')->store('workspaces/'.$emailAccount->workspace_id.'/email-logos', 'public');
        }

        $emailAccount->update($payload);

        return redirect()->route('workspace.emails.index', ['account_id' => $emailAccount->id])->with('success', 'تم تحديث إعدادات الحساب.');
    }

    public function syncAccount(EmailAccount $emailAccount): RedirectResponse
    {
        SyncEmailInboxJob::dispatch($emailAccount->id)->onQueue('emails');

        return redirect()
            ->route('workspace.emails.index', ['account_id' => $emailAccount->id])
            ->with('success', 'تم جدولة مزامنة البريد في الخلفية.');
    }

    public function sendMessage(Request $request): RedirectResponse
    {
        $workspace = $this->currentWorkspace();
        $validated = $request->validate([
            'email_account_id' => ['required', 'integer', 'exists:email_accounts,id'],
            'recipient' => ['required', 'string', 'max:1000'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'sender_alias' => ['nullable', 'string', 'max:255'],
            'reply_to_message_id' => ['nullable', 'integer', 'exists:email_messages,id'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $emailAccount = EmailAccount::query()->findOrFail($validated['email_account_id']);
        $replyToMessage = null;

        if (! empty($validated['reply_to_message_id'])) {
            $replyToMessage = EmailMessage::query()->findOrFail($validated['reply_to_message_id']);
            if ($replyToMessage->email_account_id !== $emailAccount->id) {
                abort(404);
            }
        }

        $sender = $emailAccount->email;
        if (! empty($validated['sender_alias'])) {
            $sender = trim($validated['sender_alias']).' <'.$emailAccount->email.'>';
        }

        $threadKey = $replyToMessage?->thread_key ?: ($replyToMessage?->message_id ?: Str::uuid()->toString());

        $message = EmailMessage::query()->create([
            'workspace_id' => $workspace->id,
            'email_account_id' => $emailAccount->id,
            'sender' => $sender,
            'recipient' => $validated['recipient'],
            'subject' => $validated['subject'] ?? '(بدون عنوان)',
            'body' => $validated['body'],
            'type' => 'outbound',
            'message_id' => '<'.Str::uuid().'@'.Str::after($emailAccount->email, '@').'>',
            'in_reply_to' => $replyToMessage?->message_id,
            'thread_key' => $threadKey,
        ]);

        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store('workspaces/'.$workspace->id.'/emails/attachments', 'public');

            EmailAttachment::query()->create([
                'message_id' => $message->id,
                'file_path' => $path,
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }

        SendEmailMessageJob::dispatch($message->id)->onQueue('emails');

        return redirect()
            ->route('workspace.emails.index', [
                'account_id' => $emailAccount->id,
                'message' => $message->id,
            ])
            ->with('success', 'تمت جدولة إرسال البريد في الخلفية.');
    }

    public function destroyMessage(Request $request, EmailMessage $emailMessage): RedirectResponse
    {
        $attachmentPaths = $emailMessage->attachments
            ->pluck('file_path')
            ->filter()
            ->values()
            ->all();

        DB::transaction(function () use ($emailMessage): void {
            $emailMessage->attachments()->delete();
            $emailMessage->delete();
        });

        if (! empty($attachmentPaths)) {
            Storage::disk('public')->delete($attachmentPaths);
        }

        return redirect()
            ->route('workspace.emails.index', array_filter([
                'account_id' => $request->integer('account_id') ?: null,
                'folder' => $request->string('folder', 'inbound')->toString(),
                'search' => $request->string('search')->toString() ?: null,
            ]))
            ->with('success', 'تم حذف الرسالة والمرفقات المرتبطة بها بنجاح.');
    }

    private function resolveCurrentAccount(Request $request, Collection $accounts): ?EmailAccount
    {
        if ($request->filled('account_id')) {
            $selected = EmailAccount::query()->find($request->integer('account_id'));
            if ($selected) {
                return $selected;
            }
        }

        return $accounts->first();
    }

    private function resolveFolder(string $folder): string
    {
        return in_array($folder, ['all', 'inbound', 'outbound'], true) ? $folder : 'inbound';
    }

    /**
     * @return array<int, string>
     */
    private function parseAliases(string $aliasesRaw): array
    {
        $aliases = collect(preg_split('/[\r\n,]+/', $aliasesRaw) ?: [])
            ->map(fn (string $alias): string => trim($alias))
            ->filter()
            ->unique()
            ->take(50)
            ->values()
            ->all();

        return $aliases;
    }
}
