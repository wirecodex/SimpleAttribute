<?php

declare(strict_types=1);

namespace SimpleWire\Attribute;

use Rct567\DomQuery\DomQuery;
use Exception;

/**
 * AttributeProcessor — Core Compiler
 *
 * Handles the actual compilation of pw-* attributes and {{ }} syntax to PHP.
 *
 * @package SimpleWire
 */
class AttributeProcessor
{
    /** @var \ProcessWire\ProcessWire */
    protected $wire;

    protected array $config;
    protected array $defaults;
    protected string $markup;
    protected ?DomQuery $dom = null;
    protected AttributeParser $parser;
    protected array $codeBlocks = [];
    protected bool $debug;

    public function __construct(\ProcessWire\ProcessWire $wire, array $config = [])
    {
        $this->wire   = $wire;
        $this->config = $config;
        $this->debug  = $config['debug'] ?? false;
        $this->parser = new AttributeParser();
        $this->initializeDefaults();
    }

    protected function initializeDefaults(): void
    {
        $this->defaults = [
            'attributes' => [
                'pw-include',
                'pw-import',
                'pw-repeat-me',   // repeats the entire element
                'pw-repeat',      // repeats only the inner content
                'pw-if',
                'pw-else',
                'pw-switch',
                'pw-case',
                'pw-children',
                'pw-find',
                'pw-get',         // gets and renders a single page
                'pw-cache',
                'pw-handle',
                'pw-with',
            ],
            'tokens' => [
                'pw-php',
                'style',
                'script',
            ],
            'transform' => [
                '<?php' => '<pw-php>',
                '?>'    => '</pw-php>',
                '<?='   => '<pw-php> echo',
                '{{'    => '<pw-echo>',
                '}}'    => '</pw-echo>',
                '{-'    => '<pw-comment>',
                '-}'    => '</pw-comment>',
            ],
            'restore' => [
                '<pw-root>'    => '',
                '</pw-root>'   => '',
                '<pw-wrap>'    => '',
                '</pw-wrap>'   => '',
                '<pw-php>'     => '<?php',
                '</pw-php>'    => '?>',
                '<pw-echo>'    => '<?php echo (',
                '</pw-echo>'   => '); ?>',
                '<pw-comment>' => '<!-- ',
                '</pw-comment>' => ' -->',
            ],
        ];
    }

    // ========================================
    // Main Processing
    // ========================================

    public function process(string $content, string $filename = ''): string
    {
        if ($this->debug) {
            $this->wire->log->save('attribute-debug', "Processing: {$filename}");
        }

        try {
            $this->markup = $this->transformCode($content);
            $this->dom    = $this->createDomQuery();

            $this->processCodeBlocks();
            $this->processAttributes();
            $this->processTemplateVariables();
            $this->processTokenBlocks();

            $output = $this->restorePhp();

            if ($this->debug) {
                $this->wire->log->save('attribute-debug', "Completed: {$filename}");
            }

            return $output;

        } catch (Exception $e) {
            $this->wire->log->error("Attribute processing error: " . $e->getMessage());
            return $content;
        }
    }

    // ========================================
    // Compilation Phases
    // ========================================

    protected function transformCode(string $markup): string
    {
        $markup = html_entity_decode($markup);

        // Convert angle-bracket filter syntax to internal pipe syntax before DOM parsing:
        // {{ expr<filter> }}  →  {{ expr|filter }}
        // {{ text<truncate:100,upper> }}  →  {{ text|truncate:100,upper }}
        $markup = preg_replace(
            '/\{\{\s*([^<{}\n]+?)\s*<([^>]+)>\s*\}\}/',
            '{{ $1|$2 }}',
            $markup
        ) ?? $markup;

        return str_replace(
            array_keys($this->defaults['transform']),
            array_values($this->defaults['transform']),
            $markup
        );
    }

    protected function createDomQuery(): DomQuery
    {
        $wrapped = '<pw-root>' . $this->markup . '</pw-root>';
        libxml_use_internal_errors(true);
        $dom = DomQuery::create($wrapped);
        libxml_clear_errors();
        return $dom;
    }

    protected function processCodeBlocks(): void
    {
        foreach ($this->defaults['tokens'] as $key) {
            $blocks = $this->dom->find($key);
            $index  = 0;
            foreach ($blocks as $element) {
                $this->codeBlocks[$key][$index] = $element->getOuterHtml();
                $element->replaceWith("<{$key} token={$index} />");
                $index++;
            }
        }
    }

    protected function processTokenBlocks(): void
    {
        foreach ($this->codeBlocks as $key => $blocks) {
            $elements = $this->dom->find("{$key}[token]");
            foreach ($elements as $element) {
                $number = $element->getAttribute('token');
                if (isset($blocks[$number])) {
                    $element->replaceWith($blocks[$number]);
                }
            }
        }
    }

