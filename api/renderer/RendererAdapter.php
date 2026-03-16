<?php
/* ═══════════════════════════════════════════════════════════════
   api/renderer/RendererAdapter.php
   Factory + interface for headless rendering fallback.

   Usage:
     $html = RendererAdapter::fetch('https://example.com');

   To switch provider: change RENDERER_PROVIDER in config.php.
   Each provider must implement RendererInterface.
   ═══════════════════════════════════════════════════════════════ */

require_once __DIR__ . '/JinaRenderer.php';

interface RendererInterface {
    /**
     * Fetch a URL via headless rendering and return raw HTML.
     * Returns empty string on failure.
     */
    public function fetch(string $url): string;
}

class RendererAdapter
{
    /**
     * Fetch a URL using the configured renderer provider.
     * Returns raw HTML string, or empty string on failure.
     */
    public static function fetch(string $url): string
    {
        $provider = defined('RENDERER_PROVIDER') ? RENDERER_PROVIDER : 'jina';

        switch ($provider) {
            case 'jina':
                $renderer = new JinaRenderer();
                break;
            // Add future providers here:
            // case 'scrapingbee':
            //     require_once __DIR__ . '/ScrapingBeeRenderer.php';
            //     $renderer = new ScrapingBeeRenderer();
            //     break;
            default:
                error_log('[OwlEye] Unknown RENDERER_PROVIDER: ' . $provider);
                return '';
        }

        return $renderer->fetch($url);
    }
}
