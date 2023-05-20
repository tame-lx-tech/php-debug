<?php

// Levels
// 0 - reserved
// 1 - ERRR - error
// 2 - WARN - warning
// 3 - INFO - information
// 4 - VERB - verbose


class debug {


    //
    // Properties
    //
    private $settings = array(
        'enabled'=>true,
        'redact'=>array(),
        'log'=> array(
            'logLevel'=>1,
            'logToFile'=>false,
            'logFile'=>'/var/log/php-debug.log',
            'logToSyslog'=>false,
            'syslogName'=>'GNU STP',
            'syslogFacility'=>LOG_LOCAL0
        ),
        'debug'=> array(
            'debugLevel'=>4,
            'storeMessages'=>false
        ),
        'dump'=> array(
            'dumpObeysEnabled'=>true,
            'dumpObeysStoreMessages'=>true,
            'dumpObeysRedact'=>true,
        ),
        'php' => array(
            'handlePhpErrors'=>true,
            'level'=>4,
        ),
    );
    private $environment = array(
        'sapi'=>null,
        'ajax'=>false
    );
    private $lookup = array (
        'levelStrToInt' => array(
            'ERRR' => 1,
            'WARN' => 2,
            'INFO' => 3,
            'VERB' => 4,
        ),
        'levelIntToStr' => array(
            1 => 'ERRR',
            2 => 'WARN',
            3 => 'INFO',
            4 => 'VERB'
        ),
        'phpToLevelStr' => array(
            E_ERROR => 'ERRR',
            E_WARNING => 'WARN',
            E_PARSE => 'ERRR',
            E_NOTICE => 'WARN',
            E_CORE_ERROR => 'ERRR',
            E_CORE_WARNING => 'WARN',
            E_COMPILE_ERROR => 'ERRR',
            E_COMPILE_WARNING => 'WARN',
            E_USER_ERROR => 'ERRR',
            E_USER_WARNING => 'WARN',
            E_USER_NOTICE => 'WARN',
            E_STRICT => 'WARN',
            E_RECOVERABLE_ERROR => 'ERRR',
            E_DEPRECATED => 'WARN',
            E_USER_DEPRECATED => 'WARN',
            E_ALL => 'VERB'
        ),
    );
    private $styles = array(
        'js'=>array(
            'highlight' =>array(
                'VERB'=>array('background'=>'yellow'),
                'INFO'=>array('background'=>'yellow', 'color'=>'black'),
                'WARN'=>array('background'=>'yellow'),
                'ERRR'=>array('background'=>'yellow')
            ),
            'VERB'=>array(),
            'INFO'=>array('color'=>'lightGreen'),
            'WARN'=>array(),
            'ERRR'=>array(),
        ),
        'cli' => array(
            'default' => array('foreground'=>'39', 'background'=>'49'),
            'highlight' => array('foreground'=>'30', 'background'=>'103'),
            'VERB'=> array('foreground'=>'33', 'background'=>'49'),
            'INFO'=> array('foreground'=>'32', 'background'=>'49'),
            'WARN'=> array('foreground'=>'31', 'background'=>'49'),
            'ERRR'=> array('foreground'=>'37', 'background'=>'41'),
        )
    );
    private $timeStart = null;
    private $buffer = array();



