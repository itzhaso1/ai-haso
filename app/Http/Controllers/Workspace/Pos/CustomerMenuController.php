<?php

namespace App\Http\Controllers\Workspace\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\StorePublicMenuOrderRequest;
use App\Models\DiningTable;
use App\Models\PosMenuItem;
use App\Models\Workspace;
use App\Services\Pos\PosMenuAiService;
use App\Services\Pos\PosOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use RuntimeException;

class CustomerMenuController extends Controller
{
    public function __construct(
        private readonly PosOrderService $posOrderService,
        private readonly PosMenuAiService $posMenuAiService,
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
        $validated = $request->validated();

        try {
            $order = $this->posOrderService->createQrMenuOrder($workspace, null, $validated);
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        if ((bool) ($validated['pay_now'] ?? false)) {
            try {
                $payment = $this->posOrderService->createPaymentLinkForOrder($order);
            } catch (RuntimeException $exception) {
                return back()->with('success', 'تم إرسال الطلب. رقم الطلب: '.$order->order_number)
                    ->with('error', $exception->getMessage());
            }

            return back()
                ->with('success', 'تم إرسال طلبك بنجاح. رقم الطلب: '.$order->order_number)
                ->with('payment_link', $payment->payment_link);
        }

        return back()->with('success', 'تم إرسال طلبك بنجاح. رقم الطلب: '.$order->order_number);
    }

    public function placeTableOrder(StorePublicMenuOrderRequest $request, Workspace $workspace, string $token): RedirectResponse
    {
        $table = DiningTable::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->where('qr_token', $token)
            ->firstOrFail();

        $validated = $request->validated();
        try {
            $order = $this->posOrderService->createQrMenuOrder($workspace, $table, $validated);
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        if ((bool) ($validated['pay_now'] ?? false)) {
            try {
                $payment = $this->posOrderService->createPaymentLinkForOrder($order);
            } catch (RuntimeException $exception) {
                return back()->with('success', 'تم إرسال الطلب. رقم الطلب: '.$order->order_number)
                    ->with('error', $exception->getMessage());
            }

            return back()
                ->with('success', 'تم إرسال طلب الطاولة بنجاح. رقم الطلب: '.$order->order_number)
                ->with('payment_link', $payment->payment_link);
        }

        return back()->with('success', 'تم إرسال طلب الطاولة بنجاح. رقم الطلب: '.$order->order_number);
    }

    public function askAi(Request $request, Workspace $workspace): JsonResponse
    {
        $token = (string) $request->route('token', '');
        if ($token !== '') {
            DiningTable::withoutGlobalScopes()
                ->where('workspace_id', $workspace->id)
                ->where('qr_token', $token)
                ->firstOrFail();
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $answer = $this->posMenuAiService->answer($workspace, (string) $validated['message']);
        } catch (\Throwable) {
            $answer = 'تعذر الرد الآن، حاول بعد قليل.';
        }

        return response()->json(['answer' => $answer]);
    }

    private function menuItems(int $workspaceId)
    {
        return PosMenuItem::withoutGlobalScopes()
            ->with('category:id,name')
            ->where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'pos_item_category_id',
                'name',
                'price',
                'currency',
                'item_type',
                'size_label',
                'description',
                'image_path',
            ]);
    }
}
