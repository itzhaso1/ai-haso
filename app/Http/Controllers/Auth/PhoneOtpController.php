<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\AuthenticationService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            'phone' => ['required', 'string', 'max:32'],
        ]);

        $phone = $validated['phone'];
        try {
            $issued = $this->authenticationService->requestOtp($phone);
        } catch (\RuntimeException) {
            return back()->withErrors(['phone' => 'محاولات كثيرة. حاول لاحقًا.']);
        }
        $request->session()->put('otp_phone', $phone);

        return redirect()->route('otp.verify.form')
            ->with('otp_hint', app()->isProduction() ? null : $issued)
            ->with('status', 'إذا كان الرقم مسجلاً لدينا فسيصلك رمز التحقق.');
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
            'workspace_id' => ['required', 'integer'],
        ]);

        try {
            $payload = $this->authenticationService->loginWithOtp(
                $phone,
                $validated['otp'],
                (int) $validated['workspace_id']
            );
        } catch (AuthenticationException|\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return back()->withErrors(['otp' => 'رمز التحقق غير صالح أو منتهي.']);
        }

        Auth::login($payload['user']);
        $request->session()->put('current_workspace_id', $payload['workspace']->id);
        $request->session()->forget('otp_phone');

        return redirect()->route('workspace.choose');
    }
}
