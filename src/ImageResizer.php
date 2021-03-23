<?php

namespace Kwaadpepper\ImageResizer;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageCache;
use Intervention\Image\ImageManagerStatic;
use Kwaadpepper\ImageResizer\Exceptions\ImageIsAlreadyCached;
use Kwaadpepper\ImageResizer\Exceptions\ImageResizer as ExceptionsImageResizer;

class ImageResizer
{
    public static function resizeImage(string $imageSource): string
    {
        if (!File::exists($imageSource)) {
            Log::debug(sprintf(
                'Image Resizer: image %s not found',
                $imageSource
            ));
            return $imageSource;
        }

        if (!File::isReadable($imageSource)) {
            throw new ExceptionsImageResizer(sprintf(
                'File %s is not readable',
                $imageSource
            ));
        }

        $relativePublicPath = 'cache/images';

        File::ensureDirectoryExists(public_path($relativePublicPath));

        $fileData = File::get($imageSource);
        $fileBaseName = File::basename($imageSource);

        $lifeTime = 10;
        $width = 100;
        $height = 50;
        $cache = config('image-resizer.cache');
        $publicPath = public_path(sprintf(
            '%s/%s',
            $relativePublicPath,
            $fileBaseName
        ));

        $iManager = self::getManager();

        try {
            // make the image from cache
            $iManager->cache(function (ImageCache &$image) use (
                $iManager,
                $cache,
                $fileBaseName,
                $lifeTime,
                $publicPath,
                $fileData,
                $width,
                $height
            ) {
                // Set cache driver if set in config
                if ($cache) {
                    $cache = app()->make('cache')->driver($cache);
                    $image = (new ImageCache(
                        $iManager->getManager(),
                        $cache
                    ));
                }

                /** @var \Illuminate\Cache\Repository $cache */
                $cache = $image->cache;

                if ($cache->has(md5($fileBaseName))) {
                    throw new ImageIsAlreadyCached();
                }

                $image->make($fileData)
                    ->resize($width, $height)->save($publicPath);
                // Intervention Cache use cache value as minutes
                $cache->put(md5($fileBaseName), true, $lifeTime * 60);
            }, $lifeTime, true);
        } catch (ImageIsAlreadyCached $e) {
            Log::debug(sprintf('Image %s is already cached', $fileBaseName));
        }

        return sprintf('%s/%s', $relativePublicPath, $fileBaseName);
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
