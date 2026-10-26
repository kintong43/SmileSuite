<?php

namespace App\Cms;

use App\Tenancy\TenantContext;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class DirectusClient
{
    private const FIELDS = [
        'ss_pages' => ['id', 'tenant_key', 'slug', 'title', 'body', 'status'],
        'ss_announcements' => ['id', 'tenant_key', 'title', 'body', 'status', 'publish_from', 'publish_to'],
        'ss_services' => ['id', 'tenant_key', 'title', 'body', 'status', 'service_ref'],
        'ss_directory' => ['id', 'tenant_key', 'display_name', 'roles', 'resource_id'],
        'ss_reports' => ['id', 'tenant_key', 'period', 'aggregates'],
    ];

    public function scope(): string
    {
        return app()->bound(TenantContext::class) ? 'clinic-'.TenantContext::current()->tenant->subdomain : 'platform';
    }

    public function adminUrl(): ?string
    {
        $url = (string) config('cms.admin_url');

        return $this->validUrl($url) ? $url : null;
    }

    /** @return list<array<string, mixed>> */
    public function published(string $collection, ?string $slug = null): array
    {
        if (! in_array($collection, ['ss_pages', 'ss_announcements', 'ss_services'], true)) {
            throw new \InvalidArgumentException('Unsupported public content collection.');
        }
        $filter = ['status' => ['_eq' => 'published']];
        if ($slug !== null) {
            $filter['slug'] = ['_eq' => $slug];
        }

        return array_values(array_filter($this->items($collection, $filter), function (array $row) use ($slug): bool {
            if (($row['status'] ?? null) !== 'published' || ($slug !== null && ($row['slug'] ?? null) !== $slug)) {
                return false;
            }
            foreach (['publish_from' => true, 'publish_to' => false] as $field => $from) {
                if (! empty($row[$field])) {
                    try {
                        $date = new \DateTimeImmutable($row[$field]);
                    } catch (\Exception) {
                        return false;
                    }
                    if ($from ? $date > now() : $date < now()) {
                        return false;
                    }
                }
            }

            return true;
        }));
    }

    /** @param array<string, mixed> $filter
     * @return list<array<string, mixed>>
     */
    public function items(string $collection, array $filter = []): array
    {
        $fields = self::FIELDS[$collection] ?? throw new \InvalidArgumentException('Unsupported content collection.');
        $filter['tenant_key'] = ['_eq' => $this->scope()];
        try {
            $response = $this->request()->get('/items/'.$collection, ['filter' => json_encode($filter, JSON_THROW_ON_ERROR), 'fields' => implode(',', $fields), 'limit' => 500]);
            if (! $response->successful() || ! is_array($response->json('data'))) {
                throw new CmsUnavailable;
            }
            $rows = [];
            foreach ($response->json('data') as $row) {
                if (! is_array($row) || ($row['tenant_key'] ?? null) !== $this->scope()) {
                    continue;
                }
                $rows[] = array_intersect_key($row, array_flip($fields));
            }

            return $rows;
        } catch (\Throwable) {
            throw new CmsUnavailable;
        }
    }

    /** @param array<string, mixed> $values */
    public function upsertProjection(string $collection, string $referenceField, string $reference, array $values): void
    {
        $allowed = ['ss_services' => 'service_ref', 'ss_directory' => 'resource_id', 'ss_reports' => 'period'];
        if (($allowed[$collection] ?? null) !== $referenceField || array_diff(array_keys($values), self::FIELDS[$collection]) !== []) {
            throw new \InvalidArgumentException('Unsupported projection fields.');
        }
        $rows = $this->items($collection, [$referenceField => ['_eq' => $reference]]);
        $row = collect($rows)->first(fn (array $item): bool => ($item[$referenceField] ?? null) === $reference);
        unset($values['id'], $values['tenant_key']);
        $values[$referenceField] = $reference;
        try {
            $response = $row ? $this->request()->patch('/items/'.$collection.'/'.rawurlencode((string) $row['id']), $values) : $this->request()->post('/items/'.$collection, $values);
            if (! $response->successful()) {
                throw new CmsUnavailable;
            }
        } catch (\Throwable) {
            throw new CmsUnavailable;
        }
    }

    private function request(): PendingRequest
    {
        $url = rtrim((string) config('cms.url'), '/');
        $token = config('cms.tokens.'.$this->scope());
        if (! $this->validUrl($url) || ! is_string($token) || $token === '') {
            throw new CmsUnavailable;
        }

        return Http::baseUrl($url)->acceptJson()->asJson()->withToken($token)->timeout((int) config('cms.timeout', 4))->connectTimeout(2)->withoutRedirecting();
    }

    private function validUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return false;
        }

        return (($parts['scheme'] ?? '') === 'https' && ! empty($parts['host'])) || (app()->environment('local', 'testing') && ($parts['scheme'] ?? '') === 'http' && in_array($parts['host'] ?? '', ['localhost', '127.0.0.1', '::1', 'directus'], true));
    }
}
