<?php

namespace Kwaadpepper\ImageResizer\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AutoCleanCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'image-resizer:clean-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean expired files on cache';

    /**
     * Execute the console command.
     *
     * @return integer
     */
    public function handle()
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter */
        $disk = Storage::disk('public');
        /** @var integer */
        $lifeTime = config('image-resizer.lifeTime', 10);

        $commandName = basename(str_replace('\\', '/', self::class));

        \collect($disk->files(config('image-resizer.cachePath')))
            ->map(function (string $filePath) use ($disk, $lifeTime, $commandName) {
                $fileTimestamp    = Carbon::parse($disk->lastModified($filePath));
                $timeoutTimestamp = Carbon::now()->addMinutes($lifeTime);
                // * Remove outdated cache file if needed.
                if ($timeoutTimestamp->lessThan($fileTimestamp)) {
                    if (!$disk->delete($filePath)) {
                        throw new \RuntimeException(
                            "{$commandName} : Failed to remove file old cache file '{$filePath}'"
                        );
                    }
                    Log::notice("{$commandName} : Removed outdated cache file '{$filePath}'");
                    $this->info("{$commandName} : Removed outdated cache file '{$filePath}'");
                }
            });

        return 0;
    }
}
