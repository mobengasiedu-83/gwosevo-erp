<?php

namespace App\Domain\Knowledge;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MicrosoftKnowledgeSync
{
    /** @return array{created:int,updated:int,deleted:int,skipped:int} */
    public function sync(object $connection, int $organizationId, int $userId, DocumentTextExtractor $extractor): array
    {
        $credentials = json_decode(decrypt($connection->encrypted_credentials), true, flags: JSON_THROW_ON_ERROR);
        $tenant = config('knowledge.graph_tenant_id'); $client = config('knowledge.graph_client_id'); $secret = config('knowledge.graph_client_secret');
        if (! $tenant || ! $client || ! $secret) throw new RuntimeException('Set the Microsoft Graph tenant ID, client ID, and client secret in the environment first.');

        $token = Http::asForm()->timeout(20)->post('https://login.microsoftonline.com/'.rawurlencode($tenant).'/oauth2/v2.0/token', ['client_id' => $client, 'client_secret' => $secret, 'grant_type' => 'client_credentials', 'scope' => 'https://graph.microsoft.com/.default'])->throw()->json('access_token');
        if (! is_string($token) || $token === '') throw new RuntimeException('Microsoft identity did not return an application access token.');
        $clientApi = Http::withToken($token)->acceptJson()->timeout(30);
        $url = 'https://graph.microsoft.com/v1.0/drives/'.rawurlencode($credentials['drive_id']).'/items/'.rawurlencode($credentials['folder_id']).'/children?$select=id,name,size,lastModifiedDateTime,file,folder,eTag,@microsoft.graph.downloadUrl&$top=100';
        $files = [];
        do {
            if (! str_starts_with($url, 'https://graph.microsoft.com/')) throw new RuntimeException('Microsoft Graph returned an unexpected pagination URL.');
            $page = $clientApi->get($url)->throw()->json();
            foreach ($page['value'] ?? [] as $item) if (isset($item['file']) && isset($item['id'], $item['name'])) $files[] = $item;
            $url = $page['@odata.nextLink'] ?? null;
        } while ($url && count($files) < 1000);
        if ($url) throw new RuntimeException('The selected SharePoint folder contains more than 1,000 items. Narrow the sync folder before running a deletion-reconciling sync.');

        $counts = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0]; $seen = [];
        foreach ($files as $item) {
            $id = (string) $item['id']; $seen[] = $id; $extension = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
            if (! in_array($extension, ['pdf', 'doc', 'docx', 'txt'], true) || (int) ($item['size'] ?? 0) > 20 * 1024 * 1024 || empty($item['@microsoft.graph.downloadUrl'])) { $counts['skipped']++; continue; }
            $existing = DB::table('knowledge_articles')->where('organization_id', $organizationId)->where('source_provider', 'microsoft_graph')->where('source_connection_id', $connection->id)->where('external_item_id', $id)->first();
            $modifiedAt = $item['lastModifiedDateTime'] ?? now()->toIso8601String();
            if ($existing && $existing->external_modified_at && strtotime($modifiedAt) <= strtotime($existing->external_modified_at)) continue;
            $downloadUrl = $item['@microsoft.graph.downloadUrl']; $host = strtolower((string) parse_url($downloadUrl, PHP_URL_HOST));
            if (! str_ends_with($host, '.sharepoint.com') && ! str_ends_with($host, '.1drv.com') && ! str_ends_with($host, '.blob.core.windows.net')) { $counts['skipped']++; continue; }
            $download = Http::timeout(45)->withOptions(['allow_redirects' => false])->get($downloadUrl);
            if (! $download->successful()) { $counts['skipped']++; continue; }

            $safeName = basename(preg_replace('/[^A-Za-z0-9._ -]/', '_', $item['name']) ?: 'document.'.$extension);
            $path = 'knowledge/'.$organizationId.'/microsoft/'.hash('sha256', $id).'/'.substr(hash('sha256', $modifiedAt), 0, 16).'-'.$safeName;
            Storage::disk('local')->put($path, $download->body());
            $extracted = $extractor->extract($path, $extension);
            $title = mb_substr(pathinfo($safeName, PATHINFO_FILENAME), 0, 220); $body = $extracted['text'] ?: 'This SharePoint document was imported but could not be text-indexed. Download the attachment to review its contents.';

            if ($existing) {
                DB::table('knowledge_articles')->where('id', $existing->id)->update(['title' => $title, 'summary' => 'Imported from Microsoft 365. Source item: '.$safeName, 'body' => $body, 'version' => $existing->version + 1, 'status' => 'pending_review', 'submitted_at' => now(), 'reviewer_id' => null, 'published_at' => null, 'published_by' => null, 'external_modified_at' => $modifiedAt, 'updated_at' => now()]);
                $articleId = (int) $existing->id; $version = $existing->version + 1; $counts['updated']++;
            } else {
                $articleId = DB::table('knowledge_articles')->insertGetId(['organization_id' => $organizationId, 'space_id' => $connection->space_id, 'title' => $title, 'slug' => 'm365-'.substr(hash('sha256', $connection->id.'-'.$id), 0, 32), 'summary' => 'Imported from Microsoft 365. Source item: '.$safeName, 'body' => $body, 'status' => 'pending_review', 'version' => 1, 'source_provider' => 'microsoft_graph', 'source_connection_id' => $connection->id, 'external_item_id' => $id, 'external_modified_at' => $modifiedAt, 'author_id' => $userId, 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                $version = 1; $counts['created']++;
            }
            DB::table('knowledge_article_versions')->insert(['article_id' => $articleId, 'version' => $version, 'title' => $title, 'summary' => 'Imported from Microsoft 365. Source item: '.$safeName, 'body' => $body, 'changed_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('knowledge_documents')->insert(['organization_id' => $organizationId, 'article_id' => $articleId, 'original_name' => $safeName, 'mime_type' => $download->header('Content-Type') ?: 'application/octet-stream', 'size_bytes' => strlen($download->body()), 'storage_path' => $path, 'extraction_status' => $extracted['status'], 'extraction_note' => $extracted['note'], 'extracted_text' => $extracted['text'] ?: null, 'uploaded_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('knowledge_chunks')->where('article_id', $articleId)->delete();
            $words = preg_split('/\s+/u', trim(preg_replace('/\s+/u', ' ', strip_tags($title."\n".$body)) ?: '')) ?: [];
            foreach (array_chunk($words, 450) as $index => $chunk) DB::table('knowledge_chunks')->insert(['organization_id' => $organizationId, 'space_id' => $connection->space_id, 'article_id' => $articleId, 'chunk_index' => $index, 'content' => implode(' ', $chunk), 'created_at' => now(), 'updated_at' => now()]);
        }

        $removed = DB::table('knowledge_articles')->where('organization_id', $organizationId)->where('source_provider', 'microsoft_graph')->where('source_connection_id', $connection->id)->where('status', '!=', 'archived')->whereNotNull('external_item_id');
        if ($seen !== []) $removed->whereNotIn('external_item_id', $seen);
        else $removed->whereNotNull('external_item_id');
        $removedIds = (clone $removed)->pluck('id');
        if ($removedIds->isNotEmpty()) {
            DB::table('knowledge_articles')->whereIn('id', $removedIds)->update(['status' => 'archived', 'archived_at' => now(), 'updated_at' => now()]);
            DB::table('knowledge_chunks')->whereIn('article_id', $removedIds)->delete(); $counts['deleted'] = $removedIds->count();
            $vectors = app(KnowledgeVectorIndex::class);
            foreach ($removedIds as $removedId) try { $vectors->deleteArticle($organizationId, (int) $removedId); } catch (\Throwable) { /* Published status and database filters prevent stale results from being shown. */ }
        }
        DB::table('knowledge_sync_connections')->where('id', $connection->id)->update(['status' => 'active', 'last_synced_at' => now(), 'updated_at' => now()]);
        return $counts;
    }
}
