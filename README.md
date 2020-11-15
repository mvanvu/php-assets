# php-assets
Php Assets Manager package

```json
{
	"require": {
		"mvanvu/php-registry": "~1.0"
	}
}
```

Alternatively, from the command line:

```sh
composer require mvanvu/php-registry
```

## About
* Using while you're handling Php and want to dynamic adding the css/js assets contents
* Auto minifies the assets contents (Thanks to matthiasmullie/minify)
## Usage

``` php
use MaiVu\Php\Assets;

// Add assets
Assets::addFile('path/to/file.css');
Assets::addFile('path/to/file.js');


// OR
Assets::addFiles(
    [
        'path/to/file.css',
        'path/to/file.js',
    ]
);

// Inline assets
Assets::inlineCss('body {margin: 0; padding: 0}');
Assets::inlineJs('alert("It works")');

// Compress assets
Assets::compress();

// Render Header
echo Assets::output('css');
echo Assets::output('inlineCss');
echo Assets::output('js');
echo Assets::output('inlineJs');

```
