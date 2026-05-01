<?php

declare(strict_types=1);

namespace SimpleWire\Attribute;

/**
 * AttributeParser — Variable & Condition Expression Parser
 *
 * Compiles {{ variable }} expressions and pw-if conditions into PHP code.
 * Supports dot notation, method calls, null coalescing, filters, and more.
 *
 * @package SimpleWire
 */
class AttributeParser
{
    protected array $modifiers = [
        'clean'      => 'trim(string)',
        'sentence'   => 'ucfirst(string)',
        'title'      => 'ucwords(string)',
        'wordwrap'   => 'wordwrap(string)',
        'upper'      => 'strtoupper(string)',
        'lower'      => 'strtolower(string)',
        'reverse'    => 'strrev(string)',
        'shuffle'    => 'str_shuffle(string)',
        'number'     => 'number_format(string)',
        'ceil'       => 'ceil(string)',
        'floor'      => 'floor(string)',
        'float'      => 'floatval(string)',
        'integer'    => 'intval(string)',
        'money'      => '(new \NumberFormatter(\'en_US\', \NumberFormatter::CURRENCY))->formatCurrency(floatval(string), \'USD\')',
        'dollar'     => 'sprintf("$%01.2f", floatval(string))',
        'date'       => 'date(\'Y-m-d\', strtotime(string))',
        'html'       => 'htmlspecialchars(string, ENT_QUOTES, \'UTF-8\')',
        'escape'     => 'htmlspecialchars(string, ENT_QUOTES, \'UTF-8\')',
        'slashes'    => 'addslashes(string)',
        'truncate'   => 'sanitizer()->truncate(string)',
        'linebreaks' => 'sanitizer()->linebreaks(string)',
        'json'       => 'json_encode(string, JSON_PRETTY_PRINT)',
        'raw'        => 'string',
    ];

    protected array $variables = [
        'wire'        => true,
        'this'        => true,
        'page'        => true,
        'pages'       => true,
        'modules'     => true,
        'user'        => true,
        'input'       => true,
        'sanitizer'   => true,
        'session'     => true,
        'log'         => true,
        'users'       => true,
        'permissions' => true,
        'roles'       => true,
        'cache'       => true,
        'datetime'    => true,
        'files'       => true,
        'mail'        => true,
        'config'      => true,
        'database'    => true,
        'fields'      => true,
        'templates'   => true,
        'languages'   => true,
        'classLoader' => true,
        'paths'       => true,
        'urls'        => true,
    ];

    protected bool $autoEscape = true;

    /**
     * Parse a {{ expr }} expression into a PHP expression string.
     *
     * Supports:
     * - String literals: {{ 'Hello' }}, {{ "Hello {$name}" }}
     * - Simple variables: {{ name }}
     * - Dot notation: {{ page.title }}, {{ data.key }}
     * - Method calls: {{ page.children("limit=5") }}
     * - Null coalescing: {{ summary ?? "None" }}
     * - Filters: {{ name|upper }}, {{ text|truncate:100,upper }}
     */
    public function parse(string $expr, array $options = []): string
    {
        $expr             = trim($expr);
        $this->autoEscape = $options['autoEscape'] ?? true;

        $parts   = preg_split('/\|/', $expr, 2);
        $expr    = trim($parts[0]);
        $filters = isset($parts[1]) ? trim($parts[1]) : '';

        if (strpos($expr, '??') !== false || strpos($expr, '?:') !== false) {
            $expr = $this->parseOperator($expr);
        } elseif (strpos($expr, '(') !== false) {
            $expr = $this->parseMethod($expr);
        } else {
            $expr = $this->parseVariable($expr);
        }

        return $this->applyFiltersAndEscape($expr, $filters);
    }

    // ========================================
    // Protected Parsing Methods
    // ========================================

    protected function parseVariable(string $expr): string
    {
        $expr = trim($expr);

        if (is_numeric($expr)) {
            return $expr;
        }

        if (preg_match('/^["\'].*["\']$/', $expr)) {
            return $this->parseStringLiteral($expr);
        }

        if (strpos($expr, '.') !== false) {
            [$var, $keys] = explode('.', $expr, 2);

            if (isset($this->variables[$var])) {
                return '$' . $var . '->' . str_replace('.', '->', $keys);
            }

            return '$' . $var . "['" . str_replace('.', "']['", $keys) . "']";
        }

        return '$' . $expr;
    }

    protected function parseStringLiteral(string $str): string
    {
        $quote   = $str[0];
        $content = substr($str, 1, -1);

        $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        if ($quote === "'") {
            return $str;
        }

        $content = preg_replace_callback(
            '/\{([^}]+)\}/',
            function ($matches) {
                $var = $this->parseVariable($matches[1]);
                return '{' . $var . '}';
            },
            $content
        );

        return '"' . $content . '"';
    }

