<x-layouts.app>
<div class="page-heading"><div><p class="eyebrow">Knowledge &amp; Intelligence / Module 18</p><h1>Knowledge Bank</h1><p class="muted">Find approved guidance, policies and team knowledge.</p></div></div>

<div class="stats-grid">
    <div class="card"><span class="muted">Published articles</span><strong>{{ number_format($stats['published']) }}</strong></div>
    <div class="card"><span class="muted">Drafts and reviews</span><strong>{{ number_format($stats['drafts']) }}</strong></div>
    <div class="card"><span class="muted">Attached documents</span><strong>{{ number_format($stats['documents']) }}</strong></div>
    <div class="card"><span class="muted">Knowledge spaces</span><strong>{{ number_format($spaces->count()) }}</strong></div>
</div>

@if($aiEnabled && auth()->user()->can('knowledge.ai.ask'))
<section class="card"><h2>Ask the knowledge assistant</h2><p class="muted">Answers use published sources from spaces you can access and include source links.</p>
    <form method="post" action="{{ route('knowledge.ask') }}" class="stack-form">@csrf<label>Your question<textarea name="question" rows="3" maxlength="1000" required>{{ old('question') }}</textarea></label><button class="button button-primary">Ask</button></form>
    @if(session('knowledge_answer'))<div class="knowledge-answer"><h3>Answer</h3><p>{{ session('knowledge_answer') }}</p>@if(session('knowledge_sources'))<h3>Sources</h3><ul>@foreach(session('knowledge_sources') as $source)<li><a href="{{ $source['route'] }}">{{ $source['title'] }}</a> <span class="muted">· {{ $source['space'] }}</span></li>@endforeach</ul>@endif</div>@endif
</section>
@endif

<section class="card"><div class="section-heading"><div><h2>Search knowledge</h2><p class="muted">Search article text, categories and extracted document text.</p></div>@can('knowledge.analytics.view')<a class="text-link" href="{{ route('knowledge.analytics') }}">Usage analytics</a>@endcan</div>
    <form method="get" action="{{ route('knowledge.index') }}" class="form-grid"><label>Keywords<input name="q" value="{{ request('q') }}" maxlength="200" placeholder="Search policies, processes, how-to guides"></label><label>Knowledge space<select name="space"><option value="">All spaces you can access</option>@foreach($spaces as $space)<option value="{{ $space->id }}" @selected((int)request('space')===$space->id)>{{ $space->name }}</option>@endforeach</select></label><button class="button button-primary">Search</button></form>
</section>