    //
    //
    // Constructor
    //
    //
    function __construct($newSettings = array()) {
        // get in settings
        // start timer (get system time in microseconds)
        // check if using cli or web server
        // - if web server, find out if user request or ajax request using header HTTP_X_REQUESTED_WITH
        // -- if ajax request, set $storeMessages to true, unless provided in $settings


        //
        // define constants for level
        //
        define('ERRR', 'ERRR');
        define('WARN', 'WARN');
        define('INFO', 'INFO');
        define('VERB', 'VERB');

        //
        // check logLevel and debugLevel if string, convert to int
        //
        if(array_key_exists('logLevel', $newSettings)) {
            if(is_string($newSettings['logLevel'])) {
                $tmpLookup = $this->lookup['levelStrToInt'][strtoupper($newSettings['logLevel'])];
                $newSettings['logLevel'] = $tmpLookup;
                unset($tmpLookup);
            }
            if(is_int($newSettings['logLevel'])) {
                if($newSettings['logLevel'] < 1) {
                    $newSettings['logLevel'] = 1;
                } elseif ($newSettings['logLevel'] > 4) {
                    $newSettings['logLevel'] = 4;
                }
            }
        }

        if(array_key_exists('debugLevel', $newSettings)) {
            if(is_string($newSettings['debugLevel'])) {
                $tmpLookup = $this->lookup['levelStrToInt'][strtoupper($newSettings['debugLevel'])];
                $newSettings['debugLevel'] = $tmpLookup;
                unset($tmpLookup);
            }
            if(is_int($newSettings['debugLevel'])) {
                if($newSettings['debugLevel'] < 1) {
                    $newSettings['debugLevel'] = 1;
                } elseif ($newSettings['debugLevel'] > 4) {
                    $newSettings['debugLevel'] = 4;
                }
            }
        }


        //
        // store the settings
        //

        // root level
        $toStore = array('enabled', 'redact');
        foreach($toStore as $settingToSet) {
            if (isset($newSettings[$settingToSet])) {
                $this->settings[$settingToSet] = $newSettings[$settingToSet];
            }
        }
        unset($toStore);

        // log
        $toStore = array('logLevel', 'logToFile', 'logToSyslog', 'syslogName');
        foreach($toStore as $settingToSet) {
            if (isset($newSettings[$settingToSet])) {
                $this->settings['log'][$settingToSet] = $newSettings[$settingToSet];
            }
        }
        unset($toStore);

        // debug
        $toStore = array('debugLevel', 'storeMessages');
        foreach($toStore as $settingToSet) {
            if (isset($newSettings[$settingToSet])) {
                $this->settings['debug'][$settingToSet] = $newSettings[$settingToSet];
            }
        }
        unset($toStore);

        // dump
        $toStore = array('dumpObeysEnabled', 'dumpObeysStoreMessages', 'dumpObeysRedact');
        foreach($toStore as $settingToSet) {
            if (isset($newSettings[$settingToSet])) {
                $this->settings['dump'][$settingToSet] = $newSettings[$settingToSet];
            }
        }
        unset($toStore);
        
        // php
        $toStore = array('handlePhpErrors', 'level');
        foreach($toStore as $settingToSet) {
            if (isset($newSettings[$settingToSet])) {
                $this->settings['php'][$settingToSet] = $newSettings[$settingToSet];
            }
        }
        unset($toStore);


        //
        // if not enabled stop here
        //
        if($this->settings['enabled'] == false) {
            return;
        }

        //
        // start timer
        //
        $this->timeStart = hrtime(true);

        //
        // check if cli (cli/cgi) or web server
        //
        if(strpos(php_sapi_name(), 'cli') !== false) {
            $this->environment['sapi'] = 'cli';
        } elseif (strpos(php_sapi_name(), 'cgi') !== false) {
            $this->environment['sapi'] = 'cli';
        } else {
            $this->environment['sapi'] = 'web';
        }

        //
        // if web request, is it ajax?
        //    using header HTTP_X_REQUESTED_WITH
        //
        if($this->environment['sapi'] == 'web') {
            if(isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    $this->environment['ajax'] = true;
                    if(!array_key_exists('storeMessages', $newSettings)) {
                        $this->settings['storeMessages'] = true;
                    }
                }
            }
        }

        //
        // if php errors are enabled, set error handler
        //
        if($this->settings['php']['handlePhpErrors'] == true) {
            // enable php error reporting
            error_reporting(E_ALL);
            ini_set('display_errors', 0);
            set_error_handler(array($this, 'phpErrHandler'));
        }

        //
        // if logToFile is true, make sure logFile is set
        //
        if($this->settings['log']['logToFile'] == true) {
            if(!isset($this->settings['log']['logFile']) OR $this->settings['log']['logFile'] == NULL) {
                $this->settings['log']['logToFile'] = false;
            }
        }

        //
        //if logging to file, open file for writing
        //
        if($this->settings['log']['logToFile'] == true) {
            $this->logFileHandle = fopen($this->settings['log']['logFile'], 'a');
            if($this->logFileHandle == false) {
                $this->logToFile = false;
            }
        }

