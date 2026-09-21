"""Static checks and integration tests against a real Apache with this .htaccess."""
from pathlib import Path
from html.parser import HTMLParser
from urllib.parse import urlsplit, unquote
import argparse, collections, http.client, json, re, xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
ORIGIN = 'https://tonirovka.kh.ua'
NS = {'s': 'http://www.sitemaps.org/schemas/sitemap/0.9', 'i': 'http://www.google.com/schemas/sitemap-image/1.1'}
class Page(HTMLParser):
    def __init__(self):
        super().__init__(); self.tags = []; self.json_blocks = []; self.block = None
    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs); self.tags.append((tag, attrs))
        if tag == 'script' and attrs.get('type') == 'application/ld+json': self.block = ''
    def handle_data(self, data):
        if self.block is not None: self.block += data
    def handle_endtag(self, tag):
        if tag == 'script' and self.block is not None:
            self.json_blocks.append(json.loads(self.block)); self.block = None

page = Page(); page.feed((ROOT / 'index.html').read_text())
ids = [a['id'] for _, a in page.tags if a.get('id')]
assert len(ids) == len(set(ids)), 'Duplicate HTML IDs'
assert sum(t == 'h1' for t, _ in page.tags) == 1, 'Expected one H1'
canonicals = [a['href'] for t,a in page.tags if t == 'link' and a.get('rel') == 'canonical']
assert canonicals == [ORIGIN + '/'], canonicals
assert not any(a.get('hreflang') for _,a in page.tags), 'No separate language URLs exist'
# Informative images, including the gallery viewer, need a source and an alt.
for tag, attrs in page.tags:
    if tag == 'img':
        assert attrs.get('src') or attrs.get('srcset'), attrs
        assert attrs.get('alt', '').strip(), attrs
# Public source must use external layout styles, including after admin publishing.
assert all('style' not in attrs for tag, attrs in page.tags), 'Inline style attributes in public HTML'
assert not any(tag == 'style' for tag, attrs in page.tags), 'Embedded public CSS'
assert any(tag == 'link' and a.get('media') == 'print' for tag, a in page.tags), 'Missing print stylesheet'
assert any(tag == 'link' and a.get('rel') == 'apple-touch-icon' and a.get('sizes') == '180x180' for tag, a in page.tags), 'Missing Apple icon'
assert not any(a.get('href','').startswith('mailto:') for tag,a in page.tags), 'Public email link'
assets = set()
for tag, a in page.tags:
    url = a.get('src') if tag in ('img', 'script') else a.get('href') if tag in ('a','link') else None
    if not url: continue
    p = urlsplit(url)
    if p.scheme or p.netloc: continue
    if not p.path:
        if p.fragment: assert p.fragment in ids, f'Missing anchor: {url}'
        continue
    local = unquote(p.path.lstrip('/'))
    assert (ROOT / local).is_file(), f'Missing asset: {url}'
    assets.add('/' + p.path.lstrip('/') + ('?' + p.query if p.query else ''))
for filename in ('sitemap.xml','image-sitemap.xml'):
    tree = ET.parse(ROOT / filename)
    assert [e.text for e in tree.findall('s:url/s:loc', NS)] == [ORIGIN + '/']
    assert not tree.findall('.//{http://www.w3.org/1999/xhtml}link')
    for e in tree.findall('.//i:loc', NS):
        p = urlsplit(e.text)
        assert p.scheme == 'https' and p.netloc == 'tonirovka.kh.ua' and not p.query
        assert (ROOT / unquote(p.path.lstrip('/'))).is_file(), e.text
