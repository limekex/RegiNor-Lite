"""Read-only anonymous HTTP checks plus explicit local fixture state transitions."""
import json, pathlib, re, subprocess, sys, urllib.parse, urllib.request, urllib.error, html as html_module
from html.parser import HTMLParser

fixtures=json.loads(pathlib.Path(sys.argv[1]).read_text()); base=fixtures['url']; checks=0
class Page(HTMLParser):
    def __init__(self,html):
        super().__init__(); self.links=[]; self.canonical=[]; self.scripts=[]; self.script=None; self.feed(html)
    def handle_starttag(self,tag,attrs):
        a=dict(attrs)
        if tag=='a': self.links.append(a)
        if tag=='link' and a.get('rel')=='canonical': self.canonical.append(a.get('href'))
        if tag=='script' and a.get('type')=='application/ld+json': self.script=''
    def handle_data(self,data):
        if self.script is not None: self.script+=data
    def handle_endtag(self,tag):
        if tag=='script' and self.script is not None: self.scripts.append(json.loads(self.script)); self.script=None

def check(ok,message):
    global checks
    checks+=1
    assert ok,message

def url(**query):
    parts=urllib.parse.urlsplit(base); params=urllib.parse.parse_qs(parts.query); params.update(query)
    return urllib.parse.urlunsplit(parts._replace(query=urllib.parse.urlencode(params,doseq=True)))

def get(target):
    try:
        with urllib.request.urlopen(target,timeout=20) as r: return r.status,r.read().decode(),r.headers
    except urllib.error.HTTPError as e: return e.code,e.read().decode(),e.headers

def state(mode):
    subprocess.run(['node_modules/.bin/wp-env','run','cli','wp','eval-file','/var/www/html/rnl-tests/http-fixtures.php','public-state',str(fixtures['period']),mode],check=True,capture_output=True)

# Select our own synthetic period: a user may also have published courses locally.
status,html,headers=get(url(rnl_period=fixtures['period']))
check(status==200 and 'HTTP testkurs' in html and 'Alle nivåer' in html,'Published course missing from public listing')
check('no-store' in headers.get('Cache-Control',''),'Public page allows stale page cache')
check(headers.get('CDN-Cache-Control') == 'no-store' and headers.get('Cloudflare-CDN-Cache-Control') == 'no-store', 'CDN cache headers missing')
refresh_status, refresh_html, refresh_headers = get(url(rnl_period=fixtures['period'], rnl_refresh='synthetic', utm_source='test'))
check(refresh_status == 200 and 'HTTP testkurs' in refresh_html and 'no-store' in refresh_headers.get('Cache-Control', ''), 'Refresh request loses course context or no-cache')
parsed=Page(html)
check(any(s.get('@type')=='CollectionPage' for s in parsed.scripts),'List schema missing')
check('assets/interface.css' in html and 'assets/interface.js' in html,'Styles or progressive enhancements missing')
for embed_url in fixtures['embed_urls']:
    embed_url += ('&' if '?' in embed_url else '?') + urllib.parse.urlencode({'rnl_period': fixtures['period']})
    embed_status, embed_html, embed_headers = get(embed_url)
    check(embed_status == 200 and 'HTTP testkurs' in embed_html, 'Secondary shortcode or synced block overview is missing')
    check('no-store' in embed_headers.get('Cache-Control', ''), 'Secondary overview does not disable cache before theme output')
    check(embed_headers.get('Cloudflare-CDN-Cache-Control') == 'no-store' and embed_headers.get('X-LiteSpeed-Cache-Control') == 'no-cache', 'Embedded shortcode CDN/LiteSpeed headers missing')
for view in ['list', 'week']:
    level_status, level_html, _ = get(url(rnl_period=fixtures['period'], rnl_level=fixtures['level'], rnl_view=view))
    check(level_status == 200 and 'HTTP testkurs' in level_html and 'HTTP nivå Nybegynner' in level_html, 'Named level filter does not render matching courses')
    overview_links = Page(level_html).links
    outgoing = [a for a in overview_links if a.get('data-rnl-letsreg') == str(fixtures['group'])]
    check(len(outgoing) == 1 and outgoing[0].get('target') == '_blank' and outgoing[0].get('rel') == 'noopener', 'Overview direct signup missing or not opening safely in new tab')
    check('Meld meg på' in level_html and any('Se kurset:' in a.get('aria-label', '') for a in overview_links), 'Overview does not offer separate read and signup actions')
    check('Jeg er nybegynner' not in level_html and 'Jeg har danset før' not in level_html, 'Legacy audience filter still appears')
