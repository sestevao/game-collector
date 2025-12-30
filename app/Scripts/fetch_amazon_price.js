import puppeteer from 'puppeteer';

(async () => {
    const title = process.argv[2];
    const platform = process.argv[3];

    if (!title) {
        console.log(JSON.stringify({ error: 'Title is required' }));
        process.exit(1);
    }

    const searchQuery = platform ? `${title} ${platform}` : title;
    const encodedQuery = encodeURIComponent(searchQuery);
    // Use UK store as default based on project context
    const url = `https://www.amazon.co.uk/s?k=${encodedQuery}`;

    let browser = null;
    let page = null;

    try {
        browser = await puppeteer.launch({
            headless: 'new',
            args: ['--no-sandbox', '--disable-setuid-sandbox']
        });
        page = await browser.newPage();

        // Set User-Agent to look like a real browser (Critical for Amazon)
        await page.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        
        // Set extra headers
        await page.setExtraHTTPHeaders({
            'Accept-Language': 'en-GB,en-US;q=0.9,en;q=0.8',
        });

        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });

        // Check for bot detection / captcha
        const pageTitle = await page.title();
        if (pageTitle.includes('Robot Check') || pageTitle.includes('CAPTCHA')) {
            console.log(JSON.stringify({ error: 'Amazon Bot Detection Triggered' }));
            await browser.close();
            process.exit(1);
        }

        // Evaluate page to find first result
        const result = await page.evaluate(() => {
            // Find all search results
            const items = document.querySelectorAll('div[data-component-type="s-search-result"]');
            
            for (const item of items) {
                // Skip sponsored items if possible (usually have 'AdHolder' class or text "Sponsored")
                if (item.classList.contains('AdHolder')) continue;

                const titleEl = item.querySelector('h2 a span');
                const priceEl = item.querySelector('.a-price .a-offscreen');
                
                if (titleEl && priceEl) {
                    const priceText = priceEl.innerText.replace('£', '').replace(',', '').trim();
                    const price = parseFloat(priceText);
                    
                    if (!isNaN(price)) {
                        return {
                            title: titleEl.innerText.trim(),
                            price: price,
                            url: item.querySelector('h2 a').href
                        };
                    }
                }
            }
            return null;
        });

        if (result) {
            console.log(JSON.stringify(result));
        } else {
            console.log(JSON.stringify({ error: 'No results found' }));
        }

        await browser.close();

    } catch (error) {
        console.log(JSON.stringify({ error: error.message }));
        if (browser) await browser.close();
        process.exit(1);
    }
})();
