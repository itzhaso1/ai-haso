<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_assistant_returns_a_helpful_reply(): void
    {
        $response = $this->postJson(route('assistant.chat'), [
            'message' => 'كيف أسجل الدخول؟',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['data' => ['reply']]);

        $this->assertNotSame('', trim((string) $response->json('data.reply')));
    }
}