detail_url=url(rnl_course=fixtures['group'],rnl_period=fixtures['period'],rnl_level=fixtures['level'],rnl_view='week',rnl_day=1)
status,html,headers=get(detail_url); parsed=Page(html)
check(status==200 and 'Meld deg på hos LetsReg' in html and 'Alle kurskveldene' in html,'Course detail not server rendered')
check('assets/map.js' in html and 'assets/vendor/leaflet/leaflet.js' in html and 'assets/vendor/leaflet/leaflet.css' in html, 'Course map assets missing after pretty-URL redirect')
check(len(parsed.canonical)==1 and parsed.canonical[0]==fixtures['course_url'],'Canonical loses course identity or retains filters')
check(any(s.get('@type')=='Course' for s in parsed.scripts),'Course schema missing')
check(any(s.get('educationalLevel') == 'HTTP nivå Nybegynner' for s in parsed.scripts), 'Schema level differs from course setup')
check(any('rnl_level=' + str(fixtures['level']) in a.get('href', '') and '#rnl-course-' in a.get('href', '') for a in parsed.links), 'Return loses selected level')
check('schema.org/InStock' not in html,'Unverified stock is asserted in schema')
for scenario in ['fresh','full','roles','expired','error']:
    subprocess.run(['node_modules/.bin/wp-env','run','cli','wp','eval-file','/var/www/html/rnl-tests/http-fixtures.php','capacity-scenario',str(fixtures['group']),scenario],check=True,capture_output=True)
    _,demo_html,_=get(detail_url)
    check('Påmelding Tilgjengelig' in demo_html and 'Meld deg på hos LetsReg' in demo_html and 'schema.org/InStock' not in demo_html and '_rnl_capacity' not in demo_html,'Demo affected real booking or leaked stock: '+scenario)
check(any('rnl_view=week' in a.get('href','') and '#rnl-course-' in a.get('href','') for a in parsed.links),'Return does not preserve view and focus anchor')
check('history' not in html and 'actor_id' not in html,'Private metadata exposed')
status,week,headers=get(url(rnl_period=fixtures['period'],rnl_view='week'))
check(status==200 and 'Mandager' in week and 'rnl-timetable' in week,'Week view missing')
status,empty,headers=get(url(rnl_period=fixtures['period'],rnl_level=999999999))
check('Ingen kurs passer akkurat' in empty,'No understandable empty filter state')
check(get(url(rnl_course='999999999'))[0]==404,'Unknown group does not return 404')
check(get(url(rnl_course='invalid'))[0]==404,'Malformed group selector does not return 404')
# Query-based sitemap route works with both plain and pretty permalinks.
origin=urllib.parse.urlunsplit(urllib.parse.urlsplit(base)._replace(path='/',query='',fragment=''))
sitemap=origin+'?sitemap=reginor&paged=1'
status,xml,headers=get(sitemap)
check(status==200 and fixtures['course_url'] in html_module.unescape(xml),'Public course missing from sitemap')
check('no-store' in headers.get('Cache-Control',''),'Sitemap allows stale visibility cache')
state('closed'); status,html,headers=get(detail_url)
check(status==200 and 'Stengt' in html and 'Meld deg på hos LetsReg' not in html,'Closed sale still has booking action')
state('open'); state('cancelled'); status,html,headers=get(detail_url)
check('Avlyst' in html and 'Meld deg på hos LetsReg' not in html,'Cancelled period still permits booking')
state('open'); state('expired')
check(get(detail_url)[0]==404,'Expired course direct URL is not 404')
check('HTTP testkurs' not in get(base)[1],'Expired course still listed')
check('HTTP testkurs' not in get(url(rnl_view='week'))[1],'Expired course still in calendar')
check(get(url(rnl_period=fixtures['period']))[0]==404,'Expired explicit period bypasses visibility')
check('HTTP testkurs' not in get(origin+'?p='+str(fixtures['group']))[1],'Raw post ID bypasses visibility')
check('HTTP testkurs' not in get(origin+'?post_type=rnl_group&p='+str(fixtures['group']))[1],'Raw CPT query bypasses visibility')
check(fixtures['course_url'] not in html_module.unescape(get(sitemap)[1]),'Expired course remains in sitemap')
state('open'); state('future')
check(get(detail_url)[0]==404,'Future visibility leaks detail')
check('HTTP testkurs' not in get(base)[1],'Future visibility leaks list')
# Existing core routes must not expose raw private course objects.
status,body,headers=get(origin+'?rest_route=/wp/v2/rnl_groups')
check(status==404,'Raw course REST route exposed')
status,feed,headers=get(origin+'?feed=rss2&post_type=rnl_group')
check('HTTP testkurs' not in feed,'Hidden course leaks through core feed')
status,search,headers=get(origin+'?s=HTTP+testkurs')
check('Meld deg på hos LetsReg' not in search,'Hidden course leaks through search rendering')
status,rest_page,headers=get(origin+'?rest_route=/wp/v2/pages/'+str(fixtures['page']))
check(status==200 and 'HTTP testkurs' not in rest_page,'Hidden course leaks through page REST rendering')
check('no-store' in headers.get('Cache-Control',''),'Embedded page REST response allows stale cache')
check(headers.get('Cloudflare-CDN-Cache-Control') == 'no-store' and headers.get('X-LiteSpeed-Cache-Control') == 'no-cache', 'REST CDN/LiteSpeed policy missing')
status,body,headers=get(origin+'?rest_route=/reginor/v1/journey')
check(status in [400,401,403,404,405] and headers.get('Cloudflare-CDN-Cache-Control') == 'no-store' and headers.get('X-LiteSpeed-Cache-Control') == 'no-cache', 'REST error response cached')
state('open')
print(f'Offentlige HTTP-kontroller bestått: {checks}.')
