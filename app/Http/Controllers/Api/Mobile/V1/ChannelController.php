<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Api\Mobile\Concerns\ResolvesMobileWorkspace;
use App\Http\Controllers\Api\Mobile\MobileController;
use App\Models\Communication\ChannelConnection;
use App\Services\Communication\ChannelConnectionService;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Http\JsonResponse;

class ChannelController extends MobileController
{
    use ResolvesMobileWorkspace;

    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
        private readonly ChannelConnectionService $channelConnectionService,
    ) {}

    public function index(): JsonResponse
    {
        $workspace = $this->requireWorkspace($this->workspaceContext);
        $catalog = $this->channelConnectionService->catalog($workspace);
        $embeddedSignupReady = filled(config('whatsapp.meta_app_id'))
            && filled(config('whatsapp.embedded_signup_config_id'));

        $channels = collect($catalog)
            ->reject(fn (array $row): bool => $row['key'] === 'manual')
            ->map(function (array $row) use ($embeddedSignupReady): array {
                $manageUrl = match ($row['key']) {
                    'whatsapp' => route('workspace.whatsapp-accounts.index'),
                    'email' => route('workspace.emails.accounts.index'),
                    default => route('workspace.conversations.index', ['channel' => $row['key']]),
                };

                $status = $row['status'] === ChannelConnection::STATUS_COMING_SOON
                    ? 'coming_soon'
                    : ($row['connected'] ? 'connected' : 'disconnected');

                return [
                    'key' => $row['key'],
                    'name' => $row['name'],
                    'icon' => $row['icon'],
                    'connected' => (bool) $row['connected'],
                    'status' => $status,
                    'status_label' => $this->statusLabel($status),
                    'hint' => $row['hint'],
                    'manage_url' => $manageUrl,
                    'can_connect_in_app' => $row['key'] === 'whatsapp' && $embeddedSignupReady && $status !== 'coming_soon',
                ];
            })
            ->values()
            ->all();

        return $this->ok($channels);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'connected' => 'متصل',
            'coming_soon' => 'قريبًا',
            'needs_setup' => 'يحتاج إعداد',
            default => 'غير متصل',
        };
    }
}
