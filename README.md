# composer-negotiated-output
A content negotiation based template router

## Objective

A typical PHP application processes requests in two phases:

1. Execute the requested operation
2. Produce the output

On RESTful applications, the output is dependent on who is on the other side. If it is a human, then the output is HTML, if not, it's some programatically consumable data format (usually JSON or XML). 

This package uses the Accepts HTTP header to decide the output type, and calls the templating script required for the correct output type. It is agnostic to template engine solutions. It just routes the output logic to the appropriate script in the filesystem.

## Usage scenario

```
<?php
require_once('vendor/autoload.php');
// [Placeholder for application code handling the request]
(new \sergiosgc\output\Negotiated('templates', array('application/json; charset=UTF-8', 'text/html; charset=UTF-8')))->output();
```

The last script line will include a file from either `<document_root>/templates/text-html` or `<document_root>/templates/application-json`. The decision is made using the HTTP Accept header.

The actual included file is mapped from the request URL. The URL is mapped directly onto the filesystem. The relative URL `/foo/bar`, for the text/html response, will include `<document_root>/templates/text-html/foo/bar/index.php`. If an exact match is not found, the filesystem is traversed upward until a template file is found: `<document_root>/templates/text-html/foo/index.php`, then `<document_root>/templates/text-html/index.php`.

If using sergiosgc/rest-router, then the template file to be included is not `index.php` but `<http_verb>.php` (e.g. `get.php`, `post.php`). 

## Installation 

Install via Composer. composer.json:
```
# composer.json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/sergiosgc/composer-negotiated-output.git"
        }
    ],
    "require": {
        "sergiosgc/negotiated-output": "dev-master"
    }
}
```

## Usage with sergiosgc/rest-router

If using sergiosgc/rest-router, the catch-all script should look like:
```
<?php
require_once('vendor/autoload.php');
// [Placeholder for application initialization code]
(new \sergiosgc\router\Rest('rest'))->route();
(new \sergiosgc\output\Negotiated('templates', array('application/json; charset=UTF-8', 'text/html; charset=UTF-8')))->output();
```
