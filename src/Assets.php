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

		$addedFiles[$key] = true;

		if (preg_match('/\.js(\?.*)?$/', $baseFile))
		{
			$t = 'js';
		}
		elseif (preg_match('/\.css(\?.*)?$/', $baseFile))
		{
			$t = 'css';
		}
		else
		{
			return;
		}

		$file = null;

		if (preg_match('/^https?:/', $baseFile))
		{
			$file = $baseFile;
		}
		elseif (is_file($baseFile))
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
				if (preg_match('/^https?:/', $file))
				{
					static::$callBack($file);
				}
				else
				{
					$compressor->add($file);
				}
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

	protected static function buildCss($uri)
	{
		static::$outputs['css'][preg_replace('/\?.*$/', '', $uri)] = '<link rel="stylesheet" href="' . $uri . '" type="text/css"/>';
	}

	protected static function buildJs($uri)
	{
		static::$outputs['js'][preg_replace('/\?.*$/', '', $uri)] = '<script src="' . $uri . '"></script>';
	}

	protected static function buildInlineJs($js)
	{
		static::$outputs['inlineJs'][] = '<script>' . PHP_EOL . $js . PHP_EOL . '</script>';
	}

	protected static function buildInlineCss($css)
	{
		static::$outputs['inlineCss'][] = '<style>' . PHP_EOL . $css . PHP_EOL . '</style>';
	}
}