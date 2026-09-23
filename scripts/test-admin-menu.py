"""Real authenticated HTTP regression for menu URLs and page access; local wp-env only."""
import base64
import http.cookiejar
import json
import re
from html.parser import HTMLParser
from pathlib import Path
import subprocess
import urllib.error
import urllib.parse
import urllib.request

root = Path(__file__).resolve().parent.parent
command = [str(root / 'node_modules/.bin/wp-env'), 'run', 'cli', 'wp', 'eval-file', '/var/www/html/rnl-tests/admin-menu-fixtures.php']
base = 'http://localhost:8888'
checks = 0

class MenuLinks(HTMLParser):
    def __init__(self, html):
        super().__init__(); self.depth = 0; self.links = []
        self.feed(html)
    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'ul' and (self.depth or attrs.get('id') == 'adminmenu'): self.depth += 1
        if self.depth and tag == 'a': self.links.append(attrs.get('href', ''))
    def handle_endtag(self, tag):
        if tag == 'ul' and self.depth: self.depth -= 1

def check(condition, message):
    global checks
    checks += 1
    assert condition, message

def request(opener, path, data=None):
    data = urllib.parse.urlencode(data).encode() if data is not None else None
    try:
        with opener.open(base + path, data=data, timeout=20) as response:
            return response.status, response.read().decode()
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode()