    protected function processAttributes(): void
    {
        foreach ($this->defaults['attributes'] as $attribute) {
            $elements = $this->dom->find("[{$attribute}]");
            if ($elements->length === 0) continue;

            // Strip 'pw-' prefix, then convert hyphens to camelCase:
            // 'pw-repeat-me' → 'repeat-me' → 'Repeat Me' → 'RepeatMe'
            $name   = substr($attribute, 3);
            $method = 'process' . str_replace(' ', '', ucwords(str_replace('-', ' ', $name)));

            if (!method_exists($this, $method)) {
                if ($this->debug) {
                    $this->wire->log->save('attribute-debug', "No handler for: {$attribute}");
                }
                continue;
            }

            foreach ($elements as $element) {
                $value = trim($element->attr($attribute));
                $this->$method($element, $value);

                if ($this->config['strip_attributes'] ?? true) {
                    $element->removeAttr($attribute);
                }
            }
        }
    }

    protected function processTemplateVariables(): void
    {
        $variables = $this->dom->find('pw-echo');
        foreach ($variables as $variable) {
            $varContent = trim($variable->text());
            $newContent = $this->parser->parse($varContent, [
                'autoEscape' => $this->config['auto_escape'] ?? true,
            ]);
            $variable->text($newContent);
        }
    }

    protected function restorePhp(): string
    {
        $output      = $this->dom->getOuterHtml();
        $replacements = $this->defaults['restore'];
        $markup      = str_replace(array_keys($replacements), array_values($replacements), $output);
        return html_entity_decode($markup);
    }

    // ========================================
    // Attribute Handlers
    // ========================================

    protected function processIf(\Rct567\DomQuery\DomQuery $element, string $condition): void
    {
        $phpCondition = $this->parser->parseCondition($condition);
        $element->before("<pw-php> if ({$phpCondition}): </pw-php>");

        $next        = $element->next();
        $lastInChain = $element;

        while ($next->length && $next->is('[pw-else]')) {
            $elseValue = trim($next->attr('pw-else'));

            $this->processElse($next, $elseValue, true); // emit elseif/else clause
            $next->removeAttr('pw-else');                // prevent processAttributes from re-finding it

            $lastInChain = $next;

            if ($elseValue === '') {
                break; // bare else is always last in chain
            }

            $next = $next->next();
        }

        $lastInChain->after("<pw-php> endif; </pw-php>");
    }

    protected function processElse(\Rct567\DomQuery\DomQuery $element, string $condition, bool $keep = false): void
    {
        if (!$keep) {
            // Orphan pw-else (no preceding pw-if) — remove the element entirely.
            $element->remove();
            return;
        }

        if ($condition !== '') {
            $phpCondition = $this->parser->parseCondition($condition);
            $element->before("<pw-php> elseif ({$phpCondition}): </pw-php>");
        } else {
            $element->before("<pw-php> else: </pw-php>");
        }
    }

    protected function processRepeat(\Rct567\DomQuery\DomQuery $element, string $expression): void
    {
        $this->processIterator($element, $expression, false);
    }

    protected function processRepeatMe(\Rct567\DomQuery\DomQuery $element, string $expression): void
    {
        $this->processIterator($element, $expression, true);
    }

    protected function processGet(\Rct567\DomQuery\DomQuery $element, string $selector): void
    {
        $this->processScopeVariables($element, '_pg');

        $element->before("<pw-php> \$_pg = \$pages->get('{$selector}'); if (\$_pg->id): </pw-php>");
        $element->after("<pw-php> unset(\$_pg); endif; </pw-php>");
    }

    protected function processInclude(\Rct567\DomQuery\DomQuery $element, string $path): void
    {
        $params = '';
        $file   = $this->resolvePath($path);

        if ($data = $element->attr('pw-data')) {
            $params = ", \$vars = {$data}";
        }

        $element->replaceWith("<pw-php> \$this->files()->include('{$file}'{$params}); </pw-php>");
    }

    protected function processImport(\Rct567\DomQuery\DomQuery $element, string $path): void
    {
        $file = $this->resolvePath($path);

        if (!$this->wire->files->exists($file)) {
            $element->replaceWith("<pw-echo> 'Error: File {$file} does not exist.' </pw-echo>");
            return;
        }

        $fileContent = $this->wire->files->fileGetContents($file);

        if ($fileContent === false) {
            $element->replaceWith("<pw-echo> 'Error: Could not read {$file}.' </pw-echo>");
            return;
        }

        $markup = $this->process($fileContent, $file);
        $element->replaceWith("<pw-wrap>{$markup}</pw-wrap>");
    }

    protected function processChildren(\Rct567\DomQuery\DomQuery $element, string $selector = ''): void
    {
        $source = $selector !== ''
            ? "\$page->children('{$selector}')"
            : '$page->children()';

        $element->prepend("<pw-php> foreach ({$source} as \$child): </pw-php>");
        $element->append("<pw-php> endforeach; </pw-php>");
    }

