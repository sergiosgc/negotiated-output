<?php
namespace sergiosgc\output;

class Negotiated {
    private $mediaTypePriorities;
    private $templatePath;
    private static $currentOutputStack = [];
    public function __construct( $path, $mediaTypePriorities = null ) {
        if (is_null($mediaTypePriorities)) $mediaTypePriorities = [ 'application/json', 'text/html; charset=UTF-8' ];
        $this->mediaTypePriorities = $mediaTypePriorities;
        if (is_null($path)) {
            $path = 'templates';
        } 
        if ($path[0] != '/') {
            $path = $_SERVER['DOCUMENT_ROOT'] . '/' . $path;
        }
        $this->templatePath = realpath($path);
        if (empty($this->templatePath)) throw new Exception_MissingTemplatePath(sprintf('Template path does not exist: %s', $path));
    }
    public function getNegotiatedMediaType() {
        if (!isset($_SERVER['HTTP_ACCEPT'])) {
            $mediaType = null;
        } else {
            $negotiator = new \Negotiation\Negotiator();
            $mediaType = $negotiator->getBest($_SERVER['HTTP_ACCEPT'], $this->mediaTypePriorities);
            if (!is_null($mediaType)) $mediaType = $mediaType->getValue();
        }
        if (is_null($mediaType)) $mediaType = $this->mediaTypePriorities[0];
        return $mediaType;
    }
    public function output($uri = null, $travelUpDirectoryTree = true) {
        if (is_null($uri)) $uri = isset($_SERVER['ROUTER_PATHBOUND_REQUEST_URI']) ? $_SERVER['ROUTER_PATHBOUND_REQUEST_URI'] :  $_SERVER['REQUEST_URI']; 
        self::$currentOutputStack[] = $this;
        $mediaType = $this->getNegotiatedMediaType();
        $pathFormattedMediaType = strtr(explode(';', $mediaType, 2)[0], array('/' => '-'));
        if (!is_dir(sprintf('%s/%s', $this->templatePath, $pathFormattedMediaType))) {
            array_pop(self::$currentOutputStack);
            throw new Exception_MissingTemplatePath(sprintf('Template path for media type "%s" does not exist: %s', $mediaType, sprintf('%s/%s', $this->templatePath, $pathFormattedMediaType)));
        }
        // $templatePathForMediaType looks like <template_root_dir>/<media_type>. e.g. /srv/www/example.com/templates/application-json
        $templatePathForMediaType = realpath(sprintf('%s/%s', $this->templatePath, $pathFormattedMediaType));

        /* The template filename is the PHP filename in the request, and defaults to index.php */
        $templateFile = preg_match('_.*/(?<filename>[^/]*\.php)$_', explode('?', $uri, 2)[0], $matches) ? 
            $matches['filename'] : 
            (isset($_SERVER['ROUTER_PATHBOUND_SCRIPT_FILENAME']) ? 
                basename($_SERVER['ROUTER_PATHBOUND_SCRIPT_FILENAME']) :
                'index.php'
            );

        // Split the URI local part into path components so we can travel up the directory tree looking for the template
        $parts = array_values(array_filter(explode('/', explode('?', $uri, 2)[0]), function($p) { return $p != ""; }));
        do {
            $candidate = realpath(sprintf("%s/%s/%s", $templatePathForMediaType, implode('/', $parts), $templateFile));
            if ($candidate && is_file($candidate)) return $this->_include($candidate);
            $candidate = realpath(sprintf("%s/%s/%s", $templatePathForMediaType, implode('/', $parts), 'all.php'));
            if ($candidate && is_file($candidate)) return $this->_include($candidate);

            if (!$travelUpDirectoryTree || !count($parts)) throw new Exception_MissingTemplate(sprintf('No template found under %s for %s', $templatePathForMediaType, explode('?', is_null($uri) ? $_SERVER['REQUEST_URI'] : $uri, 2)[0]));
            array_pop($parts);
        } while (true);
    }
    protected function _include($file) {
        global $tvars;
        include($file);
    }
    public static function currentOutput() {
        if (0 == count(self::$currentOutputStack)) return null;
        return self::$currentOutputStack[count(self::$currentOutputStack) - 1];
    }
    public static function outputFromCurrent($uri = null, $ob_var = null) {
        if (!is_null($ob_var))  $GLOBALS['tvars'][$ob_var] = ob_get_clean();
        self::currentOutput()->output($uri);
    }
}
