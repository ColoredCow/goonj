const os = require('os');
const puppeteer = require('puppeteer'); // use full puppeteer

(async () => {
  try {
    const htmlContent = process.argv[2];
    const outputPath = process.argv[3];

    // Detect OS for Chrome executable
    let executablePath = undefined;

    if (os.platform() === 'darwin') {
      // macOS
      executablePath = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
    } else if (os.platform() === 'linux') {
      // Linux (try system Chrome/Chromium)
      executablePath = '/usr/bin/google-chrome' ||
                       '/usr/bin/chromium-browser' ||
                       '/usr/bin/chromium';
    } else if (os.platform() === 'win32') {
      // Windows (most common install path)
      executablePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
    }

    // Launch browser
    const browser = await puppeteer.launch({
      executablePath: executablePath, // if not found, Puppeteer will use its own
      args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage();

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
    console.log(`Poster image created at: ${outputPath}`);
  } catch (err) {
    console.error("Puppeteer failed:", err);
    process.exit(1);
  }
})();
