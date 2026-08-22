<?php

namespace App\Services\Email;

use App\Mail\WorkspaceBrandedEmail;
use App\Models\EmailMessage;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class WorkspaceEmailSender
{
    public function send(EmailMessage $emailMessage): void
    {
        $emailMessage->loadMissing(['account', 'attachments']);
        $account = $emailMessage->account;

        if (! $account) {
            throw new \RuntimeException('Email account is missing.');
        }

        $transport = new EsmtpTransport(
            $account->smtp_host,
            (int) $account->smtp_port,
            (int) $account->smtp_port === 465 ? true : null
        );
        $transport->setUsername($account->email);
        $transport->setPassword((string) $account->password);

        $symfonyMailer = new SymfonyMailer($transport);
        $mailer = new Mailer('workspace-dynamic-smtp', app('view'), $symfonyMailer, app('events'));

        $recipients = collect(explode(',', (string) $emailMessage->recipient))
            ->map(fn (string $recipient): string => trim($recipient))
            ->filter()
            ->values()
            ->all();

        if (count($recipients) === 0) {
            throw new \RuntimeException('No valid recipient was provided.');
        }

        if (! $emailMessage->message_id) {
            $domain = Str::after($account->email, '@');
            $emailMessage->message_id = '<'.Str::uuid().'@'.$domain.'>';
            $emailMessage->save();
        }

        $mailer->to($recipients)->send(new WorkspaceBrandedEmail($emailMessage, $account));
    }
}
