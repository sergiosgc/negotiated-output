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
    public function output($uri = null) {
        if (is_null($uri)) {
            if (isset($_SERVER['ROUTER_PATHBOUND_REQUEST_URI'])) {
                $uri = $_SERVER['ROUTER_PATHBOUND_REQUEST_URI'];
            } else {
                $uri = $_SERVER['REQUEST_URI'];
            }
        }
        self::$currentOutputStack[] = $this;
        if (!isset($_SERVER['HTTP_ACCEPT'])) {
            $mediaType = null;
        } else {
            $negotiator = new \Negotiation\Negotiator();
            $mediaType = $negotiator->getBest($_SERVER['HTTP_ACCEPT'], $this->mediaTypePriorities);
            if (!is_null($mediaType)) $mediaType = $mediaType->getValue();
        }
        if (is_null($mediaType)) $mediaType = $this->mediaTypePriorities[0];
        $pathFormattedMediaType = strtr(explode(';', $mediaType, 2)[0], array('/' => '-'));
        if (!is_dir(sprintf('%s/%s', $this->templatePath, $pathFormattedMediaType))) {
            array_pop(self::$currentOutputStack);
            throw new Exception_MissingTemplatePath(sprintf('Template path for media type "%s" does not exist: %s', $mediaType, sprintf('%s/%s', $this->templatePath, $pathFormattedMediaType)));
        }
        $templatePathForMediaType = realpath(sprintf('%s/%s', $this->templatePath, $pathFormattedMediaType));
        if (preg_match('_.*/(?<filename>[^/]*\.php)$_', explode('?', $uri, 2)[0], $matches)) {
            $templateFile = $matches['filename'];
        } else {
            if (isset($_SERVER['ROUTER_PATHBOUND_SCRIPT_FILENAME'])) {
                $templateFile = basename($_SERVER['ROUTER_PATHBOUND_SCRIPT_FILENAME']);
            } else {
                $templateFile = 'index.php';
            }
        }
        $parts = array_values(array_filter(explode('/', explode('?', $uri, 2)[0]), function($p) { return $p != ""; }));
        if (is_file(sprintf("%s/%s/%s", $templatePathForMediaType, implode('/', $parts), $templateFile))) {
            $candidate = sprintf("%s/%s/%s", $templatePathForMediaType, implode('/', $parts), $templateFile);
        } else {
            $candidate = sprintf("%s/%s", $templatePathForMediaType, implode('/', $parts));
        }
        if (!is_file($candidate)) throw new Exception_MissingTemplate(sprintf('No template found under %s for %s', $templatePathForMediaType, explode('?', is_null($uri) ? $_SERVER['REQUEST_URI'] : $uri, 2)[0]));
        $candidate = realpath($candidate);
        $this->include($candidate);
        array_pop(self::$currentOutputStack);
    }
    protected function include($file) {
        global $tvars;
        include($file);
    }
    public static function currentOutput() {
        if (0 == count(self::$currentOutputStack)) return null;
        return self::$currentOutputStack[count(self::$currentOutputStack) - 1];
    }
    public static function outputFromCurrent($uri = null) {
        self::currentOutput()->output($uri);
    }
}
