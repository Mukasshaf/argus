<?php

declare(strict_types=1);

namespace App\Channels\Mela;

use Illuminate\Support\Facades\File;

class RemoteFileStore
{
    public function __construct(private string $basePath)
    {
        if (!File::exists($this->basePath)) {
            File::makeDirectory($this->basePath, 0755, true, true);
        }
    }

    public function ensureDirectory(string $subDir): string
    {
        $dir = rtrim($this->basePath, '/\\') . DIRECTORY_SEPARATOR . trim($subDir, '/\\');
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true, true);
        }
        return $dir;
    }

    public function put(string $subDir, string $fileName, string $contents): string
    {
        $dir = $this->ensureDirectory($subDir);
        $fullPath = $dir . DIRECTORY_SEPARATOR . $fileName;
        File::put($fullPath, $contents);
        return $fullPath;
    }

    /**
     * @return list<string>
     */
    public function listFiles(string $subDir): array
    {
        $dir = $this->ensureDirectory($subDir);
        $files = File::files($dir);
        $result = [];
        foreach ($files as $file) {
            $result[] = $file->getPathname();
        }
        return $result;
    }

    public function get(string $fullPath): string
    {
        return File::get($fullPath);
    }

    public function move(string $sourcePath, string $targetSubDir, string $fileName): string
    {
        $dir = $this->ensureDirectory($targetSubDir);
        $targetPath = $dir . DIRECTORY_SEPARATOR . $fileName;
        File::move($sourcePath, $targetPath);
        return $targetPath;
    }
}
