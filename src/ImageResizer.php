<?php

namespace Kwaadpepper\ImageResizer;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Image;
use Intervention\Image\ImageManagerStatic;
use Kwaadpepper\ImageResizer\Exceptions\ImageIsAlreadyCached;
use Kwaadpepper\ImageResizer\Exceptions\ImageResizer as ExceptionsImageResizer;

class ImageResizer
{
    public static function resizeImage(string $imageSource, string $configName = null): string
    {
        if (!File::exists($imageSource) or File::isDirectory($imageSource)) {
            Log::debug(sprintf(
                'Image Resizer: image %s not found',
                $imageSource
            ));
            return $imageSource;
        }

        $relativePath = config('image-resizer.cachePath');

        self::assertFileIsReadable($imageSource);
        File::ensureDirectoryExists($relativePath);

        $fileBaseName = File::basename($imageSource);
        $fileLastModified = File::lastModified($imageSource);

        $path = sprintf(
            '%s/%s',
            $relativePath,
            $fileBaseName
        );

        try {

            extract(self::getConfigValues($configName));

            self::assertFormatIsValid($format);

            $hash = md5($fileBaseName . $fileLastModified . $configName .
                $width . $height . $format);
            $lifeTime = config('image-resizer.lifeTime', 10);

            self::updatePathExtension($path, $format);

            /** @var \Illuminate\Cache\Repository $cache */
            $cache = self::getCache();

            if ($cache->has($hash) && File::exists($path)) {
                throw new ImageIsAlreadyCached();
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
        } catch (ImageIsAlreadyCached $e) {
            Log::debug(sprintf('Image %s is already cached', $fileBaseName));
        }

        return $path;
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
        $image = $iManager::make($fileData);

        return $image;
    }

    /**
     * Set the image in a canvas
     *
     * @param Image $image
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
     * Set the image in a canvas
     *
     * @param Image $image
     * @param integer $width
     * @param integer $height
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
     * Trim the image
     *
     * @param Image $image
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
     * @throws ImageResizer if config is not valid
     */
    private static function getConfigValues(string $configName = null): array
    {
        $config = config(sprintf('image-resizer.templates.%s', $configName));
        if (!$config) {
            $config = config('image-resizer.templates');
            $config = array_shift($config);
        }

        $out = [];
        $p = ['width', 'height'];
        foreach ($p as $required) {
            if (
                !\array_key_exists($required, $config) or
                !is_int($config[$required])
            ) {
                throw new ExceptionsImageResizer(
                    sprintf(
                        'Invalid config %s check %s is present and is integer',
                        $required,
                        $configName
                    )
                );
            }
            $out[$required] = $config[$required];
        }
        $out['inCanvas'] = array_key_exists('inCanvas', $config) ?
            $config['inCanvas'] : false;
        $out['format'] = array_key_exists('format', $config) ?
            $config['format'] : false;
        $out['resize'] = array_key_exists('resize', $config) ?
            $config['resize'] : false;
        $out['keepRatio'] = array_key_exists('keepRatio', $config) ?
            $config['keepRatio'] : false;
        $out['trim'] = array_key_exists('trim', $config) ?
            (is_array($config['trim']) ? $config['trim'] : []) : [];
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
        $o[] = $format;
        $path = implode('.', $o);
    }

    /**
     * Assert format is something of
     * 'jpg', 'png', 'gif', 'tif', 'bmp', 'ico', 'psd', 'webp'
     *
     * @param string $format
     * @return void
     * @throws ExceptionsImageResizer if format is not valid
     */
    private static function assertFormatIsValid(string $format)
    {
        $formats = ['jpg', 'png', 'gif', 'tif', 'bmp', 'ico', 'psd', 'webp'];
        if (!in_array($format, $formats)) {
            throw new ExceptionsImageResizer(sprintf(
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
     * @throws ExceptionsImageResizer if file does not exists
     * @return void
     */
    private static function assertFileIsReadable(string $path)
    {
        if (!File::isReadable($path)) {
            throw new ExceptionsImageResizer(sprintf(
                'File %s is not readable',
                $path
            ));
        }
    }

    /**
     * Get the cache driver
     *
     * @return void
     */
    private static function getCache()
    {
        // Set output driver if set in config
        $cache = config('image-resizer.cache');
        return app()->make('cache')->driver($cache);
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