setup = subprocess.run(command, cwd=root, text=True, capture_output=True, check=True)
accounts = next(json.loads(line) for line in setup.stdout.splitlines() if line.startswith('{'))
snapshot_run = subprocess.run(command + ['appearance-snapshot'], cwd=root, text=True, capture_output=True, check=True)
snapshot = next(json.loads(line) for line in snapshot_run.stdout.splitlines() if line.startswith('{'))
try:
    for role, account in accounts.items():
        opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        request(opener, '/wp-login.php')
        status, html = request(opener, '/wp-login.php', {'log': account['login'], 'pwd': account['password'], 'testcookie': '1', 'redirect_to': base + '/wp-admin/admin.php?page=reginor-lite'})
        check(status == 200 and 'Opprett kursperiode' in html, f'{role}: login/workspace failed')
        links = [link for link in MenuLinks(html).links if 'rnl-analytics' in link]
        appearance_links = [link for link in MenuLinks(html).links if 'rnl-appearance' in link]
        if role == 'administrator':
            # Every RegiNor admin route must load the shared shell and its administration stylesheet.
            for page in ['reginor-lite', 'rnl-resources', 'rnl-site', 'rnl-capacity', 'rnl-letsreg', 'rnl-analytics', 'rnl-appearance', 'reginor-lite-status']:
                page_status, page_html = request(opener, '/wp-admin/admin.php?page=' + page)
                check(page_status == 200 and 'wrap rnl-ui rnl-admin' in page_html and 'assets/admin.css' in page_html and 'assets/interface.css' in page_html, 'Missing admin shell/styles: ' + page)
            _, dashboard = request(opener, '/wp-admin/index.php')
            check('reginor-lite/assets/admin.css' not in dashboard, 'Plugin admin style leaks into WordPress dashboard')
            check('admin.php?page=rnl-letsreg' in MenuLinks(html).links, 'LetsReg connection menu URL missing')
            auth_status, auth_html = request(opener, '/wp-admin/admin.php?page=rnl-letsreg')
            check(auth_status == 200 and 'Kontroller tilkoblingen' in auth_html and 'RNL_LETSREG_PASSWORD' in auth_html, 'Connection settings page missing')
            check(request(opener, '/wp-admin/admin.php?page=rnl-letsreg', {'_wpnonce': 'invalid'})[0] == 403, 'Connection check accepts invalid nonce')
            check('name="event_id"' in auth_html and 'Hent arrangement og priskategorier' in auth_html, 'Event inspection form missing')
            auth_nonce = re.search(r'name="_wpnonce" value="([^"]+)"', auth_html).group(1)
            for invalid_id in ['', '0', '-1', 'SalsaØvet1_4_26', 'https://example.invalid', '2147483648']:
                invalid_status, invalid_html = request(opener, '/wp-admin/admin.php?page=rnl-letsreg', {'_wpnonce': auth_nonce, 'rnl_action': 'event', 'event_id': invalid_id})
                check(invalid_status == 400 and 'numeriske ID' in invalid_html, 'Event form accepts invalid API ID')
            check(request(opener, '/wp-admin/admin.php?page=rnl-letsreg', {'_wpnonce': 'invalid', 'rnl_action': 'event', 'event_id': '12345'})[0] == 403, 'Event inspection accepts invalid nonce')
            check('name="query"' in auth_html and 'Finn arrangement hos LetsReg' in auth_html, 'Event search form missing')
            check(request(opener, '/wp-admin/admin.php?page=rnl-letsreg', {'_wpnonce': 'invalid', 'rnl_action': 'search', 'query': 'Syntetisk'})[0] == 403, 'Search accepts invalid nonce')
            for invalid_search in [{'query': 'x' * 121}, {'query': 'Rueda', 'offset': '1'}, {'query': 'Rueda', 'offset': '-20'}]:
                invalid_status, invalid_html = request(opener, '/wp-admin/admin.php?page=rnl-letsreg', {'_wpnonce': auth_nonce, 'rnl_action': 'search', **invalid_search})
                check(invalid_status == 400, 'Invalid search reaches network instead of being rejected')
            if 'API-tilgang mangler' in auth_html:
                auth_nonce = re.search(r'name="_wpnonce" value="([^"]+)"', auth_html).group(1)
                missing_status, missing_html = request(opener, '/wp-admin/admin.php?page=rnl-letsreg', {'_wpnonce': auth_nonce})
                check(missing_status == 400 and 'API-tilgangen må settes opp' in missing_html, 'Valid connection form does not explain missing server credentials')
            site_status, site_html = request(opener, '/wp-admin/admin.php?page=rnl-site')
            check(site_status == 200 and 'Arrangementskalender' in site_html, 'Calendar settings panel missing')
            check(request(opener, '/wp-admin/admin.php?page=rnl-site', {'_wpnonce': 'invalid', 'rnl_calendar_settings': '1', 'category_id': '0', 'image_id': '0'})[0] == 403, 'Calendar settings accept invalid nonce')
            if 'name="rnl_calendar_settings"' in site_html:
                check('calendar-settings.js' in site_html and 'media-views' in site_html, 'WordPress image picker assets missing')
                calendar_form = next(form for form in re.findall(r'<form\b.*?</form>', site_html, re.S) if 'name="rnl_calendar_settings"' in form)
                calendar_nonce = re.search(r'name="_wpnonce" value="([^"]+)"', calendar_form).group(1)
                payload = {'_wpnonce': calendar_nonce, 'rnl_calendar_settings': '1', 'category_id': '0', 'image_id': '0'}
                saved_status, calendar_saved = request(opener, '/wp-admin/admin.php?page=rnl-site', payload)
                check(saved_status == 200 and 'Kalendervalgene er lagret.' in calendar_saved, 'Calendar settings POST/redirect failed')
                payload['image_id'] = '999999999'
                check(request(opener, '/wp-admin/admin.php?page=rnl-site', payload)[0] == 400, 'Calendar settings accept missing image')
            else:
                check('Aktiver The Events Calendar' in site_html, 'Missing TEC has no helpful explanation')
            resource_status, resources = request(opener, '/wp-admin/admin.php?page=rnl-resources')
            check(resource_status == 200 and 'data-rnl-map-picker' in resources and 'assets/map.js' in resources, 'Map picker or script missing in real admin response')
            check(request(opener, '/wp-admin/admin-ajax.php', {'action': 'rnl_map_search', 'nonce': 'invalid', 'query': 'Syntetisk test'})[0] == 403, 'Map search accepts invalid nonce')
            check(appearance_links == ['admin.php?page=rnl-appearance'], 'Appearance menu URL is invalid')
            appearance_status, appearance_html = request(opener, '/wp-admin/' + appearance_links[0])
            check(appearance_status == 200 and 'Lagre farger og visning' in appearance_html and 'data-rnl-preview' in appearance_html, 'Appearance settings do not render')
            check(request(opener, '/wp-admin/admin.php?page=rnl-appearance', {'_wpnonce': 'invalid', 'operation': 'global'})[0] == 403, 'Appearance settings accept invalid nonce')
            check('Gi et kurs eget uttrykk' not in appearance_html and 'Fremhev dette kurset' not in appearance_html, 'Individual course settings remain in global appearance')
            nonce = re.search(r'name="_wpnonce" value="([^"]+)"', appearance_html).group(1)
            status, saved = request(opener, '/wp-admin/admin.php?page=rnl-appearance', {'_wpnonce': nonce, 'operation': 'global', 'appearance[background]': '#123456', 'appearance[background_alpha]': '45', 'appearance[background_source]': 'wp:primary', 'appearance[view]': 'week'})
            check(status == 200 and 'Utseendet er lagret.' in saved and 'value="45"' in saved, 'Appearance palette/alpha POST failed')
            status, rejected = request(opener, '/wp-admin/admin.php?page=rnl-appearance', {'_wpnonce': nonce, 'operation': 'global', 'appearance[background_alpha]': '101'})
            check(status == 400 and 'Alpha må være' in rejected and 'value="45"' in rejected, 'Invalid alpha overwrites saved settings or is not rejected')
            check(links == ['admin.php?page=rnl-analytics'], 'Statistics menu missing or has invalid URL: ' + repr(links))
            status, body = request(opener, '/wp-admin/' + links[0])
            check(status == 200 and 'Statistikk og kampanjer' in body and 'Lagre måleoppsett' in body, 'Admin cannot follow Statistics menu to rendered settings')
            check(request(opener, '/wp-admin/admin.php?page=rnl-analytics', {'_wpnonce': 'invalid', 'enabled': '1'})[0] == 403, 'Statistics settings accept invalid nonce')
            check('name="sales_history"' in body and 'Klikk og utvikling hos LetsReg' in body, 'Event history settings/report missing')
            check(request(opener, '/wp-admin/admin-post.php', {'action': 'rnl_sales_history_refresh', '_wpnonce': 'invalid', 'course': '12345'})[0] == 403, 'History refresh accepts invalid nonce')
            check(request(opener, '/wp-admin/admin-post.php', {'action': 'rnl_letsreg_review', '_wpnonce': 'invalid', 'id': '12345', 'operation': 'check'})[0] == 403, 'Editorial source check accepts invalid nonce')
        else:
            check('admin.php?page=rnl-letsreg' not in MenuLinks(html).links and request(opener, '/wp-admin/admin.php?page=rnl-letsreg')[0] == 403, 'Manager can access API credentials page')
            check(request(opener, '/wp-admin/admin.php?page=rnl-letsreg', {'rnl_action': 'event', 'event_id': '12345'})[0] == 403, 'Manager can inspect LetsReg events')
            check(request(opener, '/wp-admin/admin.php?page=rnl-site')[0] == 403, 'Manager can access shared calendar settings')
            check(request(opener, '/wp-admin/admin-ajax.php', {'action': 'rnl_map_search', 'query': 'Syntetisk test'})[0] == 403, 'Manager can access map search')
            check(not appearance_links and request(opener, '/wp-admin/admin.php?page=rnl-appearance')[0] == 403, 'Manager can access appearance settings')
            check(not links, 'Course manager sees administrator-only statistics menu')
            check(request(opener, '/wp-admin/admin.php?page=rnl-analytics')[0] == 403, 'Course manager can access statistics directly')
            check(request(opener, '/wp-admin/admin-post.php', {'action': 'rnl_sales_history_refresh', 'course': '12345'})[0] == 403, 'Course manager can refresh private sales history')
    check(request(urllib.request.build_opener(), '/wp-admin/admin-ajax.php', {'action': 'rnl_map_search', 'query': 'Syntetisk test'})[0] == 400, 'Anonymous map search is registered')
    status, body = request(urllib.request.build_opener(), '/wp-admin/admin.php?page=rnl-analytics')
    check('name="log"' in body and 'Lagre måleoppsett' not in body, 'Anonymous visitor can access statistics')
finally:
    subprocess.run(command + ['appearance-restore', base64.b64encode(json.dumps(snapshot).encode()).decode()], cwd=root, text=True, capture_output=True, check=True)
    cleanup = base64.b64encode(json.dumps(accounts).encode()).decode()
    subprocess.run(command + ['cleanup', cleanup], cwd=root, text=True, capture_output=True, check=True)
print(f'{checks} admin menu HTTP checks passed; temporary users removed.')
