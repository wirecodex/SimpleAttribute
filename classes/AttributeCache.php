<?php

declare(strict_types=1);

namespace SimpleWire\Attribute;

/**
 * AttributeCache — File-Based Cache for Compiled Templates
 *
 * Stores compiled PHP in /site/assets/cache/SimpleWire/Attribute/ and
 * invalidates entries automatically when the source file changes.
 *
 * @package SimpleWire
 */
class AttributeCache
{
    protected \ProcessWire\Config $config;
    protected string $cachePath;

    public function __construct(\ProcessWire\Config $config)
    {
        $this->config    = $config;
        $this->cachePath = $config->paths->cache . 'SimpleWire/Attribute/';

        if (!is_dir($this->cachePath)) {
            \ProcessWire\wireMkdir($this->cachePath, true);
        }
    }

    /**
     * Return the cached file path if it is still valid, null otherwise.
     */
    public function get(string $filename): ?string
    {
        $cacheFile = $this->getCacheFilename($filename);

        if (!file_exists($cacheFile)) {
            return null;
        }

        if (filemtime($filename) > filemtime($cacheFile)) {
            return null;
        }

        return $cacheFile;
    }

    /**
     * Write compiled content to the cache and return the cache file path.
     */
    public function save(string $filename, string $content): string
    {
        $cacheFile = $this->getCacheFilename($filename);
        $cacheDir  = dirname($cacheFile);

        if (!is_dir($cacheDir)) {
            \ProcessWire\wireMkdir($cacheDir, true);
        }

        $header = "<?php /* Compiled from: {$filename} */ ?>\n";
        if (file_put_contents($cacheFile, $header . $content) === false) {
            return $filename;
        }

        touch($cacheFile, filemtime($filename));

        return $cacheFile;
    }

    /**
     * Remove the cached file for a given source path.
     */
    public function clear(string $filename): bool
    {
        $cacheFile = $this->getCacheFilename($filename);

        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }

        return true;
    }

    /**
     * Remove all cached files.
     */
    public function clearAll(): bool
    {
        if (!is_dir($this->cachePath)) {
            return true;
        }

        return \ProcessWire\wireRmdir($this->cachePath, true);
    }

    /**
     * Return aggregate stats for cached files.
     */
    public function getStats(): array
    {
        $stats = [
            'total_files' => 0,
            'total_size'  => 0,
            'oldest_file' => null,
            'newest_file' => null,
        ];

        if (!is_dir($this->cachePath)) {
            return $stats;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->cachePath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') continue;

            $stats['total_files']++;
            $stats['total_size'] += $file->getSize();

            $mtime = $file->getMTime();

            if ($stats['oldest_file'] === null || $mtime < $stats['oldest_file']) {
                $stats['oldest_file'] = $mtime;
            }
            if ($stats['newest_file'] === null || $mtime > $stats['newest_file']) {
                $stats['newest_file'] = $mtime;
            }
        }

        $stats['total_size_formatted'] = $this->formatBytes($stats['total_size']);

        if ($stats['oldest_file']) {
            $stats['oldest_file_formatted'] = date('Y-m-d H:i:s', $stats['oldest_file']);
        }
        if ($stats['newest_file']) {
            $stats['newest_file_formatted'] = date('Y-m-d H:i:s', $stats['newest_file']);
        }

        return $stats;
    }

    // ========================================
    // Internal
    // ========================================

    protected function getCacheFilename(string $filename): string
    {
        $relativePath = str_replace($this->config->paths->root, '', $filename);
        $cacheFile    = $this->cachePath . trim($relativePath, '/');

        if (!preg_match('/\.php$/', $cacheFile)) {
            $cacheFile .= '.php';
        }

        return $cacheFile;
    }

    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes === 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow   = min((int) floor(($bytes ? log($bytes) : 0) / log(1024)), count($units) - 1);

        return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
    }
}
