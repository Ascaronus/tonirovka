<?php
/** Shared sitemap generation for the single public page. No database required. */
function sitemapRoot() {
    return defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__);
}

function sitemapLastmod() {
    $file = sitemapRoot() . '/sitemap.xml';
    if (is_readable($file) && preg_match('/<lastmod>(\d{4}-\d{2}-\d{2})<\/lastmod>/', file_get_contents($file), $match)) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $match[1]);
        if ($date && $date->format('Y-m-d') === $match[1] && $match[1] <= date('Y-m-d')) {
            return $match[1];
        }
    }
    return null; // Never invent a new modification date just because a sitemap was rebuilt.
}

function buildSiteSitemap($includeImages = false, $lastmod = null) {
    $root = sitemapRoot();
    $origin = 'https://tonirovka.kh.ua';
    $ns = 'http://www.sitemaps.org/schemas/sitemap/0.9';
    $imageNs = 'http://www.google.com/schemas/sitemap-image/1.1';
    $xml = new DOMDocument('1.0', 'UTF-8');
    $xml->formatOutput = true;
    $urlset = $xml->createElementNS($ns, 'urlset');
    $xml->appendChild($urlset);
    $url = $xml->createElementNS($ns, 'url');
    $urlset->appendChild($url);
    $url->appendChild($xml->createElementNS($ns, 'loc', $origin . '/'));
    $lastmod = $lastmod ?? sitemapLastmod();
    if ($lastmod !== null) {
        $url->appendChild($xml->createElementNS($ns, 'lastmod', $lastmod));
    }
    if ($includeImages) {
        $html = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $html->loadHTML('<?xml encoding="UTF-8">' . file_get_contents($root . '/index.html'));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $sources = [];
        foreach ($html->getElementsByTagName('img') as $img) {
            $sources[] = $img->getAttribute('src');
        }
        foreach ($html->getElementsByTagName('meta') as $meta) {
            if ($meta->getAttribute('property') === 'og:image') {
                $sources[] = $meta->getAttribute('content');
            }
        }
        $seen = [];
        foreach ($sources as $src) {
            $parts = parse_url($src);
            if (!$parts || (isset($parts['host']) && strtolower($parts['host']) !== 'tonirovka.kh.ua')) {
                continue;
            }
            $path = ltrim(rawurldecode($parts['path'] ?? ''), '/');
            if (!preg_match('~^images/[a-zA-Z0-9_./ -]+\.(?:jpe?g|png|gif|webp|avif|svg)$~i', $path)) {
                continue;
            }
            $real = realpath($root . '/' . $path);
            $imageRoot = realpath($root . '/images');
            if (!$real || !$imageRoot || !is_file($real) || strpos($real, $imageRoot . DIRECTORY_SEPARATOR) !== 0 || isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;
            $image = $xml->createElementNS($imageNs, 'image:image');
            $loc = $xml->createElementNS($imageNs, 'image:loc');
            $loc->appendChild($xml->createTextNode($origin . '/' . implode('/', array_map('rawurlencode', explode('/', $path)))));
            $image->appendChild($loc);
            $url->appendChild($image);
        }
    }
    return $xml->saveXML();
}

/** Called after an actual content edit, not a monitoring run. */
function updateSitemapLastmod() {
    require_once __DIR__ . '/business_schema.php';
    $ok = updateBusinessSchema();
    require_once __DIR__ . '/seo_tools.php';
    $html = seoSyncFaq(file_get_contents(INDEX_HTML_PATH));
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'content.php' && seoAutomationSettings()['meta_on_save']) $html = seoApplyMeta($html, seoHeroMeta($html));
    adminAtomicWrite(INDEX_HTML_PATH, $html);
    foreach (['sitemap.xml' => false, 'image-sitemap.xml' => true] as $filename => $images) {
        $xml = buildSiteSitemap($images, date('Y-m-d'));
        if (adminAtomicWrite(sitemapRoot() . '/' . $filename, $xml) === false) {
            error_log('Cannot update ' . $filename);
            $ok = false;
        }
    }
    return $ok;
}
