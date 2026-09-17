/**
 * Renders one HTML file to a PDF through headless Chrome.
 *
 * Used by `php artisan manuals:build`. This is a build-time tool, not part of
 * the running app — the live host has neither node nor Chrome, which is why the
 * manuals are rendered here and shipped as files.
 *
 * Browsershot is not used for this: it passes its whole configuration as one
 * long command-line argument, and on Windows the shell mangles the `%` in the
 * footer template, after which the bridge hangs until the timeout. Handing node
 * a JSON file instead sidesteps the shell entirely.
 *
 *   node render-pdf.cjs <config.json>
 *
 * The config file holds { html, out, chromePath, footer, header, margin }.
 */
const fs = require('fs');
const path = require('path');

(async () => {
    const configPath = process.argv[2];

    if (!configPath) {
        throw new Error('Usage: node render-pdf.cjs <config.json>');
    }

    const config = JSON.parse(fs.readFileSync(configPath, 'utf8'));
    const puppeteer = require(path.join(__dirname, '..', '..', 'node_modules', 'puppeteer'));

    const browser = await puppeteer.launch({
        headless: true,
        ...(config.chromePath ? { executablePath: config.chromePath } : {}),
        args: ['--no-sandbox', '--disable-gpu', ...(config.args || [])],
    });

    try {
        const page = await browser.newPage();

        // Loaded from disk rather than set as a string so that relative assets
        // and the document's own @page rules resolve exactly as they would in
        // a browser opening the same file.
        await page.goto('file://' + config.html.replace(/\\/g, '/'), {
            waitUntil: 'load',
        });

        // The cover and section rules are print-only; matching the media here
        // means what Chrome lays out is what it prints.
        await page.emulateMediaType('print');

        const pdf = await page.pdf({
            format: config.format || 'A4',
            printBackground: true,
            preferCSSPageSize: true,
            displayHeaderFooter: Boolean(config.footer || config.header),
            headerTemplate: config.header || '<div></div>',
            footerTemplate: config.footer || '<div></div>',
            margin: config.margin || undefined,
        });

        fs.writeFileSync(config.out, pdf);
        process.stdout.write(String(pdf.length));
    } finally {
        await browser.close();
    }
})().catch((error) => {
    process.stderr.write(error && error.stack ? error.stack : String(error));
    process.exit(1);
});
