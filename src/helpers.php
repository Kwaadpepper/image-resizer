<?php

use Kwaadpepper\ImageResizer\ImageResizer;

/**
 * Resizes image on the fly
 *
 * @param string|null $path
 * @param string      $configName
 * @return string Returns the resized file path from cache of the original file path if resize could not be done.
 */
function resize($path, ?string $configName = null): string
{
    if (!is_string($path)) {
        return '';
    }
    return ImageResizer::resizeImageOrIgnore($path, $configName, true) ?? $path;
}

/**
 * Resizes image on the fly from public path
 *
 * @param string|null $path
 * @param string      $configName
 * @return string Returns the resized file path from cache of the original file path if resize could not be done.
 */
function resizePublic($path, ?string $configName = null): string
{
    if (!is_string($path)) {
        return '';
    }
    return ImageResizer::resizeImageOrIgnore(public_path($path), $configName, true) ?? $path;
}
