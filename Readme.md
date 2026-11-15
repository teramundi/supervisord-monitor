# Supervisord Multi Server Monitoring Tool

![Screenshot](https://raw.github.com/mlazarov/supervisord-monitor/master/supervisord-monitor.png)

## Features

* Monitor unlimited supervisord servers and processes
* Start/Stop/Restart process
* Read stderr log
* Start new Redmine ticket when stderr occurs
* Sound alert when stderr occurs
* Mute sound alerts (after some time auto resume)
* Monitor process uptime status

## Install

1.Clone supervisord-monitor to your vhost/webroot:
```
git clone https://github.com/mlazarov/supervisord-monitor.git
```

2.Copy application/config/supervisor.php.example to application/config/supervisor.php
```
cp supervisord-monitor/application/config/supervisor.php.example supervisord-monitor/application/config/supervisor.php
```

3.Enable/Uncomment inet_http_server (found in supervisord.conf) for all your supervisord servers.
```ini
[inet_http_server]
port=*:9001
username="yourusername"
password="yourpass"
```
Do not forget to restart supervisord service after changing supervisord.conf

4.Edit supervisord-monitor configuration file and add all your supervisord servers
```
vim application/config/supervisor.php
```

5.Configure your web server to point one of your vhosts to 'public_html' directory.
6.Open web browser and enter your vhost url.

### Nginx and PHP-FPM

The following example serves the monitor at the root of a dedicated host.
Replace the domain, paths and PHP-FPM address for your installation. Configure
HTTPS and restrict access: this application can start and stop processes.

```nginx
server {
    listen 80;
    server_name monitor.example.test;
    root /var/www/supervisord-monitor/public_html;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        try_files $uri =404;
        include /etc/nginx/fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass 127.0.0.1:9000;
    }
}
```

The missing `env.conf` mentioned in some external examples is not required by
this project. The PHP-FPM address may instead be a Unix socket. Protect the
entire vhost, including PHP and static files, using your access-control system.

### Installing under a URL prefix

By default the application detects its base URL from the web server's host and
`SCRIPT_NAME`. For example, to serve it at `/monitor/` instead of `/`, the
following Nginx locations can be placed in your existing server block:

```nginx
location = /monitor { return 301 /monitor/; }

location = /monitor/index.php {
    include /etc/nginx/fastcgi_params;
    fastcgi_param SCRIPT_FILENAME /var/www/supervisord-monitor/public_html/index.php;
    fastcgi_param SCRIPT_NAME /monitor/index.php;
    fastcgi_pass 127.0.0.1:9000;
}

location ~ ^/monitor/.*\.php(?:/|$) { return 404; }

location /monitor/ {
    alias /var/www/supervisord-monitor/public_html/;
    index index.php;
    try_files $uri $uri/ /monitor/index.php?$query_string;
}
```

Configure authentication for all `/monitor/` locations. If the application is
behind a TLS-terminating proxy, or your server does not provide the expected
`SCRIPT_NAME`, explicitly set `$config['base_url']` in
`application/config/config.php`, for example to `https://monitor.example.test/monitor/`.
Use a trusted, fixed host value in your web-server configuration.

The link from the dashboard to Supervisor's native interface does not include
credentials. If the native interface requires authentication, the browser must
authenticate separately.

### Regression checks

Run the lightweight checks with a PHP CLI interpreter:

```sh
php tests/regression.php
php tests/config_reference.php
```

These checks exercise dashboard error handling, absent stderr logs, credential
redaction in links, base URL generation, and configuration reference handling.
They do not replace an end-to-end test of the web server and Supervisor controls.


## Redmine integration
1.Open configuration file:
```
vim application/config/supervisor.php
```
2.Change this lines with your redmine url and auto assigne id:

```php
// Path to Redmine new issue url
$config['redmine_url'] = 'http://redmine.url/path_to_new_issue_url';
// Default Redmine assigne ID
$config['redmine_assigne_id'] = '69';
```

## Troubleshooting
```
Did not receive a '200 OK' response from remote server.
```
Having this messages in most cases means that Supervisord Monitoring tools does not have direct network access to the Supervisord RPC2 http interface. Check your firewall and network conectivity.

---

```
Did not receive a '200 OK' response from remote server. (HTTP/1.0 401 Unauthorized)
```
Having `401 Unauthorized` means that you have connection between Supervisord Monitoring tool and Supervisord but the username or password are wrong.

---

```
UNKNOWN_METHOD
```
Having this message means that your supervisord service doesn't have rpc interface enabled (only for v3+ of Supervisord). 
To enable the rpc interface add this lines to the configuration file:

*From the Supervisord Docs*

In the sample config file, there is a section which is named [rpcinterface:supervisor]. By default it looks like the following:

```
[rpcinterface:supervisor]
supervisor.rpcinterface_factory = supervisor.rpcinterface:make_main_rpcinterface
```

The [rpcinterface:supervisor] section must remain in the configuration for the standard setup of supervisor to work properly. 
If you don’t want supervisor to do anything it doesn’t already do out of the box, this is all you need to know about this type of section.

For more information go to the offitial Supervisord Configuration Docs:
http://supervisord.org/configuration.html#rpcinterface-x-section-settings

---

```
The requested URL /control/ ... was not found on this server.
```

If you are getting this error on every action (stop, start, restart etc) most probably your web server isn't respecting the .htaccess file found in `public_html` directory. 
To test this you can add `AllowOverride All` config to the httpd.conf (if you are using Apache) or to add the rules from the .htaccess file to the httpd.conf file.


## Thanks to ##
- [stvnwrgs](https://github.com/stvnwrgs) - added authentication functionality to supervisord monitor
- [rk295](https://github.com/rk295) - added handling of non authenticated servers
- [All Contributors](https://github.com/mlazarov/supervisord-monitor/contributors) 

## Who uses Supervisord Monitor? ##

[EasyWpress - wordpress hosting company](http://easywpress.com)


If you've used Supervisord Monitor Tool send me email to martin@lazarov.bg to add your project/company to this list.

## License

MIT License

Copyright (C) 2013 Martin Lazarov

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

## PHP compatibility and development environment

This fork supports PHP 7.4, 8.0, 8.1, 8.2, 8.3, 8.4 and 8.5 using the same
codebase and requires the XML and session extensions. Composer constrains PHP to
`>=7.4 <8.6`; newer minor versions need validation before extending this range.
The CodeIgniter bootstrap retains production mode by default; tests explicitly select development
mode so notices, warnings and deprecations are visible. The bundled legacy
framework includes optional components not used by this dashboard; the test suite
covers the monitor's routes and XML-RPC client rather than every optional driver.

With Docker/OrbStack and Python 3 available, run:

```sh
./tests/run.sh         # PHP 8.5 (default)
./tests/run.sh 7.4     # One specific supported version
./tests/run.sh --all   # All seven versions, sequentially
```

The suite starts the official PHP image for the selected version and a simulated
XML-RPC Supervisor on an isolated Docker network. Only the web fixture is published, on
`127.0.0.1:18085`. Production hosts and credentials are not used. It checks
individual/group controls, log clearing, mute cookies, unknown servers, static
assets and routes at both `/` and `/monitor/`. It also lints PHP files and fails on
reported runtime warnings/deprecations. Containers are removed after the run.

To keep the development dashboard open manually:

```sh
PHP_VERSION=8.5 docker compose -p monitor-tests -f tests/compose.yaml up -d
# Open http://127.0.0.1:18085/
PHP_VERSION=8.5 docker compose -p monitor-tests -f tests/compose.yaml down
```

The fixture router verifies application paths; deployment-specific Nginx and
PHP-FPM routing should still be tested before deploying a new release.

The GitHub Actions workflow runs the same suite independently for each supported
PHP version on pushes and pull requests. Local validation on 2026-11-14 passed
on all seven minor versions: 47 HTTP checks per version, transport failure
scenarios, view/configuration regressions and syntax checks for 159 PHP files,
with no reported PHP runtime warnings, notices or deprecations. This covers the
monitor; it does not certify unused optional CodeIgniter components.
