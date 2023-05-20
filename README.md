# php-debug

This is the PHP debugger. It's not as good as the other ones out there, but it suited my needs.

# What it does
This module can do logging and debugging stuff.

* Log outputs can go to syslog and/or file
* debug outputs can go to cli or JS console ( with auto detect environment)

Also, the `dump` function provides a prettier version of `var_dump`, with options for redaction and enable/disable

# Debugging Levels
| Int | Short Name | long name |
|-|-|-|
|0||Reserved|
|1|ERRR|Error|
|2|WARN|Warning|
|3|INFO|Informational|
|4|VERB|Verbose

# Notes
If using a log file output, you should add the logrotate config file to your system

# Usage

## Include
```php
include '/path/to/debugger/php-debug.php'
$debugSettings = array();
$debug = new debug($debugSettings);
```

## Settings
You can pass an array to set a lot of things


| Key | | Type | Default | Description |
|-|-|-|-|-|
| `enabled` | | boolean | true | 
| `redact` | | array | | keys that will be searched for in `data` array and values redacted |
| `log` || array | | |
|| `logLevel` | integer | 1 | messages of this level or lower will be sent to the log destinations (file and syslog) |
|| `logToFile` | boolean | false | send messages to file? |
|| `logFile` | string | /var/log/myapp.log | path to log output file |
|| `logToSyslog` | boolean | false | send log messages to syslog? |
|| `syslogName` | string | GNU STP | app name registered with syslog |
|| `syslogFacility` | integer | LOG_LOCAL0 | the source registered for syslog |
| `debug` || array |  |
|| `debugLevel` | integer | 4 | this level and lower will be sent to debug destinations (JS console or CLI) |
|| `storeMessages` | boolean | false | suppress debug output and store messages into a buffer. If not explicitly set, the auto-detect for ajax requests will set this to `true`
| `dump` || array |  |
|| `dumpObeysEnabled` | boolean | true | if false, dumps will always be outputted. otherwise, it will obey the `enabled` option |
|| `dumpObeysStoreMessages` | boolean | true | set whether dumps will be stored in the message buffer according to `['debug']['storeMessages']` |
|| `dumpObeysRedact` | boolean | true | if false, nothing will be redacted. Otherwise, redactions will be enabled |
| `php` || array |  |
|| `handlePhpErrors` | boolean | true | should PHP errors be handled using this |

## Example
```php
include '/path/to/debugger/php-debug.php'
$debugSettings = array(
    'enabled'=>true,
    'log'=>array(
        'logToFile'=>false,
        'logToSyslog'=>true,
        'logLevel'=>2,
    ),
);
$debug = new debug($debugSettings);

$data = array(
    'foo'=>'bar',
    'something'=>7,
    'somethingElse'=>true,
    'hereIsAnArray'=>array('biz', 'baz', 'box')
)
debug->msg('WARN', 'This is a warning message', $data);
```

# Functions

## changeLogLevel
### Description
change the level of messages sent to log destinations
### Arguments
| argument | type | required | description |
|-|-|-|-|
| level | INT or STR | yes | integer 1-4 or string of level short name |
### Examples
```php
debug->changeLogLevel('ERRR');
```

## changeDebugLevel

### Description
change the level of messages sent to debug destinations

### Arguments
| argument | type | required | description |
|-|-|-|-|
| level | INT or STR | yes | integer 1-4 or string of level short name |

```php
debug->changeDebugLevel('ERRR');
```

## dump
### Description
A pretty version of var_dump (slightly altered based on output destination). using the settings, can be set to obey `enabled`, `storeMessages`, `redact`.
### Arguments
| argument | type | required | description |
|-|-|-|-|
| data | any | yes | the data to be shown |
| message | string | no | a message or title to be shown above the dump |
### Examples
```php
debug->dump($data, 'This is the data here');
```

## storeMessages
### Description
Enable/disable message storage and suppress debug output
### Arguments
| argument | type | required | description |
|-|-|-|-|
| enabled | boolean | yes | `true` to enable message storage, `false` to disable |
### Examples
```php
debug->storeMessages(true);
```

## getBuffer
### Description
Get all stored messages out of the buffer
### Arguments
| argument | type | required | description |
|-|-|-|-|

### Examples
```php
debug->getBuffer();
```

## msg
### Description
Send a message (with optional data)
### Arguments
| argument | type | required | description |
|-|-|-|-|
| level | string | yes | the level of this message |
| message | string | yes | string of the message to be sent |
| data | any | no | extra data that might be useful |
| highlight | bool | no | should this message be highlighted in the debug outputs? |
| additional | array | no | currently unused |
### Examples
```php
debug->msg(INFO, "here", $extraData, true);
```