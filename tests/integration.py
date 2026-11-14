"""HTTP checks against tests/compose.yaml; never targets production servers."""
import http.cookiejar
import re
import urllib.request
import urllib.error

base = 'http://127.0.0.1:18085'
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
checks = 0

def request(path, expected=200):
    global checks
    try:
        response = client.open(base + path, timeout=10)
    except urllib.error.HTTPError as e:
        response = e
    body = response.read().decode()
    assert response.status == expected, (path, response.status)
    for marker in ('A PHP Error was encountered', 'Fatal error:', 'Deprecated:', 'Warning:', 'Notice:', 'Uncaught '):
        assert marker not in body, (path, marker)
    checks += 1
    return body, response.url

for prefix in ('', '/monitor'):
    body, _ = request(prefix + '/')
    assert 'fixture' in body and 'worker' in body and 'RUNNING' in body
    assert prefix + '/css/custom.css' in body
    assert prefix + '/control/stop/fixture/worker' in body
    assert 'XML data received' not in body
    request(prefix + '/css/custom.css')
    for action, state in [('stop', 'STOPPED'), ('start', 'RUNNING'), ('restart', 'RUNNING')]:
        body, url = request(prefix + '/control/' + action + '/fixture/worker')
        assert state in body and url.endswith(prefix + '/'), (action, state, url)
    for action, state in [('stopall', 'STOPPED'), ('startall', 'RUNNING'), ('restartall', 'RUNNING')]:
        body, url = request(prefix + '/control/' + action + '/fixture')
        assert state in body and url.endswith(prefix + '/'), (action, state, url)
    body, _ = request(prefix + '/?mute=1')
    assert 'Sound muted' in body
    body, _ = request(prefix + '/?mute=-1')
    assert 'Sound muted' not in body
    request(prefix + '/control/clear/fixture/worker')
    request(prefix + '/control/start/unknown/worker', 404)
print(f'{checks} HTTP checks passed at root and /monitor/.')
