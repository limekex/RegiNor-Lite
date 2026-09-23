"""Real HTTP route/redirect/head checks, only against synthetic local fixtures."""
import subprocess,json,base64,urllib.request,urllib.error,urllib.parse,html,http.cookiejar,re
from pathlib import Path
from html.parser import HTMLParser
root=Path(__file__).resolve().parent.parent
cli=[str(root/'node_modules/.bin/wp-env'),'run','cli','wp','eval-file']
def run(file,*args):
    p=subprocess.run(cli+['/var/www/html/rnl-tests/'+file]+list(args),cwd=root,capture_output=True,text=True,check=True)
    return next((json.loads(x) for x in p.stdout.splitlines() if x.startswith('{')),None)
def enc(v):return base64.b64encode(json.dumps(v).encode()).decode()
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs):return None
opener=urllib.request.build_opener(NoRedirect)
def get(url):
    try:r=opener.open(url,timeout=30)
    except urllib.error.HTTPError as e:r=e
    return r.code,r.read().decode(),r.headers
class Head(HTMLParser):
    def __init__(self):super().__init__();self.meta={};self.canonical=[]
    def handle_starttag(self,tag,attrs):
        a=dict(attrs)
        if tag=='meta':self.meta.setdefault(a.get('property',a.get('name')),[]).append(a.get('content'))
        if tag=='link' and a.get('rel')=='canonical':self.canonical.append(a.get('href'))
checks=0
def check(ok,message):
    global checks
    checks+=1
    if not ok:raise AssertionError(message)
