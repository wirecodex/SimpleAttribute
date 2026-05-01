<?php

declare(strict_types=1);

namespace ProcessWire;

/** @property \ProcessWire\ProcessWire $wire */
class SimpleAttribute extends WireData implements Module, ConfigurableModule
{
    /** @var \SimpleWire\Attribute\Attribute */
    protected $attribute;

    public static function getModuleInfo(): array
    {
        return [
            'title'    => 'SimpleAttribute',
            'version'  => '0.1.0',
            'summary'  => 'HTML attribute-based template syntax (.attr.phtml) with pw-* directives, {{ }} interpolation, and file-modification caching.',
            'icon'     => 'tags',
            'author'   => 'WireCodex',
            'autoload' => true,
            'singular' => true,
            'requires' => 'ProcessWire>=3.0.200,PHP>=8.1',
        ];
    }

    // ========================================
    // Lifecycle
    // ========================================

    public function init(): void
    {
        spl_autoload_register(function (string $class): void {
            $prefix = 'SimpleWire\\Attribute\\';
            if (!str_starts_with($class, $prefix)) return;
            $file = __DIR__ . '/classes/' . substr($class, strlen($prefix)) . '.php';
            if (file_exists($file)) require_once $file;
        });

        $config = array_merge(
            \SimpleWire\Attribute\Attribute::getDefaults(),
            (array) $this->wire('modules')->getConfig($this)
        );

        $this->attribute = new \SimpleWire\Attribute\Attribute($this->wire, $config);

        $this->wire('simpleattribute', $this->attribute);

        require_once __DIR__ . '/functions.php';
    }

    public function ready(): void
    {
        $this->wire->addHookBefore('WireFileTools::render', function (\ProcessWire\HookEvent $event) {
            $filename = $event->arguments(0);
            if ($this->attribute->shouldProcess($filename)) {
                $event->arguments(0, $this->attribute->processFile($filename));
            }
        });

        $this->wire->addHookBefore('Modules::saveConfig', function (\ProcessWire\HookEvent $event) {
            $module = $event->arguments(0);
            $name   = is_object($module) ? $module->className() : (string) $module;
            if ($name !== 'SimpleAttribute') return;
            $data = $event->arguments(1);
            if (!empty($data['attribute_clearCache'])) {
                $cache = new \SimpleWire\Attribute\AttributeCache($this->wire->config);
                $cache->clearAll();
                $data['attribute_clearCache'] = 0;
                $event->arguments(1, $data);
            }
        });
    }

    // ========================================
    // Config UI
    // ========================================

    public static function getModuleConfigInputfields(array $data): InputfieldWrapper
    {
        $modules  = wire()->modules;
        $defaults = \SimpleWire\Attribute\Attribute::getDefaults();
        $data     = array_merge($defaults, $data);

        /** @var InputfieldWrapper $wrapper */
        $wrapper = $modules->get('InputfieldWrapper');

        // ---- Compilation Settings ----

        /** @var \ProcessWire\InputfieldFieldset $fieldset */
        $fieldset        = $modules->get('InputfieldFieldset');
        $fieldset->label = 'Compilation Settings';
        $fieldset->icon  = 'tags';

        /** @var \ProcessWire\InputfieldCheckbox $field */
        $field              = $modules->get('InputfieldCheckbox');
        $field->name        = 'attribute_autoEscape';
        $field->label       = 'Auto-escape Variables';
        $field->description = 'Automatically escape variables for HTML output (recommended)';
        $field->columnWidth = 33;
        $field->checked     = (bool)$data['attribute_autoEscape'];
        $fieldset->add($field);

        /** @var \ProcessWire\InputfieldCheckbox $field */
        $field              = $modules->get('InputfieldCheckbox');
        $field->name        = 'attribute_stripAttributes';
        $field->label       = 'Strip Markup Attributes';
        $field->description = 'Remove pw-* attributes from final output';
        $field->columnWidth = 33;
        $field->checked     = (bool)$data['attribute_stripAttributes'];
        $fieldset->add($field);

        /** @var \ProcessWire\InputfieldCheckbox $field */
        $field              = $modules->get('InputfieldCheckbox');
        $field->name        = 'attribute_debugMode';
        $field->label       = 'Debug Mode';
        $field->description = 'Enable compilation debugging and logging';
        $field->columnWidth = 34;
        $field->checked     = (bool)$data['attribute_debugMode'];
        $fieldset->add($field);

        /** @var \ProcessWire\InputfieldTextarea $field */
        $field              = $modules->get('InputfieldTextarea');
        $field->name        = 'attribute_skipFiles';
        $field->label       = 'Skip Files';
        $field->description = 'Files to skip during compilation (one per line, partial match)';
        $field->value       = $data['attribute_skipFiles'];
        $field->rows        = 4;
        $fieldset->add($field);

        /** @var \ProcessWire\InputfieldMarkup $field */
        $field        = $modules->get('InputfieldMarkup');
        $field->name  = 'attribute_info';
        $field->label = 'How It Works';
        $field->icon  = 'info-circle';
        $field->value = '<div class="uk-alert uk-alert-primary">
            <p><strong>File Extension:</strong> Only <code>.attr.phtml</code> files are processed.</p>
            <ul>
                <li>Example: <code>detail.attr.phtml</code>, <code>product-card.attr.phtml</code></li>
                <li>Regular <code>.php</code> and <code>.phtml</code> files are ignored</li>
                <li>Processed files are cached in <code>/site/assets/cache/SimpleWire/Attribute/</code></li>
                <li>Cache is invalidated automatically on file modification</li>
            </ul>
        </div>';
        $fieldset->add($field);

        $wrapper->add($fieldset);

        // ---- Cache Management ----

        /** @var \ProcessWire\InputfieldFieldset $cacheFieldset */
        $cacheFieldset        = $modules->get('InputfieldFieldset');
        $cacheFieldset->label = 'Cache Management';
        $cacheFieldset->icon  = 'database';

        $cachePath = wire()->config->paths->cache . 'SimpleWire/Attribute/';
        $fileCount = 0;
        $totalSize = 0;
        if (is_dir($cachePath)) {
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($cachePath, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iter as $f) {
                if ($f->isFile() && $f->getExtension() === 'php') {
                    $fileCount++;
                    $totalSize += $f->getSize();
                }
            }
        }
        $units = ['B', 'KB', 'MB'];
        $pow   = $totalSize > 0 ? min((int) floor(log($totalSize) / log(1024)), 2) : 0;
        $size  = $totalSize > 0 ? round($totalSize / pow(1024, $pow), 1) . ' ' . $units[$pow] : '0 B';

        /** @var \ProcessWire\InputfieldMarkup $field */
        $field              = $modules->get('InputfieldMarkup');
        $field->label       = 'Compiled Templates';
        $field->value       = "<p>Cached files: <strong>{$fileCount}</strong> | Size: <strong>{$size}</strong></p>
            <p>Cache location: <code>site/assets/cache/SimpleWire/Attribute/</code></p>";
        $field->columnWidth = 70;
        $cacheFieldset->add($field);

        /** @var \ProcessWire\InputfieldCheckbox $field */
        $field              = $modules->get('InputfieldCheckbox');
        $field->name        = 'attribute_clearCache';
        $field->label       = 'Clear Cache on Save';
        $field->description = 'Delete all compiled templates. They will be recompiled on next use.';
        $field->value       = 0;
        $field->columnWidth = 30;
        $cacheFieldset->add($field);

        $wrapper->add($cacheFieldset);

        return $wrapper;
    }
}
