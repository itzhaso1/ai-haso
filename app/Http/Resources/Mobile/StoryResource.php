<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\Story */
class StoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $mediaUrl = null;
        if ($this->media_path) {
            $disk = $this->media_disk ?: 'public';
            $mediaUrl = Storage::disk($disk)->url($this->media_path);
        }

        return [
            'id' => $this->id,
            'type' => $this->type,
            'caption' => $this->caption,
            'body_text' => $this->body_text,
            'background_color' => $this->background_color,
            'media_url' => $mediaUrl,
            'media_mime' => $this->media_mime,
            'media_size' => $this->media_size,
            'thumbnail_url' => $this->thumbnail_path
                ? Storage::disk($this->media_disk ?: 'public')->url($this->thumbnail_path)
                : null,
            'visibility' => $this->visibility,
            'selected_user_ids' => $this->selected_user_ids,
            'hidden_user_ids' => $this->hidden_user_ids,
            'expires_at' => optional($this->expires_at)?->toIso8601String(),
            'views_count' => (int) $this->views_count,
            'status' => $this->status,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'author' => $this->whenLoaded('author', fn () => $this->author ? [
                'id' => $this->author->id,
                'name' => $this->author->name,
                'avatar_path' => $this->author->avatar_path ?? null,
            ] : null),
            'is_mine' => $request->user()
                ? (int) $this->user_id === (int) $request->user()->id
                : false,
        ];
    }
}
