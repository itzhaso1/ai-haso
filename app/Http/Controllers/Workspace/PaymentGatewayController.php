<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\InteractsWithWorkspace;
use App\Models\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentGatewayController extends Controller
{
    use InteractsWithWorkspace;

    public function index(): View
    {
        $gateways = PaymentGateway::query()->latest('id')->paginate(12);

        return view('workspace.payment-gateways.index', compact('gateways'));
    }

    public function create(): View
    {
        return view('workspace.payment-gateways.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'provider' => ['required', 'in:local,stripe'],
            'status' => ['required', 'in:connected,disconnected,error,pending'],
            'config_json' => ['nullable', 'string'],
        ]);

        PaymentGateway::query()->create([
            'provider' => $payload['provider'],
            'status' => $payload['status'],
            'config' => $this->parseJsonField($request, 'config_json'),
            'last_verified_at' => $payload['status'] === 'connected' ? now() : null,
        ]);

        return redirect()->route('workspace.payment-gateways.index')->with('success', 'تم حفظ بوابة الدفع.');
    }

    public function edit(PaymentGateway $payment_gateway): View
    {
        return view('workspace.payment-gateways.edit', [
            'gateway' => $payment_gateway,
            'configJson' => json_encode($payment_gateway->config ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function update(Request $request, PaymentGateway $payment_gateway): RedirectResponse
    {
        $payload = $request->validate([
            'provider' => ['required', 'in:local,stripe'],
            'status' => ['required', 'in:connected,disconnected,error,pending'],
            'config_json' => ['nullable', 'string'],
        ]);

        $payment_gateway->update([
            'provider' => $payload['provider'],
            'status' => $payload['status'],
            'config' => $this->parseJsonField($request, 'config_json', $payment_gateway->config ?? []),
            'last_verified_at' => $payload['status'] === 'connected' ? now() : $payment_gateway->last_verified_at,
        ]);

        return redirect()->route('workspace.payment-gateways.index')->with('success', 'تم تحديث بوابة الدفع.');
    }

    public function destroy(PaymentGateway $payment_gateway): RedirectResponse
    {
        $payment_gateway->delete();

        return redirect()->route('workspace.payment-gateways.index')->with('success', 'تم حذف البوابة.');
    }
}
