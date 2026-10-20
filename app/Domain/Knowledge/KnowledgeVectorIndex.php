<?php

namespace App\Domain\Knowledge;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class KnowledgeVectorIndex
{
    private bool $collectionReady = false;

    public function enabled(): bool
    {
        return (bool) config('knowledge.semantic_enabled') && config('knowledge.ai_api_key') && config('knowledge.qdrant_url');
    }

    public function index(int $chunkId, int $organizationId, int $spaceId, int $articleId, string $content): void
    {
        if (! $this->enabled()) return;
        $this->ensureCollection();
        $vector = $this->embed($content);
        $this->qdrant()->put('/collections/'.rawurlencode(config('knowledge.qdrant_collection')).'/points?wait=true', ['points' => [[
            'id' => $chunkId, 'vector' => $vector, 'payload' => ['organization_id' => $organizationId, 'space_id' => $spaceId, 'article_id' => $articleId],
        ]]])->throw();
    }

    /** @param array<int> $spaceIds @return array<int> */
    public function search(string $query, int $organizationId, array $spaceIds, int $limit = 8): array
    {
        if (! $this->enabled() || $spaceIds === []) return [];
        $this->ensureCollection();
        $response = $this->qdrant()->post('/collections/'.rawurlencode(config('knowledge.qdrant_collection')).'/points/search', [
            'vector' => $this->embed($query), 'limit' => $limit, 'with_payload' => false,
            'filter' => ['must' => [['key' => 'organization_id', 'match' => ['value' => $organizationId]], ['key' => 'space_id', 'match' => ['any' => array_values($spaceIds)]]]],
        ])->throw()->json('result', []);
        return collect($response)->pluck('id')->map(fn ($id) => (int) $id)->filter()->all();
    }

    public function deleteArticle(int $organizationId, int $articleId): void
    {
        if (! $this->enabled()) return;
        $this->ensureCollection();
        $this->qdrant()->post('/collections/'.rawurlencode(config('knowledge.qdrant_collection')).'/points/delete?wait=true', [
            'filter' => ['must' => [['key' => 'organization_id', 'match' => ['value' => $organizationId]], ['key' => 'article_id', 'match' => ['value' => $articleId]]]],
        ])->throw();
    }

    private function embed(string $content): array
    {
        $response = Http::withToken(config('knowledge.ai_api_key'))->acceptJson()->timeout((int) config('knowledge.ai_timeout', 30))
            ->post(rtrim(config('knowledge.ai_base_url'), '/').'/embeddings', ['model' => config('knowledge.embedding_model'), 'input' => mb_substr($content, 0, 12000)])
            ->throw()->json('data.0.embedding');
        if (! is_array($response) || $response === []) throw new RuntimeException('Knowledge embedding provider returned no vector.');
        return $response;
    }

    private function qdrant()
    {
        $url = rtrim((string) config('knowledge.qdrant_url'), '/');
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['https', 'http'], true)) throw new RuntimeException('Set a valid Qdrant HTTPS URL.');
        return Http::withHeaders(['api-key' => (string) config('knowledge.qdrant_api_key')])->acceptJson()->asJson()->timeout((int) config('knowledge.ai_timeout', 30))->baseUrl($url);
    }

    private function ensureCollection(): void
    {
        if ($this->collectionReady) return;
        $path = '/collections/'.rawurlencode(config('knowledge.qdrant_collection'));
        $response = $this->qdrant()->get($path);
        if ($response->status() === 404) $this->qdrant()->put($path, ['vectors' => ['size' => (int) config('knowledge.embedding_dimensions', 1536), 'distance' => 'Cosine']])->throw();
        elseif (! $response->successful()) $response->throw();
        $this->collectionReady = true;
    }
}