    protected function parseMethod(string $expr): string
    {
        $tokenizedArgs = [];
        $i             = 0;

        $tokenizedExpr = preg_replace_callback(
            '/\((.*?)\)/',
            function ($matches) use (&$tokenizedArgs, &$i) {
                $tokenizedArgs["arg{$i}"] = trim($matches[1]);
                $token                    = "{arg{$i}}";
                $i++;
                return $token;
            },
            $expr
        );

        $arguments = [];
        foreach ($tokenizedArgs as $key => $token) {
            if (empty($token)) {
                $arguments[$key] = '';
                continue;
            }

            $argsParsed = [];
            foreach (explode(',', $token) as $arg) {
                $argsParsed[] = $this->parseVariable(trim($arg));
            }
            $arguments[$key] = implode(', ', $argsParsed);
        }

        $expression = $this->parseVariable($tokenizedExpr);

        return \ProcessWire\wirePopulateStringTags($expression, $arguments);
    }

    protected function parseOperator(string $expr): string
    {
        $symbol = strpos($expr, '??') !== false ? '??' : '?:';
        $parts  = array_map('trim', explode($symbol, $expr));
        $output = '';

        foreach ($parts as $part) {
            $parsedPart = $this->parseVariable($part);
            $output     = $output === '' ? $parsedPart : "({$output} {$symbol} {$parsedPart})";
        }

        return $output;
    }

    protected function applyFiltersAndEscape(string $expr, string $filters): string
    {
        $hasEscape = false;
        $hasRaw    = false;

        foreach (array_map('trim', explode(',', $filters)) as $filter) {
            $filterParts = explode(':', $filter, 2);
            $filterName  = $filterParts[0];
            $args        = $filterParts[1] ?? null;

            if (!isset($this->modifiers[$filterName])) continue;

            if ($filterName === 'escape' || $filterName === 'html') {
                $hasEscape = true;
            }

            if ($filterName === 'raw') {
                $hasRaw = true;
                continue;
            }

            $template = $this->modifiers[$filterName];
            $expr     = str_replace('string', $expr, $template);

            if ($args) {
                $expr = preg_replace('/\)$/', ', ' . $args . ')', $expr) ?? $expr;
            }
        }

        if ($this->autoEscape && !$hasEscape && !$hasRaw) {
            $expr = "htmlspecialchars({$expr}, ENT_QUOTES, 'UTF-8')";
        }

        return $expr;
    }

    // ========================================
    // Condition Parsing
    // ========================================

    /**
     * Parse a pw-if / pw-elseif condition into executable PHP code.
     *
     * Supports: is, isnt, like, diff, and, or, not, in, defined
     * Example: "page.status is 'active' and user.role like 'admin'"
     */
    public function parseCondition(string $condition): string
    {
        $condition   = trim($condition);
        $operatorMap = [
            '/\s+\bis\b\s+/'    => ' == ',
            '/\s+\bisnt\b\s+/'  => ' != ',
            '/\s+\blike\b\s+/'  => ' === ',
            '/\s+\bdiff\b\s+/'  => ' !== ',
            '/\s+\band\b\s+/'   => ' && ',
            '/\s+\bor\b\s+/'    => ' || ',
            '/\bnot\s+/'        => '! ',
        ];

        $condition = preg_replace(array_keys($operatorMap), array_values($operatorMap), $condition) ?? $condition;
        $tokens    = preg_split('/\s+/', $condition, -1, PREG_SPLIT_NO_EMPTY);

        $processedTokens = [];
        $keywords        = ['==', '!=', '===', '!==', '>', '<', '>=', '<=', '&&', '||', '!', 'in', 'true', 'false', 'null'];

        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            if ($token === 'defined' && !empty($processedTokens)) {
                $lastToken         = array_pop($processedTokens);
                $processedTokens[] = "isset({$lastToken})";
                continue;
            }

            $isKeyword = in_array($token, $keywords);
            $isLiteral = preg_match('/^(\'|").*(\'|")$/', $token) || is_numeric($token);

            if ($isKeyword || $isLiteral) {
                $processedTokens[] = $token;
            } else {
                $parsedVariable    = $this->parseVariable($token);
                $safeAccess        = "(isset({$parsedVariable}) ? {$parsedVariable} : null)";
                $processedTokens[] = $safeAccess;
            }
        }

        $finalCondition = implode(' ', $processedTokens);
        $finalCondition = preg_replace(
            '/(\S+)\s+in\s+(\S+)/',
            '(is_array(\2) && in_array(\1, \2))',
            $finalCondition
        ) ?? $finalCondition;

        return '(' . $finalCondition . ')';
    }
}
