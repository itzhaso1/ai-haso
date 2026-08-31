<?php

namespace App\Http\Controllers\Workspace\Pos;

use App\Services\Pos\PosCartService;
use App\Services\Pos\PosOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PosCartController extends PosBaseController
{
    public function __construct(
        private readonly PosCartService $posCartService,
        private readonly PosOrderService $posOrderService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $this->authorizePos($request, 'orders.manage');

        return response()->json([
            'cart' => $this->posCartService->summary($this->currentWorkspace()),
        ]);
    }

    public function addItem(Request $request): JsonResponse
    {
        $this->authorizePos($request, 'orders.manage');

        $data = $request->validate([
            'pos_menu_item_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $summary = $this->posCartService->addItem(
                $this->currentWorkspace(),
                (int) $data['pos_menu_item_id'],
                (int) ($data['quantity'] ?? 1)
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['cart' => $summary]);
    }

    public function updateItem(Request $request, string $key): JsonResponse
    {
        $this->authorizePos($request, 'orders.manage');

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $summary = $this->posCartService->updateQty(
                $this->currentWorkspace(),
                $key,
                (int) $data['quantity']
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['cart' => $summary]);
    }

    public function removeItem(Request $request, string $key): JsonResponse
    {
        $this->authorizePos($request, 'orders.manage');

        $summary = $this->posCartService->removeItem($this->currentWorkspace(), $key);

        return response()->json(['cart' => $summary]);
    }

    public function updateMeta(Request $request): JsonResponse
    {
        $this->authorizePos($request, 'orders.manage');

        $data = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'dining_table_id' => ['nullable', 'integer'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $summary = $this->posCartService->setMeta($this->currentWorkspace(), $data);

        return response()->json(['cart' => $summary]);
    }

    public function clear(Request $request): JsonResponse
    {
        $this->authorizePos($request, 'orders.manage');
        $this->posCartService->clear($this->currentWorkspace());

        return response()->json([
            'cart' => $this->posCartService->summary($this->currentWorkspace()),
        ]);
    }

    public function checkout(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorizePos($request, 'orders.manage');

        $data = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'dining_table_id' => ['nullable', 'integer'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($data !== []) {
            $this->posCartService->setMeta($this->currentWorkspace(), $data);
        }

        try {
            $order = $this->posCartService->checkout($this->currentWorkspace(), $request->user());
        } catch (RuntimeException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return back()->withInput()->with('error', $exception->getMessage());
        }

        if (! $order->dining_table_id) {
            try {
                $invoice = $this->posOrderService->createInvoiceFromOrder($order, (int) $request->user()?->id);
            } catch (RuntimeException $exception) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'تم إنشاء الطلب مع خطأ في الفاتورة: '.$exception->getMessage(),
                        'order_id' => $order->id,
                    ], 201);
                }

                return back()->with('success', 'تم إنشاء طلب الكاشير.')->with('error', $exception->getMessage());
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'order_id' => $order->id,
                    'invoice_id' => $invoice->id,
                    'redirect' => route('workspace.pos.invoices.print', $invoice),
                ], 201);
            }

            return redirect()->route('workspace.pos.invoices.print', $invoice)
                ->with('success', 'تم إنشاء طلب مباشر بدون طاولة وتجهيز فاتورة الطباعة.');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'order_id' => $order->id,
                'message' => 'تم إنشاء طلب POS بنجاح.',
            ], 201);
        }

        return back()->with('success', 'تم إنشاء طلب POS بنجاح.');
    }
}
