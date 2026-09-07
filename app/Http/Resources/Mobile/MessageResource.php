<?php

namespace App\Http\Resources\Mobile;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Message */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'direction' => $this->direction,
            'message_type' => $this->message_type,
            'content' => $this->content,
            'delivery_status' => $this->delivery_status,
            'delivery_error' => $this->delivery_error,
            'ai_generated' => (bool) $this->ai_generated,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ] : null),
            'attachments' => MessageAttachmentResource::collection(
                $this->relationLoaded('attachments') ? $this->attachments : [],
            ),
        ];
    }
}
