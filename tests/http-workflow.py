"""Exercises real local WordPress forms, cookies, redirects and nonces (standard library only)."""
import http.cookiejar
import json
import pathlib
import re
import sys
import subprocess
import urllib.error
import urllib.parse
import urllib.request
from html.parser import HTMLParser

class Forms(HTMLParser):
    def __init__(self, html):
        super().__init__(); self.forms=[]; self.current=None; self.select=None; self.option=None; self.textarea=None; self.owner=None
        self.feed(html)
    def handle_starttag(self, tag, attrs):
        a=dict(attrs)
        if tag=='form':
            self.current={'id':a.get('id',''),'action':a.get('action',''),'fields':{}}; self.forms.append(self.current)
        if tag in ('input','select','textarea'):
            self.owner=next((f for f in self.forms if f['id']==a['form']),None) if a.get('form') else self.current
        if self.owner is None: return
        fields=self.owner['fields']
        if tag=='input' and a.get('name'):
            if a.get('type') in ('checkbox','radio') and 'checked' not in a: return
            if a.get('type')=='submit': return
            fields[a['name']]=a.get('value','')
        elif tag=='select': self.select=a.get('name')
        elif tag=='option' and self.select:
            if self.select not in fields or 'selected' in a: fields[self.select]=a.get('value','')
        elif tag=='textarea': self.textarea=a.get('name'); fields[self.textarea]=''
    def handle_endtag(self,tag):
        if tag=='form': self.current=None
        elif tag=='select': self.select=None
        elif tag=='textarea': self.textarea=None
    def handle_data(self,data):
        if self.owner is not None and self.textarea: self.owner['fields'][self.textarea]+=data
    def command(self,name):
        matches=[f for f in self.forms if f['fields'].get('command')==name]
        assert matches, f'Missing form: {name}'
        return matches[0]

path=pathlib.Path(sys.argv[1]); fixtures=json.loads(path.read_text()); base='http://localhost:8888'
opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
checks=0

def check(condition,message):
    global checks
    checks+=1
    assert condition,message

def request(url, data=None):
    if data is not None: data=urllib.parse.urlencode(data,doseq=True).encode()
    try:
        with opener.open(urllib.request.Request(urllib.parse.urljoin(base,url),data=data),timeout=20) as r:
            return r.status,r.geturl(),r.read().decode(),r.headers
    except urllib.error.HTTPError as e: return e.code,e.geturl(),e.read().decode(),e.headers

def submit(html,command,overrides=None):
    form=Forms(html).command(command); fields=form['fields'].copy(); fields.update(overrides or {})
    return request(form['action'],fields)

def record(id):
    fixtures['created'].append(int(id)); path.write_text(json.dumps(fixtures))

status,url,html,headers=request('/wp-login.php')
status,url,html,headers=request('/wp-login.php',{'log':fixtures['login'],'pwd':fixtures['password'],'wp-submit':'Log In','redirect_to':base+'/wp-admin/admin.php?page=reginor-lite','testcookie':'1'})
check(status==200 and 'Opprett kursperiode' in html,'Manager cannot log into workspace')
check('no-cache' in headers.get('Cache-Control',''),'Private editor must not be cached')
check('Kursinnhold og ressurser' not in html,'Manager sees admin-only menu')
for route in ['/wp-admin/options-general.php','/wp-admin/users.php','/wp-admin/admin.php?page=rnl-resources','/wp-admin/post.php?post='+str(fixtures['instructor'])+'&action=edit']:
    check(request(route)[0]==403,'Privileged admin route open: '+route)
