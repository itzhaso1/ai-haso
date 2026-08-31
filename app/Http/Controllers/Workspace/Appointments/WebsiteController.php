<?php

namespace App\Http\Controllers\Workspace\Appointments;

use App\Models\Website\Website;
use App\Services\Website\PublicWebsiteService;
use App\Services\Website\TemplateService;
use App\Services\Website\WebsiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

class WebsiteController extends AppointmentsBaseController
{
    public function __construct(
        private readonly WebsiteService $websiteService,
        private readonly TemplateService $templateService,
        private readonly PublicWebsiteService $publicWebsiteService,
    ) {}

    public function overview(Request $request): View
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');
        $workspace = $this->currentWorkspace();

        $website = Website::query()
            ->with(['template', 'domains'])
            ->where('workspace_id', $workspace->id)
            ->latest('id')
            ->first();

        return view('workspace.appointments.website.overview', [
            'website' => $website,
            'templates' => $this->templateService->listTemplates(),
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');
        $workspace = $this->currentWorkspace();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-zA-Z0-9\-]+$/'],
        ]);

        $website = $this->websiteService->createWebsite($workspace, $validated);

        return redirect()
            ->route('workspace.appointments.website.templates', $website)
            ->with('success', 'تم إنشاء موقع الحجز بنجاح.');
    }

    public function templates(Request $request, Website $website): View
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');
        $this->ensureSameWorkspace($website);
        $this->authorize('view', $website);

        return view('workspace.appointments.website.templates', [
            'website' => $website,
            'templates' => $this->templateService->listTemplates(),
        ]);
    }

    public function selectTemplate(Request $request, Website $website): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');
        $this->ensureSameWorkspace($website);
        $this->authorize('update', $website);

        $validated = $request->validate([
            'template_id' => ['required', 'integer'],
        ]);

        $this->websiteService->selectTemplate($website, (int) $validated['template_id']);

        return redirect()
            ->route('workspace.appointments.website.customize', $website)
            ->with('success', 'تم اختيار القالب وتطبيق الهيكل الافتراضي.');
    }

    public function customize(Request $request, Website $website): View
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');
        $this->ensureSameWorkspace($website);
        $this->authorize('view', $website);

        $website->load(['template', 'pages', 'sections' => fn ($query) => $query->orderBy('position')]);
        $homePage = $website->pages->firstWhere('slug', 'home') ?: $website->pages->firstWhere('is_homepage', true);
        $sections = $homePage
            ? $website->sections->where('website_page_id', $homePage->id)->sortBy('position')->values()
            : collect();

        return view('workspace.appointments.website.customize', [
            'website' => $website,
            'sections' => $sections,
            'settings' => is_array($website->settings) ? $website->settings : [],
            'theme' => is_array($website->theme) ? $website->theme : [],
        ]);
    }

    public function updateCustomization(Request $request, Website $website): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');
        $this->ensureSameWorkspace($website);
        $this->authorize('update', $website);

        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'hero_title' => ['nullable', 'string', 'max:255'],
            'hero_description' => ['nullable', 'string', 'max:1500'],
            'cta_text' => ['nullable', 'string', 'max:120'],
            'about_text' => ['nullable', 'string', 'max:5000'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_address' => ['nullable', 'string', 'max:500'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'footer_text' => ['nullable', 'string', 'max:255'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
            'font' => ['nullable', 'string', 'max:100'],
            'direction' => ['nullable', 'in:rtl,ltr'],
            'social_links' => ['nullable', 'array'],
            'social_links.*' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'hero_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192'],
            'favicon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,ico', 'max:2048'],
        ]);

        $website = $this->websiteService->updateSettings($website, $validated);
        $settings = is_array($website->settings) ? $website->settings : [];

        foreach (['logo', 'hero_image', 'favicon'] as $assetType) {
            if (! $request->hasFile($assetType)) {
                continue;
            }

            $asset = $this->websiteService->storeAsset($website, $assetType, $request->file($assetType));
            $settings[$assetType.'_url'] = Storage::disk($asset->disk)->url($asset->path);
        }

        if ($settings !== $website->settings) {
            $website->update(['settings' => $settings]);
        }

        return back()->with('success', 'تم حفظ تخصيص الموقع بنجاح.');
    }

    public function updateSections(Request $request, Website $website): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');
        $this->ensureSameWorkspace($website);
        $this->authorize('update', $website);

        $validated = $request->validate([
            'sections' => ['required', 'array'],
            'sections.*.id' => ['required', 'integer'],
            'sections.*.position' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'sections.*.is_enabled' => ['nullable', 'boolean'],
            'sections.*.config' => ['nullable', 'string'],
        ]);

        $sections = collect($validated['sections'])->map(function (array $section): array {
            $decodedConfig = [];
            if (array_key_exists('config', $section) && is_string($section['config']) && trim($section['config']) !== '') {
                $candidate = json_decode($section['config'], true);
                if (is_array($candidate)) {
                    $decodedConfig = $candidate;
                }
            }

            return [
                ...$section,
                'config' => $decodedConfig,
            ];
        })->all();

        $this->websiteService->updateSections($website, $sections);

        return back()->with('success', 'تم تحديث ترتيب وإعدادات الأقسام.');
    }

    public function preview(Request $request, Website $website): View
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');
        $this->ensureSameWorkspace($website);
        $this->authorize('view', $website);

        $page = trim((string) $request->query('page', 'home'));

        return view('public.website.show', $this->publicWebsiteService->buildWebsiteViewData($website, $page) + [
            'isPreview' => true,
        ]);
    }

    public function publish(Request $request, Website $website): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');
        $this->ensureSameWorkspace($website);
        $this->authorize('publish', $website);

        try {
            $this->websiteService->publish($website);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم نشر الموقع بنجاح.');
    }

    public function unpublish(Request $request, Website $website): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');
        $this->ensureSameWorkspace($website);
        $this->authorize('publish', $website);

        $this->websiteService->unpublish($website);

        return back()->with('success', 'تم إلغاء نشر الموقع.');
    }

    private function ensureSameWorkspace(Website $website): void
    {
        abort_unless((int) $website->workspace_id === (int) $this->currentWorkspace()->id, 404);
    }
}
