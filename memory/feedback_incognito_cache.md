---
name: HTML caching may block JS updates
description: Historically the host aggressively cached index.php HTML output, so JS/CSS changes weren't visible until purge/incognito. Unconfirmed whether this still applies on DigitalOcean. Inline scripts + server-side rendering remain cache-proof fallbacks.
type: feedback
---

The previous host served a page cache that stored the full HTML output of
index.php, so external JS/CSS file changes weren't seen by users until the
cache expired or was purged. **Unconfirmed whether DigitalOcean has any such
caching layer** — verify the current stack (plain nginx/Apache has no page
cache; a CDN or LiteSpeed in front would).

**Why:** Previously spent significant time debugging why JS changes weren't
visible — the HTML page itself was cached, so even cache-busting query params
on script tags didn't help.

**How to apply:**
- When a JS/CSS change isn't showing up, test in Incognito first to rule out caching
- For critical JS logic, inline `<script>` blocks in index.php are cache-proof fallbacks (e.g., MutationObserver overrides)
- `.htaccess` still carries LiteSpeed CacheDisable directives (harmless no-op if no LiteSpeed)
- POST responses (search.php) are never cached and always fresh
- If a caching layer is confirmed on DigitalOcean, note how to purge it here