status,url,html,headers=request('/wp-admin/admin.php?page=reginor-lite&new=1')
invalid=Forms(html).command('save'); fields=invalid['fields'].copy(); fields['_wpnonce']='invalid'
check(request(invalid['action'],fields)[0]==403,'Invalid nonce not rejected with HTTP 403')
status,url,html,headers=request('/wp-admin/admin.php?page=reginor-lite&new=1')
# Empty/new forms must preserve user input when validation fails.
status,url,bad,headers=submit(html,'save',{'data[title]':'Bevar dette navnet','data[start_date]':'2030-01-07','data[default_price_minor]':'feil'})
check('Bevar dette navnet' in bad and 'Pris må være' in bad,'Validation error loses entered text')
status,url,html,headers=submit(html,'save',{'data[title]':'HTTP periode','data[start_date]':'2030-01-07','data[default_room_id]':str(fixtures['room']),
    'data[default_session_count]':'2','data[default_price_minor]':'123,45','data[visible_from]':'2030-01-01T10:00','data[visible_until]':'2030-02-01T10:00',
    'data[sales_from]':'2030-01-01T10:00','data[sales_until]':'2030-01-06T10:00'})
period=urllib.parse.parse_qs(urllib.parse.urlparse(url).query).get('period',[None])[0]
check(period is not None and 'Kurs i perioden' in html,'Cannot create period through form'); record(period)
check('updated=1' in url,'Successful POST did not redirect')
period_url=base+'/wp-admin/admin.php?page=reginor-lite&period='+period
check('Hent nytt kurs fra LetsReg' in html, 'Manager lacks import action')
import_status,_,import_html,_=request(period_url+'&step=courses&import=1')
check(import_status==200 and 'data-rnl-import-workspace' in import_html and 'letsreg-import.js' in import_html, 'Manager import route lacks interface or scripts')
status,url,html,headers=submit(html,'add_group',{'course_id':str(fixtures['course']),'data[weekday]':'1'})
group=urllib.parse.parse_qs(urllib.parse.urlparse(url).query).get('group',[None])[0]
check(group is not None and 'Kursoppsett' in html,'Cannot create group'); record(group)
check('data-rnl-letsreg-picker' in html and 'data-search-form' in html and 'letsreg-picker.js' in html, 'Manager course lookup lacks interface or script')
check('page=rnl-letsreg' not in html, 'Manager course lookup links to administrator settings')
group_url=url
check('>Utseende<' in html and 'Dette kurset tilbyr drop-in' in html and 'data-rnl-palette-samples' in html, 'Course setup lacks appearance/drop-in fields')
editor=Forms(html).command('preview_group')['fields']
check('data[start_date]' not in editor and 'data[first_date]' in editor, 'Course has duplicate first-date controls')
check(all(key in editor for key in ['data[timezone]', 'data[appearance_color]', 'data[breaks][0][from]']), 'Trailing controls are not associated with course form')
check(html.index('>Undervisning<') < html.index('name="data[featured]"') < html.index('>Pris og påmelding<') < html.index('id="rnl-letsreg-course"') < html.index('>Kursfrie dager og flere valg<') < html.index('>Utseende<'), 'Course sections are not in the requested reading/tab order')
status,url,html,headers=submit(html,'preview_group',{'data[price_from]':'1','data[registration_from]':'2030-01-02T10:00','data[registration_until]':'2030-01-05T10:00','data[dropin_enabled]':'1','data[dropin_price_minor]':'200,50','data[featured]':'1','data[appearance_custom]':'1','data[appearance_color]':'#334455','data[appearance_alpha]':'50','data[instructor_ids][]':str(fixtures['instructor']),'data[registration_status]':'available','data[registration_url]':'https://www.letsreg.com/no/register/SalsaØvet1_4_26'})
check('https://www.letsreg.com/no/register/Salsa%C3%98vet1_4_26' in html, 'Unicode LetsReg URL rejected or encoded incorrectly in submitted form')
check('Drop-in: 200,50 kr per person og kurskveld.' in html, 'Drop-in price is missing from preview')
check('Forhåndsvisning – ikke lagret' in html and '2030-01-14' in html and '123,45' in html,'Group preview missing schedule or price')
status,url,html,headers=submit(html,'confirm')
check('Kursoppsett' in html and '2030-01-14' in html,'Cannot confirm group')
check(Forms(html).command('preview_group')['fields']['data[registration_from]'] == '2030-01-02T10:00', 'Course registration window lost on save')
check(Forms(html).command('preview_group')['fields']['data[appearance_color]'] == '#334455', 'External appearance field lost on submit')
check(Forms(html).command('preview_group')['fields']['data[dropin_price_minor]'] == '200,50', 'Drop-in price changed after save')
check(Forms(html).command('preview_group')['fields']['data[registration_url]'] == 'https://www.letsreg.com/no/register/Salsa%C3%98vet1_4_26', 'Unicode URL lost or double encoded after confirmation')
subprocess.run(['node_modules/.bin/wp-env','run','cli','wp','eval-file','/var/www/html/rnl-tests/http-fixtures.php','capacity-scenario',group,'fresh'],check=True,capture_output=True)
capacity_url='/wp-admin/admin.php?page=rnl-capacity&group='+group
status,_,capacity,headers=request(capacity_url)
check(status==200 and 'Demonstrasjon' in capacity and 'Lagre prøveoppsett' not in capacity,'Manager capacity view is missing or exposes configuration')
check('no-cache' in headers.get('Cache-Control',''),'Capacity controls may be cached')
form=Forms(capacity).command('check_capacity'); fields=form['fields'].copy(); fields['_wpnonce']='invalid'
check(request(form['action'],fields)[0]==403,'Capacity check accepts invalid nonce')
check(submit(capacity,'check_capacity',{'command':'configure_capacity','version':'3','scenario':'full'})[0]==403,'Manager can change capacity configuration')
status,_,capacity,headers=submit(capacity,'check_capacity')
check(status==200 and 'Valget er lagret' in capacity,'Manual check was not queued through HTTP')
check(submit(capacity,'check_capacity')[0]==429,'Repeated manual check is not rate limited')
status,url,html,headers=request(group_url)
# Escaping: no script element may be injected by a presentation field.
status,url,preview,headers=submit(html,'preview_group',{'data[title]':'<script>alert(1)</script> Kurs'})
check('<script>alert(1)</script>' not in preview,'Script injection in preview')
status,url,html,headers=request(period_url+'&step=publish')
status,url,html,headers=submit(html,'preview_publication',{'windows':'1'})
check('Kontrollene er bestått' in html,'Valid period fails publication checks')
status,url,html,headers=submit(html,'publish')
check('Publisert i kursoppsettet' in html,'Publication did not complete')
status,url,html,headers=request(period_url+'&step=period')
status,url,html,headers=submit(html,'lifecycle')
check('Kurs i perioden' in html,'Cannot unpublish')
status,url,html,headers=request(period_url+'&step=manage')
status,url,html,headers=submit(html,'copy',{'data[title]':'HTTP neste periode','data[start_date]':'2030-03-04'})
copy=urllib.parse.parse_qs(urllib.parse.urlparse(url).query).get('period',[None])[0]
check(copy and copy!=period and 'HTTP neste periode' in html,'Cannot copy period'); record(copy)
status,url,html,headers=request(base+'/wp-admin/admin.php?page=reginor-lite&period='+copy+'&step=publish')
check('Synlig fra: Ikke satt' in html,'Copy retained old windows')
status,url,html,headers=request(base+'/wp-admin/admin.php?page=reginor-lite&period='+copy+'&step=manage')
status,url,html,headers=submit(html,'lifecycle')
check('Papirkurv' in html and 'Gjenopprett som kladd' in html,'Cannot trash copy')
status,url,html,headers=submit(html,'lifecycle')
check('HTTP neste periode' in html and 'Kurs i perioden' in html,'Cannot restore copy')
# CPTs remain inaccessible to public channels, even when administrative publication works.
anonymous=urllib.request.build_opener()
try:
    response=anonymous.open(base+'/?post_type=rnl_group&p='+group,timeout=20)
    body=response.read().decode(); check('HTTP testkurs' not in body,'Private course leaked publicly')
except urllib.error.HTTPError as e: check(e.code==404,'Unexpected public response')
print(f'HTTP-kontroller bestått: {checks}.')
