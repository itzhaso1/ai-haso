<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\InteractsWithWorkspace;
use App\Models\Communication\CommunicationTeam;
use App\Models\Conversation;
use App\Services\Communication\CommunicationTeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunicationTeamController extends Controller
{
    use InteractsWithWorkspace;

    public function __construct(
        private readonly CommunicationTeamService $communicationTeamService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Conversation::class);

        return view('workspace.communication.teams.index', [
            'teams' => CommunicationTeam::query()->with('members')->orderBy('name')->get(),
            'members' => $this->currentWorkspace()->users()->wherePivot('status', 'active')->orderBy('name')->get(['users.id', 'users.name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Conversation::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer'],
        ]);

        $this->communicationTeamService->create(
            $this->currentWorkspace(),
            $validated['name'],
            $validated['user_ids'] ?? [],
            $request->user(),
        );

        return back()->with('success', 'تم إنشاء الفريق.');
    }

    public function update(Request $request, CommunicationTeam $team): RedirectResponse
    {
        $this->authorize('update', Conversation::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer'],
        ]);

        $team->update(['name' => $validated['name']]);
        $this->communicationTeamService->syncMembers(
            $team,
            $this->currentWorkspace(),
            $validated['user_ids'] ?? [],
            $request->user(),
        );

        return back()->with('success', 'تم تحديث الفريق.');
    }
}
