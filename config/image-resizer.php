<?php

return [
    /**
     * The php image driver to use
     * this can be 'imagick' or 'gd'
     */
    'driver' => 'gd',
    /**
     * The cache driver to use as defined
     * in config/cache of your app
     */
    'cache' => 'file',

    /**
     * Image cache life time
     * This is given in minutes
     * Here the cache for images is
     * one hour
     */
    'lifetime' => 60,

    'templates' => [
        'small' => [
            'height' => 500,
            'width' => 250,
            'inCanvas' => true
        ]
    ]
];
