<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;

class UserManualController extends Controller
{
    public function __invoke(Request $request)
    {
        $documents = $this->documents();
        $selected = (string) $request->query('document', 'user-guide/README.md');
        abort_unless(isset($documents[$selected]), 404);

        $markdown = file_get_contents(base_path('docs/'.$selected));
        $markdown = $this->rewriteDocumentLinks($markdown, $selected, $documents);

        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'heading_permalink' => [
                'apply_id_to_heading' => true,
                'insert' => 'none',
                'id_prefix' => '',
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new HeadingPermalinkExtension());
        $converter = new MarkdownConverter($environment);
        $html = (string) $converter->convert($markdown);
        $topLevelKeys = [
            'module-one-workspace.md',
            'module-two-crm.md',
            'module-three-social-media.md',
            'top-level-module-four-commercial-sales.md',
            'top-level-module-five-customer-experience.md',
            'top-level-module-six-human-resources.md',
            'top-level-module-seven-operations-projects.md',
            'top-level-module-eight-procurement-assets.md',
            'top-level-module-nine-finance.md',
            'top-level-module-ten-knowledge-intelligence.md',
            'top-level-module-eleven-administration.md',
        ];
        $topLevelDocuments = [];
        foreach ($topLevelKeys as $key) {
            if (isset($documents[$key])) {
                $topLevelDocuments[$key] = $documents[$key];
            }
        }

        return view('user-manual.index', [
            'document' => $selected,
            'documentTitle' => $documents[$selected],
            'documents' => $documents,
            'topLevelDocuments' => $topLevelDocuments,
            'html' => $html,
            'contents' => $this->contents($markdown, $environment),
        ]);
    }

    private function documents(): array
    {
        $documents = ['user-guide/README.md' => 'Complete user guide'];

        foreach (glob(base_path('docs/top-level-module-*.md')) ?: [] as $path) {
            $key = basename($path);
            $documents[$key] = $this->title($path);
        }

        foreach (glob(base_path('docs/module-*.md')) ?: [] as $path) {
            if (basename($path) === 'module-installation-review.md') {
                continue;
            }
            $key = basename($path);
            $documents[$key] = $this->title($path);
        }

        $roadmap = base_path('docs/user-guide/module-roadmap.md');
        if (is_file($roadmap)) {
            $documents['user-guide/module-roadmap.md'] = $this->title($roadmap);
        }

        return $documents;
    }

    private function title(string $path): string
    {
        $firstLine = strtok((string) file_get_contents($path), "\n");

        return trim(preg_replace('/^#+\s*/', '', (string) $firstLine)) ?: basename($path, '.md');
    }

    private function rewriteDocumentLinks(string $markdown, string $current, array $documents): string
    {
        return preg_replace_callback('/\]\(([^)]+\.md)(#[^)]*)?\)/', function (array $match) use ($current, $documents) {
            $target = $match[1];
            if (str_contains($target, '://') || str_starts_with($target, '/')) {
                return $match[0];
            }

            $parts = [];
            foreach (explode('/', trim(dirname($current).'/'.$target, './')) as $part) {
                if ($part === '' || $part === '.') continue;
                if ($part === '..') array_pop($parts); else $parts[] = $part;
            }
            $key = implode('/', $parts);
            if (! isset($documents[$key])) {
                return $match[0];
            }

            $url = route('user-manual', ['document' => $key]).($match[2] ?? '');

            return ']('.$url.')';
        }, $markdown) ?? $markdown;
    }

    private function contents(string $markdown, Environment $environment): array
    {
        preg_match_all('/^(#{2,3})\s+(.+)$/m', $markdown, $matches, PREG_SET_ORDER);

        return array_map(function (array $match) use ($environment) {
            $label = trim(preg_replace('/[`*_~]/', '', $match[2]));

            return [
                'label' => $label,
                'id' => $environment->getSlugNormalizer()->normalize($label),
                'level' => strlen($match[1]),
            ];
        }, $matches);
    }
}
