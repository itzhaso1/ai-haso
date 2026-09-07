<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\InteractsWithWorkspace;
use App\Services\Communication\ChannelConnectionService;
use App\Services\WhatsApp\WhatsAppEmbeddedSignupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ChannelController extends Controller
{
    use InteractsWithWorkspace;

    public function __construct(
        private readonly ChannelConnectionService $channelConnectionService,
    ) {}

    public function index(): View
    {
        $workspace = $this->currentWorkspace();
        $catalog = $this->channelConnectionService->catalog($workspace);

        $channels = collect($catalog)
            ->reject(fn (array $row): bool => $row['key'] === 'manual')
            ->map(function (array $row): array {
                $manageUrl = match ($row['key']) {
                    'whatsapp' => route('workspace.whatsapp-accounts.index'),
                    'email' => route('workspace.emails.accounts.index'),
                    default => route('workspace.conversations.index', ['channel' => $row['key']]),
                };

                return [
                    'key' => $row['key'],
                    'name' => $row['name'],
                    'icon' => $row['icon'],
                    'connected' => (bool) $row['connected'],
                    'status' => $row['status'],
                    'status_text' => $row['status_text'],
                    'hint' => $row['hint'],
                    'primary_action' => $row['primary_action'],
                    'manage_url' => $manageUrl,
                    'coming_soon' => $row['status'] === 'coming_soon',
                ];
            })
            ->values()
            ->all();

        return view('workspace.channels.index', [
            'channels' => $channels,
            'metaAppId' => (string) config('whatsapp.meta_app_id'),
            'metaConfigId' => (string) config('whatsapp.embedded_signup_config_id'),
            'graphApiVersion' => (string) config('whatsapp.api_version'),
        ]);
    }

    public function connectWhatsApp(Request $request, WhatsAppEmbeddedSignupService $embeddedSignupService): JsonResponse
    {
        $workspace = $this->currentWorkspace();
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:1024'],
            'session_info' => ['nullable', 'array'],
            'session_info.waba_id' => ['nullable', 'string', 'max:191'],
            'session_info.phone_number_id' => ['nullable', 'string', 'max:191'],
            'session_info.business_id' => ['nullable', 'string', 'max:191'],
        ]);

        try {
            $result = $embeddedSignupService->connectWorkspace($workspace, $validated);
            $this->channelConnectionService->syncWhatsApp($workspace);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'WhatsApp connected successfully.',
            'account' => [
                'id' => $result['account']->id,
                'business_account_id' => $result['account']->business_account_id,
                'display_name' => $result['account']->display_name,
                'status' => $result['account']->status,
            ],
            'phone_numbers' => collect($result['account']->phoneNumbers)->map(fn ($phone) => [
                'id' => $phone->id,
                'phone_number_id' => $phone->phone_number_id,
                'display_phone_number' => $phone->display_phone_number,
                'status' => $phone->status,
            ])->values(),
        ]);
    }
}
