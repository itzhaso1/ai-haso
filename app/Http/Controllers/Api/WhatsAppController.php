<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    public function index(): JsonResponse
    {
        $accounts = WhatsAppAccount::query()->with('phoneNumbers')->get();

        return response()->json(['data' => $accounts]);
    }

    public function storeAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_account_id' => ['required', 'string', 'max:255'],
            'app_id' => ['nullable', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:pending,connected,disconnected,error'],
            'metadata' => ['nullable', 'array'],
        ]);

        $account = WhatsAppAccount::query()->create($validated);

        return response()->json(['data' => $account], 201);
    }

    public function storePhoneNumber(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'whats_app_account_id' => ['required', 'integer', 'exists:whats_app_accounts,id'],
            'phone_number_id' => ['required', 'string', 'max:255'],
            'display_phone_number' => ['required', 'string', 'max:255'],
            'verified_name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:connected,pending,disconnected'],
        ]);

        $phone = WhatsAppPhoneNumber::query()->create($validated);

        return response()->json(['data' => $phone], 201);
    }
}
