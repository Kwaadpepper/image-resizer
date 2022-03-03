# Changelog

All notable changes to `ImageResizer` will be documented in this file.

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
