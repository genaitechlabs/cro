<?php
/* ═══════════════════════════════════════════════════════════════
   OWLEYE — api/ai/AiAdapter.php
   Routes to the configured AI provider. Never edit this file
   to switch providers — change AI_PROVIDER in config.php only.
   ═══════════════════════════════════════════════════════════════ */

require_once __DIR__ . '/openai.php';
require_once __DIR__ . '/claude.php';

class AiAdapter
{
    /**
     * Analyse a store and return scores for all 28 parameters.
     *
     * @param string      $url      The store URL being analysed
     * @param array       $pages    ['home'=>html, 'product'=>html, 'category'=>html, 'cart'=>html]
     * @param string|null $desktop  Base64-encoded desktop screenshot (or null)
     * @param string|null $mobile   Base64-encoded mobile screenshot (or null)
     * @return array  { scores: { checkout_flow: int, ... } }
     */
    public static function analyse(string $url, array $pages, ?string $desktop, ?string $mobile): array
    {
        switch (AI_PROVIDER) {
            case 'claude':
                return ClaudeProvider::analyse($url, $pages, $desktop, $mobile);
            case 'openai':
            default:
                return OpenAIProvider::analyse($url, $pages, $desktop, $mobile);
        }
    }
}
