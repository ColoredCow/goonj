const puppeteer = require('puppeteer');

(async () => {
    const browser = await puppeteer.launch({
        // executablePath: '/home/apache/.cache/puppeteer/chrome/linux-129.0.6668.89/chrome-linux64/chrome',
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
    });

    const page = await browser.newPage();

    const htmlContent = process.argv[2];
    const outputPath = process.argv[3];

    const fullHtmlContent = `
        <style>
            body { margin: 0; padding: 0; height: 100%; width: 100%; }
        </style>
        ${htmlContent}
    `;

    await page.setContent(fullHtmlContent, { waitUntil: 'networkidle0' });

    await page.setViewport({ width: 1080, height: 1080 });

    await page.screenshot({ path: outputPath, fullPage: true });

    await browser.close();
})();
