<?php

namespace App\Support;

use Illuminate\Http\Response;
use Spatie\Browsershot\Browsershot;

/**
 * Renders a Blade view to a PDF through headless Chrome.
 *
 * The PMD forms use CSS grid, flexbox and a rotated approval stamp, none of
 * which a PHP PDF engine can lay out, so the same browser that displays the
 * form is the one that prints it.
 *
 * Rendering happens server-side from the record, never from HTML posted by the
 * client: a printed NTP carries approval stamps, and only the server may decide
 * what those say.
 */
class PdfRenderer
{
    /**
     * Render a view to raw PDF bytes.
     *
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, array $data = []): string
    {
        $shot = Browsershot::html(view($view, $data)->render())
            ->format(config('pdf.format', 'A4'))
            ->showBackground()          // the gold table headings are backgrounds
            ->timeout(config('pdf.timeout', 60))
            // The form supplies its own page margins in CSS, so Chrome adds none.
            ->margins(0, 0, 0, 0)
            // Local crest images are read straight off disk.
            ->waitUntilNetworkIdle();

        if ($binary = config('pdf.node_binary')) {
            $shot->setNodeBinary($binary);
        }

        if ($binary = config('pdf.npm_binary')) {
            $shot->setNpmBinary($binary);
        }

        if ($chrome = config('pdf.chrome_path')) {
            $shot->setChromePath($chrome);
        }

        if ($args = config('pdf.chrome_args')) {
            $shot->addChromiumArguments($args);
        }

        return $shot->pdf();
    }

    /**
     * The form, ready to print, opened in a new tab.
     *
     * Under the 'browser' driver this is the form itself as HTML, opening the
     * print dialog on load — the viewer's own Chrome lays it out and writes the
     * PDF, so the server needs neither node nor Chrome. Under 'chrome' it is a
     * real PDF shown inline, which falls back to the printable page if the
     * render fails: a host without Chrome should still be able to print.
     */
    public function stream(string $view, array $data, string $filename): Response
    {
        if (config('pdf.driver', 'browser') !== 'chrome') {
            return $this->printable($view, $data);
        }

        try {
            return response($this->render($view, $data), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $this->safeName($filename) . '"',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return $this->printable($view, $data);
        }
    }

    /**
     * The form as an HTML page that prints itself.
     *
     * `autoPrint` is what the layout keys off to open the dialog; the page is
     * otherwise byte-for-byte the document Chrome would have rendered, since
     * it is the same view and the same stylesheet.
     */
    private function printable(string $view, array $data): Response
    {
        return response(view($view, [...$data, 'autoPrint' => true])->render(), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    /**
     * A local image as a data URI.
     *
     * The crest is inlined rather than linked: Browsershot refuses `file://`
     * in its HTML (it is a local-file-read surface), and an http:// link would
     * make rendering depend on the app being reachable from the machine
     * running Chrome. Memoized — each form embeds the crest twice.
     */
    public static function embeddedImage(string $absolutePath): string
    {
        static $cache = [];

        if (! isset($cache[$absolutePath])) {
            $bytes = @file_get_contents($absolutePath);
            $mime  = str_ends_with(strtolower($absolutePath), '.svg') ? 'image/svg+xml' : 'image/png';

            $cache[$absolutePath] = $bytes === false
                ? ''
                : 'data:' . $mime . ';base64,' . base64_encode($bytes);
        }

        return $cache[$absolutePath];
    }

    /** Strips anything that would break the Content-Disposition header. */
    private function safeName(string $filename): string
    {
        $name = preg_replace('/[^A-Za-z0-9 ._-]+/', '-', $filename);
        $name = trim((string) preg_replace('/-{2,}/', '-', $name), '- ');

        return ($name ?: 'document') . '.pdf';
    }
}
