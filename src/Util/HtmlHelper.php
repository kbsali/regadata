<?php

namespace Util;

class HtmlHelper
{
    /**
     * -> http://www.phpro.org/tutorials/Dropdown-Select-With-PHP-and-MySQL.html
     * create a dropdown select.
     *
     * @param string $name
     * @param array  $options
     * @param string $selected  (optional)
     * @param string $nullLabel (optional)
     *
     * @return string
     *
     * $name = 'my_dropdown';
     * $options = array( 'dingo', 'wombat', 'kangaroo' );
     * $selected = 1;
     * $nullLabel = 'Choose your option';
     * echo htmlHelper::dropdown( $name, $options, $selected );
     */
    public function dropdown($name, array $options, $selected = null, $nullLabel = null)
    {
        if (!is_null($nullLabel)) {
            $options = [0 => $nullLabel] + $options;
        }
        $ret = '<select name="' . $name . '" id="' . $name . '">' . PHP_EOL;
        foreach ($options as $key => $option) {
            $select = $selected === $key ? ' selected="selected"' : '';
            $ret .= '<option value="' . $key . '"' . $select . '>' . $option . '</option>' . PHP_EOL;
        }
        $ret .= '</select>' . PHP_EOL;

        return $ret;
    }
    /**
     * -> http://www.phpro.org/tutorials/Dropdown-Select-With-PHP-and-MySQL.html
     * create a multi select dropdown menu.
     *
     * @param string $name
     * @param array  $options
     * @param array  $selected (default null)
     * @param int size (optional)
     *
     * @return string
     *
     * $name = 'multi_dropdown';
     * $options = array( 'dingo', 'wombat', 'kangaroo', 'steve irwin', 'wallaby', 'kookaburra' );
     * $selected = array( 'dingo', 'kangaroo', 'kookaburra' );
     * echo htmlHelper::multiDropdown( $name, $options, $selected );
     */
    public function multiDropdown($name, array $options, $selected = null, $size = 4)
    {
        $ret = '<select name="' . $name . '[]" id="' . $name . '" size="' . $size . '" multiple="multiple">' . PHP_EOL;
        foreach ($options as $key => $option) {
            $select = in_array($key, $selected, true) ? ' selected="selected"' : '';
            $ret .= '<option value="' . $key . '"' . $select . '>' . $option . '</option>' . PHP_EOL;
        }
        $ret .= '</select>' . PHP_EOL;

        return $ret;
    }
}
