# Image Resizer

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]
[![CI][ico-ci]][link-ci]

Resizes an image on the fly and returns the new link

## Installation

Via Composer

``` bash
composer require kwaadpepper/image-resizer
```

## Usage

1 - Publish config

``` bash
php artisan vendor:publish --provider="Kwaadpepper\ImageResizer\ImageResizerServiceProvider"
```

2 - Set a config in templates array (config/image-resizer.php)

    /**
     *   resize => will resize the image (boolean)
     *   fit => Combine cropping and resizing to format image in a smart way (boolean)
     *   keepRatio => will keep image ratio wile resizing (boolean)
     *   trim => boolean to trim the image using border color
     *   inCanvas => to make sure image boundarie is respected
     *   format => select tha wantd ouput form, yan can just convert images if you want
     */
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

``` html
<img src="{{ asset(resize('images/volaillesfr_landing.png', 'smallWebp')) }}" alt="My resized image">
```

5 - Optional You can manually clean outdated cache files using command `php artisan image-resizer:clean-cache`
    or force cleaning the cache using `php artisan cache:clear`

- **Please note that this is automatically scheduled on production every half hour**
- Cache lifetime defaults to 1 week (configurable via `lifetime` in `config/image-resizer.php`). Cached images are regenerated on demand.

## Change log

Please see the [changelog](changelog.md) for more information on what has changed recently.

## Testing

``` bash
composer test
```

## Contributing

Please see [contributing.md](contributing.md) for details and a todolist.

## Security

If you discover any security related issues, please email <github@jeremydev.ovh> instead of using the issue tracker.

## Credits

- [Jérémy Munsch][link-author]
- [All Contributors][link-contributors]

## License

MIT. Please see the [license file](license.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/kwaadpepper/image-resizer?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/kwaadpepper/image-resizer?style=flat-square
[ico-ci]: https://img.shields.io/github/actions/workflow/status/Kwaadpepper/image-resizer/ci.yml?branch=master&style=flat-square&label=CI

[link-packagist]: https://packagist.org/packages/kwaadpepper/image-resizer
[link-downloads]: https://packagist.org/packages/kwaadpepper/image-resizer
[link-ci]: https://github.com/Kwaadpepper/image-resizer/actions/workflows/ci.yml
[link-author]: https://github.com/kwaadpepper
[link-contributors]: ../../contributors
