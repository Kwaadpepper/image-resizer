<?php

namespace Kwaadpepper\ImageResizer;

use Illuminate\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\Exception\NotReadableException;
use Intervention\Image\Image;
use Intervention\Image\ImageManagerStatic;
use Kwaadpepper\ImageResizer\Exceptions\ImageIsAlreadyCachedException;
use Kwaadpepper\ImageResizer\Exceptions\ImageResizerException;

class ImageResizer
{
    /**
     * Resize image
     *
     * @param string      $imageSource
     * @param string|null $configName
     * @return string|null Returns null if file does not exists, else returns the resized file path from cache.
     * @throws \Kwaadpepper\ImageResizer\Exceptions\ImageResizerException If file is not readable or format is invalid.
     * @phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag.Missing
     */
    public static function resizeImage(string $imageSource, string $configName = null): ?string
    {
        if (!File::exists($imageSource) or File::isDirectory($imageSource)) {
            Log::debug(sprintf(
                'Image Resizer: image %s not found',
                $imageSource
            ));
            return null;
        }

        $relativePath = config('image-resizer.cachePath');

        self::assertFileIsReadable($imageSource);
        File::ensureDirectoryExists($relativePath);

        $fileBaseName = sprintf(
            '%s.%s',
            Str::slug(File::name($imageSource)),
            File::extension($imageSource)
        );

        $fileLastModified = File::lastModified($imageSource);

        try {
            $config = self::getConfigValues($configName);
            extract($config);

            self::assertFormatIsValid($format);

            $hash     = self::configToMd5($config, $fileLastModified);
            $lifeTime = config('image-resizer.lifeTime', 10);

            $path = sprintf(
                '%s/%s_%s',
                $relativePath,
                $hash,
                $fileBaseName
            );

            self::updatePathExtension($path, $format);

            $cache = self::getCache();

            if ($cache->has($hash) && File::exists($path)) {
                throw new ImageIsAlreadyCachedException();
            }

            $image = self::genImage($imageSource);

            if (count($trim)) {
                self::trim($image, $trim);
            }

            if ($resize) {
                self::resize($image, $width, $height, $keepRatio);
            }

            if ($inCanvas) {
                self::setInCanvas($image, $width, $height);
            }

            $image->save($path, null, $format);

            $cache->put(
                $hash,
                true,
                Carbon::now()->addMinutes($lifeTime)
            );
        } catch (ImageIsAlreadyCachedException $e) {
            Log::debug(sprintf('Image %s is already cached', $fileBaseName));
        }//end try

        return $path;
    }

    /**
     * Igore possible exceptions
     * like passing an svg which is not a binary image
     * and cannot be resized
     *
     * @param string      $imageSource
     * @param string|null $configName
     * @return string|null Returns null if file does not exists, else returns the resized file path from cache.
     */
    public static function resizeImageOrIgnore(string $imageSource, string $configName = null): ?string
    {
        try {
            return self::resizeImage($imageSource, $configName);
        } catch (NotReadableException $e) {
            return null;
        }
    }

    /**
     * Set config parameter to md5 value in order to detect changes.
     *
     * @param array   $config           The config array.
     * @param integer $fileLastModified The config file timestamp.
     * @return string
     * @throws \Kwaadpepper\ImageResizer\Exceptions\ImageResizerException If config has an unsupported entry.
     */
    public static function configToMd5(array $config, int $fileLastModified): string
    {
        $string = '';
        foreach ($config as $param => $value) {
            switch (gettype($value)) {
                case 'boolean':
                    $string .= $param . ($value ? 'true' : 'false');
                    break;
                case 'float':
                case 'double':
                case 'integer':
                    $string .= $param . ((string)$value);
                    break;
                case 'string':
                    $string .= "$param$value";
                    break;
                case 'array':
                    $string .= $param . serialize($value);
                    break;
                default:
                    throw new ImageResizerException(
                        sprintf(
                            'Invalid config %s, unsupported type',
                            $param
                        )
                    );
            }//end switch
        }//end foreach
        $string .= $fileLastModified;
        return md5($string);
    }

    /**
     * Generate image
     *
     * @param string $sourcePath
     * @return \Intervention\Image\Image
     */
    private static function genImage(string $sourcePath): Image
    {
        $iManager = self::getManager();
        $fileData = File::get($sourcePath);
        $image    = $iManager::make($fileData);

        return $image;
    }

