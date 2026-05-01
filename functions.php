<?php

declare(strict_types=1);

namespace ProcessWire;

if (!function_exists('ProcessWire\simpleattribute')) {
    /**
     * Return the Attribute singleton instance.
     *
     * @return \SimpleWire\Attribute\Attribute
     */
    function simpleattribute(): \SimpleWire\Attribute\Attribute
    {
        return wire()->simpleattribute;
    }
}

if (!function_exists('ProcessWire\attribute')) {
    /**
     * Process an .attr.phtml file through the Attribute compiler.
     *
     * Returns the path to the processed (cached) PHP file, ready for inclusion.
     *
     * @param string $filename Absolute path to the .attr.phtml file
     * @return string Absolute path to the processed (cached) PHP file
     */
    function attribute(string $filename): string
    {
        return wire()->simpleattribute->process($filename);
    }
}
