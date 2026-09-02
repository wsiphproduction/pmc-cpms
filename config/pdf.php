<?php

return [

    /*
    |--------------------------------------------------------------------------
    | How a form reaches the printer
    |--------------------------------------------------------------------------
    |
    | 'browser'  — serve the form as an HTML page that opens the print dialog
    |              itself, and let the viewer's own Chrome make the PDF. No
    |              server dependencies at all, which is what shared hosting
    |              gives us: cPanel has neither node nor Chrome.
    |
    | 'chrome'   — render to a PDF server-side through headless Chrome, so the
    |              route returns a real PDF file. Needs node and Chrome on the
    |              box; falls back to 'browser' if the render fails, rather
    |              than handing the user a 500.
    |
    | The layout is the same either way — the only difference is who runs the
    | print engine.
    |
    */

    'driver' => env('PDF_DRIVER', 'browser'),

    /*
    |--------------------------------------------------------------------------
    | Headless Chrome
    |--------------------------------------------------------------------------
    |
    | The controlled PMD forms are laid out with CSS grid, flexbox and a
    | rotated approval stamp, so they are rendered by real Chrome rather than
    | a PHP PDF engine — the printout is then the same document the browser
    | shows, not an approximation of it.
    |
    | Leave the binaries null to let Browsershot find `node` and Chrome on the
    | PATH; set them when they live somewhere the web user cannot discover
    | (Laragon on Windows, or a server where Chrome came from Puppeteer).
    |
    */

    'node_binary'  => env('PDF_NODE_BINARY'),
    'npm_binary'   => env('PDF_NPM_BINARY'),
    'chrome_path'  => env('PDF_CHROME_PATH'),

    /*
    | Chrome flags. --no-sandbox is required when PHP-FPM runs as a user
    | without the kernel privileges Chrome's sandbox needs, which is the usual
    | case on a Linux host.
    */
    'chrome_args'  => array_filter(explode(',', (string) env('PDF_CHROME_ARGS', ''))),

    /** Seconds to allow for one render before giving up. */
    'timeout'      => (int) env('PDF_TIMEOUT', 60),

    /** Paper. The forms are designed against A4 portrait. */
    'format'       => env('PDF_FORMAT', 'A4'),

];
