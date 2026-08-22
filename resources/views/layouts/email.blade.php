<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle ?? 'مركز البريد الإلكتروني' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 font-sans text-slate-900 antialiased">
    <div x-data="{ mobileSidebar: false }" class="min-h-screen">
        <aside class="fixed inset-y-0 right-0 z-40 hidden w-80 border-l border-slate-200 bg-white xl:flex xl:flex-col">
            <div class="border-b border-slate-200 px-5 py-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Hasem Email Hub</p>
                <h2 class="mt-1 text-xl font-bold text-slate-900">البريد الإلكتروني</h2>
            </div>

            <nav class="space-y-2 px-4 py-4">
                <a href="{{ route('workspace.emails.index') }}"
                   class="{{ request()->routeIs('workspace.emails.*') ? 'bg-[#06C2A4] text-white' : 'text-slate-700 hover:bg-slate-100' }} block rounded-lg px-3 py-2 text-sm font-semibold transition">
                    صندوق البريد
                </a>
                <a href="{{ route('workspace.emails.index', array_filter(['account_id' => request('account_id'), 'folder' => 'inbound'])) }}"
                   class="{{ request('folder', 'inbound') === 'inbound' ? 'bg-[#E8FAF6] text-[#0f7668]' : 'text-slate-600 hover:bg-slate-100' }} block rounded-lg px-3 py-2 text-sm transition">
                    الوارد
                </a>
                <a href="{{ route('workspace.emails.index', array_filter(['account_id' => request('account_id'), 'folder' => 'outbound'])) }}"
                   class="{{ request('folder') === 'outbound' ? 'bg-[#E8FAF6] text-[#0f7668]' : 'text-slate-600 hover:bg-slate-100' }} block rounded-lg px-3 py-2 text-sm transition">
                    الصادر
                </a>
                <a href="{{ route('workspace.emails.index', array_filter(['account_id' => request('account_id'), 'folder' => 'all'])) }}"
                   class="{{ request('folder') === 'all' ? 'bg-[#E8FAF6] text-[#0f7668]' : 'text-slate-600 hover:bg-slate-100' }} block rounded-lg px-3 py-2 text-sm transition">
                    كل الرسائل
                </a>
            </nav>

            <div class="mt-2 border-t border-slate-200 px-4 py-4">
                <h3 class="text-sm font-bold text-slate-900">أقل بيانات يحتاجها العميل</h3>
                <ul class="mt-2 space-y-1 text-xs leading-6 text-slate-600">
                    <li>• اسم الشركة (يظهر للمستلم)</li>
                    <li>• البريد الإلكتروني + كلمة المرور</li>
                    <li>• IMAP Host + Port</li>
                    <li>• SMTP Host + Port</li>
                </ul>
            </div>
        </aside>

        <div class="xl:pr-80">
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
                <div class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center gap-3">
                        <button @click="mobileSidebar = true" type="button" class="rounded-lg border border-slate-200 p-2 text-slate-600 xl:hidden">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <div>
                            <p class="text-sm text-slate-500">{{ request()->attributes->get('workspace')?->name }}</p>
                            <h1 class="text-lg font-bold text-slate-900">{{ $pageTitle ?? 'مركز البريد الإلكتروني' }}</h1>
                        </div>
                    </div>

                    <a href="{{ route('workspace.dashboard') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100">
                        ← العودة إلى حاسم
                    </a>
                </div>
            </header>

            <main class="p-4 sm:p-6 lg:p-8">
                @include('partials.flash')
                @yield('content')
            </main>
        </div>

        <div x-cloak x-show="mobileSidebar" class="fixed inset-0 z-50 xl:hidden">
            <button @click="mobileSidebar = false" type="button" class="absolute inset-0 bg-slate-900/50"></button>
            <aside x-transition class="absolute inset-y-0 right-0 w-80 border-l border-slate-200 bg-white">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-4">
                    <h2 class="text-base font-bold text-slate-900">البريد الإلكتروني</h2>
                    <button @click="mobileSidebar = false" type="button" class="rounded-md p-2 text-slate-600 hover:bg-slate-100">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <nav class="space-y-2 p-4">
                    <a href="{{ route('workspace.emails.index') }}" class="block rounded-lg bg-[#06C2A4] px-3 py-2 text-sm font-semibold text-white">
                        صندوق البريد
                    </a>
                    <a href="{{ route('workspace.emails.index', array_filter(['account_id' => request('account_id'), 'folder' => 'inbound'])) }}" class="block rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100">الوارد</a>
                    <a href="{{ route('workspace.emails.index', array_filter(['account_id' => request('account_id'), 'folder' => 'outbound'])) }}" class="block rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100">الصادر</a>
                    <a href="{{ route('workspace.emails.index', array_filter(['account_id' => request('account_id'), 'folder' => 'all'])) }}" class="block rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100">كل الرسائل</a>
                </nav>
            </aside>
        </div>
    </div>
</body>
</html>
