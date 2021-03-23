<?php

use Kwaadpepper\ImageResizer\ImageResizer;

/**
 * Resizes image on the fly
 *
 * @param string $path
 * @param string $configName
 * @return string
 */
function resize(string $path, string $configName = null): string
{
    return ImageResizer::resizeImage($path, $configName);
}

/**
 * Resizes image on the fly from public path
 *
 * @param string $path
 * @param string $configName
 * @return string
 */
function resizePublic(string $path, string $configName = null): string
{
    return resize(public_path($path), $configName);
}
