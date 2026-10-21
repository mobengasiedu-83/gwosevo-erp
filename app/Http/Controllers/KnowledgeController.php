<?php

namespace App\Http\Controllers;

use App\Domain\Administration\Modules;
use App\Domain\Knowledge\DocumentTextExtractor;
use App\Domain\Knowledge\KnowledgeAssistant;
use App\Domain\Knowledge\KnowledgeVectorIndex;
use App\Domain\Knowledge\MicrosoftKnowledgeSync;
use App\Domain\Administration\AdminAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class KnowledgeController extends Controller
{
    private function context(Request $request): array
    {
        abort_unless(app(Modules::class)->enabled('knowledge'), 404);
        abort_unless($request->user()->can('knowledge.search'), 403);
        $organizationId = (int) $request->user()->active_organization_id;
        abort_unless($organizationId && $request->user()->canAccessOrganization($organizationId), 403);
        return [$organizationId, $request->user()];
    }

    private function spaces(int $organizationId, $user)
    {
        return DB::table('knowledge_spaces as s')->where('s.organization_id', $organizationId)->where('s.is_active', true)
            ->where(function ($query) use ($organizationId, $user) {
                if ($user->can('knowledge.spaces.manage')) return;
                $query->where('s.scope_type', 'shared')
                    ->orWhereIn('s.id', DB::table('knowledge_space_members')->select('space_id')->where('user_id', $user->id))
                    ->orWhere(fn ($q) => $q->where('s.scope_type', 'department')->whereIn('s.department_id', DB::table('user_organization_memberships')->select('department_id')->where('user_id', $user->id)->where('organization_id', $organizationId)->whereNotNull('department_id')))
                    ->orWhere(fn ($q) => $q->where('s.scope_type', 'project')->whereIn('s.project_id', DB::table('project_members')->select('project_id')->where('user_id', $user->id)));
            });
    }

    private function articleVisible(int $organizationId, int $id, $user, bool $allowOwnDraft = false): object
    {
        $query = DB::table('knowledge_articles as a')->join('knowledge_spaces as s', 's.id', '=', 'a.space_id')
            ->where('a.organization_id', $organizationId)->where('a.id', $id)->where('s.is_active', true);
        $spaceIds = $this->spaces($organizationId, $user)->select('s.id');
        $query->whereIn('a.space_id', $spaceIds);
        if (! $user->can('knowledge.articles.review') && ! $user->can('knowledge.articles.publish') && ! $user->can('knowledge.articles.edit')) {
            $query->where(function ($q) use ($user, $allowOwnDraft) {
                $q->where('a.status', 'published');
                if ($allowOwnDraft && $user->can('knowledge.articles.create')) $q->orWhere(fn ($draft) => $draft->where('a.author_id', $user->id)->whereIn('a.status', ['draft', 'pending_review', 'changes_requested', 'approved']));
            });
        }
        $article = $query->select('a.*', 's.name as space_name')->first();
        abort_unless($article, 404);
        return $article;
    }

    public function index(Request $request)
    {
        [$organizationId, $user] = $this->context($request);
        $section = (string) $request->query('section', 'knowledge-bank');
        abort_unless(in_array($section, ['knowledge-bank', 'documents', 'ai-search', 'sources'], true), 404);
        $spaces = $this->spaces($organizationId, $user)->orderBy('s.name')->get();
        $spaceIds = $spaces->pluck('id');
        $query = trim((string) $request->query('q', ''));
        try { $semanticIds = $query !== '' ? app(KnowledgeVectorIndex::class)->search($query, $organizationId, $spaceIds->map(fn ($id) => (int) $id)->all(), 40) : []; }
        catch (Throwable $error) { Log::warning('Knowledge semantic search failed; using keyword retrieval.', ['organization_id' => $organizationId, 'message' => $error->getMessage()]); $semanticIds = []; }
        $searchMode = $semanticIds !== [] ? 'semantic' : 'keyword';
        $articles = DB::table('knowledge_articles as a')->join('knowledge_spaces as s', 's.id', '=', 'a.space_id')
            ->leftJoin('knowledge_categories as c', 'c.id', '=', 'a.category_id')->where('a.organization_id', $organizationId)->whereIn('a.space_id', $spaceIds)
            ->where(function ($q) use ($user) {
                $q->where('a.status', 'published');
                if ($user->can('knowledge.articles.review') || $user->can('knowledge.articles.publish') || $user->can('knowledge.articles.edit')) $q->orWhereIn('a.status', ['draft', 'pending_review', 'changes_requested', 'approved']);
                elseif ($user->can('knowledge.articles.create')) $q->orWhere(fn ($own) => $own->where('a.author_id', $user->id)->whereIn('a.status', ['draft', 'pending_review', 'changes_requested', 'approved']));
            })
            ->when($request->filled('space'), fn ($q) => $q->where('a.space_id', $request->integer('space')))
            ->when($query !== '', function ($q) use ($query, $semanticIds) {
                $term = '%'.$query.'%';
                $q->where(fn ($search) => $search->where('a.title', 'like', $term)->orWhere('a.summary', 'like', $term)->orWhere('a.body', 'like', $term)->orWhere('c.name', 'like', $term)
                    ->orWhereExists(fn ($docs) => $docs->selectRaw('1')->from('knowledge_documents as d')->whereColumn('d.article_id', 'a.id')->where('d.extracted_text', 'like', $term))
                    ->when($semanticIds !== [], fn ($semantic) => $semantic->orWhereIn('a.id', DB::table('knowledge_chunks')->whereIn('id', $semanticIds)->select('article_id'))));
            })
            ->select('a.id', 'a.space_id', 'a.title', 'a.slug', 'a.summary', 'a.status', 'a.version', 'a.author_id', 'a.published_at', 'a.updated_at', 's.name as space_name', 'c.name as category_name')
            ->orderByRaw("CASE a.status WHEN 'published' THEN 0 ELSE 1 END")->orderByDesc('a.updated_at')->paginate(20)->withQueryString();

        if ($query !== '') {
            DB::table('knowledge_search_events')->insert(['organization_id' => $organizationId, 'user_id' => $user->id, 'query_hash' => hash('sha256', mb_strtolower($query)), 'query_text' => mb_substr($query, 0, 500), 'result_count' => $articles->total(), 'search_mode' => $searchMode, 'created_at' => now(), 'updated_at' => now()]);
        }

        $documents = DB::table('knowledge_documents as d')->join('knowledge_articles as a', 'a.id', '=', 'd.article_id')->join('knowledge_spaces as s', 's.id', '=', 'a.space_id')
            ->where('d.organization_id', $organizationId)->whereIn('a.space_id', $spaceIds)
            ->where(function ($visibility) use ($user) {
                if ($user->can('knowledge.articles.review') || $user->can('knowledge.articles.publish') || $user->can('knowledge.articles.edit')) return;
                $visibility->where('a.status', 'published');
                if ($user->can('knowledge.articles.create')) $visibility->orWhere(fn ($own) => $own->where('a.author_id', $user->id)->whereIn('a.status', ['draft', 'pending_review', 'changes_requested', 'approved']));
            })
            ->select('d.*', 'a.title as article_title', 'a.status as article_status', 'a.id as article_id', 's.name as space_name')->orderByDesc('d.created_at')->paginate(30)->withQueryString();
        return view('knowledge.index', [
            'section' => $section, 'documentsList' => $documents,
            'spaces' => $spaces, 'articles' => $articles, 'categories' => DB::table('knowledge_categories')->where('organization_id', $organizationId)->orderBy('name')->get(),
            'canManageSpaces' => $user->can('knowledge.spaces.manage'), 'canCreate' => $user->can('knowledge.articles.create'),
            'canUpload' => $user->can('knowledge.documents.upload'), 'aiEnabled' => config('knowledge.ai_enabled') && (bool) config('knowledge.ai_api_key'),
            'graphConfigured' => (bool) (config('knowledge.graph_tenant_id') && config('knowledge.graph_client_id') && config('knowledge.graph_client_secret')),
            'syncConnections' => $user->can('knowledge.sync.manage') ? DB::table('knowledge_sync_connections')->where('organization_id', $organizationId)->orderBy('name')->get() : collect(),
            'syncLogs' => $user->can('knowledge.sync.manage') ? DB::table('knowledge_sync_logs as l')->join('knowledge_sync_connections as c', 'c.id', '=', 'l.connection_id')->where('c.organization_id', $organizationId)->select('l.*', 'c.name as connection_name')->orderByDesc('l.created_at')->limit(10)->get() : collect(),
            'stats' => ['published' => DB::table('knowledge_articles')->where('organization_id', $organizationId)->where('status', 'published')->whereIn('space_id', $spaceIds)->count(),
                'drafts' => $user->can('knowledge.articles.edit') || $user->can('knowledge.articles.review') ? DB::table('knowledge_articles')->where('organization_id', $organizationId)->whereIn('status', ['draft', 'pending_review', 'changes_requested'])->whereIn('space_id', $spaceIds)->count() : 0,
                'documents' => DB::table('knowledge_documents')->where('organization_id', $organizationId)->whereIn('article_id', DB::table('knowledge_articles')->whereIn('space_id', $spaceIds)->select('id'))->count()],
        ]);
    }

    public function section(Request $request, string $section)
    {
        abort_unless(in_array($section, ['documents', 'ai-search', 'sources'], true), 404);
        if ($section === 'documents') abort_unless($request->user()->can('knowledge.documents.view'), 403);
        if ($section === 'sources') abort_unless($request->user()->can('knowledge.sync.manage'), 403);
        $request->query->set('section', $section);
        return $this->index($request);
    }

    public function show(Request $request, int $article)
    {
        [$organizationId, $user] = $this->context($request); $item = $this->articleVisible($organizationId, $article, $user, true);
        $documents = DB::table('knowledge_documents')->where('organization_id', $organizationId)->where('article_id', $article)->orderBy('original_name')->get();
        $tags = DB::table('knowledge_article_tags as at')->join('knowledge_tags as t', 't.id', '=', 'at.tag_id')->where('at.article_id', $article)->orderBy('t.name')->pluck('t.name');
        $versions = DB::table('knowledge_article_versions')->where('article_id', $article)->orderByDesc('version')->get();
        $feedback = DB::table('knowledge_feedback')->where('article_id', $article)->where('user_id', $user->id)->first();
        return view('knowledge.show', compact('item', 'documents', 'tags', 'versions', 'feedback'));
    }

    public function createSpace(Request $request)
    {
        [$organizationId, $user] = $this->context($request); abort_unless($user->can('knowledge.spaces.manage'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:3000'], 'scope_type' => ['required', Rule::in(['shared', 'department', 'project', 'restricted'])], 'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('organization_id', $organizationId)], 'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('organization_id', $organizationId)], 'member_ids_text' => ['nullable', 'string', 'max:2000']]);
        abort_if($data['scope_type'] === 'department' && empty($data['department_id']), 422, 'Choose the department for this knowledge space.');
        abort_if($data['scope_type'] === 'project' && empty($data['project_id']), 422, 'Choose the project for this knowledge space.');
        $memberIds = collect(preg_split('/\D+/', $data['member_ids_text'] ?? '') ?: [])->filter()->map(fn ($id) => (int) $id)->unique();
        $validIds = collect();
        if ($memberIds->isNotEmpty()) {
            $validIds = DB::table('user_organization_memberships')->where('organization_id', $organizationId)->whereIn('user_id', $memberIds)->pluck('user_id')->map(fn ($id) => (int) $id);
            abort_unless($validIds->count() === $memberIds->count(), 422, 'Every space member must belong to the active organization.');
        }
        $slug = Str::slug($data['name']); $base = $slug; $i = 2;
        while (DB::table('knowledge_spaces')->where('organization_id', $organizationId)->where('slug', $slug)->exists()) $slug = $base.'-'.$i++;
        $id = DB::table('knowledge_spaces')->insertGetId(['organization_id' => $organizationId, 'name' => $data['name'], 'slug' => $slug, 'scope_type' => $data['scope_type'], 'department_id' => $data['department_id'] ?? null, 'project_id' => $data['project_id'] ?? null, 'description' => $data['description'] ?? null, 'is_active' => true, 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        if ($memberIds->isNotEmpty()) {
            foreach ($validIds as $memberId) DB::table('knowledge_space_members')->insert(['space_id' => $id, 'user_id' => $memberId, 'access_level' => 'reader', 'created_at' => now(), 'updated_at' => now()]);
        }
        AdminAudit::record('knowledge.space.created', 'knowledge_spaces', $id, null, ['name' => $data['name'], 'scope_type' => $data['scope_type']], $organizationId);
        return back()->with('success', 'Knowledge space created.');
    }

    public function createCategory(Request $request)
    {
        [$organizationId, $user] = $this->context($request); abort_unless($user->can('knowledge.spaces.manage'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]); $slug = Str::slug($data['name']);
        abort_if($slug === '', 422, 'Enter a category name that contains letters or numbers.');
        DB::table('knowledge_categories')->updateOrInsert(['organization_id' => $organizationId, 'slug' => $slug], ['name' => $data['name'], 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Knowledge category saved.');
    }

    public function saveSync(Request $request)
    {
        [$organizationId, $user] = $this->context($request); abort_unless($user->can('knowledge.sync.manage'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'space_id' => ['required', 'integer'], 'drive_id' => ['required', 'string', 'max:255'], 'folder_id' => ['required', 'string', 'max:255']]);
        abort_unless($this->spaces($organizationId, $user)->where('s.id', $data['space_id'])->exists(), 404);
        $credentials = Crypt::encryptString(json_encode(['drive_id' => $data['drive_id'], 'folder_id' => $data['folder_id']], JSON_THROW_ON_ERROR));
        DB::table('knowledge_sync_connections')->insert(['organization_id' => $organizationId, 'space_id' => $data['space_id'], 'provider' => 'microsoft_graph', 'name' => $data['name'], 'encrypted_credentials' => $credentials, 'status' => 'disabled', 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Microsoft 365 sync mapping saved encrypted and disabled. Run a sync to import files as drafts requiring local review.');
    }

    public function syncMicrosoft(Request $request, int $connection, MicrosoftKnowledgeSync $sync, DocumentTextExtractor $extractor)
    {
        [$organizationId, $user] = $this->context($request); abort_unless($user->can('knowledge.sync.manage'), 403);
        $profile = DB::table('knowledge_sync_connections')->where('organization_id', $organizationId)->where('id', $connection)->where('provider', 'microsoft_graph')->first() ?: abort(404);
        try {
            $counts = $sync->sync($profile, $organizationId, $user->id, $extractor);
            DB::table('knowledge_sync_logs')->insert(['connection_id' => $profile->id, 'status' => 'success', 'created_count' => $counts['created'], 'updated_count' => $counts['updated'], 'deleted_count' => $counts['deleted'], 'message' => 'Sync complete; '.$counts['skipped'].' unsupported files skipped.', 'created_at' => now(), 'updated_at' => now()]);
            AdminAudit::record('knowledge.microsoft_sync.completed', 'knowledge_sync_connections', $profile->id, null, $counts, $organizationId);
            return back()->with('success', 'Microsoft 365 sync completed: '.$counts['created'].' imported, '.$counts['updated'].' updated, '.$counts['deleted'].' archived, '.$counts['skipped'].' skipped. Imported or changed articles need review.');
        } catch (Throwable $error) {
            DB::table('knowledge_sync_logs')->insert(['connection_id' => $profile->id, 'status' => 'failed', 'message' => mb_substr($error->getMessage(), 0, 2000), 'created_at' => now(), 'updated_at' => now()]);
            Log::warning('Microsoft 365 knowledge sync failed.', ['connection_id' => $profile->id, 'organization_id' => $organizationId, 'message' => $error->getMessage()]);
            return back()->withErrors(['knowledge_sync' => 'Microsoft 365 sync failed: '.$error->getMessage()]);
        }
    }

    public function createArticle(Request $request)
    {
        [$organizationId, $user] = $this->context($request); abort_unless($user->can('knowledge.articles.create'), 403);
        $data = $request->validate(['space_id' => ['required', 'integer'], 'category_id' => ['nullable', 'integer', Rule::exists('knowledge_categories', 'id')->where('organization_id', $organizationId)], 'title' => ['required', 'string', 'max:220'], 'summary' => ['nullable', 'string', 'max:3000'], 'body' => ['required', 'string', 'max:500000'], 'tags' => ['nullable', 'string', 'max:1000']]);
        abort_unless($this->spaces($organizationId, $user)->where('s.id', $data['space_id'])->exists(), 404);
        $slug = Str::slug($data['title']).'-'.Str::lower(Str::random(5));
        $id = DB::table('knowledge_articles')->insertGetId(['organization_id' => $organizationId, 'space_id' => $data['space_id'], 'category_id' => $data['category_id'] ?? null, 'title' => $data['title'], 'slug' => $slug, 'summary' => $data['summary'] ?? null, 'body' => $data['body'], 'status' => 'draft', 'version' => 1, 'author_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('knowledge_article_versions')->insert(['article_id' => $id, 'version' => 1, 'title' => $data['title'], 'summary' => $data['summary'] ?? null, 'body' => $data['body'], 'changed_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->syncTags($organizationId, $id, $data['tags'] ?? '');
        $this->indexChunks($organizationId, (int) $data['space_id'], $id, $data['title']."\n".($data['summary'] ?? '')."\n".$data['body']);
        AdminAudit::record('knowledge.article.created', 'knowledge_articles', $id, null, ['title' => $data['title'], 'space_id' => $data['space_id']], $organizationId);
        return redirect()->route('knowledge.index')->with('success', 'Draft article created.');
    }

    public function updateArticle(Request $request, int $article)
    {
        [$organizationId, $user] = $this->context($request); $current = $this->articleVisible($organizationId, $article, $user, true);
        abort_unless($user->can('knowledge.articles.edit') || ((int) $current->author_id === (int) $user->id && $user->can('knowledge.articles.create')), 403);
        abort_if($current->status === 'archived', 422, 'Archived articles cannot be edited.');
        $data = $request->validate(['category_id' => ['nullable', 'integer', Rule::exists('knowledge_categories', 'id')->where('organization_id', $organizationId)], 'title' => ['required', 'string', 'max:220'], 'summary' => ['nullable', 'string', 'max:3000'], 'body' => ['required', 'string', 'max:500000'], 'tags' => ['nullable', 'string', 'max:1000']]);
        $version = $current->version + 1;
        DB::transaction(function () use ($current, $data, $version, $user, $organizationId, $article) {
            DB::table('knowledge_articles')->where('id', $article)->update(['category_id' => $data['category_id'] ?? null, 'title' => $data['title'], 'summary' => $data['summary'] ?? null, 'body' => $data['body'], 'version' => $version, 'status' => 'draft', 'submitted_at' => null, 'reviewer_id' => null, 'published_at' => null, 'published_by' => null, 'updated_at' => now()]);
            DB::table('knowledge_article_versions')->insert(['article_id' => $article, 'version' => $version, 'title' => $data['title'], 'summary' => $data['summary'] ?? null, 'body' => $data['body'], 'changed_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->syncTags($organizationId, $article, $data['tags'] ?? '');
            $this->indexChunks($organizationId, (int) $current->space_id, $article, $data['title']."\n".($data['summary'] ?? '')."\n".$data['body']);
        });
        AdminAudit::record('knowledge.article.updated', 'knowledge_articles', $article, ['version' => $current->version, 'status' => $current->status], ['version' => $version, 'status' => 'draft'], $organizationId);
        return back()->with('success', 'Article saved as a new draft version. It must be reviewed and published again.');
    }

    public function upload(Request $request, int $article, DocumentTextExtractor $extractor)
    {
        [$organizationId, $user] = $this->context($request); abort_unless($user->can('knowledge.documents.upload'), 403);
        $item = $this->articleVisible($organizationId, $article, $user, true);
        abort_unless(in_array($item->status, ['draft', 'changes_requested'], true), 422, 'Return the article to draft before changing its attached documents so the updated content can be reviewed.');
        abort_unless($user->can('knowledge.articles.edit') || ((int) $item->author_id === (int) $user->id && $user->can('knowledge.articles.create')), 403);
        $data = $request->validate(['document' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,txt']]); $file = $data['document'];
        $path = $file->store('knowledge/'.$organizationId.'/'.$article, 'local');
        $extracted = $extractor->extract($path, strtolower($file->getClientOriginalExtension()));
        DB::table('knowledge_documents')->insert(['organization_id' => $organizationId, 'article_id' => $article, 'original_name' => basename($file->getClientOriginalName()), 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size_bytes' => $file->getSize(), 'storage_path' => $path, 'extraction_status' => $extracted['status'], 'extraction_note' => $extracted['note'], 'extracted_text' => $extracted['text'] ?: null, 'uploaded_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        if ($extracted['text'] !== '') {
            $allDocumentText = DB::table('knowledge_documents')->where('article_id', $article)->whereNotNull('extracted_text')->pluck('extracted_text')->implode("\n");
            $this->indexChunks($organizationId, (int) $item->space_id, $article, $item->title."\n".$item->body."\n".$allDocumentText);
        }
        AdminAudit::record('knowledge.document.uploaded', 'knowledge_articles', $article, null, ['name' => basename($file->getClientOriginalName()), 'extraction_status' => $extracted['status']], $organizationId);
        return back()->with('success', 'Document stored privately.'.($extracted['note'] ? ' '.$extracted['note'] : ' Text has been added to the search index.'));
    }

    public function download(Request $request, int $document)
    {
        [$organizationId, $user] = $this->context($request); abort_unless($user->can('knowledge.documents.view'), 403);
        $item = DB::table('knowledge_documents as d')->join('knowledge_articles as a', 'a.id', '=', 'd.article_id')->where('d.id', $document)->where('d.organization_id', $organizationId)->select('d.*', 'a.id as article_id')->first() ?: abort(404);
        $this->articleVisible($organizationId, (int) $item->article_id, $user);
        abort_unless(Storage::disk('local')->exists($item->storage_path), 404);
        return Storage::disk('local')->download($item->storage_path, $item->original_name, ['Content-Type' => $item->mime_type, 'X-Content-Type-Options' => 'nosniff']);
    }

    public function submit(Request $request, int $article)
    {
        [$organizationId, $user] = $this->context($request); $item = $this->articleVisible($organizationId, $article, $user, true);
        abort_unless($user->can('knowledge.articles.edit') || ((int) $item->author_id === (int) $user->id && $user->can('knowledge.articles.create')), 403);
        abort_unless(in_array($item->status, ['draft', 'changes_requested'], true), 422);
        DB::table('knowledge_articles')->where('id', $article)->update(['status' => 'pending_review', 'submitted_at' => now(), 'updated_at' => now()]);
        AdminAudit::record('knowledge.article.submitted', 'knowledge_articles', $article, ['status' => $item->status], ['status' => 'pending_review'], $organizationId);
        return back()->with('success', 'Article submitted for review.');
    }

    public function review(Request $request, int $article)
    {
        [$organizationId, $user] = $this->context($request); abort_unless($user->can('knowledge.articles.review'), 403); $item = $this->articleVisible($organizationId, $article, $user, true);
        abort_unless($item->status === 'pending_review' && (int) $item->author_id !== (int) $user->id, 422, 'Only another person can review a submitted article.');
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'changes_requested'])], 'review_note' => ['nullable', 'string', 'max:2000']]);
        DB::table('knowledge_articles')->where('id', $article)->update(['status' => $data['decision'], 'reviewer_id' => $user->id, 'updated_at' => now()]);
        AdminAudit::record('knowledge.article.reviewed', 'knowledge_articles', $article, ['status' => 'pending_review'], ['status' => $data['decision'], 'note' => $data['review_note'] ?? null], $organizationId);
        return back()->with('success', $data['decision'] === 'approved' ? 'Article approved. A publisher can publish it.' : 'Changes requested. The author can edit and resubmit.');
    }

    public function publish(Request $request, int $article)
    {
        [$organizationId, $user] = $this->context($request); abort_unless($user->can('knowledge.articles.publish'), 403); $item = $this->articleVisible($organizationId, $article, $user, true);
        abort_unless($item->status === 'approved' && (int) $item->reviewer_id !== (int) $user->id && (int) $item->author_id !== (int) $user->id, 422, 'A different reviewer must approve the article before publication.');
        DB::table('knowledge_articles')->where('id', $article)->update(['status' => 'published', 'published_by' => $user->id, 'published_at' => now(), 'updated_at' => now()]);
        $this->syncVectors($organizationId, $article);
        AdminAudit::record('knowledge.article.published', 'knowledge_articles', $article, ['status' => 'approved'], ['status' => 'published'], $organizationId);
        return back()->with('success', 'Article published to its knowledge space.');
    }

    public function archive(Request $request, int $article)
    {
        [$organizationId, $user] = $this->context($request); abort_unless($user->can('knowledge.articles.archive'), 403); $item = $this->articleVisible($organizationId, $article, $user, true);
        DB::table('knowledge_articles')->where('id', $article)->update(['status' => 'archived', 'archived_at' => now(), 'updated_at' => now()]);
        DB::table('knowledge_chunks')->where('article_id', $article)->delete();
        try { app(KnowledgeVectorIndex::class)->deleteArticle($organizationId, $article); }
        catch (Throwable $error) { Log::warning('Knowledge vector deletion failed; database access filters remain active.', ['article_id' => $article, 'message' => $error->getMessage()]); }
        AdminAudit::record('knowledge.article.archived', 'knowledge_articles', $article, ['status' => $item->status], ['status' => 'archived'], $organizationId);
        return back()->with('success', 'Article archived and removed from search.');
    }

    public function ask(Request $request, KnowledgeAssistant $assistant)
    {
        [$organizationId, $user] = $this->context($request); abort_unless($user->can('knowledge.ai.ask'), 403);
        $data = $request->validate(['question' => ['required', 'string', 'max:1000']]);
        $matches = $this->matches($organizationId, $user, $data['question'], 6);
        $sources = $matches->map(fn ($row) => ['title' => $row->title, 'slug' => $row->slug, 'space' => $row->space_name, 'content' => mb_substr($row->content, 0, 5000)])->all();
        try { $answer = $assistant->answer($data['question'], $sources); }
        catch (Throwable $error) { return back()->withErrors(['question' => $error->getMessage()])->withInput(); }
        return back()->with('knowledge_answer', $answer)->with('knowledge_sources', $matches->map(fn ($row) => ['title' => $row->title, 'space' => $row->space_name, 'route' => route('knowledge.articles.show', $row->id)])->all())->withInput();
    }

    public function feedback(Request $request, int $article)
    {
        [$organizationId, $user] = $this->context($request); $this->articleVisible($organizationId, $article, $user);
        abort_unless($user->can('knowledge.feedback'), 403); $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'comment' => ['nullable', 'string', 'max:2000']]);
        DB::table('knowledge_feedback')->updateOrInsert(['article_id' => $article, 'user_id' => $user->id], ['organization_id' => $organizationId, 'rating' => $data['rating'], 'comment' => $data['comment'] ?? null, 'updated_at' => now(), 'created_at' => now()]);
        return back()->with('success', 'Thank you for rating this knowledge article.');
    }

    public function analytics(Request $request)
    {
        [$organizationId, $user] = $this->context($request); abort_unless($user->can('knowledge.analytics.view'), 403);
        $events = DB::table('knowledge_search_events')->where('organization_id', $organizationId);
        return view('knowledge.analytics', ['totalSearches' => (clone $events)->where('created_at', '>=', now()->subDays(30))->count(), 'unanswered' => (clone $events)->where('created_at', '>=', now()->subDays(30))->where('result_count', 0)->count(), 'topQueries' => (clone $events)->select('query_hash', 'query_text', DB::raw('count(*) as searches'), DB::raw('max(result_count) as max_results'))->where('created_at', '>=', now()->subDays(30))->groupBy('query_hash', 'query_text')->orderByDesc('searches')->limit(20)->get(), 'feedback' => DB::table('knowledge_feedback as f')->join('knowledge_articles as a', 'a.id', '=', 'f.article_id')->where('f.organization_id', $organizationId)->select('a.title', DB::raw('avg(f.rating) as rating'), DB::raw('count(*) as votes'))->groupBy('a.id', 'a.title')->orderByDesc('votes')->limit(20)->get()]);
    }

    private function syncTags(int $organizationId, int $articleId, string $raw): void
    {
        DB::table('knowledge_article_tags')->where('article_id', $articleId)->delete();
        foreach (array_unique(array_filter(array_map(fn ($tag) => trim($tag), explode(',', $raw)))) as $name) {
            $slug = Str::slug($name); if ($slug === '') continue;
            DB::table('knowledge_tags')->updateOrInsert(['organization_id' => $organizationId, 'slug' => $slug], ['name' => mb_substr($name, 0, 80), 'created_at' => now(), 'updated_at' => now()]);
            $tagId = DB::table('knowledge_tags')->where('organization_id', $organizationId)->where('slug', $slug)->value('id');
            DB::table('knowledge_article_tags')->insertOrIgnore(['article_id' => $articleId, 'tag_id' => $tagId]);
        }
    }

    private function indexChunks(int $organizationId, int $spaceId, int $articleId, string $content): void
    {
        DB::table('knowledge_chunks')->where('article_id', $articleId)->delete();
        $content = trim(preg_replace('/\s+/u', ' ', strip_tags($content)) ?: ''); if ($content === '') return;
        foreach (array_chunk(preg_split('/\s+/u', $content) ?: [], 450) as $index => $words) {
            DB::table('knowledge_chunks')->insert(['organization_id' => $organizationId, 'space_id' => $spaceId, 'article_id' => $articleId, 'chunk_index' => $index, 'content' => implode(' ', $words), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function matches(int $organizationId, $user, string $question, int $limit = 8)
    {
        $terms = collect(preg_split('/[^\pL\pN]+/u', mb_strtolower($question)) ?: [])->filter(fn ($term) => mb_strlen($term) > 2)->unique()->take(12);
        if ($terms->isEmpty()) $terms = collect([mb_strtolower($question)]);
        $spaceIds = $this->spaces($organizationId, $user)->select('s.id');
        $spaceIdList = $this->spaces($organizationId, $user)->pluck('s.id')->map(fn ($id) => (int) $id)->all();
        try {
            $vectorIds = app(KnowledgeVectorIndex::class)->search($question, $organizationId, $spaceIdList, $limit);
            if ($vectorIds !== []) return DB::table('knowledge_chunks as k')->join('knowledge_articles as a', 'a.id', '=', 'k.article_id')->join('knowledge_spaces as s', 's.id', '=', 'k.space_id')
                ->whereIn('k.id', $vectorIds)->where('k.organization_id', $organizationId)->whereIn('k.space_id', $spaceIds)->where('a.status', 'published')
                ->select('a.id', 'a.title', 'a.slug', 's.name as space_name', 'k.content')->get();
        } catch (Throwable $error) { Log::warning('Knowledge semantic search failed; falling back to keyword retrieval.', ['organization_id' => $organizationId, 'message' => $error->getMessage()]); }
        return DB::table('knowledge_chunks as k')->join('knowledge_articles as a', 'a.id', '=', 'k.article_id')->join('knowledge_spaces as s', 's.id', '=', 'k.space_id')
            ->where('k.organization_id', $organizationId)->whereIn('k.space_id', $spaceIds)->where('a.status', 'published')
            ->where(function ($query) use ($terms) { foreach ($terms as $term) $query->orWhere('k.content', 'like', '%'.$term.'%'); })
            ->select('a.id', 'a.title', 'a.slug', 's.name as space_name', 'k.content')
            ->selectRaw('('.implode(' + ', array_fill(0, $terms->count(), 'CASE WHEN LOWER(k.content) LIKE ? THEN 1 ELSE 0 END')).') as relevance', $terms->map(fn ($term) => '%'.$term.'%')->all())
            ->orderByDesc('relevance')->limit($limit)->get();
    }

    private function syncVectors(int $organizationId, int $articleId): void
    {
        try {
            $vectors = app(KnowledgeVectorIndex::class); $vectors->deleteArticle($organizationId, $articleId);
            $chunks = DB::table('knowledge_chunks')->where('organization_id', $organizationId)->where('article_id', $articleId)->get();
            foreach ($chunks as $chunk) $vectors->index((int) $chunk->id, $organizationId, (int) $chunk->space_id, $articleId, $chunk->content);
        } catch (Throwable $error) {
            Log::warning('Knowledge semantic indexing failed; keyword search remains available.', ['organization_id' => $organizationId, 'article_id' => $articleId, 'message' => $error->getMessage()]);
        }
    }
}
