# Enum

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]
[![Build Status][ico-travis]][link-travis]
[![StyleCI][ico-styleci]][link-styleci]

Resizes an image on the fly and returns the new link

## Installation

Via Composer

``` bash
$ composer require kwaadpepper/image-resizer
```

## Usage

1 - Publish config

    php artisan vendor:publish --provider="Kwaadpepper\ImageResizer\ImageResizerServiceProvider"

2 - Set a config in templates array (config/image-resizer.php)

    'templates' => [
        'smallWebp' => [
            'height' => 500,
            'width' => 250,
            'inCanvas' => true,
            'format' => 'webp',
            'trim' => ['transparent', null, 10]
        ]
    ]

3 - type in console `php artisan storage:link`

4 - in your blade template override an image link

    <img src="{{ asset(resize('images/volaillesfr_landing.png', 'smallWebp')) }}" alt="My resized image">

5 - Optional You can clean manually outdated cache file using command `php artisan image-resizer:clean-cache`
    or force cleaning the cache using `php artisan cache:clean`

**Please Note that is automatically scheduled on production every half hour**

## Change log

Please see the [changelog](changelog.md) for more information on what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [contributing.md](contributing.md) for details and a todolist.

## Security

If you discover any security related issues, please email github@jeremydev.ovh instead of using the issue tracker.

## Credits

- [Jérémy Munsch][link-author]
- [All Contributors][link-contributors]

## License

MIT. Please see the [license file](license.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/kwaadpepper/image-resizer?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/kwaadpepper/image-resizer?style=flat-square
[ico-travis]: https://img.shields.io/travis/kwaadpepper/image-resizer/master.svg?style=flat-square
[ico-styleci]: https://styleci.io/repos/12345678/shield

[link-packagist]: https://packagist.org/packages/kwaadpepper/image-resizer
[link-downloads]: https://packagist.org/packages/kwaadpepper/image-resizer
[link-travis]: https://travis-ci.org/kwaadpepper/image-resizer
[link-styleci]: https://styleci.io/repos/12345678
[link-author]: https://github.com/kwaadpepper
[link-contributors]: ../../contributors
