<?php

namespace ComAtg\DocsViewer;

use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class DocumentationController extends Controller
{
    public function index(): View
    {
        $docs = $this->buildDocList();

        return $this->renderView('index', compact('docs'));
    }

    public function show(string $slug): View
    {
        abort_unless(preg_match('/^[a-z0-9\-]+$/', $slug), 404);

        $path = $this->resolveDocPath($slug);

        abort_unless($path !== null, 404);

        $markdown = file_get_contents($path);

        $converter = new GithubFlavoredMarkdownConverter([
            'html_input'         => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $html  = $this->postProcessHtml($converter->convert($markdown)->getContent());
        $title = $this->extractTitle($markdown, $slug);
        $docs  = $this->buildDocList();

        return $this->renderView('show', compact('html', 'title', 'slug', 'docs'));
    }

    private function renderView(string $name, array $data = []): View
    {
        $vendor = 'docs-viewer';

        // Prefer published view over package default
        $published = "vendor/{$vendor}/{$name}";

        $view = view()->exists($published) ? $published : "{$vendor}::{$name}";

        return view($view, $data);
    }

    /** @return array<int, array{slug: string, title: string, description: string}> */
    private function buildDocList(): array
    {
        $docsPath = config('docs-viewer.docs_path');

        $sorted = collect(glob("{$docsPath}/*.md"))
            ->map(function (string $path): array {
                $slug     = basename($path, '.md');
                $markdown = file_get_contents($path);

                return [
                    'slug'        => $slug,
                    'title'       => $this->extractTitle($markdown, $slug),
                    'description' => $this->extractDescription($markdown),
                ];
            })
            ->sortBy('title')
            ->values()
            ->all();

        $readmePath = config('docs-viewer.readme_path');

        if (! $readmePath || ! file_exists($readmePath)) {
            return $sorted;
        }

        $readme      = file_get_contents($readmePath);
        $readmeEntry = [
            'slug'        => 'readme',
            'title'       => $this->extractTitle($readme, 'readme'),
            'description' => $this->extractDescription($readme),
        ];

        return [$readmeEntry, ...$sorted];
    }

    private function resolveDocPath(string $slug): ?string
    {
        if ($slug === 'readme') {
            $path = config('docs-viewer.readme_path');

            return ($path && file_exists($path)) ? $path : null;
        }

        $path = config('docs-viewer.docs_path')."/{$slug}.md";

        return file_exists($path) ? $path : null;
    }

    private function postProcessHtml(string $html): string
    {
        $namePrefix = config('docs-viewer.route_name_prefix');

        // Mermaid: <pre><code class="language-mermaid"> → <pre class="mermaid">
        // Keep HTML entities encoded so the browser parses the diagram source as text;
        // mermaid.run() reads textContent which decodes entities back to the raw source.
        $html = preg_replace_callback(
            '/<pre><code class="language-mermaid">(.*?)<\/code><\/pre>/s',
            fn (array $m) => '<pre class="mermaid">'.$m[1].'</pre>',
            $html
        ) ?? $html;

        // README.md links → readme route
        $html = preg_replace_callback(
            '/href="(?:\.\.\/)*README\.md(#[^"]*)?"/',
            fn (array $m) => 'href="'.route("{$namePrefix}.show", 'readme').($m[1] ?? '').'"',
            $html
        ) ?? $html;

        // docs/*.md and relative *.md links → show route
        $html = preg_replace_callback(
            '/href="(?:\.\/)?(?:docs\/)?([a-z0-9][a-z0-9\-]*)\.md(#[^"]*)?"/',
            fn (array $m) => 'href="'.route("{$namePrefix}.show", $m[1]).($m[2] ?? '').'"',
            $html
        ) ?? $html;

        return $html;
    }

    private function extractTitle(string $markdown, string $slug): string
    {
        if (preg_match('/^#\s+(.+)$/m', $markdown, $matches)) {
            return trim($matches[1]);
        }

        return Str::title(str_replace('-', ' ', $slug));
    }

    private function extractDescription(string $markdown): string
    {
        $lines       = explode("\n", $markdown);
        $inParagraph = false;
        $buffer      = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (str_starts_with($trimmed, '#') || $trimmed === '---' || $trimmed === '') {
                if ($inParagraph) {
                    break;
                }
                continue;
            }

            $inParagraph = true;
            $buffer[]    = $trimmed;
        }

        return Str::limit(implode(' ', $buffer), 120);
    }
}
