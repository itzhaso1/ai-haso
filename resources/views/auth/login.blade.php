<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div>
            <x-input-label for="email_or_phone" value="البريد الإلكتروني أو رقم الجوال" />
            <x-text-input id="email_or_phone" class="block mt-1 w-full" type="text" name="email_or_phone" :value="old('email_or_phone')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email_or_phone')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" value="كلمة المرور" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">تذكرني</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                    نسيت كلمة المرور؟
                </a>
            @endif

            <x-primary-button class="ms-3">
                تسجيل الدخول
            </x-primary-button>
        </div>
    </form>

    <div class="mt-6 border-t border-gray-200 pt-4 space-y-2">
        <a href="{{ route('otp.login') }}" class="block text-sm text-blue-600 hover:text-blue-700">تسجيل الدخول عبر OTP</a>
        <a href="{{ route('social.redirect', 'google') }}" class="block text-sm text-gray-700 hover:text-gray-900">تسجيل الدخول عبر Google</a>
        <a href="{{ route('social.redirect', 'facebook') }}" class="block text-sm text-gray-700 hover:text-gray-900">تسجيل الدخول عبر Facebook</a>
    </div>
</x-guest-layout>
