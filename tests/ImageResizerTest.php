<?php

namespace Kwaadpepper\ImageResizer\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Kwaadpepper\ImageResizer\Exceptions\ImageResizerException;
use Kwaadpepper\ImageResizer\ImageResizer;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ImageResizerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is required to run these tests.');
        }

        // Must be set before parent::setUp() because defineEnvironment() uses it
        $this->tempDir = sys_get_temp_dir() . '/image-resizer-tests-' . uniqid();
        mkdir($this->tempDir, 0755, true);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        if (isset($this->tempDir) && is_dir($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [\Kwaadpepper\ImageResizer\ImageResizerServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('filesystems.disks.public', [
            'driver' => 'local',
            'root' => $this->tempDir . '/storage',
            'url' => '/storage',
            'visibility' => 'public',
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('image-resizer.driver', 'gd');
        $app['config']->set('image-resizer.cache', 'array');
        $app['config']->set('image-resizer.cachePath', 'cache/images');
        $app['config']->set('image-resizer.lifeTime', 60);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createPngImage(int $width = 100, int $height = 80): string
    {
        $img = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($img, 200, 50, 50);
        imagefilledrectangle($img, 0, 0, $width - 1, $height - 1, $color);

        $path = $this->tempDir . '/source.png';
        imagepng($img, $path);
        imagedestroy($img);

        return $path;
    }

    private function createJpgImage(int $width = 100, int $height = 80): string
    {
        $img = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($img, 100, 150, 200);
        imagefilledrectangle($img, 0, 0, $width - 1, $height - 1, $color);

        $path = $this->tempDir . '/source.jpg';
        imagejpeg($img, $path, 90);
        imagedestroy($img);

        return $path;
    }

    private function setTemplates(array $templates): void
    {
        config()->set('image-resizer.templates', $templates);
    }

    // =========================================================================
    // resizeImage — fichier inexistant
    // =========================================================================

    #[Test]
    public function it_returns_null_when_image_does_not_exist(): void
    {
        // Given a path pointing to a non-existent file
        $missingPath = $this->tempDir . '/nonexistent.png';

        // When we attempt to resize it
        $result = ImageResizer::resizeImage($missingPath);

        // Then null is returned
        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_null_when_path_is_a_directory(): void
    {
        // Given a path pointing to a directory (not a file)
        $dirPath = $this->tempDir . '/somedir';
        mkdir($dirPath, 0755, true);

        // When we attempt to resize it
        $result = ImageResizer::resizeImage($dirPath);

        // Then null is returned
        $this->assertNull($result);
    }

    // =========================================================================
    // resizeImage — resize operation
    // =========================================================================

    #[Test]
    public function it_resizes_a_png_image_to_specified_dimensions(): void
    {
        // Given a 100x80 PNG image and a resize template of 50x40
        $imagePath = $this->createPngImage(100, 80);
        $this->setTemplates([
            'thumb' => [
                'width' => 50,
                'height' => 40,
                'resize' => true,
            ],
        ]);

        // When we resize the image
        $result = ImageResizer::resizeImage($imagePath, 'thumb');

        // Then the cached file exists and has the expected dimensions
        $this->assertNotNull($result);
        $disk = Storage::disk('public');
        $this->assertTrue($disk->exists($result));

        $size = getimagesize($disk->path($result));
        $this->assertSame(50, $size[0]);
        $this->assertSame(40, $size[1]);
    }

    #[Test]
    public function it_resizes_keeping_ratio_with_scale(): void
    {
        // Given a 200x100 PNG image and a resize+keepRatio template
        $imagePath = $this->createPngImage(200, 100);
        $this->setTemplates([
            'scaled' => [
                'width' => 100,
                'height' => 100,
                'resize' => true,
                'keepRatio' => true,
            ],
        ]);

        // When we resize the image with keepRatio
        $result = ImageResizer::resizeImage($imagePath, 'scaled');

        // Then the cached file preserves the 2:1 aspect ratio
        $this->assertNotNull($result);
        $disk = Storage::disk('public');
        $size = getimagesize($disk->path($result));
        $this->assertSame(100, $size[0]);
        $this->assertSame(50, $size[1]);
    }

    // =========================================================================
    // resizeImage — fit operation
    // =========================================================================

    #[Test]
    public function it_fits_an_image_without_exceeding_original_size(): void
    {
        // Given a 100x80 PNG image and a fit template targeting 200x200
        $imagePath = $this->createPngImage(100, 80);
        $this->setTemplates([
            'fitted' => [
                'width' => 200,
                'height' => 200,
                'fit' => true,
            ],
        ]);

        // When we fit the image with resizeDown (no upscaling)
        $result = ImageResizer::resizeImage($imagePath, 'fitted');

        // Then the result does not exceed the original dimensions
        $this->assertNotNull($result);
        $disk = Storage::disk('public');
        $size = getimagesize($disk->path($result));
        $this->assertLessThanOrEqual(100, $size[0]);
        $this->assertLessThanOrEqual(80, $size[1]);
    }

    // =========================================================================
    // resizeImage — inCanvas operation
    // =========================================================================

    #[Test]
    public function it_places_image_in_canvas_with_exact_dimensions(): void
    {
        // Given a 60x40 PNG image and an inCanvas template of 120x100
        $imagePath = $this->createPngImage(60, 40);
        $this->setTemplates([
            'canvas' => [
                'width' => 120,
                'height' => 100,
                'inCanvas' => true,
            ],
        ]);

        // When we place the image in a canvas
        $result = ImageResizer::resizeImage($imagePath, 'canvas');

        // Then the output has the exact canvas dimensions
        $this->assertNotNull($result);
        $disk = Storage::disk('public');
        $size = getimagesize($disk->path($result));
        $this->assertSame(120, $size[0]);
        $this->assertSame(100, $size[1]);
    }

    // =========================================================================
    // resizeImage — format conversion
    // =========================================================================

    #[Test]
    public function it_converts_a_png_image_to_jpg_format(): void
    {
        // Given a PNG image and a template that forces JPG output
        $imagePath = $this->createPngImage(80, 60);
        $this->setTemplates([
            'tojpg' => [
                'width' => 80,
                'height' => 60,
                'resize' => true,
                'format' => 'jpg',
            ],
        ]);

        // When we resize with format conversion
        $result = ImageResizer::resizeImage($imagePath, 'tojpg');

        // Then the output path ends with .jpg and the file is a valid JPEG
        $this->assertNotNull($result);
        $this->assertStringEndsWith('.jpg', $result);

        $disk = Storage::disk('public');
        $size = getimagesize($disk->path($result));
        $this->assertSame(IMAGETYPE_JPEG, $size[2]);
    }

    // =========================================================================
    // resizeImage — caching
    // =========================================================================

    #[Test]
    public function it_returns_cached_path_on_second_call(): void
    {
        // Given a resized image already in cache
        $imagePath = $this->createPngImage(100, 80);
        $this->setTemplates([
            'cached' => [
                'width' => 50,
                'height' => 40,
                'resize' => true,
            ],
        ]);
        $firstResult = ImageResizer::resizeImage($imagePath, 'cached');

        // When we call resize again with the same source and config
        $secondResult = ImageResizer::resizeImage($imagePath, 'cached');

        // Then the same cached path is returned without re-processing
        $this->assertSame($firstResult, $secondResult);
    }

    // =========================================================================
    // resizeImage — publicPath flag
    // =========================================================================

    #[Test]
    public function it_returns_a_public_relative_url_when_flag_is_set(): void
    {
        // Given a PNG image and a resize template
        $imagePath = $this->createPngImage(100, 80);
        $this->setTemplates([
            'pub' => [
                'width' => 50,
                'height' => 40,
                'resize' => true,
            ],
        ]);

        // When we resize with publicPath = true
        $result = ImageResizer::resizeImage($imagePath, 'pub', true);

        // Then the result is a relative URL (not an absolute disk path)
        $this->assertNotNull($result);
        $this->assertStringStartsWith('storage/', $result);
    }

    // =========================================================================
    // resizeImage — trim operation
    // =========================================================================

    #[Test]
    public function it_trims_image_borders(): void
    {
        // Given a 100x80 image with a uniform border and a trim template
        $imagePath = $this->createPngImage(100, 80);
        $this->setTemplates([
            'trimmed' => [
                'width' => 100,
                'height' => 80,
                'resize' => true,
                'trim' => [true],
            ],
        ]);

        // When we resize with trim enabled
        $result = ImageResizer::resizeImage($imagePath, 'trimmed');

        // Then a cached file is produced (trim was applied without error)
        $this->assertNotNull($result);
        $disk = Storage::disk('public');
        $this->assertTrue($disk->exists($result));
    }

    // =========================================================================
    // resizeImage — invalid format
    // =========================================================================

    #[Test]
    public function it_throws_exception_for_unsupported_format(): void
    {
        // Given a PNG image and a template with an invalid format
        $imagePath = $this->createPngImage(100, 80);
        $this->setTemplates([
            'bad' => [
                'width' => 50,
                'height' => 40,
                'resize' => true,
                'format' => 'svg',
            ],
        ]);

        // Then an ImageResizerException is thrown
        $this->expectException(ImageResizerException::class);

        // When we attempt to resize
        ImageResizer::resizeImage($imagePath, 'bad');
    }

    // =========================================================================
    // resizeImage — missing width/height for resize config
    // =========================================================================

    #[Test]
    public function it_throws_exception_when_resize_config_lacks_dimensions(): void
    {
        // Given a PNG image and a resize template without width/height
        $imagePath = $this->createPngImage(100, 80);
        $this->setTemplates([
            'broken' => [
                'resize' => true,
            ],
        ]);

        // Then an ImageResizerException is thrown
        $this->expectException(ImageResizerException::class);

        // When we attempt to resize
        ImageResizer::resizeImage($imagePath, 'broken');
    }

    // =========================================================================
    // resizeImageOrIgnore
    // =========================================================================

    #[Test]
    public function it_ignores_runtime_exceptions_and_returns_null(): void
    {
        // Given a non-image file that will cause a runtime exception
        $fakePath = $this->tempDir . '/notanimage.png';
        file_put_contents($fakePath, 'this is not a valid image');
        $this->setTemplates([
            'safe' => [
                'width' => 50,
                'height' => 40,
                'resize' => true,
            ],
        ]);

        // When we call resizeImageOrIgnore
        $result = ImageResizer::resizeImageOrIgnore($fakePath, 'safe');

        // Then null is returned instead of throwing
        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_resized_path_via_or_ignore_on_success(): void
    {
        // Given a valid PNG image
        $imagePath = $this->createPngImage(100, 80);
        $this->setTemplates([
            'ok' => [
                'width' => 50,
                'height' => 40,
                'resize' => true,
            ],
        ]);

        // When we call resizeImageOrIgnore
        $result = ImageResizer::resizeImageOrIgnore($imagePath, 'ok');

        // Then the cached path is returned
        $this->assertNotNull($result);
    }

    // =========================================================================
    // configToMd5
    // =========================================================================

    #[Test]
    public function it_produces_deterministic_md5_for_same_config(): void
    {
        // Given the same config and timestamp
        $config = ['width' => 50, 'height' => 40, 'resize' => true];
        $timestamp = 1700000000;

        // When we compute md5 twice
        $hash1 = ImageResizer::configToMd5($config, $timestamp);
        $hash2 = ImageResizer::configToMd5($config, $timestamp);

        // Then the hashes are identical
        $this->assertSame($hash1, $hash2);
    }

    #[Test]
    public function it_produces_different_md5_for_different_configs(): void
    {
        // Given two different configs
        $config1 = ['width' => 50, 'height' => 40, 'resize' => true];
        $config2 = ['width' => 100, 'height' => 80, 'resize' => true];
        $timestamp = 1700000000;

        // When we compute md5 for each
        $hash1 = ImageResizer::configToMd5($config1, $timestamp);
        $hash2 = ImageResizer::configToMd5($config2, $timestamp);

        // Then the hashes differ
        $this->assertNotSame($hash1, $hash2);
    }

    #[Test]
    public function it_throws_exception_for_unsupported_config_type(): void
    {
        // Given a config with an object value (unsupported)
        $config = ['bad' => new \stdClass()];

        // Then an ImageResizerException is thrown
        $this->expectException(ImageResizerException::class);

        // When we compute md5
        ImageResizer::configToMd5($config, 123);
    }

    // =========================================================================
    // resizeImage — JPG source
    // =========================================================================

    #[Test]
    public function it_resizes_a_jpg_image_preserving_format(): void
    {
        // Given a 120x90 JPG image and a resize template
        $imagePath = $this->createJpgImage(120, 90);
        $this->setTemplates([
            'jpgresize' => [
                'width' => 60,
                'height' => 45,
                'resize' => true,
            ],
        ]);

        // When we resize the image
        $result = ImageResizer::resizeImage($imagePath, 'jpgresize');

        // Then the output is a valid JPEG with expected dimensions
        $this->assertNotNull($result);
        $disk = Storage::disk('public');
        $size = getimagesize($disk->path($result));
        $this->assertSame(60, $size[0]);
        $this->assertSame(45, $size[1]);
        $this->assertSame(IMAGETYPE_JPEG, $size[2]);
    }

    // =========================================================================
    // resizeImage — file not readable
    // =========================================================================

    #[Test]
    public function it_throws_exception_when_file_is_not_readable(): void
    {
        // Given a file that exists but is not readable
        $imagePath = $this->createPngImage(100, 80);
        chmod($imagePath, 0000);

        // Then an ImageResizerException is thrown
        $this->expectException(ImageResizerException::class);

        // When we attempt to resize
        try {
            ImageResizer::resizeImage($imagePath);
        } finally {
            chmod($imagePath, 0644);
        }
    }
}
