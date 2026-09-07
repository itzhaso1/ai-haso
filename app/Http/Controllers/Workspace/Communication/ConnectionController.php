<?php

namespace App\Http\Controllers\Workspace\Communication;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\InteractsWithWorkspace;
use App\Models\Communication\ChannelConnection;
use App\Models\Conversation;
use App\Services\Communication\ChannelConnectionService;
use App\Support\Communication\ChannelPresentation;
use Illuminate\View\View;

class ConnectionController extends Controller
{
    use InteractsWithWorkspace;

    public function __construct(
        private readonly ChannelConnectionService $channelConnectionService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Conversation::class);

        $workspace = $this->currentWorkspace();
        $catalog = collect($this->channelConnectionService->catalog($workspace))
            ->reject(fn (array $row): bool => $row['key'] === 'manual')
            ->map(function (array $row): array {
                $connections = collect($row['connections'] ?? []);
                $comingSoon = ($row['status'] ?? '') === ChannelConnection::STATUS_COMING_SOON
                    || ChannelPresentation::isComingSoon((string) $row['key']);

                $manageUrl = match ($row['key']) {
                    'whatsapp' => route('workspace.whatsapp-accounts.index'),
                    'email' => route('workspace.emails.accounts.index'),
                    default => null,
                };

                $statusText = $comingSoon
                    ? 'Coming Soon'
                    : ($row['key'] === 'email' && $connections->isNotEmpty()
                        ? 'Connected / Not wired'
                        : (string) ($row['status_text'] ?? 'Disconnected'));

                return [
                    'key' => $row['key'],
                    'name' => ChannelPresentation::label((string) $row['key']),
                    'status' => $comingSoon ? ChannelConnection::STATUS_COMING_SOON : $row['status'],
                    'status_text' => $statusText,
                    'coming_soon' => $comingSoon,
                    'connected' => ! $comingSoon && (bool) $row['connected'],
                    'hint' => $row['key'] === 'email'
                        ? 'Email Hub remains independent. Conversational send is not wired.'
                        : (string) ($row['hint'] ?? ''),
                    'manage_url' => $manageUrl,
                    'connections' => $connections->map(fn (ChannelConnection $connection): array => [
                        'id' => $connection->id,
                        'display_name' => $connection->display_name,
                        'status' => $connection->status,
                        'external_account_id' => $connection->external_account_id,
                    ])->all(),
                ];
            })
            ->values();

        $connectionCount = ChannelConnection::query()->count();

        return view('workspace.communication.connections.index', [
            'catalog' => $catalog,
            'connectionCount' => $connectionCount,
        ]);
    }
}
