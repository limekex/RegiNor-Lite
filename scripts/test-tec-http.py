"""Real local TEC calendar, REST, redirects and iCalendar; own synthetic fixtures only."""
import json
import html as html_module
import subprocess
import urllib.request
import urllib.error
from pathlib import Path
from datetime import date, timedelta
root = Path(__file__).resolve().parent.parent
command = [str(root / 'node_modules/.bin/wp-env'), 'run', 'cli', 'wp', 'eval-file', '/var/www/html/rnl-tests/tec-http-fixtures.php']
checks = 0

def check(ok, message):
    global checks
    checks += 1
    assert ok, message

def request(url):
    try:
        with urllib.request.urlopen(url, timeout=30) as response:
            return response.status, response.read().decode(), response.url, response.headers
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode(), error.url, error.headers

try:
    result = subprocess.run(command, cwd=root, text=True, capture_output=True, check=True)
    data = next(json.loads(line) for line in result.stdout.splitlines() if line.startswith('{'))
    base = 'http://localhost:8888'
    rest_url = base + '/wp-json/tribe/events/v1/events/' + str(data['event'])
    status, body, _, headers = request(rest_url)
    event = json.loads(body)
    check(status == 200 and event['all_day'] is True, 'TEC REST does not expose an all-day period')
    check(event['start_date'].startswith(data['start']) and event['end_date'].startswith(data['end']), 'TEC does not span the whole period')
    check(event['url'] == data['url'], 'TEC REST link does not point to all period courses')
    check('no-store' in headers.get('Cache-Control', ''), 'TEC REST can cache beyond visibility boundary')
    status, calendar, _, headers = request(data['calendar'])
    check(status == 200 and data['title'] in calendar, 'TEC month calendar lacks the course period')
    check(data['url'] in html_module.unescape(calendar), 'Calendar entry does not link to period')
    check('no-store' in headers.get('Cache-Control', ''), 'Calendar page can cache past visibility boundary')
    raw_url = base + '/?post_type=tribe_events&p=' + str(data['event'])
    status, body, url, _ = request(raw_url)
    check(status == 200 and url == data['url'] and 'TEC HTTP kurs' in body, 'Direct TEC detail does not lead to period courses')
    ics_url = base + '/?post_type=tribe_events&ical=1&event_ids[]=' + str(data['event'])
    status, ics, _, _ = request(ics_url)
    check(status == 200 and 'BEGIN:VCALENDAR' in ics and data['title'] in ics, 'TEC iCalendar export lacks period')
    check('DTSTART;VALUE=DATE:' + data['start'].replace('-', '') in ics, 'ICS start date differs: ' + repr([line for line in ics.splitlines() if line.startswith(('DTSTART', 'DTEND'))]))
    exclusive_end = (date.fromisoformat(data['end']) + timedelta(days=1)).strftime('%Y%m%d')
    check('DTEND;VALUE=DATE:' + exclusive_end in ics, 'ICS does not include final period day')
    subprocess.run(command + ['expire'], cwd=root, text=True, capture_output=True, check=True)
    check(request(rest_url)[0] == 404, 'Expired period exposed in direct TEC REST')
    check(data['title'] not in request(data['calendar'])[1], 'Expired period exposed in calendar')
    check(data['title'] not in request(ics_url)[1], 'Expired period exposed in ICS export')
    check(request(raw_url)[0] == 404, 'Expired direct event is still accessible')
finally:
    subprocess.run(command + ['cleanup'], cwd=root, text=True, capture_output=True, check=True)
print(f'{checks} TEC HTTP checks passed; fixtures and course page restored.')
