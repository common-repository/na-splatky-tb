<?php

if (! function_exists('array_sanitize_text_fields')) {

    /**
     * Sanitize a multi-dimensional array
     *
     * @param [type] $array
     *
     * @return void
     */
    function array_sanitize_text_fields($array)
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $value = array_sanitize_text_fields($value);
            } else {
                $value = sanitize_text_field($value);
            }
        }
        
        return $array;
    }
}
