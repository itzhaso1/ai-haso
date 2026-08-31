<?php

namespace Tests\Feature\Feature\Appointments;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Website\Website;
use App\Models\Website\WebsiteDomain;
use App\Models\Workspace;
use App\Services\Website\TemplateService;
use App\Services\Website\WebsiteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class WebsiteBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_owner_can_create_customize_and_publish_website(): void
    {
        config()->set('website.platform_domain', 'platform.test');
        [, $workspace] = $this->createWorkspaceOwnerWithWebsiteFeatures('company');

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.website.store'), [
                'name' => 'Clinic Website',
                'slug' => 'clinic-website',
            ])
            ->assertRedirect();

        $website = Website::withoutGlobalScopes()->where('workspace_id', $workspace->id)->firstOrFail();
        $this->assertSame('draft', $website->status);

        $platformDomain = WebsiteDomain::withoutGlobalScopes()
            ->where('website_id', $website->id)
            ->where('type', 'platform_subdomain')
            ->first();
        $this->assertNotNull($platformDomain);
        $this->assertTrue((bool) $platformDomain->is_primary);

        $template = app(TemplateService::class)->listTemplates()->first();
        $this->assertNotNull($template);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.website.templates.select', $website), [
                'template_id' => $template->id,
            ])
            ->assertRedirect(route('workspace.appointments.website.customize', $website));

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.website.customize.update', $website), [
                'business_name' => 'Clinic Name',
                'hero_title' => 'Book Your Appointment',
                'hero_description' => 'Simple and secure booking flow.',
                'cta_text' => 'Book now',
                'about_text' => 'About section text',
                'primary_color' => '#0f766e',
                'secondary_color' => '#14b8a6',
                'font' => 'Cairo',
                'direction' => 'rtl',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.website.publish', $website))
            ->assertRedirect()
            ->assertSessionHas('success');

        $website->refresh();
        $this->assertSame('published', $website->status);
        $this->assertNotNull($website->published_at);
    }

    public function test_workspace_cannot_access_another_workspace_website_builder_resources(): void
    {
        config()->set('website.platform_domain', 'platform.test');
        [$ownerA, $workspaceA] = $this->createWorkspaceOwnerWithWebsiteFeatures('company');
        [$ownerB, $workspaceB] = $this->createWorkspaceOwnerWithWebsiteFeatures('company');

        $website = Website::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceA->id,
            'name' => 'Private Site',
            'slug' => 'private-site',
            'status' => 'draft',
            'preview_token' => 'preview-token-private-site',
            'settings' => [],
            'theme' => [],
            'metadata' => [],
        ]);

        $this->actingAs($ownerB)
            ->withSession(['current_workspace_id' => $workspaceB->id])
            ->get(route('workspace.appointments.website.customize', $website))
            ->assertNotFound();
    }

    public function test_publish_requires_active_or_verified_domain_when_platform_subdomain_not_configured(): void
    {
        config()->set('website.platform_domain', '');
        [$owner, $workspace] = $this->createWorkspaceOwnerWithWebsiteFeatures('company');

        /** @var WebsiteService $websiteService */
        $websiteService = app(WebsiteService::class);
        $website = $websiteService->createWebsite($workspace, [
            'name' => 'No Domain Website',
            'slug' => 'no-domain-site',
        ]);
        $template = app(TemplateService::class)->listTemplates()->firstOrFail();
        $websiteService->selectTemplate($website, $template->id);
        $websiteService->updateSettings($website, ['business_name' => 'No Domain Business']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('At least one verified or active domain is required for publishing.');

        $websiteService->publish($website->fresh());
    }

    /**
     * @return array{0: User, 1: Workspace}
     */
    private function createWorkspaceOwnerWithWebsiteFeatures(string $workspaceType): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $user->id,
            'type' => $workspaceType,
        ]);

        $workspace->users()->attach($user->id, [
            'membership_role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $plan = Plan::query()->create([
            'code' => 'plan-'.$workspace->id.'-'.uniqid(),
            'name' => 'Workspace Plan',
            'workspace_type' => $workspaceType,
            'billing_period' => 'monthly',
            'currency' => 'USD',
            'price' => 99,
            'is_active' => true,
            'features' => ['appointments', 'website_builder', 'custom_domains', 'public_booking'],
            'limits' => [],
        ]);

        Subscription::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'current_period_start' => now()->subDay(),
            'current_period_end' => now()->addMonth(),
            'metadata' => [],
        ]);

        return [$user, $workspace];
    }
}
