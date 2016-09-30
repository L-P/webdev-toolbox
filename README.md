# webdev-toolbox
A place where I put those once tiny shell scripts that grew out of control.  
Licensed under the MIT license.

## Commands
Table of contents:

1. [adminer](#adminer)
2. [docker-shell](#docker-shell)
3. [httpping](#httping)
4. [run](#run)

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
Times are in milliseconds.

  * time: timestamp
  * size: bytes received
  * timers:
    * connecting: time spent to establish the TCP(+TLS) connection
    * sending: time spent to send the HTTP request
    * waiting: time we waited before we received the first byte from the server
    * receiving: time spent receiving the whole response

Pings are done synchronously.

### run
Run a set of jobs described in a `jobs.json` file. Originally made to execute
and benchmark encodings, this can be used for anything simple where make and
ninja don't cut it because of how poorly they handle multiple targets/ouputs.

#### Example
```
$ webdev-toolbox runner
Running job copy_zero: 
dd if=/dev/zero bs=1M count=1024 of='zero.bin';
1024+0 records in
1024+0 records out
1073741824 bytes (1.1 GB, 1.0 GiB) copied, 6.72629 s, 160 MB/s
Running job multiline: 
echo This is another command on a second line, notice how \
 the line before ends with a ';' to mark the end of the command \
 and how the space is needed at the beginning here. | fmt; \
echo Also, this commands has no outputs and no inputs \
 so it will always run.
This is another command on a second line, notice how the line before ends
with a ; to mark the end of the command and how the space is needed at
the beginning here.
Also, this commands has no outputs and no inputs so it will always run.
Running job copy_urandom: 
dd if=/dev/zero bs=1M count=1024 of='urandom.bin';
1024+0 records in
1024+0 records out
1073741824 bytes (1.1 GB, 1.0 GiB) copied, 4.30827 s, 249 MB/s
$ run --stats
+--------------+----------+-----------+-------+-----------+-------------+
| name         | time     | time_diff | size  | size_diff | return_code |
+--------------+----------+-----------+-------+-----------+-------------+
| copy_zero    | 00:00:06 |           | 1 GiB |           | 0           |
| copy_urandom | 00:00:04 | 0.36×     | 1 GiB | 0×        | 0           |
| multiline    | 00:00:00 | 1×        | 0 B   | 1×        | 0           |
+--------------+----------+-----------+-------+-----------+-------------+

$ run --dry-run
Running job copy_zero: job outputs already generated, skipping
Running job multiline: 
echo This is another command on a second line, notice how \
 the line before ends with a ';' to mark the end of the command \
 and how the space is needed at the beginning here. | fmt; \
echo Also, this commands has no outputs and no inputs \
 so it will always run.
Running job copy_urandom: job outputs already generated, skipping
$ run --clean
Removed file(s) from job copy_zero: zero.bin
No files to remove for job: multiline
Removed file(s) from job copy_urandom: urandom.bin
```

Generated from configuration:
```json
{
    "statsReference": "copy_zero",
    "jobs": [
        {
            "name": "copy_zero",
            "input": "/dev/zero",
            "outputs": [
                "zero.bin"
            ],
            "command": [
                "dd if=/dev/zero bs={bs} count={count} of={outputs};"
            ],
            "variables": {
                "sleep": 0,
                "count": 1024,
                "bs": "1M"
            }
        },
        {
            "name": "copy_urandom",
            "overrides": "copy_zero",
            "input": "/dev/urandom",
            "outputs": [
                "urandom.bin"
            ]
        },
        {
            "name": "multiline",
            "command": [
                "echo This is another command on a second line, notice how",
                " the line before ends with a ';' to mark the end of the command",
                " and how the space is needed at the beginning here. | fmt;",
                "echo Also, this commands has no outputs and no inputs",
                " so it will always run."
            ]
        }
    ]
}
```

#### Configuration
The runner reads `jobs.json` in the current working directory by default and
writes in the adjacent `stats.json`. These paths can be changed using
`--config-file=` and `--stats-file=`.
