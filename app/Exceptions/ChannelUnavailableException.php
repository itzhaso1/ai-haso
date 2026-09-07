<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChannelUnavailableException extends Exception
{
    public function __construct(
        public readonly string $channel,
        public readonly string $reason = 'unsupported',
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : __('هذه القناة غير متاحة للإرسال حالياً.'));
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $this->getMessage(),
                'error' => 'channel_unavailable',
                'channel' => $this->channel,
                'reason' => $this->reason,
            ], 422);
        }

        return back()->with('error', $this->getMessage());
    }
}
