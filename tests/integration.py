"""HTTP checks against tests/compose.yaml; never targets production servers."""
import http.cookiejar
import re
import urllib.request
import urllib.error
import urllib.parse

base = 'http://127.0.0.1:18085'
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
checks = 0

def request(path, expected=200, data=None):
    global checks
    try:
        response = client.open(base + path, data=data, timeout=10)
    except urllib.error.HTTPError as e:
        response = e
    body = response.read().decode()
    assert response.status == expected, (path, response.status)
    for marker in ('A PHP Error was encountered', 'Fatal error:', 'Deprecated:', 'Warning:', 'Notice:', 'Uncaught '):
        assert marker not in body, (path, marker)
    checks += 1
    return body, response.url

def perform(path):
    body, _ = request('/monitor/' if path.startswith('/monitor/') else '/')
    token = re.search(r'name="monitor_csrf" value="([a-f0-9]+)"', body).group(1)
    return request(path, data=urllib.parse.urlencode({'monitor_csrf': token}).encode())

for prefix in ('', '/monitor'):
    body, _ = request(prefix + '/')
    assert 'fixture' in body and 'worker' in body and 'RUNNING' in body
    assert 'Restart all 1 processes on fixture?' in body
    assert 'Stop all 1 processes on fixture?' in body
    assert re.search(re.escape(prefix + '/css/custom.css') + r'\?v=[a-f0-9]{12}', body), 'CSS fingerprint missing'
    assert prefix + '/control/stop/fixture/worker' in body
    assert 'XML data received' not in body
    request(prefix + '/css/custom.css')
    for operation, state in [('stop', 'STOPPED'), ('start', 'RUNNING'), ('restart', 'RUNNING')]:
        body, url = perform(prefix + '/control/' + operation + '/fixture/worker')
        assert state in body and url.endswith(prefix + '/'), (operation, state, url)
        assert 'completed.' in body, re.findall(r'<div role="status"[^>]*>(.*?)</div>', body)
    for operation, state in [('stopall', 'STOPPED'), ('startall', 'RUNNING'), ('restartall', 'RUNNING')]:
        body, url = perform(prefix + '/control/' + operation + '/fixture')
        assert state in body and url.endswith(prefix + '/'), (operation, state, url)
        assert 'completed.' in body, re.findall(r'<div role="status"[^>]*>(.*?)</div>', body)
    body, _ = request(prefix + '/?mute=1')
    assert 'Sound muted' in body
    body, _ = request(prefix + '/?mute=-1')
    assert 'Sound muted' not in body
    perform(prefix + '/control/clear/fixture/worker')
    request(prefix + '/control/start/fixture/worker', 405)
    request(prefix + '/control/start/fixture/worker', 403, b'')
    request(prefix + '/control/start/fixture/worker', 403, b'monitor_csrf=wrong')
body, _ = perform('/control/start/fixture/failing')
assert 'failed:' in body and 'SPAWN_ERROR' in body
body, _ = request('/')
assert 'SPAWN_ERROR' not in body, 'result messages must be one-shot'
body, _ = perform('/control/restart/fixture/failing-stop')
assert 'Restart (stop phase)' in body and 'failed:' in body
body, _ = perform('/control/clear/fixture/failing-clear')
assert 'failed:' in body and 'service account permissions' in body and 'FAILED: failing-clear' in body
print(f'{checks} HTTP checks passed at root and /monitor/.')
