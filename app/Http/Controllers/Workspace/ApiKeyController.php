<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\InteractsWithWorkspace;
use App\Models\ApiKey;
use App\Services\ApiKey\ApiKeyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiKeyController extends Controller
{
    use InteractsWithWorkspace;

    public function __construct(
        private readonly ApiKeyService $apiKeyService,
    ) {}

    public function index(Request $request): View
    {
        $workspace = $this->currentWorkspace();
        $keys = $this->apiKeyService->list($workspace);

        return view('workspace.api-keys.index', [
            'keys' => $keys,
            'plainText' => $request->session()->pull('api_key_plain_text'),
            'createdKeyName' => $request->session()->pull('api_key_created_name'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $result = $this->apiKeyService->create(
            workspace: $this->currentWorkspace(),
            name: $validated['name'],
            user: $request->user(),
        );

        return redirect()
            ->route('workspace.api-keys.index')
            ->with('success', 'تم إنشاء مفتاح API. انسخه الآن — لن يظهر مرة أخرى.')
            ->with('api_key_plain_text', $result['plain_text'])
            ->with('api_key_created_name', $result['api_key']->name);
    }

    public function revoke(Request $request, ApiKey $apiKey): RedirectResponse
    {
        $this->ensureSameWorkspace($apiKey);
        $this->apiKeyService->revoke($apiKey);

        return redirect()
            ->route('workspace.api-keys.index')
            ->with('success', 'تم إلغاء المفتاح.');
    }

    public function regenerate(Request $request, ApiKey $apiKey): RedirectResponse
    {
        $this->ensureSameWorkspace($apiKey);

        $result = $this->apiKeyService->regenerate($apiKey, $request->user());

        return redirect()
            ->route('workspace.api-keys.index')
            ->with('success', 'تم إعادة توليد المفتاح. انسخه الآن — لن يظهر مرة أخرى.')
            ->with('api_key_plain_text', $result['plain_text'])
            ->with('api_key_created_name', $result['api_key']->name);
    }

    private function ensureSameWorkspace(ApiKey $apiKey): void
    {
        abort_unless((int) $apiKey->workspace_id === (int) $this->currentWorkspace()->id, 404);
    }
}
