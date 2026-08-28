<?php

namespace App\Services\Contracts;

use App\Mail\WorkspaceBrandedEmail;
use App\Models\Contract\Contract;
use App\Models\EmailAccount;
use App\Models\EmailAttachment;
use App\Models\EmailMessage;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class ContractEmailService
{
    public function __construct(
        private readonly ContractPdfService $contractPdfService,
    ) {}

    /**
     * @param  array{email_account_id:int,recipient:string,cc?:string|null,subject?:string|null,message?:string|null}  $payload
     */
    public function sendActivationEmail(Contract $contract, array $payload): EmailMessage
    {
        $toRecipients = $this->parseEmails($payload['recipient']);
        if ($toRecipients === []) {
            throw new RuntimeException('يرجى إدخال بريد مستلم صالح.');
        }

        $ccRecipients = $this->parseEmails((string) ($payload['cc'] ?? ''));
        $account = EmailAccount::query()->findOrFail((int) $payload['email_account_id']);

        $message = DB::transaction(function () use ($contract, $payload, $toRecipients, $ccRecipients): EmailMessage {
            $message = EmailMessage::query()->create([
                'workspace_id' => $contract->workspace_id,
                'email_account_id' => (int) $payload['email_account_id'],
                'sender' => '',
                'recipient' => implode(', ', array_values(array_unique(array_merge($toRecipients, $ccRecipients)))),
                'subject' => (string) ($payload['subject'] ?? ('تفعيل العقد '.$contract->contract_number)),
                'body' => (string) ($payload['message'] ?? 'تم تفعيل العقد وإرفاق نسخة PDF.'),
                'type' => 'outbound',
                'delivery_status' => 'sending',
                'message_id' => '<'.Str::uuid().'@contracts.hasem>',
                'thread_key' => Str::uuid()->toString(),
            ]);

            $pdfBinary = $this->contractPdfService->renderBinary($contract);
            $filename = 'contract-'.$contract->contract_number.'.pdf';
            $storedPath = 'workspaces/'.$contract->workspace_id.'/contracts/emails/'.Str::uuid().'_'.$filename;
            Storage::disk('public')->put($storedPath, $pdfBinary);

            EmailAttachment::query()->create([
                'message_id' => $message->id,
                'file_path' => $storedPath,
                'file_type' => 'application/pdf',
                'file_size' => strlen($pdfBinary),
            ]);

            return $message->fresh(['account', 'attachments']);
        });

        try {
            $transport = new EsmtpTransport(
                $account->smtp_host,
                (int) $account->smtp_port,
                (int) $account->smtp_port === 465 ? true : null
            );
            $transport->setUsername($account->email);
            $transport->setPassword((string) $account->password);

            $mailer = new Mailer('workspace-dynamic-smtp', app('view'), $transport, app('events'));
            $mailer->to($toRecipients);
            if ($ccRecipients !== []) {
                $mailer->cc($ccRecipients);
            }
            $mailer->send(new WorkspaceBrandedEmail($message, $account));

            $message->forceFill([
                'sender' => $account->email,
                'delivery_status' => 'sent',
                'delivery_error' => null,
                'delivered_at' => now(),
            ])->save();
        } catch (\Throwable $exception) {
            $message->forceFill([
                'sender' => $account->email,
                'delivery_status' => 'failed',
                'delivery_error' => $exception->getMessage(),
            ])->save();

            throw new RuntimeException('فشل إرسال العقد عبر البريد: '.$exception->getMessage(), previous: $exception);
        }

        return $message->fresh(['attachments']);
    }

    /**
     * @return array<int,string>
     */
    private function parseEmails(string $value): array
    {
        return collect(preg_split('/[\r\n,;]+/', $value) ?: [])
            ->map(fn (string $part): string => strtolower(trim($part)))
            ->filter(fn (string $candidate): bool => filter_var($candidate, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }
}
