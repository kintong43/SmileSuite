<?php

namespace Tests\Feature;

use App\Cms\CmsProjection;
use App\Cms\CmsUnavailable;
use App\Cms\DirectusClient;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_escape_content_and_redact_fields_from_provider(): void
    {
        config(['cms.tokens.platform' => 'platform-private-token']);
        Http::preventStrayRequests();
        Http::fake(['127.0.0.1:8055/*' => Http::response(['data' => [
            ['id' => 'one', 'tenant_key' => 'platform', 'slug' => 'about', 'title' => 'About <script>x</script>', 'body' => '<img src=x onerror=alert(1)>', 'status' => 'published', 'password' => 'CMS-secret'],
            ['tenant_key' => 'harbor', 'slug' => 'about', 'title' => 'Other clinic', 'body' => 'Private content', 'status' => 'published'],
        ]])]);

        $this->get('http://smilesuite.localhost/about')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<img src=x', false)->assertDontSee('CMS-secret')->assertDontSee('platform-private-token')->assertDontSee('Private content');
        Http::assertSent(fn ($request): bool => str_contains($request['filter'], 'platform') && $request->hasHeader('Authorization', 'Bearer platform-private-token'));
    }

    public function test_clinic_filter_and_defensive_row_filter_keep_content_scoped(): void
    {
        $tenant = Tenant::create(['name' => 'CMS Clinic', 'subdomain' => 'cms-clinic', 'timezone' => 'UTC', 'currency' => 'PHP']);
        config(['cms.tokens.clinic-cms-clinic' => 'clinic-private-token']);
        Http::preventStrayRequests();
        Http::fake(['127.0.0.1:8055/*' => Http::response(['data' => [
            ['tenant_key' => 'clinic-cms-clinic', 'slug' => 'about', 'title' => 'My clinic', 'body' => 'Safe body', 'status' => 'published'],
            ['tenant_key' => 'other', 'slug' => 'about', 'title' => 'Other clinic', 'body' => 'Wrong clinic', 'status' => 'published'],
        ]])]);

        $this->get('http://cms-clinic.smilesuite.localhost/content/about')->assertOk()->assertSee('My clinic')->assertDontSee('Wrong clinic');
        Http::assertSent(fn ($request): bool => json_decode($request['filter'], true)['tenant_key']['_eq'] === 'clinic-'.$tenant->subdomain);
    }

    public function test_clinic_named_platform_has_a_separate_scope(): void
    {
        $tenant = Tenant::create(['name' => 'Platform clinic', 'subdomain' => 'platform', 'timezone' => 'UTC', 'currency' => 'PHP']);
        $this->assertSame('platform', app(DirectusClient::class)->scope());
        $scope = app(TenantRunner::class)->run($tenant, fn (): string => app(DirectusClient::class)->scope());
        $this->assertSame('clinic-platform', $scope);
    }

    public function test_outage_redirect_and_missing_configuration_show_safe_fallback(): void
    {
        config(['cms.tokens.platform' => 'private-token']);
        Http::preventStrayRequests();
        Http::fake(['127.0.0.1:8055/*' => Http::response('private-provider-error private-token', 302, ['Location' => 'https://untrusted.invalid'])]);

        $this->get('http://smilesuite.localhost/about')->assertOk()->assertSee('Live CMS content is temporarily unavailable')->assertSee('booking remains available')->assertDontSee('private-provider-error')->assertDontSee('private-token');
        Http::assertSentCount(1);
    }

    public function test_unpublished_wrong_slug_and_expired_announcements_are_hidden(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 27));
        config(['cms.tokens.platform' => 'private-token']);
        Http::preventStrayRequests();
        Http::fake(['127.0.0.1:8055/items/ss_services*' => Http::response(['data' => []]), '127.0.0.1:8055/*' => Http::response(['data' => [
            ['tenant_key' => 'platform', 'title' => 'Draft secret', 'status' => 'draft'],
            ['tenant_key' => 'platform', 'title' => 'Expired secret', 'status' => 'published', 'publish_to' => '2020-01-01'],
            ['tenant_key' => 'platform', 'title' => 'Future secret', 'status' => 'published', 'publish_from' => '2099-01-01'],
            ['tenant_key' => 'platform', 'title' => 'Current notice', 'body' => 'Available now', 'status' => 'published'],
        ]])]);

        $this->get('http://smilesuite.localhost/announcements')->assertOk()->assertSee('Current notice')->assertDontSee('Draft secret')->assertDontSee('Expired secret')->assertDontSee('Future secret');
        $this->get('http://smilesuite.localhost/content/missing')->assertNotFound();
    }

    public function test_non_local_http_cms_is_rejected_before_sending_token(): void
    {
        config(['cms.url' => 'http://example.invalid', 'cms.tokens.platform' => 'private-token']);
        Http::preventStrayRequests();
        Http::fake();
        try {
            app(DirectusClient::class)->items('ss_pages');
            $this->fail('Untrusted HTTP CMS URL accepted.');
        } catch (CmsUnavailable $exception) {
            $this->assertStringNotContainsString('private-token', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_owner_status_requires_authentication_and_owner_role(): void
    {
        $this->seed();
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['data' => []])]);
        $this->get('http://harbor.smilesuite.localhost/cms')->assertRedirect('/login');
        $tenant = Tenant::where('subdomain', 'harbor')->firstOrFail();
        $patient = app(TenantRunner::class)->run($tenant, fn () => User::where('email', 'patient@harbor.example.test')->firstOrFail());
        $this->actingAs($patient)->get('http://harbor.smilesuite.localhost/cms')->assertForbidden();
        Http::assertNothingSent();
        $owner = app(TenantRunner::class)->run($tenant, fn () => User::where('email', 'owner@harbor.example.test')->firstOrFail());
        config(['cms.tokens.clinic-harbor' => 'owner-secret-token']);
        $this->actingAs($owner)->withSession(['mfa_confirmed' => true, 'mfa_user_id' => $owner->id])->get('http://harbor.smilesuite.localhost/cms')->assertOk()->assertSee('Directus is connected')->assertDontSee('owner-secret-token');
    }

    public function test_projection_payloads_contain_no_patient_or_authentication_data(): void
    {
        $this->seed();
        config(['cms.tokens.clinic-harbor' => 'server-only']);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['data' => []])]);
        $tenant = Tenant::where('subdomain', 'harbor')->firstOrFail();
        $counts = app(TenantRunner::class)->run($tenant, fn (): array => app(CmsProjection::class)->sync());
        $this->assertGreaterThan(0, $counts['services']);
        $this->assertGreaterThan(0, $counts['directory']);
        $this->assertSame(1, $counts['reports']);
        foreach (Http::recorded() as [$request]) {
            if ($request->method() === 'POST') {
                $serialized = json_encode($request->data(), JSON_THROW_ON_ERROR);
                foreach (['email', 'password', 'two_factor', 'patient', 'clinical', 'phone', 'tenant_id'] as $secret) {
                    $this->assertStringNotContainsString($secret, strtolower($serialized));
                }
                $this->assertArrayNotHasKey('tenant_key', $request->data());
            }
        }
        Http::assertSent(fn ($request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/items/ss_reports') && array_keys($request['aggregates']) === ['totals', 'currency']);
    }
}
