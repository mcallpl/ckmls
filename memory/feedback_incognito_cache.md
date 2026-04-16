---
name: GoDaddy LiteSpeed cache blocks JS updates
description: GoDaddy hosting aggressively caches index.php HTML output via LiteSpeed; JS/CSS changes require cache purge or incognito to verify. Use inline scripts and server-side rendering as cache-proof fallbacks.
type: feedback
---

GoDaddy's LiteSpeed server caches the full HTML output of index.php. External JS/CSS file changes won't be seen by users until the LiteSpeed cache expires or is purged.

**Why:** Spent significant time debugging why JS changes weren't visible — the HTML page itself was cached, so even cache-busting query params on script tags didn't help.

**How to apply:**
- Always test changes in Incognito first
- For critical JS logic, add inline `<script>` blocks in index.php as cache-proof fallbacks (e.g., MutationObserver overrides)
- The .htaccess has LiteSpeed CacheDisable directives — verify they're working
- POST responses (search.php) are never cached and always fresh
- Consider asking user to purge LiteSpeed cache from cPanel when needed
