<?php

if (!defined('ABSPATH')) {
    exit('No direct script access allowed');
}

if (!class_exists('dynamic_acf_blocks_key_helper')) {

    class dynamic_acf_blocks_key_helper
    {

        private $id;

        public function __construct($id)
        {
            $this->id = $id;
        }

        public function key()
        {
            $key = [];

            $key[] = $this->id;

            $args = func_get_args();
            foreach ($args as $index => $arg) {
                $key[] = $arg;
                unset($args[$index]);
            }

            return implode('_', $key);
        }

        function name()
        {
            $args = func_get_args();
            return call_user_func_array([$this, 'key'], $args);
        }

        function prefixed($prefix, $args)
        {
            return $prefix . '_' . call_user_func_array([$this, "key"], $args);
        }

        function group()
        {
            $args = func_get_args();
            return call_user_func_array([$this, 'prefixed'], ['group', $args]);
        }

        function field()
        {
            $args = func_get_args();
            return call_user_func_array([$this, 'prefixed'], ['field', $args]);
        }

        public function key_from_title()
        {
            $args = func_get_args();
            $key = call_user_func_array([$this, "key"], $args);
            return $this->title_to_key($key);
        }

        public function title_to_key($title)
        {
            return str_replace('-', '_', sanitize_title($title));
        }

        /**
         * For example:
         *
         * `$this->foo('bar')` produces `foo_{id}_bar`
         *
         * @param string $prefix
         * @param array $args
         * @return string
         */
        public function __call($prefix, $args)
        {
            return call_user_func_array([$this, 'prefixed'], [$prefix, $args]);
        }

    }


}
