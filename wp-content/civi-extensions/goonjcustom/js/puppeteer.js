const puppeteer = require('puppeteer');

(async () => {
    const browser = await puppeteer.launch();
    const page = await browser.newPage();

    // Get HTML content from command line arguments
    const htmlContent = process.argv[2];
    const outputPath = process.argv[3];

    // Set the content of the page
    await page.setContent(htmlContent, { waitUntil: 'networkidle0' });

    // Set the viewport size
    await page.setViewport({ width: 1200, height: 800 });

    // Capture the screenshot
    await page.screenshot({ path: outputPath, fullPage: true });

    await browser.close();
})();
