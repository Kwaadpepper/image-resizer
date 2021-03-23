<?php

namespace Kwaadpepper\ImageResizer;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageCache;
use Intervention\Image\ImageManagerStatic;
use Kwaadpepper\ImageResizer\Exceptions\ImageIsAlreadyCached;
use Kwaadpepper\ImageResizer\Exceptions\ImageResizer as ExceptionsImageResizer;

class ImageResizer
{
    public static function resizeImage(
        string $imageSource,
        string $configName = null
    ): string
    {
        $relativePublicPath = 'cache/images';

        if (!File::exists($imageSource)) {
            Log::debug(sprintf(
                'Image Resizer: image %s not found',
                $imageSource
            ));
            return $imageSource;
        }

        self::assertFileIsReadable($imageSource);
        File::ensureDirectoryExists(public_path($relativePublicPath));

        $fileBaseName = File::basename($imageSource);
        $fileLastModified = File::lastModified($imageSource);

        $iManager = self::getManager();

        try {
            // Set cache driver if set in config
            $cache = config('image-resizer.cache');
            $cache = app()->make('cache')->driver($cache);
            extract(self::getConfigValues($configName));

            $hash = md5($fileBaseName . $fileLastModified . $configName .
            $width . $height);

            $lifeTime = config('image-resizer.lifeTime', 10);

            /** @var \Illuminate\Cache\Repository $image->cache */
            if ($cache->has($hash)) {
                throw new ImageIsAlreadyCached();
            }

            $fileData = File::get($imageSource);
            $publicPath = public_path(sprintf(
                '%s/%s',
                $relativePublicPath,
                $fileBaseName
            ));

            $image = $iManager::make($fileData);

            $image = $image->resize($width, $height, function ($constraint) {
                $constraint->aspectRatio();
            });
            if ($inCanvas) {
                $image = $image->resizeCanvas($width, $height, 'center', false, 'rgba(0, 0, 0, 0)');
            }
            $image->save($publicPath);

            // Intervention Cache use cache value as minutes
            $cache->put(
                $hash,
                true,
                Carbon::now()->addMinutes($lifeTime * 60)
            );
        } catch (ImageIsAlreadyCached $e) {
            Log::debug(sprintf('Image %s is already cached', $fileBaseName));
        }

        return sprintf('%s/%s', $relativePublicPath, $fileBaseName);
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
        $out['inCanvas'] = array_key_exists('inCanvas', $config) ? $config['inCanvas'] : false;
        return $out;
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
