<?php

namespace Tests\Feature\Email;

use App\Models\EmailLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResendWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_resend_webhook_endpoint_records_event_and_updates_log_status(): void
    {
        $log = EmailLog::query()->create([
            'provider' => 'resend',
            'template' => 'general_notification',
            'recipient' => 'webhook@example.com',
            'subject' => 'Webhook Subject',
            'status' => 'pending',
            'provider_message_id' => 'resend-msg-123',
        ]);

        $response = $this->postJson(route('webhooks.resend'), [
            'type' => 'email.delivered',
            'data' => [
                'email_id' => 'resend-msg-123',
            ],
        ]);

        $response->assertAccepted()
            ->assertJson([
                'ok' => true,
            ]);

        $this->assertDatabaseHas('email_webhook_events', [
            'provider' => 'resend',
            'event_type' => 'email.delivered',
            'provider_message_id' => 'resend-msg-123',
            'email_log_id' => $log->id,
        ]);

        $this->assertDatabaseHas('email_logs', [
            'id' => $log->id,
            'status' => 'sent',
        ]);
    }
}