<div class="content-grid">
    <section class="card"><h2>Articles</h2><div class="table-wrap"><table><thead><tr><th>Article</th><th>Space</th><th>Category</th><th>Status</th><th>Updated</th></tr></thead><tbody>
    @forelse($articles as $article)<tr><td><a href="{{ route('knowledge.articles.show', $article->id) }}"><strong>{{ $article->title }}</strong></a>@if($article->summary)<br><span class="muted">{{ \Illuminate\Support\Str::limit($article->summary, 110) }}</span>@endif</td><td>{{ $article->space_name }}</td><td>{{ $article->category_name ?: '—' }}</td><td><span class="status-badge">{{ str_replace('_', ' ', ucfirst($article->status)) }}</span></td><td>{{ $article->updated_at }}</td></tr>
    @empty<tr><td colspan="5" class="empty-state">No articles match. Create a space or draft an article to begin.</td></tr>@endforelse
    </tbody></table></div><div class="mt-4">{{ $articles->links() }}</div></section>

    <div>
    @if($canCreate)<section class="card"><h2>Write an article</h2><p class="muted">New articles are drafts. A different reviewer must approve them before publication.</p>
        <form method="post" action="{{ route('knowledge.articles.store') }}" class="stack-form">@csrf<label>Knowledge space<select name="space_id" required><option value="">Choose a space</option>@foreach($spaces as $space)<option value="{{ $space->id }}">{{ $space->name }}</option>@endforeach</select></label><label>Title<input name="title" maxlength="220" required></label><label>Category<select name="category_id"><option value="">No category</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></label><label>Summary<textarea name="summary" rows="2" maxlength="3000"></textarea></label><label>Article body<textarea name="body" rows="9" maxlength="500000" required></textarea></label><label>Tags <span class="muted">(comma separated)</span><input name="tags" maxlength="1000" placeholder="security, onboarding, policy"></label><button class="button button-primary">Save draft</button></form>
    </section>@endif

    @if($canManageSpaces)<section class="card"><h2>Create a knowledge space</h2><form method="post" action="{{ route('knowledge.spaces.store') }}" class="stack-form">@csrf<label>Name<input name="name" maxlength="160" required></label><label>Description<textarea name="description" rows="2" maxlength="3000"></textarea></label><label>Scope<select name="scope_type"><option value="shared">Shared with the organization</option><option value="department">Department</option><option value="project">Project</option><option value="restricted">Restricted to selected members</option></select></label><label>Department ID <span class="muted">(required for Department scope)</span><input type="number" min="1" name="department_id"></label><label>Project ID <span class="muted">(required for Project scope)</span><input type="number" min="1" name="project_id"></label><label>Member user IDs <span class="muted">(comma separated; used for restricted access)</span><input name="member_ids_text" maxlength="2000" placeholder="e.g. 12, 24"></label><button class="button">Create space</button></form><hr><h3>Add category</h3><form method="post" action="{{ route('knowledge.categories.store') }}" class="form-grid">@csrf<label>Category name<input name="name" maxlength="120" required></label><button class="button">Save category</button></form></section>@endif
    @can('knowledge.sync.manage')<section class="card"><h2>Microsoft 365 sync</h2><p class="muted">SharePoint files are imported as drafts for local review. Removed source files are archived on the next complete sync.</p>
        @if(!$graphConfigured)<div class="notice">Set KNOWLEDGE_GRAPH_TENANT_ID, KNOWLEDGE_GRAPH_CLIENT_ID and KNOWLEDGE_GRAPH_CLIENT_SECRET on the server. Grant the Entra app read access to the selected SharePoint site.</div>@endif
        <form method="post" action="{{ route('knowledge.microsoft-365.store') }}" class="stack-form">@csrf<label>Connection name<input name="name" maxlength="160" required placeholder="HR policies"></label><label>Target knowledge space<select name="space_id" required><option value="">Choose a space</option>@foreach($spaces as $space)<option value="{{ $space->id }}">{{ $space->name }}</option>@endforeach</select></label><label>SharePoint drive ID<input name="drive_id" maxlength="255" required></label><label>Folder item ID<input name="folder_id" maxlength="255" required></label><button class="button">Save disabled mapping</button></form>
        @foreach($syncConnections as $connection)<div class="knowledge-sync-row"><div><strong>{{ $connection->name }}</strong><small class="muted">{{ ucfirst($connection->status) }} · Last sync {{ $connection->last_synced_at ?: 'never' }}</small></div><form method="post" action="{{ route('knowledge.microsoft-365.sync', $connection->id) }}">@csrf<button class="button" @disabled(!$graphConfigured)>Sync SharePoint files</button></form></div>@endforeach
        @if($syncLogs->isNotEmpty())<h3 class="mt-5">Recent sync activity</h3><div class="table-wrap"><table><thead><tr><th>Connection</th><th>Status</th><th>Result</th><th>When</th></tr></thead><tbody>@foreach($syncLogs as $log)<tr><td>{{ $log->connection_name }}</td><td>{{ ucfirst($log->status) }}</td><td>{{ $log->message ?: ($log->created_count.' imported · '.$log->updated_count.' updated · '.$log->deleted_count.' archived') }}</td><td>{{ $log->created_at }}</td></tr>@endforeach</tbody></table></div>@endif
    </section>@endcan
    </div>
</div>
</x-layouts.app>
