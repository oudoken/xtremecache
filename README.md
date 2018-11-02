This is modified and tweaked version for PrestaShop 1.7 & PrestaShop 1.6 caching module, based on SimoneS93.
- ability to generate same cache for mobile and desktop (usefull for people using responsive templates and serving same content for mobile and desktop)
- ability to detect maintenance mode (must be enabled in config.php) and do not serve cached pages when we do maintenance
- ability to check for currency
- ability to clean cache or only one product by CRON

To clean cache by CRON, call this url:
```
http://www.yoursite.com/modules/xtremecache/clean.php?your-secret&flush
```
If you just want to clean simple product with ID 8, use:
```
http://www.yoursite.com/modules/xtremecache/clean.php?your-secret&flush=8&regenerate
```


To setup module, edit constants in config.php:
```
/**
 * Cache Time-To-Live in seconds
 */
const CACHE_TTL = 172800;

/**
 * Cache driver
 */
const DRIVER = 'files'; // 'prestashop' or plain files

/**
 * Cache folder for file cache
 */
const CACHE_DIR = 'xcache';

/**
 * Default CHMOD for created files and folders
 * For security, please use 0666 for module and 0644 for cgi
 */
const DEFAULT_CHMOD = 0770;
	
/**
 * Cache mobile and desktop versions separatelly?
 */
const SEPARATE_MOBILE_AND_DESKTOP = false;

/**
 * Do you use more than one currency?
 * Set to false, to disable querying database.
 */
const MULTICURRENCY = false;

/**
 * If value is false, we will serve cached pages during maintenance and do not query DB.
 * If value is true, cache will be completly off during maintenance and we may query DB.
 */
const CHECK_FOR_MAINTENANCE = false;
```

On PrestaShop 1.6 disable moving javasript to end, then it will be working.

Cavecats:
- if you use dynamic modules like Your last viewed items, it will be cached and displayed for another visitors :)
- if you turn your PrestaShop into maintenance mode and do not change CHECK_FOR_MAINTENANCE to true, your shop will be serving cached pages, maybe it is desired for some of us


SimoneS93 wrote:
#Prestashop Xtreme cache module

Today I was thinking about Prestashop front office performance optimization and the lack of a full cache system came to mind (by full cache, I mean save the page html to file and serve that on subsequent requests, with no processing at all). 
In the first place I thought it was not possible, since Prestashop is higly dynamic and needs to update whenever a user interacts with the carts or the account.
But the I realized not all people visit our site logged in and for those the content
is almost static (at least in the short term, if we’re not updating our catalogue).
So the full cache system idea (with an expiration time near in the future) gained sense to me and I implemented a module just to do that.
It works hooking into *actionDispatcher* to process the incoming request as soon as possibile, before any database query or controller’s processing: if the user is not logged in and it finds a cached version of the requested page, it serves that page and aborts execution. 
You gain not only a better response time, but a lighter workload on the server, too! A win-win!
But to serve cached pages we need to store one, first. Prestashop doesn’t provide such an hook by default, so I created one in the Controller class, right before echoing the response to the browser.
You’ll find the module on the Prestashop official forum.
