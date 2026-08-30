<?php

namespace App\Http\Controllers\Workspace\Pos;

use App\Http\Requests\Pos\StoreDiningTableRequest;
use App\Http\Requests\Pos\UpdateDiningTableRequest;
use App\Models\DiningTable;
use App\Models\TableSession;
use App\Services\Pos\PosOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class TableController extends PosBaseController
{
    public function __construct(
        private readonly PosOrderService $posOrderService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePos($request, 'tables.manage');

        $tables = DiningTable::query()
            ->with(['sessions' => fn ($query) => $query->where('status', 'open')->latest('id')])
            ->withCount([
                'orders as orders_count' => fn ($query) => $query->whereIn('source', ['pos', 'qr_menu']),
                'orders as open_orders_count' => fn ($query) => $query
                    ->whereIn('source', ['pos', 'qr_menu'])
                    ->whereIn('pos_status', ['new', 'accepted', 'preparing', 'ready']),
            ])
            ->orderBy('name')
            ->paginate(30);

        return view('workspace.pos.tables.index', [
            'tables' => $tables,
            'posStatuses' => $this->posStatusLabels(),
        ]);
    }

    public function show(Request $request, DiningTable $table): View
    {
        $this->authorizePos($request, 'tables.manage');
        $this->authorize('view', $table);

        $table->load([
            'sessions' => fn ($query) => $query->latest('id')->limit(20),
            'orders' => fn ($query) => $query
                ->whereIn('source', ['pos', 'qr_menu'])
                ->with(['items', 'financeInvoice'])
                ->latest('id')
                ->limit(50),
        ]);

        $currentSession = $table->sessions->firstWhere('status', 'open');

        return view('workspace.pos.tables.show', [
            'table' => $table,
            'currentSession' => $currentSession,
            'posStatuses' => $this->posStatusLabels(),
        ]);
    }

    public function store(StoreDiningTableRequest $request): RedirectResponse
    {
        $this->authorizePos($request, 'tables.manage');

        $workspace = $this->currentWorkspace();
        $validated = $request->validated();
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('dining_tables', 'name')->where(fn ($query) => $query->where('workspace_id', $workspace->id)),
            ],
        ]);

        DiningTable::query()->create([
            'name' => $validated['name'],
            'status' => 'available',
            'qr_token' => Str::random(48),
        ]);

        return back()->with('success', 'تم إنشاء الطاولة بنجاح.');
    }

    public function update(UpdateDiningTableRequest $request, DiningTable $table): RedirectResponse
    {
        $this->authorizePos($request, 'tables.manage');
        $this->authorize('update', $table);

        $workspace = $this->currentWorkspace();
        $validated = $request->validated();

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('dining_tables', 'name')
                    ->where(fn ($query) => $query->where('workspace_id', $workspace->id))
                    ->ignore($table->id),
            ],
        ]);

        $table->update([
            'name' => $validated['name'],
            'status' => $validated['status'] ?? $table->status,
        ]);

        return back()->with('success', 'تم تحديث بيانات الطاولة.');
    }

    public function openSession(Request $request, DiningTable $table): RedirectResponse
    {
        $this->authorizePos($request, 'tables.manage');
        $this->authorize('update', $table);

        $this->posOrderService->openSession($table);

        return back()->with('success', 'تم فتح جلسة للطاولة.');
    }

    public function closeSession(Request $request, DiningTable $table, TableSession $session): RedirectResponse
    {
        $this->authorizePos($request, 'tables.manage');
        $this->authorize('update', $table);

        abort_unless((int) $session->dining_table_id === (int) $table->id, 404);

        try {
            $this->posOrderService->closeSession($session);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم إغلاق الجلسة.');
    }

    public function regenerateQr(Request $request, DiningTable $table): RedirectResponse
    {
        $this->authorizePos($request, 'tables.manage');
        $this->authorize('update', $table);

        $table->update([
            'qr_token' => Str::random(48),
        ]);

        return back()->with('success', 'تم إنشاء رمز QR جديد للطاولة.');
    }

    /**
     * @return array<string,string>
     */
    private function posStatusLabels(): array
    {
        return [
            'new' => 'جديد',
            'accepted' => 'تم القبول',
            'preparing' => 'قيد التحضير',
            'ready' => 'جاهز',
            'completed' => 'مكتمل',
            'cancelled' => 'ملغي',
        ];
    }
}
