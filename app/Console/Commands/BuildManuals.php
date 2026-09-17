<?php

namespace App\Console\Commands;

use App\Support\ManualContent;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Renders the CPMS user manuals to PDF into public/manuals.
 *
 * The manuals are built once and shipped as files rather than rendered per
 * request: their content changes only when App\Support\ManualContent does, and
 * the live cPanel host has neither node nor Chrome. Run this on a machine that
 * has both, and commit the result.
 *
 *   php artisan manuals:build                # every manual
 *   php artisan manuals:build --only=admin   # just one
 */
class BuildManuals extends Command
{
    protected $signature = 'manuals:build
                            {--only= : Build a single manual by slug}
                            {--path= : Write somewhere other than public/manuals}
                            {--keep-html : Leave the intermediate HTML in place, for tuning the layout}';

    protected $description = 'Render the CPMS user manuals to PDF in public/manuals';

    /** Long enough for Chrome to lay out the complete edition. */
    private const RENDER_TIMEOUT = 180;

    public function handle(): int
    {
        $target = $this->option('path') ?: public_path('manuals');

        if (! is_dir($target) && ! mkdir($target, 0755, true) && ! is_dir($target)) {
            $this->error("Could not create {$target}.");

            return self::FAILURE;
        }

        $manuals = ManualContent::manuals();

        if ($only = $this->option('only')) {
            if (! isset($manuals[$only])) {
                $this->error("No manual with slug \"{$only}\". Known: ".implode(', ', array_keys($manuals)).'.');

                return self::FAILURE;
            }

            $manuals = [$only => $manuals[$only]];
        }

        $failed = 0;

        foreach ($manuals as $slug => $manual) {
            $this->line("Building <info>{$slug}</info> …");

            try {
                $bytes = $this->render($slug, $manual, "{$target}/{$slug}.pdf");

                $this->line(sprintf(
                    '  <fg=green>OK</> %s.pdf — %s KB, %d sections',
                    $slug,
                    number_format($bytes / 1024),
                    count($manual['sections'])
                ));
            } catch (\Throwable $e) {
                $failed++;
                $this->error('  FAILED '.$slug.': '.trim($e->getMessage()));
            }
        }

        $this->newLine();

        if ($failed > 0) {
            $this->warn("{$failed} manual(s) failed. Node and Chrome are required — check PDF_NODE_BINARY and PDF_CHROME_PATH in .env.");

            return self::FAILURE;
        }

        $this->info('Manuals written to '.$target);

        return self::SUCCESS;
    }

    /** Render one manual, returning the size of the PDF written. */
    private function render(string $slug, array $manual, string $out): int
    {
        $html = view('manuals.document', [
            'manual' => $manual,
            'sections' => ManualContent::sectionsFor($slug),
            'docNo' => ManualContent::DOC_NO,
            'version' => ManualContent::VERSION,
            'issuedOn' => now()->format('F Y'),
        ])->render();

        $work = $this->workingDirectory();
        $htmlPath = "{$work}/{$slug}.html";
        $confPath = "{$work}/{$slug}.json";

        file_put_contents($htmlPath, $html);
        file_put_contents($confPath, json_encode([
            'html' => $htmlPath,
            'out' => $out,
            'chromePath' => config('pdf.chrome_path'),
            'format' => config('pdf.format', 'A4'),
            'footer' => $this->footer($manual),
            'args' => config('pdf.chrome_args') ?: [],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $process = new Process(
            [config('pdf.node_binary') ?: 'node', resource_path('manuals/render-pdf.cjs'), $confPath],
            base_path()
        );
        $process->setTimeout(self::RENDER_TIMEOUT);
        $process->run();

        if (! $this->option('keep-html')) {
            @unlink($htmlPath);
        }

        @unlink($confPath);

        if (! $process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput() ?: $process->getOutput());
        }

        return (int) $process->getOutput();
    }

    /** A scratch directory for the intermediate HTML, made if it is missing. */
    private function workingDirectory(): string
    {
        $path = storage_path('app/manuals-build');

        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        return $path;
    }

    /**
     * The running footer. Chrome renders it outside the page box, so it carries
     * its own font sizing and cannot inherit the document's stylesheet.
     */
    private function footer(array $manual): string
    {
        $left = e(ManualContent::DOC_NO.'  ·  '.$manual['subtitle']);

        return '<div style="width:100%;font-family:Calibri,Segoe UI,Arial,sans-serif;font-size:7.5pt;'
            .'color:#8b93a1;padding:0 16mm;display:flex;justify-content:space-between;align-items:center;">'
            .'<span>'.$left.'</span>'
            .'<span>Page <span class="pageNumber"></span> of <span class="totalPages"></span></span>'
            .'</div>';
    }
}
