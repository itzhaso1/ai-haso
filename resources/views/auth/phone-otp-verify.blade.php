<x-guest-layout>
    <h1 class="text-xl font-semibold text-gray-900 mb-2">تأكيد رمز OTP</h1>
    <p class="text-sm text-gray-600 mb-4">تم إرسال الرمز إلى: {{ $phone }}</p>

    @if($otpHint)
        <div class="mb-4 rounded-md bg-blue-50 p-3 text-sm text-blue-800">
            OTP (بيئة محلية): {{ $otpHint }}
        </div>
    @endif

    <form method="POST" action="{{ route('otp.verify') }}">
        @csrf
        <div>
            <x-input-label for="workspace_id" value="مساحة العمل" />
            <select id="workspace_id" name="workspace_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                @foreach($workspaces as $workspace)
                    <option value="{{ $workspace->id }}">{{ $workspace->name }} ({{ $workspace->type }})</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('workspace_id')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="otp" value="رمز التحقق" />
            <x-text-input id="otp" class="block mt-1 w-full" type="text" name="otp" maxlength="6" required />
            <x-input-error :messages="$errors->get('otp')" class="mt-2" />
        </div>

        <x-primary-button class="mt-4 w-full justify-center">
            دخول
        </x-primary-button>
    </form>
</x-guest-layout>
