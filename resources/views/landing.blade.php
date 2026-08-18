<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'AI-HASO') }} | منصة إدارة المحادثات والتجارة</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-gray-900 antialiased">
    <div class="relative overflow-hidden">
        <div class="absolute inset-x-0 -top-32 -z-10 transform-gpu overflow-hidden blur-3xl">
            <div class="relative right-1/2 aspect-[1200/520] w-[72rem] bg-gradient-to-r from-blue-200 via-sky-100 to-indigo-100 opacity-70"></div>
        </div>

        <header class="sticky top-0 z-30 border-b border-gray-100 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <a href="/" class="flex items-center gap-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 text-lg font-bold text-white">AI</span>
                    <span class="text-lg font-bold text-gray-900">AI-HASO</span>
                </a>
                <nav class="hidden items-center gap-6 text-sm text-gray-600 md:flex">
                    <a href="#features" class="hover:text-blue-600">الميزات</a>
                    <a href="#pricing" class="hover:text-blue-600">الاشتراكات</a>
                    <a href="#footer" class="hover:text-blue-600">روابط سريعة</a>
                </nav>
                <div class="flex items-center gap-2">
                    @auth
                        <a href="{{ route('workspace.dashboard') }}" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">ابدأ الآن</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">تسجيل الدخول</a>
                        <a href="{{ route('register') }}" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">التسجيل</a>
                    @endauth
                </div>
            </div>
        </header>

        <main>
            <section class="mx-auto grid max-w-7xl items-center gap-10 px-4 pb-16 pt-16 sm:px-6 lg:grid-cols-2 lg:px-8 lg:pb-24 lg:pt-24">
                <div>
                    <span class="inline-flex items-center rounded-full border border-blue-100 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                        منصة SaaS عربية عصرية وProduction-Ready
                    </span>
                    <h1 class="mt-5 text-4xl font-extrabold leading-tight text-gray-900 sm:text-5xl">
                        منصة موحدة لإدارة
                        <span class="text-blue-600">المحادثات، الطلبات، والذكاء الاصطناعي</span>
                    </h1>
                    <p class="mt-6 text-lg leading-8 text-gray-600">
                        شغّل أعمالك من لوحة واحدة: مساحات عمل متعددة، أتمتة WhatsApp، ردود AI ذكية،
                        إدارة منتجات ومخزون، وتتبع كامل للطلبات والمدفوعات.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        @auth
                            <a href="{{ route('workspace.dashboard') }}" class="rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">ابدأ الآن</a>
                        @else
                            <a href="{{ route('register') }}" class="rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">ابدأ مجانًا</a>
                            <a href="{{ route('login') }}" class="rounded-xl border border-gray-300 px-6 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50">تسجيل الدخول</a>
                            <a href="{{ route('otp.login') }}" class="rounded-xl border border-gray-300 px-6 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50">دخول OTP</a>
                        @endauth
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-xl shadow-blue-100/40">
                    <h2 class="text-lg font-semibold text-gray-900">نظرة تشغيلية سريعة</h2>
                    <div class="mt-5 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4"><p class="font-semibold text-gray-800">Workspaces</p><p class="mt-1 text-gray-500">عزل كامل لكل عميل</p></div>
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4"><p class="font-semibold text-gray-800">WhatsApp</p><p class="mt-1 text-gray-500">Webhook + Automation</p></div>
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4"><p class="font-semibold text-gray-800">AI Replies</p><p class="mt-1 text-gray-500">ذكاء اصطناعي مخصص</p></div>
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4"><p class="font-semibold text-gray-800">Orders</p><p class="mt-1 text-gray-500">تتبع دورة الطلب كاملة</p></div>
                    </div>
                </div>
            </section>

            <section id="features" class="border-y border-gray-100 bg-gray-50/70 py-16">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl text-center">
                        <h2 class="text-3xl font-bold text-gray-900">ميزات أساسية لبناء وتشغيل أعمالك</h2>
                        <p class="mt-4 text-gray-600">كل ميزة مرتبطة مباشرة بالـBackend الحقيقي في Laravel وقواعد البيانات.</p>
                    </div>
                    <div class="mt-10 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <article class="rounded-2xl border border-gray-200 bg-white p-6">
                            <h3 class="text-lg font-semibold">مساحات عمل متعددة المستخدمين</h3>
                            <p class="mt-2 text-sm text-gray-600">Workspace Isolation كامل مع أدوار وصلاحيات لكل فريق.</p>
                        </article>
                        <article class="rounded-2xl border border-gray-200 bg-white p-6">
                            <h3 class="text-lg font-semibold">أتمتة WhatsApp</h3>
                            <p class="mt-2 text-sm text-gray-600">استقبال/إرسال الرسائل وربط Webhooks ومعالجة آمنة ومتكررة.</p>
                        </article>
                        <article class="rounded-2xl border border-gray-200 bg-white p-6">
                            <h3 class="text-lg font-semibold">ردود الذكاء الاصطناعي</h3>
                            <p class="mt-2 text-sm text-gray-600">AI Settings لكل Workspace مع تتبع الاستخدام والسجلات.</p>
                        </article>
                        <article class="rounded-2xl border border-gray-200 bg-white p-6">
                            <h3 class="text-lg font-semibold">إدارة المنتجات والمخزون</h3>
                            <p class="mt-2 text-sm text-gray-600">CRUD كامل + Variants + عمليات مخزون آمنة لمنع overselling.</p>
                        </article>
                        <article class="rounded-2xl border border-gray-200 bg-white p-6">
                            <h3 class="text-lg font-semibold">إدارة الطلبات</h3>
                            <p class="mt-2 text-sm text-gray-600">إنشاء الطلبات، تتبع الحالة، الدفع، الشحن، والإلغاء.</p>
                        </article>
                        <article class="rounded-2xl border border-gray-200 bg-white p-6">
                            <h3 class="text-lg font-semibold">تحليلات وتشغيل يومي</h3>
                            <p class="mt-2 text-sm text-gray-600">لوحات متابعة للإيرادات، المحادثات، والعمليات اليومية.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="pricing" class="py-16">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl text-center">
                        <h2 class="text-3xl font-bold text-gray-900">نظرة عامة على الاشتراكات</h2>
                        <p class="mt-4 text-gray-600">ابدأ بخطة مناسبة ثم توسّع بسهولة مع نمو عملك.</p>
                    </div>
                    <div class="mt-10 grid gap-5 md:grid-cols-3">
                        <div class="rounded-2xl border border-gray-200 bg-white p-6">
                            <p class="text-sm font-semibold text-gray-500">Individuals</p>
                            <h3 class="mt-2 text-2xl font-bold">Free / Pro</h3>
                            <ul class="mt-5 space-y-2 text-sm text-gray-600">
                                <li>• محادثات شخصية</li>
                                <li>• Smart Replies</li>
                                <li>• AI Usage أساسي</li>
                            </ul>
                        </div>
                        <div class="rounded-2xl border-2 border-blue-600 bg-blue-50 p-6">
                            <p class="text-sm font-semibold text-blue-700">Companies & Stores</p>
                            <h3 class="mt-2 text-2xl font-bold text-gray-900">Basic / Pro</h3>
                            <ul class="mt-5 space-y-2 text-sm text-gray-700">
                                <li>• منتجات ومخزون</li>
                                <li>• طلبات ومدفوعات</li>
                                <li>• أتمتة WhatsApp + AI</li>
                            </ul>
                        </div>
                        <div class="rounded-2xl border border-gray-200 bg-white p-6">
                            <p class="text-sm font-semibold text-gray-500">Enterprise</p>
                            <h3 class="mt-2 text-2xl font-bold">Custom</h3>
                            <ul class="mt-5 space-y-2 text-sm text-gray-600">
                                <li>• حدود أعلى ومرونة كاملة</li>
                                <li>• إعدادات متقدمة</li>
                                <li>• دعم احترافي</li>
                            </ul>
                        </div>
                    </div>
                    <div class="mt-8 text-center">
                        @auth
                            <a href="{{ route('workspace.subscriptions.index') }}" class="inline-flex rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-700">إدارة اشتراكك</a>
                        @else
                            <a href="{{ route('register') }}" class="inline-flex rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-700">ابدأ خطتك الآن</a>
                        @endauth
                    </div>
                </div>
            </section>
        </main>

        <footer id="footer" class="border-t border-gray-100 bg-white">
            <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-3 lg:px-8">
                <div>
                    <h4 class="text-lg font-bold">AI-HASO</h4>
                    <p class="mt-3 text-sm text-gray-600">منصة عربية لإدارة المحادثات والتجارة والذكاء الاصطناعي من مكان واحد.</p>
                </div>
                <div>
                    <h5 class="text-sm font-semibold text-gray-900">روابط سريعة</h5>
                    <ul class="mt-3 space-y-2 text-sm text-gray-600">
                        <li><a href="#features" class="hover:text-blue-600">الميزات</a></li>
                        <li><a href="#pricing" class="hover:text-blue-600">الاشتراكات</a></li>
                        <li><a href="{{ route('workspace.choose') }}" class="hover:text-blue-600">مساحات العمل</a></li>
                    </ul>
                </div>
                <div>
                    <h5 class="text-sm font-semibold text-gray-900">الدخول</h5>
                    <ul class="mt-3 space-y-2 text-sm text-gray-600">
                        <li><a href="{{ route('login') }}" class="hover:text-blue-600">تسجيل الدخول</a></li>
                        <li><a href="{{ route('register') }}" class="hover:text-blue-600">إنشاء حساب</a></li>
                        <li><a href="{{ route('platform.login') }}" class="hover:text-blue-600">دخول منصة الإدارة</a></li>
                    </ul>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