    protected function processCache(\Rct567\DomQuery\DomQuery $element, string $key): void
    {
        $duration = $element->attr('pw-duration') ?: '3600';
        $cacheKey = "simple_attributes_{$key}";

        // $_sw_mem is a per-request global array that prevents redundant DB lookups
        // when the same pw-cache key is encountered more than once in a single request.
        $element->before("<pw-php> global \$_sw_mem; \$_sw_mem = \$_sw_mem ?? []; \$cache = \$cache ?? \$this->wire('cache'); </pw-php>");
        $element->before("<pw-php> if (!array_key_exists('{$cacheKey}', \$_sw_mem)): \$_sw_mem['{$cacheKey}'] = \$cache->get('{$cacheKey}'); endif; </pw-php>");
        $element->before("<pw-php> if (\$_sw_mem['{$cacheKey}'] === null): ob_start(); </pw-php>");

        $element->after("<pw-php> \$_sw_mem['{$cacheKey}'] = ob_get_clean(); \$cache->save('{$cacheKey}', \$_sw_mem['{$cacheKey}'], {$duration}); endif; </pw-php>");
        $element->after("<pw-php> echo \$_sw_mem['{$cacheKey}']; </pw-php>");
    }

    protected function processHandle(\Rct567\DomQuery\DomQuery $element, string $expression): void
    {
        $pairs = array_map('trim', explode(',', $expression));

        foreach ($pairs as $pair) {
            $eqPos = strpos($pair, '=');
            if ($eqPos === false) continue;

            $key   = trim(substr($pair, 0, $eqPos));
            $value = trim(substr($pair, $eqPos + 1));

            if ($key === '') continue;

            $element->attr("hx-{$key}", $value);
        }
    }

    protected function processWith(\Rct567\DomQuery\DomQuery $element, string $expression): void
    {
        $context = trim($expression, '$');
        $html    = $element->html();

        $html = preg_replace_callback(
            '/\{\{\s*([^}]+)\s*\}\}/s',
            function ($matches) use ($context) {
                $var = trim($matches[1]);
                if (!preg_match('/[.$@]/', $var)) {
                    return '{{ ' . $context . '.' . $var . ' }}';
                }
                return $matches[0];
            },
            $html
        ) ?? $html;

        $element->html($html);
    }

    // ========================================
    // Helper Methods
    // ========================================

    protected function processIterator(\Rct567\DomQuery\DomQuery $element, string $expression, bool $includeElement): void
    {
        $parts       = explode(' ', trim($expression));
        $iterableVar = $parts[0];
        $useKey      = isset($parts[1]) && $parts[1] === 'key';
        $itemVar     = 'itm';

        if ($useKey) {
            $this->processScopeVariables($element);
            $foreach = "<pw-php> foreach (\${$iterableVar} as \$key => \$value):";
        } else {
            $this->processScopeVariables($element, $itemVar);
            $foreach = "<pw-php> foreach (\${$iterableVar} as \${$itemVar}):";
        }

        if ($continue = $element->attr('pw-continue')) {
            $foreach .= " if ({$continue}) continue;";
            $element->removeAttr('pw-continue');
        }

        if ($break = $element->attr('pw-break')) {
            $foreach .= " if ({$break}) break;";
            $element->removeAttr('pw-break');
        }

        $foreach    .= " </pw-php>";
        $endforeach  = "<pw-php> endforeach; </pw-php>";

        if ($includeElement) {
            $element->before($foreach);
            $element->after($endforeach);
        } else {
            $element->prepend($foreach);
            $element->append($endforeach);
        }
    }

    protected function processScopeVariables(\Rct567\DomQuery\DomQuery $element, string $scope = ''): void
    {
        $variables = $element->find('pw-echo');
        foreach ($variables as $variable) {
            $varContent = trim($variable->text());

            if (str_starts_with($varContent, '\\')) {
                $newContent = substr($varContent, 1);
            } elseif ($scope) {
                $newContent = $scope . '.' . $varContent;
            } else {
                $newContent = $varContent;
            }

            $variable->text($newContent);
        }
    }

    protected function resolvePath(string $path): string
    {
        if (!preg_match('/\.(php|phtml)$/', $path)) {
            $basePath = $this->wire->config->paths->templates . $path;

            if (file_exists($basePath . '.attr.phtml')) {
                return $basePath . '.attr.phtml';
            } elseif (file_exists($basePath . '.phtml')) {
                return $basePath . '.phtml';
            } else {
                return $basePath . '.php';
            }
        }

        if (!str_starts_with($path, '/')) {
            $path = $this->wire->config->paths->templates . $path;
        }

        return $path;
    }
}
