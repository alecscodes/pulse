#!/usr/bin/env node

/**
 * Standalone script to validate SPA content using Playwright (Chromium).
 * This script loads a URL, waits for JavaScript to execute, then extracts
 * title and text content for validation.
 *
 * Usage: node scripts/validate-spa-content.js <config-json>
 * Output: JSON with { title: string, textContent: string, error?: string }
 */

import { chromium } from 'playwright';
import { existsSync } from 'fs';

const config = JSON.parse(process.argv[2] || '{}');

(async () => {
  let browser = null;
  try {
    const chromiumPath = process.env.CHROMIUM_PATH || (process.platform === 'linux' ? '/usr/bin/chromium' : null);
    
    const launchOptions = {
      headless: true,
      args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
    };
    
    if (chromiumPath && existsSync(chromiumPath)) {
      launchOptions.executablePath = chromiumPath;
    }
    
    browser = await chromium.launch(launchOptions);
    const page = await browser.newPage();
    await page.setViewportSize({ width: 1280, height: 720 });
    await page.goto(config.url, {
      waitUntil: 'networkidle',
      timeout: 30000,
    });

    const result = {
      title: await page.title() || '',
      textContent: (await page.evaluate(() => document.body.textContent || '')) || '',
    };

    console.log(JSON.stringify(result));
  } catch (error) {
    console.log(JSON.stringify({
      title: '',
      textContent: '',
      error: error.message || String(error),
    }));
    process.exit(1);
  } finally {
    if (browser) {
      await browser.close().catch(() => {});
    }
  }
})();

