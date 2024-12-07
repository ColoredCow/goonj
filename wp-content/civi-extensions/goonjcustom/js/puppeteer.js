const puppeteer = require('puppeteer');

require('dotenv').config();

(async () => {
    const executablePath = process.env.NODE_ENV === 'production' ? process.env.EXECUTABLE_PATH : '';
    console.log(executablePath,'executablePath')
    const browser = await puppeteer.launch({
        executablePath: executablePath
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
