<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\AuthenticationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PhoneOtpController extends Controller
{
    public function __construct(
        private readonly AuthenticationService $authenticationService,
    ) {}

    public function create()
    {
        return view('auth.phone-otp-request');
    }

    public function requestOtp(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'exists:users,phone'],
        ]);

        $otp = $this->authenticationService->requestOtp($validated['phone']);
        $request->session()->put('otp_phone', $validated['phone']);

        $user = User::query()->where('phone', $validated['phone'])->first();
        $workspaces = $user?->workspaces()->wherePivot('status', 'active')->get() ?? collect();

        return redirect()->route('otp.verify.form')
            ->with('otp_hint', app()->isProduction() ? null : $otp)
            ->with('workspaces', $workspaces);
    }

    public function verifyForm(Request $request)
    {
        $phone = $request->session()->get('otp_phone');
        abort_unless($phone, 403);

        $user = User::query()->where('phone', $phone)->first();
        $workspaces = $user?->workspaces()->wherePivot('status', 'active')->get() ?? collect();

        return view('auth.phone-otp-verify', [
            'phone' => $phone,
            'workspaces' => $workspaces,
            'otpHint' => session('otp_hint'),
        ]);
    }

    public function verify(Request $request)
    {
        $phone = $request->session()->get('otp_phone');
        abort_unless($phone, 403);

        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
            'workspace_id' => ['required', Rule::exists('workspaces', 'id')],
        ]);

        $payload = $this->authenticationService->loginWithOtp(
            $phone,
            $validated['otp'],
            (int) $validated['workspace_id']
        );

        Auth::login($payload['user']);
        $request->session()->put('current_workspace_id', $payload['workspace']->id);
        $request->session()->forget('otp_phone');

        return redirect()->route('workspace.choose');
    }
}