for p in (ROOT / 'langs').glob('*.json'): json.loads(p.read_text())
assert 'Disallow: /\n' not in (ROOT/'robots.txt').read_text()
businesses = [b for b in page.json_blocks if b.get('@type') in ('LocalBusiness', 'HomeAndConstructionBusiness')]
assert len(businesses) == 1, 'One business entity per page'
business = businesses[0]
assert business['telephone'].startswith('+380')
assert business['address']['addressLocality'] == 'Харків'
for offer in business['hasOfferCatalog']['itemListElement']:
    price = offer.get('priceSpecification')
    if price:
        assert isinstance(price['minPrice'], (int, float)) and price['minPrice'] <= price['maxPrice']
        assert price['priceCurrency'] == 'UAH' and price['unitCode'] == 'MTK'
print(f'Static checks passed: {len(assets)} local assets, {len(page.json_blocks)} JSON-LD blocks, both sitemaps and translations.')

parser = argparse.ArgumentParser(); parser.add_argument('--http'); args = parser.parse_args()
if args.http:
    endpoint = urlsplit(args.http)
    count = 0
    def request(path, host='tonirovka.kh.ua', proto='https', ssl=None, method='GET'):
        conn = http.client.HTTPConnection(endpoint.hostname, endpoint.port or 80, timeout=10)
        headers = {'Host': host}
        if proto: headers['X-Forwarded-Proto'] = proto
        if ssl: headers['X-Forwarded-SSL'] = ssl
        conn.request(method, path, headers=headers)
        res = conn.getresponse(); data = res.read(); result = res.status, dict((k.lower(),v) for k,v in res.getheaders()), data
        conn.close(); return result
    def check(path, status, location=None, **kw):
        global count
        actual, headers, body = request(path, **kw)
        assert actual == status, (path,kw,actual,status,body[:100])
        if location:
            assert headers.get('location') == location, (path,headers.get('location'),location)
            # Follow the canonical target through the local server, never through production.
            target=urlsplit(location)
            final, _, _ = request(target.path or '/', proto='https')
            assert final == 200, (location,final)
        count += 1
        return headers, body
    for path in ('/','/?utm_source=test','/?lang=ru&utm_source=test','/robots.txt','/sitemap.xml','/image-sitemap.xml'):
        check(path,200)
    for method in ('GET','HEAD'):
        for host in ('tonirovka.kh.ua','www.tonirovka.kh.ua'):
            for proto in (None,'https'):
                for path in ('/index.html','/index.html?lang=uk','/?lang=ru','/?LANG=RU','/ru','/ru/','/ru/?lang=ru','/ru/index.html'):
                    check(path,301,ORIGIN+'/',host=host,proto=proto,method=method)
                for old,anchor in [('services','films'),('prices','pricing'),('portfolio','gallery'),('contacts','contacts'),('about','')]:
                    check('/'+old+'.html',301,ORIGIN+'/'+('#'+anchor if anchor else ''),host=host,proto=proto,method=method)
    check('/',301,ORIGIN+'/',proto=None)
    check('/',301,ORIGIN+'/',host='www.tonirovka.kh.ua')
    check('/index.html?utm_source=test',301,ORIGIN+'/?utm_source=test')
    check('/',200,proto=None,ssl='on')
    check('/',200,host='localhost',proto=None)
    for path in ('/not-a-real-page','/ru/not-a-real-page','/404.html'):
        check(path,404)
    for path in ('/.git/config','/admin/.env','/admin/.env.example','/data/backups/backup.json','/admin/config.php','/admin/create_seo_settings_table.sql','/images/3.jpg.backup.1761299280','/403.html'):
        check(path,403)
    headers,_ = check('/admin/',200)
    assert 'noindex' in headers.get('x-robots-tag',''), headers
    status, headers, _ = request('/admin/gallery.php')
    assert status == 302 and headers.get('location') == 'index.php'
    for asset in sorted(assets): check(asset,200)
    # The generator output written by PHP must remain well-formed and canonical.
    for name in ('sitemap.xml','image-sitemap.xml'):
        _,body = check('/'+name,200)
        tree=ET.fromstring(body)
        assert [e.text for e in tree.findall('s:url/s:loc',NS)] == [ORIGIN+'/']
    print(f'Apache integration passed: {count} HTTP cases plus sitemap regeneration.')
