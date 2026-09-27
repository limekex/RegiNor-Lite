"""Isolated anonymous level routes, metadata and editorial restrictions."""
import subprocess,json,base64,urllib.request,urllib.error,os,re,html as html_module
from pathlib import Path
root=Path(__file__).resolve().parent.parent
cli=[str(root/'node_modules/.bin/wp-env'),'run','cli','wp','eval-file']
def run(file,*args):
 p=subprocess.run(cli+['/var/www/html/rnl-tests/'+file]+list(map(str,args)),cwd=root,text=True,capture_output=True)
 if p.returncode:raise RuntimeError(p.stdout+p.stderr)
 return next((json.loads(x) for x in p.stdout.splitlines() if x.startswith('{')),None)
def enc(v):return base64.b64encode(json.dumps(v).encode()).decode()
def get(url):
 try:r=urllib.request.urlopen(url,timeout=30)
 except urllib.error.HTTPError as e:r=e
 return r.status,r.read().decode(),r.headers,r.url
checks=0
def check(ok,message):
 global checks
 checks+=1
 if not ok:raise AssertionError(message)
f=run('http-fixtures.php','public');a=None
try:
 a=run('level-archive-http-fixtures.php','setup',f['group'])
 status,html,headers,url=get(a['url'])
 if os.environ.get('RNL_LEVEL_PREVIEW'):Path(os.environ['RNL_LEVEL_PREVIEW']).write_text(html)
 check(status==200,'Level URL failed')
 for text in ['HTTP nybegynnerarkiv','INTRO ARKIV','EKSTRA ARKIV','HTTP testkurs','HTTP artikkel A','rnl-faq-list','data-rnl-carousel-play']:
  check(text in html,'Missing '+text)
 check('HEMMELIG ARTIKKEL' not in html,'Private post leaked')
 check('HTTP fremhevet intro' not in html and 'HTTP vanlig intro' not in html,'Other level leaked')
 check('no-store' in headers.get('Cache-Control',''),'Cache enabled')
 check(f'rel="canonical" href="{a["url"]}"' in html,'Wrong canonical')
 check('Nivåarkivets metadata' in html and f'content="{a["url"]}"' in html,'Wrong SEO/OG')
 check(a['url'] in get('http://localhost:8888/?rnl_sitemap=1')[1],'Missing sitemap entry')
 check('HTTP testkurs' in get(a['url']+'?rnl_level=99999&rnl_period=99999')[1],'Visitor parameter widened/broke archive')
 navigation=re.search(r'<nav class="rnl-view-switch"[^>]*>(.*?)</nav>',html,re.S).group(1)
 links=[html_module.unescape(x) for x in re.findall(r'href="([^"]+)"',navigation)]
 check(len(links)==2 and all(x.startswith(a['url']) for x in links),'View switch left archive')
 calendar=get(links[1])[1]
 check('HTTP nybegynnerarkiv' in calendar and 'HTTP vanlig intro' not in calendar,'Calendar lost level restriction')
 check('name="rnl_level"' not in html,'Archive shows irrelevant level choice')
 target=run('level-archive-http-fixtures.php','rename',a['id'])['url']
 check(get(a['url'])[3]==target,'Alias redirect failed')
 run('level-archive-http-fixtures.php','hide',a['id'])
 for address in [a['url'],target]:check(get(address)[0]==404,'Hidden alias exposed')
 print(f'Level archive HTTP controls passed: {checks}')
finally:
 if a:run('level-archive-http-fixtures.php','cleanup',enc(a))
 run('http-fixtures.php','cleanup',enc(f))
