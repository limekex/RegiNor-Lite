"""Anonymous HTTP + independent ICS parser. Synthetic local fixtures only."""
import os, subprocess, json, base64, urllib.request, urllib.error, urllib.parse
from concurrent.futures import ThreadPoolExecutor
from html.parser import HTMLParser
from pathlib import Path
root = Path(__file__).resolve().parent.parent
cli = [str(root/'node_modules/.bin/wp-env'), 'run', 'cli', 'wp', 'eval-file']
parser_python = os.environ.get('RNL_CALENDAR_PYTHON', 'python3')
subprocess.run([parser_python, '-c', 'import icalendar'], check=True)
def run(file, *args):
    p = subprocess.run(cli+['/var/www/html/rnl-tests/'+file]+list(map(str,args)), cwd=root, capture_output=True, text=True)
    if p.returncode:
        raise RuntimeError(p.stdout+p.stderr)
    return next((json.loads(x) for x in p.stdout.splitlines() if x.startswith('{')), None)
def enc(v):return base64.b64encode(json.dumps(v).encode()).decode()
def get(url, method='GET', headers=None):
    try:r=urllib.request.urlopen(urllib.request.Request(url, method=method, headers=headers or {}),timeout=30)
    except urllib.error.HTTPError as e:r=e
    return r.code,r.read(),r.headers
parse_code='''import sys,json
from icalendar import Calendar
c=Calendar.from_ical(sys.stdin.buffer.read())
events=[]
for e in c.walk('VEVENT'):
    assert not e.errors, e.errors
    events.append(dict(uid=str(e['UID']),start=e.decoded('DTSTART').isoformat(),end=e.decoded('DTEND').isoformat(),status=str(e['STATUS']),sequence=int(e['SEQUENCE']),title=str(e['SUMMARY']),url=str(e['URL'])))
print(json.dumps(events))'''
def parse(body):return json.loads(subprocess.run([parser_python,'-c',parse_code],input=body,capture_output=True,check=True).stdout)
class Elements(HTMLParser):
    def __init__(self,body):super().__init__();self.items=[];self.feed(body.decode())
    def handle_starttag(self,tag,attrs):self.items.append((tag,dict(attrs)))
checks=0
def check(ok,message):
    global checks
    checks+=1
    if not ok:raise AssertionError(message)
f=run('http-fixtures.php','public')
try:
    c=run('calendar-http-fixtures.php','read',f['group']);feed=c['feed']
    status,body,headers=get(feed);original=parse(body)
    check(status==200 and headers.get_content_type()=='text/calendar','Feed response')
    check('no-store' in headers.get('Cache-Control','') and 'noindex' in headers.get('X-Robots-Tag',''),'Cache/index policy')
    check(len(original)==2 and all(e['status']=='CONFIRMED' for e in original),'Actual sessions')
    check(original[0]['start']=='2030-01-07T17:00:00+00:00','Wrong actual date/time')
    check(len({e['uid'] for e in original})==2,'Duplicate UID')
    check(all(len(line)<=75 and line.decode('utf-8') is not None for line in body.split(b'\r\n')),'Invalid byte folding')
    status,download,h=get(feed+'&rnl_calendar_download=1')
    check(status==200 and download==body and h['Content-Disposition'].startswith('attachment'),'Download differs from feed')
    check(get(feed,'HEAD')[1]==b'','HEAD includes body')
    check(get(feed,'POST')[0]==405,'POST accepted')
    with ThreadPoolExecutor(max_workers=4) as pool:
        results=list(pool.map(lambda _:get(feed), range(4)))
    check(all(r[0]==200 and r[1]==body for r in results),'Concurrent reads revise or corrupt feed')
    status,html,h=get(f['course_url']+'?utm_source=private-source&gclid=private-ad&_gl=private-linker')
    elements=Elements(html);panels=[a for tag,a in elements.items if 'data-rnl-share-url' in a]
    check(status==200 and len(panels)==1,'Sharing section missing')
    check(panels[0]['data-rnl-share-url']==f['course_url'],'Shared canonical polluted')
    destinations=[a['href'] for tag,a in elements.items if tag=='a' and ('facebook.com/sharer/' in a.get('href','') or 'wa.me/' in a.get('href',''))]
    check(len(destinations)==2 and not any('private-' in link for link in destinations),'Social destinations missing/polluted')
    inputs=[a.get('value') for tag,a in elements.items if tag=='input' and 'readonly' in a]
    check(feed in inputs and f['course_url'] in inputs,'No-JS readable links missing')
    check(not any(tag in ['script','iframe','img'] and any(host in a.get('src','') for host in ['facebook.com','whatsapp.com','wa.me']) for tag,a in elements.items),'External platform loaded before action')
    run('calendar-http-fixtures.php','move',f['group']);moved=parse(get(feed)[1])
    check({e['uid'] for e in moved}=={e['uid'] for e in original},'Move created a new UID')
    check(any(e['start']=='2030-01-07T17:30:00+00:00' and e['end']=='2030-01-07T19:00:00+00:00' for e in moved),'Move/duration absent')
    check(max(e['sequence'] for e in moved)>max(e['sequence'] for e in original),'Missing move revision')
    run('calendar-http-fixtures.php','rename',f['group']);renamed=parse(get(feed)[1])
    check({e['uid'] for e in renamed}=={e['uid'] for e in original} and all('http-calendar-renamed-' in e['url'] for e in renamed),'Slug broke subscription/canonical')
    run('http-fixtures.php','public-state',f['period'],'cancelled');cancelled=parse(get(feed)[1])
    check(all(e['status']=='CANCELLED' for e in cancelled),'Period cancellation not propagated')
    run('http-fixtures.php','public-state',f['period'],'expired')
    for url in [feed,feed+'&rnl_calendar_download=1']:
        status,hidden,h=get(url,headers={'If-None-Match':'*','If-Modified-Since':'Wed, 01 Jan 2031 00:00:00 GMT'})
        check(status==404 and b'HTTP testkurs' not in hidden and b'VEVENT' not in hidden,'Warm/conditional hidden response leaks')
        check('no-store' in h.get('Cache-Control',''),'Hidden response cached')
    check(get(feed.replace('rnl_calendar=','rnl_calendar[]='))[0]==404,'Array token accepted')
    check(get(feed.replace('rnl_calendar_language=default','rnl_calendar_language=invalid'))[0]==404,'Invalid language accepted')
    print(f'Calendar HTTP / independent ICS parser checks passed: {checks}')
finally:run('http-fixtures.php','cleanup',enc(f))
