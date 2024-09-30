const puppeteer = require('puppeteer');

(async () => {
    const browser = await puppeteer.launch();
    const page = await browser.newPage();

    const htmlContent = process.argv[2];
    const outputPath = process.argv[3];

    await page.setContent(htmlContent, { waitUntil: 'networkidle0' });

    await page.setViewport({ width: 1080, height: 1080 });

    await page.screenshot({ path: outputPath, fullPage: true });

    await browser.close();
})();
