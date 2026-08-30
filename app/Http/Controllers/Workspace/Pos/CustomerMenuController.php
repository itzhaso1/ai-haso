<?php

namespace App\Http\Controllers\Workspace\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\StorePublicMenuOrderRequest;
use App\Models\DiningTable;
use App\Models\PosMenuItem;
use App\Models\Workspace;
use App\Services\Pos\PosOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

class CustomerMenuController extends Controller
{
    public function __construct(
        private readonly PosOrderService $posOrderService,
    ) {}

    public function generalMenu(Workspace $workspace): View
    {
        return view('workspace.pos.menu', [
            'workspace' => $workspace,
            'table' => null,
            'items' => $this->menuItems($workspace->id),
        ]);
    }

    public function tableMenu(Workspace $workspace, string $token): View
    {
        $table = DiningTable::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->where('qr_token', $token)
            ->firstOrFail();

        return view('workspace.pos.menu', [
            'workspace' => $workspace,
            'table' => $table,
            'items' => $this->menuItems($workspace->id),
        ]);
    }

    public function placeGeneralOrder(StorePublicMenuOrderRequest $request, Workspace $workspace): RedirectResponse
    {
        try {
            $order = $this->posOrderService->createQrMenuOrder($workspace, null, $request->validated());
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم إرسال طلبك بنجاح. رقم الطلب: '.$order->order_number);
    }

    public function placeTableOrder(StorePublicMenuOrderRequest $request, Workspace $workspace, string $token): RedirectResponse
    {
        $table = DiningTable::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->where('qr_token', $token)
            ->firstOrFail();

        try {
            $order = $this->posOrderService->createQrMenuOrder($workspace, $table, $request->validated());
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم إرسال طلب الطاولة بنجاح. رقم الطلب: '.$order->order_number);
    }

    private function menuItems(int $workspaceId)
    {
        return PosMenuItem::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'price',
                'currency',
                'item_type',
                'image_path',
            ]);
    }
}
