<x-layouts.app>
<style>
.manual-hero{position:relative;overflow:hidden;padding:34px;border-radius:20px;background:linear-gradient(120deg,#253d29,#335c34 60%,#577848);color:white;box-shadow:0 16px 42px #20352220}
.manual-hero:after{content:"";position:absolute;width:270px;height:270px;border:1px solid #ffffff30;border-radius:50%;right:-55px;top:-115px;box-shadow:0 0 0 28px #ffffff0c,0 0 0 58px #ffffff08}
.manual-hero h1{font-size:clamp(28px,4vw,42px);margin:8px 0}.manual-hero p{max-width:760px;color:#e1ebdf;margin:0}.manual-eyebrow{text-transform:uppercase;letter-spacing:.16em;font-size:11px;font-weight:700;color:#c1e2a8}.manual-source{font-size:11px!important;margin-top:18px!important;color:#d5e1d2!important}
.manual-module-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(205px,1fr));gap:12px;margin:22px 0 28px}.manual-module-card{display:flex;gap:12px;align-items:flex-start;min-height:92px;padding:16px;border:1px solid #e5e9e3;border-radius:14px;background:#fff;color:#332d35;box-shadow:0 2px 8px #211c2607;transition:transform .18s,border-color .18s,box-shadow .18s}.manual-module-card:hover{transform:translateY(-2px);border-color:#b6d2a9;box-shadow:0 8px 22px #211c2612}.manual-module-number{display:grid;place-items:center;flex:0 0 34px;height:34px;border-radius:10px;background:#eef6e9;color:#387333;font-weight:800}.manual-module-card strong{display:block;font-size:13px;line-height:1.35}.manual-module-card small{display:block;margin-top:5px;color:#867c89;font-size:11px}
.manual-layout{display:grid;grid-template-columns:minmax(210px,270px) minmax(0,1fr);gap:20px;align-items:start}.manual-toc{position:sticky;top:20px;max-height:calc(100vh - 40px);overflow:auto;padding:17px;border:1px solid #e7e5e9;border-radius:15px;background:white}.manual-toc h2{font-size:14px;margin:0 0 10px}.manual-toc a{display:block;padding:6px 8px;border-radius:7px;font-size:11px;line-height:1.4;color:#716778}.manual-toc a:hover{background:#f1f7ed;color:#315e2d}.manual-toc a[data-level="3"]{padding-left:20px;font-size:10px}.manual-doc-list{margin-top:16px;padding-top:13px;border-top:1px solid #ece9ef}.manual-doc-list summary{cursor:pointer;color:#60576a;font-size:11px;font-weight:700}.manual-doc-list a{overflow-wrap:anywhere}.manual-prose{min-width:0;padding:clamp(20px,4vw,42px);border:1px solid #e7e5e9;border-radius:16px;background:white;box-shadow:0 3px 12px #211c2608;color:#403b43;font-size:14px;line-height:1.75}.manual-prose h1{font-size:30px;line-height:1.2;margin:0 0 18px;color:#211f22}.manual-prose h2{font-size:23px;line-height:1.3;margin:42px 0 13px;padding-top:18px;border-top:1px solid #ece9ef;color:#28252a;scroll-margin-top:24px}.manual-prose h3{font-size:17px;margin:26px 0 8px;color:#343039;scroll-margin-top:24px}.manual-prose h4{font-size:14px;margin:20px 0 6px}.manual-prose p{margin:9px 0 14px}.manual-prose a{color:#397d32;text-decoration:underline;text-underline-offset:2px}.manual-prose img{display:block;max-width:100%;height:auto;margin:18px auto 26px;border:1px solid #e8e6eb;border-radius:16px;box-shadow:0 8px 24px #211c260c}.manual-prose ul,.manual-prose ol{padding-left:24px;margin:8px 0 18px}.manual-prose li{padding-left:3px;margin:4px 0}.manual-prose blockquote{margin:16px 0;padding:12px 16px;border-left:4px solid #86b978;background:#f4f8f1;color:#5d5660}.manual-prose code{padding:2px 5px;border-radius:5px;background:#f2f0f4;font-size:.9em}.manual-prose pre{overflow:auto;padding:15px;border-radius:10px;background:#242126;color:#f6f3f7}.manual-prose pre code{padding:0;background:transparent;color:inherit}.manual-prose table{width:100%;border-collapse:separate;border-spacing:0;margin:16px 0 22px;font-size:12px}.manual-prose th,.manual-prose td{padding:10px 12px;border-bottom:1px solid #eae7ed;text-align:left;vertical-align:top}.manual-prose th{background:#f3f5f7;color:#50495a;font-weight:700}.manual-prose tr:nth-child(even) td{background:#fcfcfd}.manual-prose hr{border:0;border-top:1px solid #ece9ef;margin:26px 0}.manual-back{display:inline-flex;margin:0 0 14px;padding:8px 12px;border-radius:8px;background:#eef6e9;color:#397d32;font-size:12px;font-weight:700;text-decoration:none}
@media(max-width:900px){.manual-layout{grid-template-columns:1fr}.manual-toc{position:static;max-height:340px}.manual-hero{padding:26px}.manual-prose{padding:22px}}
</style>

<section class="manual-hero">
    <span class="manual-eyebrow">GWO SEVO ERP · Help centre</span>
    <h1>{{ $documentTitle }}</h1>
    <p>Browse practical guidance for the workspace, all 11 top-level menu areas, and the installed module workflows. Use the contents panel to jump to a section, or open a detailed module reference.</p>
    <p class="manual-source">The manual is rendered from the maintained files in <code>docs/</code>. User-facing workflow changes should be reflected there.</p>
</section>

@if($document === 'user-guide/README.md')
<section class="manual-module-grid" aria-label="Top-level ERP modules">
    @foreach($topLevelDocuments as $key => $title)
        <a class="manual-module-card" href="{{ route('user-manual', ['document' => $key]) }}">
            <span class="manual-module-number">{{ str_pad((string) (array_search($key, array_keys($topLevelDocuments), true) + 1), 2, '0', STR_PAD_LEFT) }}</span>
            <span><strong>{{ preg_replace('/^(?:Top-level\s+)?Module\s+\d+\s*[—:-]?\s*/i', '', $title) }}</strong><small>Open module guide →</small></span>
        </a>
    @endforeach
</section>
@else
    <a class="manual-back" href="{{ route('user-manual') }}">← Back to complete user guide</a>
@endif

<div class="manual-layout">
    <aside class="manual-toc" aria-label="Manual contents">
        <h2>On this page</h2>
        @foreach($contents as $item)
            <a href="#{{ $item['id'] }}" data-level="{{ $item['level'] }}">{{ $item['label'] }}</a>
        @endforeach
        <details class="manual-doc-list">
            <summary>All module references ({{ count($documents) - 1 }})</summary>
            @foreach($documents as $key => $title)
                @if($key !== 'user-guide/README.md' && $key !== $document)
                    <a href="{{ route('user-manual', ['document' => $key]) }}">{{ $title }}</a>
                @endif
            @endforeach
        </details>
    </aside>
    <article class="manual-prose">{!! $html !!}</article>
</div>
</x-layouts.app>
