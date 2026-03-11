<?php
/* ═══════════════════════════════════════════════════════════════
   OWLEYE — api/pagespeed/PageSpeedAdapter.php
   Routes to the configured PageSpeed provider.
   To add a new provider: implement a static score(string $url): ?int
   method in a new file and add the case below.
   ═══════════════════════════════════════════════════════════════ */

class PageSpeedAdapter
{
    /**
     * Returns a 0–100 mobile performance score, or null on failure.
     */
    public static function score(string $url): ?int
    {
        // Only Google PSI supported for now — add cases as needed
        require_once __DIR__ . '/googlepsi.php';
        return GooglePsiProvider::score($url);
    }
}
