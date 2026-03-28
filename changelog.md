# Changelog

All notable changes to `ImageResizer` will be documented in this file.

## VERSION 4.0.0

- **BREAKING** Upgraded Intervention Image from v3 to v4
- **BREAKING** PHP 8.3+ required
- Added Laravel 13 support
- Added PHPUnit tests for ImageResizer
- Added GitHub Actions CI workflow (PHPCS, PHPStan, PHPUnit)
- Fixed exception handling to use Intervention Image v4 exception hierarchy
- Updated PHPStan and Larastan to latest versions
- Updated phpunit.xml for PHPUnit 12 compatibility

## VERSION 3.0.2

- Fixed picture output had wrong extension

## VERSION 3.0.1

- Fixed picture output was wrong when already having picture in cache.

## VERSION 3.0.0

- Changed to laravel 11 support only
- Removed Intervention Image cache

## VERSION 2.1.0

- Added fit option to resize smartly.

## VERSION 2.0.0

- Fixed version to Laravel 10 only

## VERSION 0.1.3

- Changed Support passing null to resize helper (for nullable props), will return an empty string

## VERSION 0.1.2

- Fixed Using Storage instead of blind path, thus this package now need the usage of storage:link
- Added command `php artisan image-resizer:clean-cache`
- Added The previous command is **automatically scheduled every half hour**

## VERSION 0.1.1

- Fixed strip prepended slash on source string

## VERSION 0.1.0

- Added PHPCS rules
- Added function docblock comments
- Renamed Internal Exceptions
- Refactorized code
- resizeImage now returns string or null if the image could not be resized.
  It still can throws ImageResizerException If any gone wrong.

## VERSION 0.0.10

- Fixed file name slugify

## VERSION 0.0.9

- Fixed slugify image basename to avoir further exceptions
- Ignore non binary images (like svg)

## VERSION 0.0.8

- Fixed serialize config to md5 to prevent cache collisions
  Use hash in cache path name to prevent cached images collisions
- Fixed Add in Canvas check for required params

## VERSION 0.0.7

- Changed Removed required check in with and height if resize is false
  You can now just convert images using only format

## VERSION 0.0.6

- minor bug fix

## VERSION 0.0.4

- pass config to trim

## Version 0.0.1

- Initial release
