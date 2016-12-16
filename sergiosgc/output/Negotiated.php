<?php
namespace sergiosgc\output;

class Negotiated {
    private $mediaTypePriorities;
    private $templatePath;
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
    public function output() {
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
            throw new Exception_MissingTemplatePath(sprintf('Template path for media type "%s" does not exist: %s', $mediaType, sprintf('%s/%s', $this->templatePath, $pathFormattedMediaType)));
        }
        $templatePathForMediaType = realpath(sprintf('%s/%s', $this->templatePath, $pathFormattedMediaType));
        if (isset($_SERVER['ROUTER_PATHBOUND_SCRIPT_FILENAME'])) {
            $templateFile = basename($_SERVER['ROUTER_PATHBOUND_SCRIPT_FILENAME']);
        } else {
            $templateFile = 'index.php';
        }
        $parts = array_values(array_filter(explode('/', explode('?', $_SERVER['REQUEST_URI'], 2)[0]), function($p) { return $p != ""; }));
        do {
            $candidate = sprintf("%s/%s/%s", $templatePathForMediaType, implode('/', $parts), $templateFile);
            array_pop($parts);
        } while (count($parts) && !is_file($candidate));
        if (!is_file($candidate)) throw new Exception_MissingTemplate(sprintf('No template found under %s for %s', $templatePathForMediaType, explode('?', $_SERVER['REQUEST_URI'], 2)[0]));
        $candidate = realpath($candidate);
        $this->include($candidate);
    }
    protected function include($file) {
        include($file);
    }
}
