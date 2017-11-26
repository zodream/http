<?php
namespace Zodream\Http;

class Http {

    const XML = 'xml';
    const JSON = 'json';

    /**
     * @param string|Uri $url
     * @param array $parameters
     */
    public function url($url, $parameters = []) {
        if (!$url instanceof Uri) {
            $url = new Uri($url);
        }
        $url->addData($parameters);

    }

    public function maps($map, $parameters = []) {

    }

    public function parameters($parameters) {

    }

    public function encode($func = self::JSON) {

    }

    public function method($method = 'GET') {

    }

    public function cookie($key, $value = null) {

    }

    public function header($key, $value = null) {

    }

    public function get() {

    }

    public function post() {

    }

    public function delete() {

    }

    public function patch() {

    }

    public function put() {

    }

    public function head() {

    }

    public function options() {

    }

    public function search() {

    }

    public function text() {

    }

    public function xml() {

    }

    public function json() {

    }

    public function save() {

    }

    public function show() {

    }

    public function decode($func = null) {

    }

    public function error() {

    }

}