<?php
namespace sergiosgc\output;

class NegotiatedErrorHandler {
    public static $hideDetails = false;
    public static $fatalErrorMask = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR  | E_USER_ERROR | E_RECOVERABLE_ERROR;
    public static $browserLogErrorMask = E_ALL;
    public static $delegatedErrorMask = E_ALL;
    public static $delegatedErrorHandler = NULL;

    public static function setup() {
        set_exception_handler(array('\sergiosgc\output\NegotiatedErrorHandler', 'exception_handler'));
        self::$delegatedErrorHandler = set_error_handler(array('\sergiosgc\output\NegotiatedErrorHandler', 'error_handler'));
    }
    public static function error_handler($errno, $errstr, $errfile, $errline, $errcontext) {
        if (0 === error_reporting()) return; // Error was fired by lined masked with the @ operator
        if ($errno & self::$fatalErrorMask) {
            throw new Exception($errstr);
        }
        if ($errno & self::$browserLogErrorMask) {
            $chromeLoggerPayload = array(
                'version' => '0.2',
                'columns' => array('log', 'backtrace', 'type'),
                'rows' => array(
                    array(
                        array(
                            array(
                            E_ERROR => 'E_ERROR',
                            E_WARNING => 'E_WARNING',
                            E_PARSE => 'E_PARSE',
                            E_NOTICE => 'E_NOTICE',
                            E_CORE_ERROR => 'E_CORE_ERROR',
                            E_CORE_WARNING => 'E_CORE_WARNING',
                            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
                            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
                            E_USER_ERROR => 'E_USER_ERROR',
                            E_USER_WARNING => 'E_USER_WARNING',
                            E_USER_NOTICE => 'E_USER_NOTICE',
                            E_STRICT => 'E_STRICT',
                            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
                            E_DEPRECATED => 'E_DEPRECATED',
                            E_USER_DEPRECATED => 'E_USER_DEPRECATED')[$errno] . ': ' . $errstr, debug_backtrace()), 
                        sprintf('%s: %d', $errfile, $errline), 
                        array(
                            E_ERROR => 'error',
                            E_WARNING => 'warn',
                            E_PARSE => 'error',
                            E_NOTICE => 'info',
                            E_CORE_ERROR => 'error',
                            E_CORE_WARNING => 'warn',
                            E_COMPILE_ERROR => 'error',
                            E_COMPILE_WARNING => 'warn',
                            E_USER_ERROR => 'error',
                            E_USER_WARNING => 'warn',
                            E_USER_NOTICE => 'info',
                            E_STRICT => 'warn',
                            E_RECOVERABLE_ERROR => 'error',
                            E_DEPRECATED => 'warn',
                            E_USER_DEPRECATED => 'warn',
                        )[$errno]
                    )
                )
            );
            header(sprintf('X-ChromeLogger-Data: %s', base64_encode(json_encode($chromeLoggerPayload))));
        }
        if ($errno & self::$delegatedErrorMask && is_callable(self::$delegatedErrorHandler)) call_user_func(self::$delegatedErrorHandler, $errno, $errstr, $errfile, $errline, $errcontext);
    }
    public static function exception_handler($ex) {
        try {
            if (!isset($_SERVER['HTTP_ACCEPT'])) throw new Exception('Negotiated template paths do not support CLI');
            if (!isset($GLOBALS['tvars'])) $GLOBALS['tvars'] = array();
            $GLOBALS['tvars']['exception'] = $ex;
            (new \sergiosgc\output\Negotiated('templates', array('application/json; charset=UTF-8', 'text/html; charset=UTF-8')))->output('/exception/');
        } catch (Exception $e) {
            static::fallback_exception_handler($ex);
        }
        exit;
    }
    public static function fallback_exception_handler($ex) {
        $negotiator = new \Negotiation\Negotiator();
        $mediaType = $negotiator->getBest($_SERVER['HTTP_ACCEPT'], array('application/json; charset=UTF-8', 'text/html; charset=UTF-8'));
        if (!is_null($mediaType)) $mediaType = $mediaType->getValue();
        if (is_null($mediaType)) $mediaType = 'text/plain';
        if (preg_match('_text/html_', $mediaType)) {
            NegotiatedErrorHandler::fallback_html_exception_handler($ex); 
        } elseif (preg_match('_/json_', $mediaType)) {
            NegotiatedErrorHandler::fallback_json_exception_handler($ex); 
        } else NegotiatedErrorHandler::fallback_text_exception_handler($ex);
    }
    public static function fallback_json_exception_handler($ex) {
        $result = array(
            'message' => $ex->getMessage(),
            'code' => $ex->getCode(),
            'traceback' => array_merge(
                array(array(
                    'function' => '',
                    'file' => $ex->getFile(),
                    'line' => $ex->getLine()
                )), 
                $ex->getTrace()));
        if (self::$hideDetails) unset($result['traceback']);
        header('Content-type: application/json; charset=utf-8');
        print(json_encode(array('exception' => $result)));
	}
    public static function fallback_html_exception_handler($ex) {
?>
<html>
 <head>
  <title>Top level exception received</title>
 <head>
 <body>
  <pre>
<?php self::_fallback_text_exception_handler($ex); ?>
  </pre>
 </body>
</html>
<?php
    }
    public static function fallback_text_exception_handler($ex) {
        header('Content-type: text/plain; charset=utf-8');
        self::_fallback_text_exception_handler($ex);
    }
    public static function _fallback_text_exception_handler($ex) {
?>



 /$$   /$$           /$$                                 /$$ /$$                 /$$       /$$$$$$$$                                           /$$     /$$                    
| $$  | $$          | $$                                | $$| $$                | $$      | $$_____/                                          | $$    |__/                    
| $$  | $$ /$$$$$$$ | $$$$$$$   /$$$$$$  /$$$$$$$   /$$$$$$$| $$  /$$$$$$   /$$$$$$$      | $$       /$$   /$$  /$$$$$$$  /$$$$$$   /$$$$$$  /$$$$$$   /$$  /$$$$$$  /$$$$$$$ 
| $$  | $$| $$__  $$| $$__  $$ |____  $$| $$__  $$ /$$__  $$| $$ /$$__  $$ /$$__  $$      | $$$$$   |  $$ /$$/ /$$_____/ /$$__  $$ /$$__  $$|_  $$_/  | $$ /$$__  $$| $$__  $$
| $$  | $$| $$  \ $$| $$  \ $$  /$$$$$$$| $$  \ $$| $$  | $$| $$| $$$$$$$$| $$  | $$      | $$__/    \  $$$$/ | $$      | $$$$$$$$| $$  \ $$  | $$    | $$| $$  \ $$| $$  \ $$
| $$  | $$| $$  | $$| $$  | $$ /$$__  $$| $$  | $$| $$  | $$| $$| $$_____/| $$  | $$      | $$        >$$  $$ | $$      | $$_____/| $$  | $$  | $$ /$$| $$| $$  | $$| $$  | $$
|  $$$$$$/| $$  | $$| $$  | $$|  $$$$$$$| $$  | $$|  $$$$$$$| $$|  $$$$$$$|  $$$$$$$      | $$$$$$$$ /$$/\  $$|  $$$$$$$|  $$$$$$$| $$$$$$$/  |  $$$$/| $$|  $$$$$$/| $$  | $$
 \______/ |__/  |__/|__/  |__/ \_______/|__/  |__/ \_______/|__/ \_______/ \_______/      |________/|__/  \__/ \_______/ \_______/| $$____/    \___/  |__/ \______/ |__/  |__/
                                                                                                                                  | $$                                        
                                                                                                                                  | $$                                        
                                                                                                                                  |__/                                        
 Top level exception received 
==============================

 Message: <?= $ex->getMessage() ?>


 Code: <?= $ex->getCode() ?>
<?php if (self::$hideDetails) return; ?>

 Backtrace: 

<?php
        $widths = array(1, 1, 1);
        foreach ($ex->getTrace() as $idx => $tb) { 
            $widths[0] = max($widths[0], strlen((string)$idx));
            $widths[1] = max($widths[1], strlen(isset($tb['class']) ? sprintf('%s%s%s', $tb['class'], $tb['type'], $tb['function']) : $tb['function']));
            $widths[2] = max($widths[2], strlen($tb['file'] . ' +' . $tb['line']));
        }
        $lineFormatString = sprintf('  %%-%ds | %%-%ds | %%-%ds' . "\n", $widths[0], $widths[1], $widths[2]);
        printf($lineFormatString, ' ', 'Function', 'Location');
        echo(strtr(sprintf($lineFormatString, '', '', ''), array('|' => '+', ' ' => '-', "\n" => "-\n")));
        foreach ($ex->getTrace() as $idx => $tb) { 
            printf($lineFormatString, $idx, isset($tb['class']) ? sprintf('%s%s%s', $tb['class'], $tb['type'], $tb['function']) : $tb['function'], $tb['file'] . ' +' . $tb['line']);
        }


    }
}

