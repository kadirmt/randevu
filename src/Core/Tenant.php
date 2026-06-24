<?php

namespace App\Core;

/**
 * Çift mod tenant çözümleyici.
 *   - PATH MODE (test, app.path_tenancy=true): /d/{slug} müşteri, /panel, /sys.
 *   - SUBDOMAIN MODE (prod): {slug}.berber.com müşteri, panel/sys/api.berber.com.
 * Her iki mod da aynı bağlamı üretir → alt katmanlar URL şekline bakmaz.
 *
 * resolve() bir bağlam dizisi döndürür ve statik olarak saklar:
 *   class : 'shop' | 'panel' | 'sys' | 'api' | 'home' | '404'
 *   shop  : shops satırı (class=shop) ya da null
 *   base  : shop URL'lerine eklenecek önek (path modda '/d/{slug}', subdomain'de '')
 *   path  : önek çıkarıldıktan sonra yönlendirme için iç yol
 */
final class Tenant
{
    public const RESERVED = ['www','panel','sys','api','app','admin','static','assets','cdn','mail','berber'];

    private static array $ctx = ['class' => 'home', 'shop' => null, 'base' => '', 'path' => '/'];

    public static function resolve(array $config, string $uri): array
    {
        $pathMode = (bool) ($config['app']['path_tenancy'] ?? false);
        $root     = strtolower((string) ($config['app']['root_domain'] ?? ''));
        $host     = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));

        $uri   = '/' . trim($uri, '/');
        $first = $uri === '/' ? '' : explode('/', ltrim($uri, '/'))[0];

        if ($pathMode) {
            if ($first === 'd') {
                $parts = explode('/', ltrim($uri, '/'));      // ['d','demo',...]
                $slug  = $parts[1] ?? '';
                $inner = '/' . implode('/', array_slice($parts, 2));
                $shop  = self::lookupSlug($slug);
                if (!$shop) return self::set('404');
                return self::set('shop', $shop, '/d/' . $slug, self::clean($inner));
            }
            if ($first === 'panel') return self::set('panel', null, '/panel', self::strip($uri, 'panel'));
            if ($first === 'sys')   return self::set('sys',   null, '/sys',   self::strip($uri, 'sys'));
            return self::set('home', null, '', $uri);
        }

        // --- SUBDOMAIN MODE ---
        $label  = explode('.', $host)[0] ?? '';
        $isApex = ($host === $root || $host === 'www.' . $root || $label === 'www' || $label === '');
        if ($isApex)            return self::set('home', null, '', $uri);
        if ($label === 'panel') return self::set('panel', null, '', $uri);
        if ($label === 'sys')   return self::set('sys',   null, '', $uri);
        if ($label === 'api')   return self::set('api',   null, '', $uri);
        if (in_array($label, self::RESERVED, true)) return self::set('404');

        $shop = self::lookupSubdomain($host) ?: self::lookupSlug($label);
        if (!$shop) return self::set('404');
        return self::set('shop', $shop, '', $uri);
    }

    private static function set(string $class, ?array $shop = null, string $base = '', string $path = '/'): array
    {
        return self::$ctx = ['class' => $class, 'shop' => $shop, 'base' => $base, 'path' => $path];
    }

    private static function clean(string $p): string
    {
        $p = '/' . ltrim($p, '/');
        return rtrim($p, '/') ?: '/';
    }

    private static function strip(string $uri, string $prefix): string
    {
        $p = preg_replace('#^/' . $prefix . '#', '', $uri);
        return self::clean((string) $p);
    }

    private static function lookupSlug(string $slug): ?array
    {
        $slug = strtolower($slug);
        if ($slug === '' || in_array($slug, self::RESERVED, true)) return null;
        return Database::fetch(
            'SELECT * FROM shops WHERE slug = :s AND is_active = TRUE
               AND (expires_at IS NULL OR expires_at > NOW())',
            [':s' => $slug]
        );
    }

    private static function lookupSubdomain(string $host): ?array
    {
        return Database::fetch(
            'SELECT * FROM shops WHERE subdomain = :h AND is_active = TRUE
               AND (expires_at IS NULL OR expires_at > NOW())',
            [':h' => $host]
        );
    }

    public static function current(): array { return self::$ctx; }
    public static function shop(): ?array   { return self::$ctx['shop']; }
    public static function shopId(): ?int   { return isset(self::$ctx['shop']['id']) ? (int) self::$ctx['shop']['id'] : null; }
    public static function base(): string   { return self::$ctx['base']; }

    /** Aktif dükkân bağlamında URL üretir (path/subdomain moduna göre). */
    public static function url(string $path = ''): string
    {
        $p = '/' . ltrim($path, '/');
        $base = self::$ctx['base'];
        if ($p === '/') return $base !== '' ? $base . '/' : '/';
        return $base . $p;
    }
}
