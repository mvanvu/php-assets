<?php

namespace MaiVu\Php;

use MatthiasMullie\Minify\CSS;
use MatthiasMullie\Minify\JS;

class Assets
{
	protected static $publicBasePath = null;
	protected static $importExtensions = [
		'gif' => 'data:image/gif',
		'png' => 'data:image/png',
		'svg' => 'data:image/svg+xml',
	];
	protected static $debug = false;
	protected static $assets = [
		'css'       => [],
		'js'        => [],
		'inlineCss' => [],
		'inlineJs'  => [],
	];

	protected static $outputs = [
		'js'        => [],
		'css'       => [],
		'inlineCss' => [],
		'inlineJs'  => [],
	];

	public static function setDebugMode(bool $mode)
	{
		static::$debug = $mode;
	}

	public static function setImportExtensions(array $importExtensions)
	{
		static::$importExtensions = $importExtensions;
	}

	public static function cleanPath(string $path): string
	{
		return rtrim(trim($path), '/\\');
	}

	public static function addFiles(array $baseFiles, $basePath = null)
	{
		$basePath = $basePath ?: static::getPublicBasePath();

		foreach ($baseFiles as $baseFile)
		{
			static::addFile($baseFile, $basePath);
		}
	}

	public static function getPublicBasePath(): string
	{
		if (null === static::$publicBasePath || !is_dir(static::$publicBasePath))
		{
			$dir = dirname($_SERVER['SCRIPT_FILENAME']);

			if (is_dir($dir . '/public'))
			{
				static::$publicBasePath = $dir . '/public';
			}
			elseif (is_dir($dir . '/assets'))
			{
				static::$publicBasePath = $dir . '/assets';
			}
			else
			{
				static::$publicBasePath = $dir;
			}
		}

		return static::$publicBasePath;
	}

	public static function setPublicBasePath($publicBasePath)
	{
		if (is_dir($publicBasePath))
		{
			static::$publicBasePath = rtrim($publicBasePath, '\\/');
		}
	}

	public static function addFile($baseFile, $basePath = null)
	{
		static $addedFiles = [];
		$key      = ($basePath ?: '') . ':' . $baseFile;
		$basePath = $basePath ?: static::getPublicBasePath();

		if (array_key_exists($key, $addedFiles))
		{
			return;
		}

		preg_match('/\.(css|js)(\?.*)?$/', $baseFile, $matches);
		$addedFiles[$key] = true;

		if (!$t = ($matches[1] ?? null))
		{
			return;
		}

		$file = null;

		if (is_file($baseFile))
		{
			$file = realpath($baseFile);
		}
		elseif (is_file($basePath . '/' . $baseFile))
		{
			$file = $basePath . '/' . $baseFile;
		}
		elseif (is_file($basePath . '/' . $t . '/' . $baseFile))
		{
			$file = $basePath . '/' . $t . '/' . $baseFile;
		}

		if ($file)
		{
			static::$assets[$t][] = $file;
		}
	}

	public static function inlineCss($css)
	{
		static::$assets['inlineCss'][] = $css;
	}

	public static function inlineJs($js)
	{
		static::$assets['inlineJs'][] = $js;
	}

	public static function compress($publicPath = null, $publicUri = null)
	{
		$scriptDirName = dirname($_SERVER['SCRIPT_FILENAME']);
		$publicPath    = $publicPath ?: static::getPublicBasePath();

		if (null === $publicUri)
		{
			if (strpos($publicPath, $scriptDirName) === 0)
			{
				$publicUri = substr($publicPath, strlen($scriptDirName)) . '/' . basename($scriptDirName);
			}
			else
			{
				$publicUri = '';
			}
		}

		foreach (static::$assets as $type => $files)
		{
			if (empty($files))
			{
				continue;
			}

			$callBack = 'build' . ucfirst($type);

			if (in_array($type, ['inlineCss', 'inlineJs']))
			{
				foreach ($files as $content)
				{
					static::$callBack($content);
				}

				continue;
			}

			$fileName = md5(implode(':', $files)) . '.' . $type;
			$filePath = $publicPath . '/compressed/' . $fileName;
			$fileUri  = $publicUri . '/compressed/' . $fileName . (static::$debug ? '?' . time() : '');
			$hasAsset = is_file($filePath);

			if ($hasAsset && !static::$debug)
			{
				static::$callBack($fileUri);
				continue;
			}

			$compressor = static::getCompressor($type);

			foreach ($files as $file)
			{
				$compressor->add($file);
			}

			if (!is_dir($publicPath . '/compressed/'))
			{
				@mkdir($publicPath . '/compressed/', 0755, true);
			}

			if ($compressor->minify($filePath))
			{
				@chmod($filePath, 0644);
				static::$callBack($fileUri);
			}
		}
	}

	public static function getCompressor($type)
	{
		if ('css' === $type)
		{
			$compressor = new CSS;
			$compressor->setImportExtensions(static::$importExtensions);
		}
		else
		{
			$compressor = new JS;
		}

		return $compressor;
	}

	public static function output($type)
	{
		return isset(static::$outputs[$type]) ? implode(PHP_EOL, static::$outputs[$type]) : '';
	}

	public static function buildCss($uri, $attributes = [])
	{
		static::$outputs['css'][static::prepareUri($uri)] = '<link rel="stylesheet" href="' . $uri . '" type="text/css"' . static::parseAttributes($attributes) . '/>';
	}

	protected static function prepareUri($uri)
	{
		return preg_replace('/\?.*$/', '', $uri);
	}

	protected static function parseAttributes($attributes)
	{
		$results = [];

		foreach ($attributes as $key => $value)
		{
			if (is_integer($key))
			{
				$results[] = $value;
			}
			else
			{
				$results[] = $key . '="' . htmlspecialchars($value, ENT_COMPAT, 'UTF-8') . '"';
			}
		}

		return $results ? ' ' . implode(' ', $results) : '';
	}

	public static function buildJs($uri, $attributes = [])
	{
		static::$outputs['js'][static::prepareUri($uri)] = '<script src="' . $uri . '"' . static::parseAttributes($attributes) . '></script>';
	}

	public static function buildInlineJs($js)
	{
		static::$outputs['inlineJs'][] = '<script>' . PHP_EOL . $js . PHP_EOL . '</script>';
	}

	public static function buildInlineCss($css)
	{
		static::$outputs['inlineCss'][] = '<style>' . PHP_EOL . $css . PHP_EOL . '</style>';
	}
}