    /**
     * Resize image in a canvas operation
     *
     * @param Image   $image
     * @param integer $width
     * @param integer $height
     * @return void
     */
    private static function setInCanvas(Image &$image, int $width, int $height)
    {
        $image->resizeCanvas(
            $width,
            $height,
            'center',
            false,
            'rgba(0, 0, 0, 0)'
        );
    }

    /**
     * Resize image operation
     *
     * @param Image   $image     Intervention image to work with.
     * @param integer $width     The image witdh target.
     * @param integer $height    The image height target.
     * @param boolean $keepRatio Does the image resize operation should keep ratio.
     * @return void
     */
    private static function resize(Image &$image, int $width, int $height, bool $keepRatio = false)
    {
        $image->resize(
            $width,
            $height,
            function ($constraint) use ($keepRatio) {
                if ($keepRatio) {
                    $constraint->aspectRatio();
                }
            }
        );
    }

    /**
     * Trim image operation
     *
     * @param Image $image  Intervention image to work with.
     * @param array $config Config to pass to Intervention image.
     * @return void
     */
    private static function trim(Image &$image, array $config = [])
    {
        call_user_func_array([$image, 'trim'], $config);
    }

    /**
     * Get the config values
     *
     * @param string $configName
     * @return array
     * @throws \Kwaadpepper\ImageResizer\Exceptions\ImageResizerException If config is not valid.
     */
    private static function getConfigValues(string $configName = null): array
    {
        $config = collect(config('image-resizer.templates'))
        ->get($configName, collect(config('image-resizer.templates'))->first());

        $out = [];

        $out = \array_replace_recursive([
            'inCanvas' => false,
            'format' => false,
            'resize' => false,
            'keepRatio' => false,
            'trim' => []
        ], $config);

        $out['trim'] = is_array($out['trim']) ? $out['trim'] : [];

        foreach (['width', 'height'] as $required) {
            if (
                ($out['resize'] or $out['inCanvas']) and (
                    !\array_key_exists($required, $config) or
                    !is_int($config[$required]))
            ) {
                throw new ImageResizerException(
                    sprintf(
                        'Invalid config %s check %s is present and is integer',
                        $required,
                        $configName
                    )
                );
            }
            $out[$required] = $config[$required] ?? 0;
        }

        return $out;
    }

    /**
     * Update extension path
     *
     * @param string $path
     * @param string $format
     * @return void
     */
    private static function updatePathExtension(string &$path, string $format)
    {
        $o = explode('.', $path);
        array_pop($o);
        $o[]  = $format;
        $path = implode('.', $o);
    }

    /**
     * Assert format is something of
     * 'jpg', 'png', 'gif', 'tif', 'bmp', 'ico', 'psd', 'webp'
     *
     * @param string $format
     * @return void
     * @throws \Kwaadpepper\ImageResizer\Exceptions\ImageResizerException If format is not valid.
     */
    private static function assertFormatIsValid(string $format)
    {
        $formats = ['jpg', 'png', 'gif', 'tif', 'bmp', 'ico', 'psd', 'webp'];
        if (!in_array($format, $formats)) {
            throw new ImageResizerException(sprintf(
                'format %s is not accepted, choose within %s',
                $format,
                implode(',', $formats)
            ));
        }
    }

    /**
     * Assert the file exists and is readable
     *
     * @param string $path
     * @throws \Kwaadpepper\ImageResizer\Exceptions\ImageResizerException If file does not exists.
     * @return void
     */
    private static function assertFileIsReadable(string $path)
    {
        if (!File::isReadable($path)) {
            throw new ImageResizerException(sprintf(
                'File %s is not readable',
                $path
            ));
        }
    }

    /**
     * Get the cache driver
     *
     * @return \Illuminate\Cache\Repository
     */
    private static function getCache(): Repository
    {
        // Set output driver if set in config.
        /** @var string|mixed|null */
        $cache = config('image-resizer.cache', config('cache.default'));
        /** @var \Illuminate\Cache\CacheManager */
        $cacheManager = app()->make('cache');
        return $cacheManager->driver($cache);
    }

    /**
     * Get the image driver manager
     *
     * @return \Intervention\Image\ImageManagerStatic
     */
    private static function getManager()
    {
        $iM = new ImageManagerStatic();
        $iM->configure(config('image-resizer'));
        return $iM;
    }
}
