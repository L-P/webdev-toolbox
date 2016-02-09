# webdev-toolbox
A place where I put those once tiny shell scripts that grew out of control.  
Licensed under the MIT license.

## Commands

### adminer
Launch [Adminer](https://github.com/vrana/adminer) in a Firefox tab.  
If Adminer is not yet installed, the latest version will be downloaded. Don't
get MITM'd.

Use the `--force-update` flag to upgrade Adminer to its latest version.

### docker-shell
Log into a docker container matched by a fuzzy name.

This command gets more useful when aliased eg. `alias ds='webdev-toolbox
docker-shell'` so you can just type `ds app` and be logged inside a container.

#### Example
```shell
user@host $ webdev-toolbox docker-shell app
/usr/bin/docker exec -it portal_appserver_1 /bin/login -p -f user TERM=xterm
Last login: Fri Jul 31 10:38:57 UTC 2015 on UNKNOWN
Welcome to Ubuntu 14.04.2 LTS (GNU/Linux 3.19.0-22-generic x86_64)

 * Documentation:  https://help.ubuntu.com/
user@container $
```

#### Configuration
`docker-shell` reads its configuration from `~/.config/webdev-toolbox/docker-shell.json`.  
You can specifiy options to a set of containers using PCRE that will be matched
against the full container name (including namespace).

##### Available options
  * string `user`: user to login as. Will default to the current user.
  * bool `noLogin`: set to true if you don't want to use `/bin/login` but
    `/bin/sh` directly.

##### Example
```json
{
    "database": {
        "user": "postgres"
    },
    "(memorycache|mailer)": {
        "noLogin": true
    },
    "webserver": {
        "user": "root"
    }
}
```

### httpping
Ping an URL via HTTP/HTTPS and report the detailed timings.  
Time are in milliseconds.

  * time: timestamp
  * size: bytes received
  * timers:
    * connecting: time spent to establish the TCP(+TLS) connection
    * sending: time spent to send the HTTP request
    * waiting: time we waited before we received the first byte from the server
    * receiving: time spent receiving the whole response

Pings are done synchronously.
