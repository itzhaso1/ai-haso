<?php

namespace App\Services\AI;

use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use App\Services\Workspace\WorkspaceResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebsiteAssistantService
{
    public function __construct(
        private readonly AiProviderManager $providerManager,
        private readonly WorkspaceResolverService $workspaceResolverService,
    ) {}

    public function reply(string $prompt, Request $request, ?User $user = null): string
    {
        return $this->replyWithMeta($prompt, $request, $user)['reply'];
    }

    /**
     * @return array{reply:string,source:string,reason:?string,model:?string}
     */
    public function replyWithMeta(string $prompt, Request $request, ?User $user = null): array
    {
        $workspace = $this->workspaceResolverService->resolveFromRequest($request, $user);
        $plans = $this->activePlansSnapshot();
        $products = [];

        if ($workspace) {
            $products = Product::query()
                ->where('workspace_id', $workspace->id)
                ->where('status', 'active')
                ->limit(12)
                ->get(['name', 'sku', 'price', 'sale_price', 'stock', 'currency'])
                ->map(fn (Product $product): array => [
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'price' => $product->sale_price ?: $product->price,
                    'stock' => $product->stock,
                    'currency' => $product->currency,
                ])->all();
        }

        $messages = [
            [
                'role' => 'system',
                'content' => $this->buildSystemPrompt($workspace !== null, $products),
            ],
            [
                'role' => 'user',
                'content' => $this->buildUserPrompt($prompt, $plans),
            ],
        ];

        $providerName = $this->providerManager->normalize((string) config('ai.default_provider', 'google_ai_studio'));
        $model = $this->resolveModel($providerName);
        if ($providerName === 'google_ai_studio' && ! filled(config('services.google_ai_studio.key'))) {
            $fallbackReply = $this->fallbackReply($prompt, $workspace !== null, $plans);

            return [
                'reply' => $fallbackReply."\n\n(ملاحظة: الرد الحالي يعمل بوضع احتياطي لأن مفتاح Gemini غير مضاف بعد.)",
                'source' => 'fallback',
                'reason' => 'missing_gemini_key',
                'model' => $model,
            ];
        }

        try {
            $provider = $this->providerManager->resolve($providerName);
            $result = $provider->generate(
                messages: $messages,
                model: $model,
                temperature: $this->resolveTemperature($providerName),
                maxTokens: $this->resolveMaxTokens($providerName),
            );

            $content = trim((string) ($result['content'] ?? ''));

            if ($content !== '') {
                return [
                    'reply' => $content,
                    'source' => $providerName,
                    'reason' => null,
                    'model' => $model,
                ];
            }

            return [
                'reply' => $this->fallbackReply($prompt, $workspace !== null, $plans),
                'source' => 'fallback',
                'reason' => 'empty_provider_response',
                'model' => $model,
            ];
        } catch (Throwable $exception) {
            $fallbackReply = $this->fallbackReply($prompt, $workspace !== null, $plans);

            Log::error('website-assistant-provider-failed', [
                'provider' => $providerName,
                'model' => $model,
                'message' => $exception->getMessage(),
            ]);

            return [
                'reply' => $fallbackReply."\n\n(ملاحظة تقنية: تعذر الوصول إلى مزود الذكاء الاصطناعي الآن - provider: {$providerName}, model: {$model})",
                'source' => 'fallback',
                'reason' => 'provider_error',
                'model' => $model,
            ];
        }
    }

    private function resolveModel(string $providerName): string
    {
        $configModel = (string) config('ai.'.$providerName.'.model', '');
        if ($configModel !== '') {
            return $configModel;
        }

        if ($providerName === 'google_ai_studio') {
            return (string) config('services.google_ai_studio.model', 'gemini-2.5-flash');
        }

        return (string) config('ai.openai.model', 'gpt-4o-mini');
    }

    private function resolveTemperature(string $providerName): float
    {
        return (float) config('ai.'.$providerName.'.temperature', 0.3);
    }

    private function resolveMaxTokens(string $providerName): int
    {
        return (int) config('ai.'.$providerName.'.max_tokens', 1024);
    }

    private function buildSystemPrompt(bool $hasWorkspace, array $products): string
    {
        $workspaceContext = $hasWorkspace
            ? "المستخدم مسجل دخولًا وقد يطلب معلومات منتجات. استخدم فقط المنتجات التالية:\n".json_encode($products, JSON_UNESCAPED_UNICODE)
            : 'المستخدم زائر. مهمتك إرشاده لتسجيل الدخول، إنشاء حساب، واختيار مساحة العمل.';

        return <<<PROMPT
أنت مساعد منصة حاسم.
- اكتب بالعربية الواضحة.
- كن مختصرًا ومهذبًا وخطواتك عملية.
- لا تقدم أي معلومات غير مؤكدة.
- إذا لم توجد بيانات منتج مطابقة، صرّح بعدم التوفر بوضوح.
- إذا طلب المستخدم دعم الدخول: اشرح تسجيل الدخول من /login أو إنشاء حساب من /register.
- إذا سأل الزائر عن الأسعار أو الباقات، استخدم أسعار الباقات القادمة من قاعدة البيانات المرفقة في رسالة المستخدم.
- إذا كانت هناك مشكلة شائعة (كلمة المرور، التحقق، عدم وصول رمز، عدم فتح الصفحة)، أعطِ خطوات حل عملية ومباشرة.
- إذا كان السؤال تقنيًا لا يعتمد على بيانات مؤكدة، قدّم توجيهًا عامًا ثم اقترح التواصل مع الدعم.

{$workspaceContext}
PROMPT;
    }

    /**
     * @param  array<int, array{code:string,name:string,workspace_type:?string,billing_period:string,currency:string,price:string}>  $plans
     */
    private function buildUserPrompt(string $prompt, array $plans): string
    {
        $plansJson = json_encode($plans, JSON_UNESCAPED_UNICODE);

        return <<<TEXT
سؤال الزائر:
{$prompt}

الباقات المتاحة (من قاعدة البيانات):
{$plansJson}
TEXT;
    }

    /**
     * @param  array<int, array{code:string,name:string,workspace_type:?string,billing_period:string,currency:string,price:string}>  $plans
     */
    private function fallbackReply(string $prompt, bool $hasWorkspace, array $plans): string
    {
        $text = mb_strtolower($prompt);

        if (
            str_contains($text, 'سعر')
            || str_contains($text, 'اسعار')
            || str_contains($text, 'باقات')
            || str_contains($text, 'اشتراك')
            || str_contains($text, 'plans')
            || str_contains($text, 'pricing')
        ) {
            if (count($plans) === 0) {
                return 'حاليًا لا توجد باقات نشطة في النظام. يمكن لمدير المنصة إضافتها من لوحة الأدمن: /platform/plans';
            }

            $lines = array_map(
                fn (array $plan): string => "- {$plan['name']} ({$plan['workspace_type']}) : {$plan['price']} {$plan['currency']} / {$plan['billing_period']}",
                $plans
            );

            return "هذه أسعار الباقات الحالية من قاعدة البيانات:\n".implode("\n", $lines);
        }

        if (str_contains($text, 'تسجيل') || str_contains($text, 'دخول') || str_contains($text, 'login')) {
            return 'للدخول إلى حسابك: افتح صفحة /login ثم أدخل البريد أو رقم الهاتف مع كلمة المرور. إذا نسيت كلمة المرور استخدم "نسيت كلمة المرور".';
        }

        if (str_contains($text, 'حساب') || str_contains($text, 'register') || str_contains($text, 'إنشاء')) {
            return 'لإنشاء حساب جديد: افتح صفحة /register، اختر نوع الحساب (فرد/شركة/متجر)، ثم أكمل بيانات التسجيل.';
        }

        if (str_contains($text, 'نسيت') || str_contains($text, 'كلمة المرور') || str_contains($text, 'password')) {
            return 'إذا نسيت كلمة المرور: افتح صفحة /forgot-password ثم أدخل البريد الإلكتروني، وستصلك رسالة إعادة تعيين كلمة المرور.';
        }

        if (str_contains($text, 'رمز') || str_contains($text, 'otp') || str_contains($text, 'تحقق')) {
            return 'إذا واجهت مشكلة في رمز التحقق: تأكد من صحة الرقم، ثم أعد طلب الرمز من صفحة OTP. إذا استمرت المشكلة جرّب بعد دقيقة بسبب حماية معدل الطلب.';
        }

        if (str_contains($text, 'مشكلة') || str_contains($text, 'لا يعمل') || str_contains($text, 'error') || str_contains($text, 'خطأ')) {
            return 'لحل المشكلة بسرعة: 1) حدّث الصفحة 2) تأكد من اتصال الإنترنت 3) سجّل خروج/دخول 4) جرّب متصفحًا آخر. إذا استمرت المشكلة، أرسل لنا لقطة شاشة ووقت الخطأ لدعم أسرع.';
        }

        if ($hasWorkspace) {
            return 'يمكنني مساعدتك في الإرشاد داخل النظام أو البحث عن المنتجات المتاحة في مساحة العمل الحالية. اكتب اسم المنتج أو المواصفات المطلوبة.';
        }

        return 'أنا مساعد حاسم. يمكنني مساعدتك في تسجيل الدخول، إنشاء الحساب، والتنقل داخل المنصة خطوة بخطوة.';
    }

    /**
     * @return array<int, array{code:string,name:string,workspace_type:?string,billing_period:string,currency:string,price:string}>
     */
    private function activePlansSnapshot(): array
    {
        return Plan::query()
            ->where('is_active', true)
            ->orderBy('workspace_type')
            ->orderBy('price')
            ->limit(20)
            ->get(['code', 'name', 'workspace_type', 'billing_period', 'currency', 'price'])
            ->map(fn (Plan $plan): array => [
                'code' => (string) $plan->code,
                'name' => (string) $plan->name,
                'workspace_type' => $plan->workspace_type,
                'billing_period' => (string) $plan->billing_period,
                'currency' => (string) $plan->currency,
                'price' => number_format((float) $plan->price, 2, '.', ''),
            ])
            ->values()
            ->all();
    }
}