f=run('http-fixtures.php','public'); routing=None
try:
    routing=run('permalink-http-fixtures.php','setup',enc(f)); current=routing['course_url'];period=routing['period_url']
    overview='http://localhost:8888/kursrekke/'
    for query in ['', '?rnl_view=week', '?rnl_day=1&rnl_level=0&rnl_view=list&utm_source=test']:
        status,body,headers=get(overview+query)
        reference_status,reference,_=get(routing['base']+query)
        check(status==200 and reference_status==200,'Root overview or shortcode page unavailable')
        def output(markup):
            section=re.search(r'<section\b[^>]*class="rnl-ui rnl-public.*?</section>',markup,re.S)
            return re.sub(r'data-rnl-now="[0-9]+"','data-rnl-now="TIME"',section.group()) if section else None
        check(output(body) is not None and output(body)==output(reference),'Root differs from default shortcode output')
        check('no-store' in headers.get('Cache-Control',''),'Root overview cached')
        parsed=Head();parsed.feed(body)
        check(parsed.canonical==[overview],'Root canonical wrong or duplicated')
    status,body,headers=get(overview.rstrip('/')+'?utm_source=test&_gl=keep&rnl_day=1')
    check(status==301 and headers.get('Location')==overview+'?utm_source=test&_gl=keep&rnl_day=1','Root slash redirect loses filters or tracking')
    status,body,headers=get(overview+'?rnl_period='+str(f['period'])+'&rnl_view=week&utm_source=test')
    check(status==301 and headers.get('Location')==period+'?rnl_view=week&utm_source=test','Root period selection loses context')
    for path in [current,period]:
        status,body,headers=get(path)
        check(status==200,'Direct route failed: '+path+' '+str(status))
        check('HTTP testkurs' in body,'Course content missing')
        check('no-store' in headers.get('Cache-Control',''),'Pretty route cached')
        parsed=Head();parsed.feed(body)
        check(parsed.canonical==[path],'Canonical duplicate/wrong: '+str(parsed.canonical))
        check(parsed.meta.get('og:url')==[path],'OG URL wrong')
        check(len(parsed.meta.get('description',[]))==1,'Description duplicate/missing')
    status,body,headers=get(current);parsed=Head();parsed.feed(body)
    check(parsed.meta['og:title'][0].startswith('Salsa "Øvet" & moro'),'Custom share title missing')
    check(parsed.meta['description']==['Delingsbeskrivelse uten HTML.'],'Custom description missing')
    check(parsed.meta['twitter:card']==['summary'],'Social fallback wrong')
    check('PostalAddress' in body and '#session-' in body,'Structured event identity/location missing')
    query='utm_source=google&utm_campaign=host&_gl=test%2Flinker&rnl_view=week'
    legacy=routing['base']+'?rnl_course='+str(f['group'])+'&'+query
    for path in [legacy,routing['old_url']+'?'+query,routing['middle_url']+'?'+query]:
        status,body,headers=get(path)
        check(status==301,'Legacy/alias not redirected: '+str(status)+' '+path)
        target=headers.get('Location','');parts=urllib.parse.urlsplit(target)
        check(urllib.parse.urlunsplit(parts._replace(query=''))==current,'Redirect chain or wrong target')
        retained=urllib.parse.parse_qs(parts.query)
        check(retained.get('_gl')==['test/linker'] and retained.get('utm_source')==['google'] and retained.get('rnl_view')==['week'],'Tracking/filter lost')
    check(get(routing['old_period'])[2].get('Location')==period,'Old period does not redirect')
    check(get(current.rstrip('/'))[2].get('Location')==current,'Trailing slash not normalized')
    status,body,headers=get(current+'?'+query);parsed=Head();parsed.feed(body)
    check(parsed.canonical==[current] and 'noindex' in parsed.meta['robots'][0],'Filtered canonical/robots wrong')
    origin='http://localhost:8888/'
    status,xml,headers=get(origin+'?rnl_sitemap=1')
    check(status==200 and current in html.unescape(xml) and period in html.unescape(xml),'Independent sitemap missing routes')
    check('no-store' in headers.get('Cache-Control',''),'Sitemap cached')
    check(get(origin+'kursrekke/ukjent/'+current.rstrip('/').split('/')[-1]+'/')[0]==404,'Wrong parent exposes course')
    check(get(period+'ukjent/')[0]==404,'Unknown course exposed')
    check(get(current+'?rnl_course=99999999')[2].get('Location')==current,'Query changes pretty route identity')
    # Exercise the real sharing form as the course manager, while the course stays published.
    class SharingForm(HTMLParser):
        def __init__(self,body):super().__init__();self.active=False;self.fields={};self.textarea=None;self.action='';self.feed(body)
        def handle_starttag(self,tag,attrs):
            a=dict(attrs)
            if tag=='form':self.active='data-rnl-sharing' in a;self.action=a.get('action','') if self.active else self.action
            if not self.active:return
            if tag=='input' and a.get('name') and a.get('type')!='submit':self.fields[a['name']]=a.get('value','')
            if tag=='textarea':self.textarea=a.get('name');self.fields[self.textarea]=''
        def handle_data(self,data):
            if self.active and self.textarea:self.fields[self.textarea]+=data
        def handle_endtag(self,tag):
            if tag=='form':self.active=False
            if tag=='textarea':self.textarea=None
    auth=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def authorized(url,data=None):
        try:r=auth.open(urllib.request.Request(url,data=urllib.parse.urlencode(data).encode() if data is not None else None),timeout=30)
        except urllib.error.HTTPError as e:r=e
        return r.code,r.read().decode()
    authorized(origin+'wp-login.php')
    authorized(origin+'wp-login.php',{'log':f['login'],'pwd':f['password'],'testcookie':'1','redirect_to':origin+'wp-admin/admin.php?page=reginor-lite'})
    adminurl=origin+'wp-admin/admin.php?page=reginor-lite&period='+str(f['period'])+'&group='+str(f['group'])
    status,body=authorized(adminurl);form=SharingForm(body)
    check(status==200 and form.fields.get('action')=='rnl_sharing','Published course sharing form missing')
    invalid=dict(form.fields);invalid['_wpnonce']='invalid'
    check(authorized(form.action,invalid)[0]==403,'Sharing CSRF protection failed')
    status,body=authorized(form.action,form.fields)
    check(status==200 and 'Delingsvalgene er lagret' in body,'Manager cannot save sharing on published course')
    check(get(current)[0]==200,'Sharing save unpublished course')
    check(authorized(form.action,form.fields)[0]==409,'Stale sharing form overwrites newer version')
    run('http-fixtures.php','public-state',str(f['period']),'expired')
    for path in [current,period,routing['old_url'],legacy]:
        status,body,headers=get(path)
        check(status==404 and not headers.get('Location'),'Hidden route redirects or visible')
        check('Salsa &quot;Øvet&quot;' not in body and 'Delingsbeskrivelse uten HTML.' not in body,'Hidden metadata leak')
    check(current not in html.unescape(get(origin+'?rnl_sitemap=1')[1]),'Expired sitemap entry')
    status,body,headers=get(overview)
    check(status==200 and current not in html.unescape(body),'Root exposes expired course or stops being an overview')
    print(f'M4.1 HTTP controls passed: {checks}')
finally:
    if routing:run('permalink-http-fixtures.php','restore',enc(routing['old']))
    cleanup={key:f[key] for key in ['created','user','old_types','types_existed','old_page','capacity_cron_existed']}
    run('http-fixtures.php','cleanup',enc(cleanup))
