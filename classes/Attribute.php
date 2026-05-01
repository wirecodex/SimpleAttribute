<?php

declare(strict_types=1);

namespace SimpleWire\Attribute;

/**
 * Attribute — HTML Attribute-Based Template Syntax for ProcessWire
 *
 * Compiles .attr.phtml files using pw-* attributes and {{ variable }} syntax
 * to optimized PHP with file-modification-time caching.
 *
 * @package SimpleWire
 */
class Attribute
{

    /** @var \ProcessWire\ProcessWire */
    protected $wire;

    protected array $config = [];

    public function __construct(\ProcessWire\ProcessWire $wire, array $config = [])
    {
        $this->wire   = $wire;
        $this->config = array_merge(static::getDefaults(), $config);
        $this->loadDependencies();
    }

    protected function loadDependencies(): void
    {
        if (class_exists('\\Rct567\\DomQuery\\DomQuery')) return;

        $autoload = $this->wire->config->paths->root . 'vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }

        if (!class_exists('\\Rct567\\DomQuery\\DomQuery')) {
            throw new \ProcessWire\WireException(
                'Attribute requires the DomQuery library. Run: $ composer require rct567/dom-query'
            );
        }
    }

    // ========================================
    // Configurable Interface
    // ========================================

    public static function getDefaults(): array
    {
        return [
            'attribute_autoEscape'      => true,
            'attribute_stripAttributes' => true,
            'attribute_debugMode'       => false,
            'attribute_skipFiles'       => "_init.php\n_main.php\n_func.php\nconfig.php",
        ];
    }

    // ========================================
    // Processing
    // ========================================

    /**
     * Check whether a file should be processed by the Attribute compiler.
     */
    public function shouldProcess(string $filename): bool
    {
        if (!file_exists($filename)) {
            return false;
        }

        if (strpos($filename, '/admin/') !== false) {
            return false;
        }

        if (strpos($filename, '.attr.phtml') === false) {
            return false;
        }

        $skipFiles = $this->config['attribute_skipFiles'] ?? '';
        if (!empty($skipFiles)) {
            foreach (explode("\n", $skipFiles) as $skipFile) {
                $skipFile = trim($skipFile);
                if ($skipFile !== '' && strpos($filename, $skipFile) !== false) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Process a file: compile attribute syntax to PHP, cache and return cached path.
     */
    public function processFile(string $filename): string
    {
        $cache = new AttributeCache($this->wire->config);

        if ($cachedFile = $cache->get($filename)) {
            return $cachedFile;
        }

        $content = file_get_contents($filename);

        if ($content === false) {
            return $filename;
        }

        if (!$this->needsProcessing($content)) {
            return $filename;
        }

        $processor = new AttributeProcessor($this->wire, [
            'auto_escape'      => $this->config['attribute_autoEscape'] ?? true,
            'strip_attributes' => $this->config['attribute_stripAttributes'] ?? true,
            'debug'            => $this->config['attribute_debugMode'] ?? false,
        ]);

        $processed  = $processor->process($content, $filename);
        $cachedFile = $cache->save($filename, $processed);

        return $cachedFile;
    }

    /**
     * Quick check whether the content contains any processable syntax.
     */
    protected function needsProcessing(string $content): bool
    {
        return strpos($content, 'pw-') !== false
            || strpos($content, '{{') !== false;
    }

    /**
     * Public entry point for manual processing (used by the global function).
     */
    public function process(string $filename): string
    {
        return $this->processFile($filename);
    }
}
