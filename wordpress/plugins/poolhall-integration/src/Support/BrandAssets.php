<?php
/** Shared immutable artwork for site, email and generated documents. */
declare(strict_types=1);
namespace Poolhall\Integration\Support;
final class BrandAssets {
    private const FILES = ['logo-light.svg', 'logo-dark.svg', 'mark-dark.svg', 'mark-light.svg', 'mark-current.svg', 'mark-email.png', 'wordmark-email.png', 'mark-document.png', 'site-icon.png'];
    public static function path(string $file): string {
        if (!in_array($file, self::FILES, true)) { return ''; }
        $path = POOLHALL_INTEGRATION_DIR . 'assets/branding/' . $file;
        return is_file($path) ? $path : '';
    }
    public static function url(string $file): string {
        return self::path($file) !== '' ? POOLHALL_INTEGRATION_URL . 'assets/branding/' . rawurlencode($file) . '?ver=' . rawurlencode(POOLHALL_INTEGRATION_VERSION) : '';
    }
    public static function inline_mark(): string {
        $path = self::path('mark-dark.svg');
        return $path !== '' ? (string) file_get_contents($path) : '';
    }
}
