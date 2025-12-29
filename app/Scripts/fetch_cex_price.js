import puppeteer from 'puppeteer';
import fs from 'fs';

(async () => {
    const title = process.argv[2];
    const platform = process.argv[3];

    if (!title) {
        console.log(JSON.stringify({ error: 'Title is required' }));
        process.exit(1);
    }

    const searchQuery = platform ? `${title} ${platform}` : title;
    // URL encoded query
    const encodedQuery = encodeURIComponent(searchQuery);
    const url = `https://uk.webuy.com/search?stext=${encodedQuery}`;

    let results = [];
    let apiResponse = null;
    let browser = null;
    let page = null;

    try {
        browser = await puppeteer.launch({
            headless: 'new',
            args: ['--no-sandbox', '--disable-setuid-sandbox']
        });
        page = await browser.newPage();

        // Set User-Agent to look like a real browser
        await page.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

        // let apiResponse = null; // Moved to outer scope

        // Listen for API responses
        page.on('response', async response => {
            const url = response.url();
            console.error('Response URL:', url); 
            if (url.includes('boxes') || url.includes('search')) {
                try {
                    const json = await response.json();
                    if (json && (json.boxes || (json.response && json.response.data && json.response.data.boxes))) {
                         apiResponse = json;
                    }
                } catch (e) {
                    // Ignore
                }
            }
        });

        // Go to search page
        // Wait for network idle to ensure Cloudflare challenge passes if possible
        await page.goto(url, { waitUntil: 'networkidle2', timeout: 60000 });

        const pageTitle = await page.title();
        
        if (pageTitle.includes('Cloudflare') || pageTitle.includes('Attention Required')) {
            console.log(JSON.stringify({ error: 'Blocked by Cloudflare' }));
            await browser.close();
            process.exit(1);
        }

        // console.error('Page Title:', pageTitle); // Debug

        // Wait for results to load
        try {
            await page.waitForSelector('.product-main-price', { timeout: 10000 });
        } catch (e) {
            // console.error('Selector .product-main-price not found');
            // Check if we have results in another format
        }

        if (apiResponse) {
             const findBoxes = (obj) => {
                if (!obj) return [];
                if (Array.isArray(obj)) return obj; // If it's the array itself
                if (obj.boxes && Array.isArray(obj.boxes)) return obj.boxes;
                if (obj.response && obj.response.data && obj.response.data.boxes) return obj.response.data.boxes;
                if (obj.data && obj.data.boxes) return obj.data.boxes;
                return [];
             };
             
             const boxes = findBoxes(apiResponse);
             const items = boxes.map(b => ({
                 title: b.boxName || b.name,
                 price: b.sellPrice,
                 category: b.categoryFriendlyName || b.categoryName,
                 id: b.boxId
             }));
             console.log(JSON.stringify(items));
             await browser.close();
             process.exit(0);
        }

        // Extract data from the page using DOM
        results = await page.evaluate(() => {
            const items = [];
            
            // Strategy: Find all price elements, then find their related titles
            const priceEls = document.querySelectorAll('.product-main-price');
            
            priceEls.forEach(priceEl => {
                // Walk up to find a container that also holds the title
                let container = priceEl.parentElement;
                let titleEl = null;
                let found = false;
                
                // Try up to 5 levels up
                for (let i = 0; i < 5; i++) {
                    if (!container) break;
                    
                    // Look for title in this container
                    const possibleTitle = container.querySelector('.card-title a') || 
                                          container.querySelector('a.line-clamp') ||
                                          container.querySelector('div.card-title');
                                          
                    if (possibleTitle) {
                        titleEl = possibleTitle;
                        found = true;
                        break;
                    }
                    container = container.parentElement;
                }
                
                if (found && titleEl) {
                    // Extract price (remove £ and clean)
                    const priceText = priceEl.innerText.replace('£', '').trim();
                    const price = parseFloat(priceText);
                    
                    items.push({
                        title: titleEl.innerText.trim(),
                        price: price,
                        url: titleEl.href || '',
                        id: titleEl.href ? titleEl.href.split('/').pop() : '' // basic ID extraction
                    });
                }
            });
            
            return items;
        });

        console.log(JSON.stringify(results));
        await browser.close();

    } catch (error) {
        console.log(JSON.stringify({ error: error.message }));
        process.exit(1);
    } finally {
        if (results.length === 0 && !apiResponse && page) {
             try {
                 const html = await page.content();
                 fs.writeFileSync('cex_dump.html', html);
             } catch (e) {
                 // ignore write errors
             }
        }
    }
})();
