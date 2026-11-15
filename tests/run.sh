#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
if [ "${1:-}" = "--all" ]; then
    result=0
    for version in 7.4 8.0 8.1 8.2 8.3 8.4 8.5; do
        if ! sh tests/run.sh "$version"; then result=1; fi
    done
    exit "$result"
fi
PHP_VERSION=${1:-${PHP_VERSION:-8.5}}
case "$PHP_VERSION" in
    7.4|8.0|8.1|8.2|8.3|8.4|8.5) ;;
    *) echo "Supported test versions: 7.4, 8.0, 8.1, 8.2, 8.3, 8.4, 8.5" >&2; exit 2 ;;
esac
export PHP_VERSION
printf '\nTesting PHP %s\n' "$PHP_VERSION"
compose() { docker compose -p monitor-tests -f tests/compose.yaml "$@"; }
trap 'compose down >/dev/null' EXIT
compose up -d --force-recreate
python3 - <<'PY'
import time,urllib.request
for attempt in range(40):
    try:
        urllib.request.urlopen('http://127.0.0.1:18085/',timeout=2).close()
        break
    except OSError:
        time.sleep(.5)
else:
    raise SystemExit('PHP test server did not become ready')
PY
compose exec -T web php -r 'echo "Runtime: ", PHP_VERSION, PHP_EOL;'
compose exec -T web php tests/regression.php
compose exec -T web php tests/config_reference.php
compose exec -T web php tests/diagnostics.php
python3 tests/integration.py
python3 - <<'PY'
import subprocess
cmd=['docker','compose','-p','monitor-tests','-f','tests/compose.yaml']
r=subprocess.run(cmd+['exec','-T','web','sh','-c','find application system public_html tests -name "*.php" -exec php -l {} \\;'],capture_output=True,text=True)
lines=(r.stdout+r.stderr).splitlines()
errors=[line for line in lines if line.strip() and not line.startswith('No syntax errors detected')]
if r.returncode or errors:
    raise SystemExit('\n'.join(errors) or 'Syntax scan failed')
print(f'{len(lines)} PHP files passed syntax/deprecation scan.')
r=subprocess.run(cmd+['logs','--no-color','web'],capture_output=True,text=True,check=True)
errors=[line for line in r.stdout.splitlines() if any(s in line for s in ('PHP Warning:', 'PHP Notice:', 'PHP Deprecated:', 'PHP Fatal error:', 'Uncaught '))]
if errors: raise SystemExit('\n'.join(errors))
print('No PHP runtime warnings in container logs.')
PY
