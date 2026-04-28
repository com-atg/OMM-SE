<?php

namespace App\Services;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Blade;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

/**
 * Renders a Blade-with-Markdown email template supplied as a string,
 * mirroring the pipeline of {@see Markdown::render()}.
 *
 * The template string is compiled via Blade's inline-render path, which
 * writes the cached compile to `storage/framework/views` (never to
 * `resources/views/`) and unlinks it after rendering. The output is then
 * CSS-inlined against the configured mail theme.
 */
class MailTemplateRenderer
{
    public function __construct(
        private Markdown $markdown,
        private ViewFactory $view,
    ) {}

    public function render(string $template, array $data): string
    {
        $this->view->replaceNamespace('mail', $this->markdown->htmlComponentPaths());

        $contents = Blade::render($template, $data, deleteCachedView: true);

        $themeView = 'mail::themes.'.$this->markdown->getTheme();
        $themeCss = $this->view->make($themeView, $data)->render();

        return (new CssToInlineStyles)->convert($contents, $themeCss);
    }
}