        //
        // if logging to syslog, open syslog
        //
        if($this->settings['log']['logToSyslog'] == true) {
            openlog($this->settings['log']['syslogName'], LOG_PID, $this->settings['log']['syslogFacility']);
        }
    }


    //
    //
    // destructor function
    //
    //
    function __destruct() {

        //
        // if logging to file, close file
        //
        if($this->settings['log']['logToFile'] == true) {
            fclose($this->logFileHandle);
        }

        //
        // if debugging to cli, send message to reset colour
        //
        if($this->environment['sapi'] == 'cli') {
            foreach($this->styles['cli']['default'] as $key=>$value) {
                $output = "\033[".$value."m";
            }
            $output .= "\n";
            fwrite(STDOUT, $output);
        }

        //
        // if logging to syslog, close syslog
        //
        if($this->settings['log']['logToSyslog'] == true) {
            closelog();
        }

        //
        // all done
        //
        return;
    }


    //
    //
    // change logLevel
    //
    //
    function changeLogLevel($level) {

        //
        // if string, convert to int
        //
        if(is_string($level)) {
            $level = $this->lookup['levelStrToInt'][strtoupper($level)];
        }

        //
        // check within range
        //
        if(is_int($level)) {
            if($level < 1) {
                $level = 1;
            } elseif ($level > 4) {
                $level = 4;
            }
        }

        //
        // fail if not int or string
        //
        if(!is_string($level) && !is_int($level)) {
            return;
        }

        //
        // set logLevel
        //
        $this->settings['log']['logLevel'] = $level;
        return;
    }


    //
    //
    // function to change debug level
    //
    //
    function changeDebugLevel($level) {

        //
        // if string, convert to int
        //
        if(is_string($level)) {
            $level = $this->lookup['levelStrToInt'][strtoupper($level)];
        }

        //
        // check within range
        //
        if(is_int($level)) {
            if($level < 1) {
                $level = 1;
            } elseif ($level > 4) {
                $level = 4;
            }
        }

        //
        // fail if not int or string
        //
        if(!is_string($level) && !is_int($level)) {
            return;
        }

        //
        // set debugLevel
        //
        $this->settings['debug']['debugLevel'] = $level;
        return;
    }


    //
    //
    // a pretty var_dump
    //
    //
    function dump ($data, $message=NULL, $additional=array()) {
        $currentMessage = array(
            'level'=> 4,
            'message' => $message,
            'data'=> $data,
            'highlight' => false,
            'additional' => $additional
        );

        //
        // enabled?
        //
        if($this->settings['dump']['dumpObeysEnabled'] == true) {
            if($this->settings['enabled'] == false) {
                return;
            }    
        }
        
        //
        // store?
        //
        if($this->settings['dump']['dumpObeysStoreMessages'] == true) {
            if($this->settings['debug']['storeMessages'] == true) {
                $this->buffer[] = $currentMessage;
                return;
            }
        }

        //
        // redact?
        //
        if($this->settings['dump']['dumpObeysRedact'] == true) {
            if(count($this->settings['redact']) != 0) {
                foreach($this->settings['redact'] as $redactThis) {
                    if(isset($currentMessage['data'][$redactThis])) {
                        $currentMessage['data'][$redactThis] = 'REDACTED';
                    }
                }
            }
        }

        //
        // display message
        //
        if($message != NULL) {
            echo $message;
            PHP_EOL;
        }

        //
        // output
        //
        if($this->environment['sapi'] == 'cli') {
            var_dump($data);
        } else {
            echo '<pre>';
            var_dump($data);
            echo '</pre>';
        }
        PHP_EOL;
        return;
    }
    
        
    //    
    //
    // make all messages be stored in the buffer
    //
    //
    function storeMessages ($enabled) {
        if($enabled === false) {
            $this->storeMessages = false;
            return;
        }
        if($enabled === true) {
            $this->storeMessages = true;
            return;
        }
        return;
    }


    //
    //
    // return all messages in the buffer
    //
    //
    function getBuffer() {
        return($this->buffer);
    }


    //
    //
    // PHP Error Handler
    //
    //
    private function phpErrHandler($phpErrNo, $phpErrStr, $phpErrFile=NULL, $phpErrLine=NUll, $phpErrContext=NULL) {
        // convert level to int
        $levelStr = $this->lookup['phpToLevelStr'][$phpErrNo];
        $levelInt = $this->lookup['levelStrToInt'][$levelStr];

        // if over what we're logging, return
        if($levelInt > $this->settings['php']['level']) {
            return;
        }

        // make message
        $data = array(
            'file'=>$phpErrFile,
            'line'=>$phpErrLine,
            'context'=>$phpErrContext
        );
        $additional = array(
            'php' => true,
        );

        // output
        $this->msg($levelStr, $phpErrStr, $data, false, $additional);
    }

    //
    //
    // wrapper functions for msg()
    //
    //
    function verb ($message, $data=NULL, $highlight=false, $additional=array()) {
        $this->msg('verb', $message, $data, $highlight, $additional);
    }
    function info ($message, $data=NULL, $highlight=false, $additional=array()) {
        $this->msg('info', $message, $data, $highlight, $additional);
    }
    function warn ($message, $data=NULL, $highlight=false, $additional=array()) {
        $this->msg('warn', $message, $data, $highlight, $additional);
    }
    function err ($message, $data=NULL, $highlight=false, $additional=array()) {
        $this->msg('err', $message, $data, $highlight, $additional);
    }
    

    //
    //
    // take a message and optional data, and make it be output
    //
    //
    function msg ($levelStr, $message, $data=NULL, $highlight=false, $additional=array()) {
        // $levelStr: string of level
        // $message: string
        // $data: any type of useful debug data to be shown in the output
        // $highlight: boolean, if true, highlight the message in the output
        // $additional: array

        //
        // quit if disabled
        //
        if($this->settings['enabled'] == false) {
            return;
        }

        //
        // level string to int
        //
        $levelStr = strtoupper($levelStr);
        $levelInt = $this->lookup['levelStrToInt'][$levelStr];
        

        //
        // make one nice big array of the message
        //
        $currentMessage = array(
            'level'=> $levelInt,
            'message' => $message,
            'data'=> $data,
            'highlight' => $highlight,
            'additional' => $additional
        );

        //
        // time after $timeStart
        //
        $currentMessage['time'] = hrtime(true) - $this->timeStart;

        //
        // redact
        //
        if(isset($currentMessage['data'])) {
            if(count($this->settings['redact']) != 0) {
                foreach($this->settings['redact'] as $redactThis) {
                    if(isset($currentMessage['data'][$redactThis])) {
                        $currentMessage['data'][$redactThis] = 'REDACTED';
                    }
                }
            }
        }
        unset($redactThis);

        
        //
        // if $storeMessages is true, add to buffer
        //
        if($this->settings['debug']['storeMessages'] == true) {
            $this->buffer[] = $currentMessage;
        }

        //
        // test if new message should be output to log outputs (syslog and file) based on $levelInt and $logLevel
        //
        if($levelInt <= $this->settings['log']['logLevel']) {
            if ($this->settings['log']['logToFile'] == true) $this->output_file($currentMessage);
            if ($this->settings['log']['logToSyslog'] == true) $this->output_syslog($currentMessage);
        }

        //
        // if storeMessages is true, return (we dont want debug output)
        //
        if($this->settings['debug']['storeMessages'] == true) {
            return;
        }


        //
        // test if new message should be output to debug outputs (cli and js), based on $levelInt and $debugLevel
        //
        if ($levelInt <= $this->settings['debug']['debugLevel']) {
            if($this->environment['sapi'] == 'cli') {
                $this->output_cli($currentMessage);
            } else {
                $this->output_js($currentMessage);
            }
        }

        //
        // all done
        //
        return;
    }



    //
    //
    // output to JS console
    //
    //
    protected function output_js ($wholeMessage) {
        // work out based on level if we're using console.debug, console.info, console.warn, console.error
        // if highlight is true, output with yellow background
        // if we have data, output that as json object
        // output to js console

        // level to string
        $levelStr = $this->lookup['levelIntToStr'][$wholeMessage['level']];

        //
        // get console method
        //
        switch ($wholeMessage['level']) {
            case 4:
                $consoleMethod = 'debug';
                break;
            case 3:
                $consoleMethod = 'info';
                break;
            case 2:
                $consoleMethod = 'warn';
                break;
            case 1:
                $consoleMethod = 'error';
                break;
            default:
                $consoleMethod = 'log';
                break;
        }

        //
        // console styling 
        //
        $consoleStyle = array();
        // highlight
        if($wholeMessage['highlight'] == true) {
            if(count($this->styles['js']['highlight']) != 0) {
                $consoleStyle = $this->styles['js']['highlight'][$levelStr];
            }
        }
        // other styles
        if($wholeMessage['highlight'] == false) {
            if(count($this->styles['js'][$levelStr]) != 0) {
                $consoleStyle = $this->styles['js'][$levelStr];
            }
        }




        //
        // make a object to output to console
        //
        $consoleObject = array();
        if(isset($wholeMessage['data'])) {
            $consoleObject['data'] = $wholeMessage['data'];
        }

        // start
        $output = '<script>console.'.$consoleMethod.'(';


        // strat first part of output
        $output .= '"';

        // style
        if(count($consoleStyle) != 0) {
            $output .= '%c';
        }

        // time
        $output .= '['.$wholeMessage['time'].'] ';

        //php
        if(array_key_exists('php', $wholeMessage['additional'])) {
            $output .= '[PHP] ';
        }

        // message
        $output .= $wholeMessage['message'];

        // data
        if(isset($wholeMessage['data'])) {
            $output .= '%o';
        }

        // end first part of output
        $output .= '"';


        // second part of output

        // style
        if(count($consoleStyle) != 0) {
            $output .= ', "';
            foreach($consoleStyle as $key=>$val) {
                $output .= $key.':'.$val.';';
            }
            unset($key);
            unset($val);
            $output .= '"';
        }

        // data
        if(isset($wholeMessage['data'])) {
            $output .= ', ';
            $output .= json_encode($consoleObject);
        }

        // end
        $output .= ');</script>';


        // output to js console
        echo $output;
        PHP_EOL;

    }


    //
    //
    // output to CLI
    //
    //
    protected function output_cli ($wholeMessage) {
        // convert current message level to str using lookup
        // make some styles
        // do output to stdout
        // if error, also output to stderr
        
        //convert level to string
        $levelString = $this->lookup['levelIntToStr'][$wholeMessage['level']];

        // start output string
        $output = '';

        // style - highlight
        if($wholeMessage['highlight'] == true) {
            foreach($this->styles['cli']['highlight'] as $key=> $val) {
                $output .= "\33[".$val."m";
            }
        }
        // style - non highlight
        if($wholeMessage['highlight'] == false) {
            foreach($this->styles['cli'][$levelString] as $key=> $val) {
                $output .= "\33[".$val."m";
            }
        }

        // time
        $output .= '['.$wholeMessage['time'].'] ';

        // level string
        $output .= '['.$levelString.'] ';

        // php
        if(array_key_exists('php', $wholeMessage['additional'])) {
            $output .= '[PHP] ';
        }

        // message
        $output .= $wholeMessage['message'];

        // reset colour to default
        foreach($this->styles['cli']['default'] as $key=> $val) {
            $output .= "\33[".$val."m";
        }
        // new line just for good luck
        $output .= "\n";


        // write output to stdout
        fwrite(STDOUT, $output);

        // if error, also output to stderr
        if($levelString == 'error') {
            fwrite(STDERR, $output);
        }

    }

    //
    //
    // output to syslog
    //
    //
    protected function output_syslog ($wholeMessage) {
        // set correct syslog level
        if($wholeMessage['level'] == 1) {
            $level = LOG_ERR;
        } elseif ($wholeMessage['level'] == 2) {
            $level = LOG_WARNING;
        } elseif ($wholeMessage['level'] == 3) {
            $level = LOG_INFO;
        } elseif ($wholeMessage['level'] == 4) {
            $level = LOG_DEBUG;
        }

        // if php error, prepend 'php' to message
        if(array_key_exists('php', $wholeMessage['additional'])) {
            $wholeMessage['message'] = '[PHP] '.$wholeMessage['message'];
        }
        
        // output
        syslog($level, $wholeMessage['message']);
    }

    //
    //
    // output to file
    //
    //
    protected function output_file ($wholeMessage) {
        //
        // get information
        $levelString = $this->lookup['levelIntToStr'][$wholeMessage['level']];
        $message = $wholeMessage['message'];
        $humanTime = date('Y-m-d H:i:s');

        //
        // make output string
        //
        $output = "[".$humanTime."] ";
        $output .= $levelString.": ";
        if(array_key_exists('php', $wholeMessage['additional'])) {
            $output .= '[PHP] ';
        }
        $output .= $message;
        $output .= PHP_EOL;

        //
        // put into file
        //
        fwrite($this->logFileHandle, $output);
        
    }

}
?>