<?php
namespace sergiosgc\output;

class Negotiated {
    private $mediaTypePriorities;
    private $primaryTemplatePath;
    private static $componentTemplatePaths = [];
    private static $currentOutputStack = [];
    public static $singleton = null;
    public function __construct( $path, $mediaTypePriorities = null ) {
        if (static::$singleton) throw new Exception('\sergiosgc\output\Negotiated is a singleton class. Cannot instantiate twice.');
        static::$singleton = $this;
        if (is_null($mediaTypePriorities)) $mediaTypePriorities = [ 'application/json', 'text/html; charset=UTF-8' ];
        $this->mediaTypePriorities = $mediaTypePriorities;
        if (is_null($path)) {
            $path = 'templates';
        } 
        if ($path[0] != '/') {
            $path = $_SERVER['DOCUMENT_ROOT'] . '/' . $path;
        }
        $this->primaryTemplatePath = realpath($path);
        if (empty($this->primaryTemplatePath)) throw new Exception_MissingTemplatePath(sprintf('Template path does not exist: %s', $path));
    }
    public static function registerComponentTemplatePath($path) {
        if (!is_dir($path)) throw new Exception_MissingTemplatePath(sprintf('Template path does not exist: %s', $path)); 
        static::$componentTemplatePaths[] = $path;
        static::$componentTemplatePaths = array_values(array_keys(array_flip(static::$componentTemplatePaths))); // Remove duplicates
    }
    protected function _pathsBetween($shorter, $longer, $acc = []) {
        if (!is_dir($shorter)) return [];
        if (strlen($longer) < strlen($shorter)) return $acc;
        if (is_dir($longer)) {
            $longer = realpath($longer);
            $acc[] = $longer;
            if (is_file(sprintf('%s/stop_updir_search.setting', $longer))) return $acc;
            if (is_file(sprintf('%s/stop_updir_search.setting', $shorter))) return $acc;
        }
        if ($shorter == realpath($longer)) return $acc;
        $recurseLonger = preg_replace('_(.*)/[^/]*_', '\1', $longer);
        if (strlen($recurseLonger) >= strlen($longer)) throw new Exception('Infinite recursion assertion fail');
        return $this->_pathsBetween($shorter, $recurseLonger, $acc);
    }
    protected function _findTemplate($uri) {
        $mediaType = strtr(explode(';', $this->getNegotiatedMediaType(), 2)[0], ['/' => '-']);
        $candidateDirectories = $this->_pathsBetween(sprintf('%s/%s', $this->primaryTemplatePath, $mediaType), sprintf('%s/%s/%s', $this->primaryTemplatePath, $mediaType, $uri));
        foreach (static::$componentTemplatePaths as $path) {
            $candidateDirectories = array_merge(
                $candidateDirectories, 
                $this->_pathsBetween(sprintf('%s/%s', $path, $mediaType), sprintf('%s/%s/%s', $path, $mediaType, $uri)));
        }
        $candidateFiles = [ 
            preg_match('_.*/(?<filename>[^/]*\.php)$_', explode('?', $uri, 2)[0], $matches) ? 
                $matches['filename'] : 
                (isset($_SERVER['ROUTER_PATHBOUND_SCRIPT_FILENAME']) ? 
                    basename($_SERVER['ROUTER_PATHBOUND_SCRIPT_FILENAME']) :
                    'index.php'
                ),
            'all.php'
        ];
        foreach ($candidateDirectories as $dir) foreach($candidateFiles as $file) {
            if (is_file(sprintf('%s/%s', $dir, $file))) return realpath(sprintf('%s/%s', $dir, $file));
        }
        return false;
    }
    public function getNegotiatedMediaType() {
        $accept = null;
        if (isset($_REQUEST['x-accept'])) {
            $accept = $_REQUEST['x-accept'];
        } elseif (isset($_SERVER['HTTP_ACCEPT'])) {
            $accept = $_SERVER['HTTP_ACCEPT'];
        }
        if (is_null($accept)) {
            $mediaType = null;
        } else {
            $negotiator = new \Negotiation\Negotiator();
            $mediaType = $negotiator->getBest($accept, $this->mediaTypePriorities);
            if (!is_null($mediaType)) $mediaType = $mediaType->getValue();
        }
        if (is_null($mediaType)) $mediaType = $this->mediaTypePriorities[0];
        return $mediaType;
    }
    protected function _include($file, $tvars) {
        include($file);
    }
    public function template($uri = null, $tvars = null, $ob_var = null) {
        if (is_null($tvars)) $tvars = $GLOBALS['tvars'];
        if (!is_null($ob_var)) $tvars[$ob_var] = ob_get_clean();
        if (is_null($uri)) $uri = isset($_SERVER['ROUTER_PATHBOUND_REQUEST_URI']) ? $_SERVER['ROUTER_PATHBOUND_REQUEST_URI'] :  $_SERVER['REQUEST_URI']; 
        $templateFile = $this->_findTemplate($uri);
        if (!$templateFile) throw new Exception_MissingTemplate(sprintf('No template found for uri %s', $uri));
        $this->_include($templateFile, $tvars);
    }
    public function stemplate($uri = null, $tvars = null, $ob_var = null) {
        if (!is_null($ob_var)) $tvars[$ob_var] = ob_get_clean();
        ob_start();
        $this->template($uri, $tvars);
        return ob_get_clean();
    }
}