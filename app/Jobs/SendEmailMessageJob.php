<?php

namespace App\Jobs;

use App\Models\EmailMessage;
use App\Services\Email\WorkspaceEmailSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendEmailMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public readonly int $emailMessageId,
    ) {}

    public function handle(WorkspaceEmailSender $workspaceEmailSender): void
    {
        $emailMessage = EmailMessage::withoutGlobalScopes()
            ->with(['account', 'attachments'])
            ->find($this->emailMessageId);

        if (! $emailMessage || $emailMessage->type !== 'outbound') {
            return;
        }

        try {
            $workspaceEmailSender->send($emailMessage);
        } catch (\Throwable $exception) {
            Log::error('email-send-job-failed', [
                'email_message_id' => $this->emailMessageId,
                'workspace_id' => $emailMessage->workspace_id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
