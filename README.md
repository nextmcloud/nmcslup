# NextMagentaCloud provisioning functions

## App configuration

|App parameter                | Purpose                                                                                                     |
|-----------------------------|-------------------------------------------------------------------------------------------------------------|
|nmcslup slupid               | slup application id to use for registration                                                                 |
|nmcslup slupsecret           | slup secret to use for registration                                                                         |
|nmcslup slupgwendpoint       | Webservice endpoint URL for SLUP gateway                                                                    |
|nmcslup slupcontrolintv      | (optional override, int) interval to OPEN after boot other than 300 (sec)                                   |
|nmcslup local_cert           | local path to a client certificate for use with SLUP requester authentication                               |
|nmcslup local_key            | local path to a private key file in case of separate files for certificate (local_cert) and private key     |

Remember that NextCloud app configuration values only support string, so 300sec is '300'.

The configuration could be done with the following commandline calls (only, no UI):
```
sudo -u www-data php /var/www/nextcloud/occ config:app:set nmcslup slupid --value <secret value delivered by slup partner>
sudo -u www-data php /var/www/nextcloud/occ config:app:set nmcslup slupsecret --value <secret value delivered by slup partner>
sudo -u www-data php /var/www/nextcloud/occ config:app:set nmcslup slupgwendpoint --value <value delivered by slup partner>
sudo -u www-data php /var/www/nextcloud/occ config:app:set nmcslup slupcontrolintv --value 123
sudo -u www-data php /var/www/nextcloud/occ config:app:set nmcslup local_cert --value <set value to local path where file is stored>
sudo -u www-data php /var/www/nextcloud/occ config:app:set nmcslup local_key --value <set value to local path where file is stored>
```

## Getting API status by API

There is a public endpoint to ask for the state of the SLUP circuit breaker
(open, halfopen or closed):
```
curl -X GET -H "Accept: application/json" https://mynext.cloud/apps/nmcslup/api/1.0/status

{"circuit_state":"closed","has_token":"true","num_msg_since_keepalive":42}
```


## Running app unit tests
Before first run, prepare your app for unittesting by:
```
cd custom_apps/myapp

# run once if needed
composer install --no-dev -o
```

Execute unittests with the mandatory standard run before push:
```
phpunit --stderr --bootstrap tests/bootstrap.php tests/unit/MyTest.php
```

For quicker development (only!), you could skip large/long running tests
```
phpunit --stderr --bootstrap tests/bootstrap.php --exclude-group=large tests/unit/MyTest.php
```

Or you could limit your call to some methods only:
```
phpunit --stderr --bootstrap tests/bootstrap.php --filter='testMethod1|testMethod2' tests/unit/MyTest.php
```


## Tip for logfile filtering:
```
tail -f /var/log/nextcloud/nextcloud.json.log |jq 'select(.app=="nmcprovisioning")'
```

Only user_oidc and nmcslup, without deprecation warnings:
```
tail -f /var/log/nextcloud/nextcloud.json.log |jq 'select(.app=="nmcslup") | select(.message|contains("deprecated")|not)'
```

## calling composer
For building only
```
composer install --no-dev -o
```
If you want to check in `vendor/` dir, make sure to call composer in this mode and check in only the files
generated in non-dev mode!

For dev:
```
composer install --dev -o
```
Don´t check in the additionally pulled dev content!
