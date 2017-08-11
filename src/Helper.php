<?php
namespace Zodream\Http;

class Helper {
    /**
     * 多维数组格式化成二维数组
     *
     * @access public
     * @param  $array
     * @param  $prefix
     *
     * @return array
     */
    public static function format($array, $prefix = false) {
        $return = array();
        if (is_array($array) || is_object($array)) {
            if (empty($array)) {
                $return[$prefix] = '';
            } else {
                foreach ($array as $key => $value) {
                    if (is_scalar($value)) {
                        if ($prefix) {
                            $return[$prefix . '[' . $key . ']'] = $value;
                        } else {
                            $return[$key] = $value;
                        }
                    } else {
                        if ($value instanceof \CURLFile) {
                            $return[$key] = $value;
                        } else {
                            $return = array_merge(
                                $return,
                                self::format(
                                    $value,
                                    $prefix ? $prefix . '[' . $key . ']' : $key
                                )
                            );
                        }
                    }
                }
            }
        } elseif ($array === null) {
            $return[$prefix] = $array;
        }
        return $return;
    }
}