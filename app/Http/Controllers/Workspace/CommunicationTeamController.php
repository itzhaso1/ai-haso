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

        $teams = CommunicationTeam::query()
            ->with('members')
            ->withCount([
                'conversations as active_conversations_count' => fn ($query) => $query->where('status', 'open'),
                'conversations as unassigned_conversations_count' => fn ($query) => $query->whereNull('assigned_user_id'),
            ])
            ->orderBy('name')
            ->get();

        return view('workspace.communication.teams.index', [
            'teams' => $teams,
            'members' => $this->currentWorkspace()->users()->wherePivot('status', 'active')->orderBy('name')->get(['users.id', 'users.name']),
            'workspaceUnassignedCount' => Conversation::query()
                ->whereNull('assigned_user_id')
                ->whereNull('assigned_team_id')
                ->count(),
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
        $this->authorize('create', Conversation::class);

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

    public function addMember(Request $request, CommunicationTeam $team): RedirectResponse
    {
        $this->authorize('create', Conversation::class);

        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $this->communicationTeamService->addMember(
            $team,
            $this->currentWorkspace(),
            (int) $validated['user_id'],
            $request->user(),
        );

        return back()->with('success', 'تمت إضافة العضو.');
    }

    public function removeMember(Request $request, CommunicationTeam $team, int $user): RedirectResponse
    {
        $this->authorize('create', Conversation::class);

        $this->communicationTeamService->removeMember(
            $team,
            $this->currentWorkspace(),
            $user,
            $request->user(),
        );

        return back()->with('success', 'تمت إزالة العضو.');
    }
}
