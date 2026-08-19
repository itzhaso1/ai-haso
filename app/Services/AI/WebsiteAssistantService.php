<?php

namespace App\Services\AI;

use App\Models\Product;
use App\Models\User;
use App\Services\Workspace\WorkspaceResolverService;
use Illuminate\Http\Request;
use Throwable;

class WebsiteAssistantService
{
    public function __construct(
        private readonly AiProviderManager $providerManager,
        private readonly WorkspaceResolverService $workspaceResolverService,
    ) {}

    public function reply(string $prompt, Request $request, ?User $user = null): string
    {
        $workspace = $this->workspaceResolverService->resolveFromRequest($request, $user);
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
                'content' => $prompt,
            ],
        ];

        try {
            $provider = $this->providerManager->resolve('google_ai_studio');
            $result = $provider->generate(
                messages: $messages,
                model: (string) config('ai.google_ai_studio.model', 'gemini-2.5-flash'),
                temperature: (float) config('ai.google_ai_studio.temperature', 0.3),
                maxTokens: (int) config('ai.google_ai_studio.max_tokens', 1024),
            );

            $content = trim((string) ($result['content'] ?? ''));

            return $content !== '' ? $content : $this->fallbackReply($prompt, $workspace !== null);
        } catch (Throwable) {
            return $this->fallbackReply($prompt, $workspace !== null);
        }
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
- إذا كان السؤال تقنيًا لا يعتمد على بيانات مؤكدة، قدّم توجيهًا عامًا ثم اقترح التواصل مع الدعم.

{$workspaceContext}
PROMPT;
    }

    private function fallbackReply(string $prompt, bool $hasWorkspace): string
    {
        $text = mb_strtolower($prompt);

        if (str_contains($text, 'تسجيل') || str_contains($text, 'دخول') || str_contains($text, 'login')) {
            return 'للدخول إلى حسابك: افتح صفحة /login ثم أدخل البريد أو رقم الهاتف مع كلمة المرور. إذا نسيت كلمة المرور استخدم "نسيت كلمة المرور".';
        }

        if (str_contains($text, 'حساب') || str_contains($text, 'register') || str_contains($text, 'إنشاء')) {
            return 'لإنشاء حساب جديد: افتح صفحة /register، اختر نوع الحساب (فرد/شركة/متجر)، ثم أكمل بيانات التسجيل.';
        }

        if ($hasWorkspace) {
            return 'يمكنني مساعدتك في الإرشاد داخل النظام أو البحث عن المنتجات المتاحة في مساحة العمل الحالية. اكتب اسم المنتج أو المواصفات المطلوبة.';
        }

        return 'أنا مساعد حاسم. يمكنني مساعدتك في تسجيل الدخول، إنشاء الحساب، والتنقل داخل المنصة خطوة بخطوة.';
    }
}
