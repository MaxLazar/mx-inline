<?php

namespace MX\Inline;

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * MX Inline Plugin class.
 *
 * @author        Max Lazar <max@eecms.dev>
 *
 * @see           http://eecms.dev/
 *
 * @license       http://opensource.org/licenses/MIT
 */
class Inline
{
    // --------------------------------------------------------------------
    // PROPERTIES
    // --------------------------------------------------------------------

    /**
     * [$_cache_path description].
     *
     * @var bool
     */
    private $_cache_path = false;

    /**
     * Package name.
     *
     * @var string
     */
    protected $package;

    /**
     * Plugin return data.
     *
     * @var string
     */
    public $return_data;

    /**
     * Plugin return data.
     *
     * @var string
     */
    public $settings = array();

    /**
     * Site id shortcut.
     *
     * @var int
     */
    protected $site_id;


    /**
     * $mime_type shortcut.
     *
     * @var int
     */
    protected $mime_type;

    /**
     * [$cache_dir description]
     *
     * @var string
     */
    private $cache_dir = 'inline/remote';

    /**
     * [$base64_encode description]
     *
     * @var boolean
     */
    private $base64_encode = false;

    /**
     * [$type description]
     *
     * @var boolean
     */
    private $type = false;

    /**
     * [$prefix description]
     *
     * @var boolean
     */
    private $prefix = false;

    /**
     * [$attr description]
     *
     * @var array
     */
    public $attr = array();

    /**
     * [$attrStr description]
     *
     * @var array
     */
    public $attrStr = "";

    /**
     * [$started description]
     *
     * @var boolean
     */
    private static $started = false;

    /**
     * @var int
     */
    public $cache_lifetime = 1440;

    /**
     * @var bool
     */
    public $cache = true;

    // --------------------------------------------------------------------
    // METHODS
    // --------------------------------------------------------------------

    /**
     * Constructor.
     *
     * @return string
     */
    public function __construct()
    {
        if (!self::$started) {
            self::$started = true;
            if (ee()->extensions->active_hook('mx_inline_start')) {
                ee()->extensions->call('mx_inline_start');
            }
        }

        $data                 = false;
        $this->base64_encode  = ee()->TMPL->fetch_param('base64_encode', false);
        $file                 = ee()->TMPL->fetch_param('file', false);
        $this->wrap           = ee()->TMPL->fetch_param('wrap', true); // yes
        $this->type           = ee()->TMPL->fetch_param('type', false);  // type :  image, png, jpeg, js, css, svg
        $remote               = ee()->TMPL->fetch_param('remote', false);
        $this->prefix         = ee()->TMPL->fetch_param('prefix', 'inline');
        $this->cache_lifetime = ee()->TMPL->fetch_param('refresh', $this->cache_lifetime);
        $this->cache          = ee()->TMPL->fetch_param('cache', true);

        if (!$file) {
            return;
        }

        $args = ee()->TMPL->tagparams;

        foreach ($args as $key => $value) {
            if (substr($key, 0, 5) == 'attr:') {
                $this->attr[substr($key, 5)] = $value;

                $this->attrStr .= substr($key, 5) . '="' . $value . '" ';
            }
        }

        $mime = new \ExpressionEngine\Library\Mime\MimeType();

        if (file_exists($file)) {
            $data       = file_get_contents($file);
            $this->type = $mime->ofFile($file);
        }

        if ($remote) {
            $parts     = explode('.', $file);
            $extension = end($parts);

            // check protocol
            if (strpos($file, '//') === 0) {
                $protocol = ee('Request')->isEncrypted() ? 'https:' : 'http:';
                $file     = $protocol . $file;
            }

            $cache_path = str_replace('\\', '/', PATH_CACHE) . '/' . $this->cache_dir;
            $filepath   = $cache_path . "/" . md5($file) . '.' . $extension;

            if (!($data = self::_readCache(md5($file) . '.' . $extension)) || $this->cache != 'yes') {
                $data = @file_get_contents($file);
                self::_createCacheFile($data, md5($file) . '.' . $extension);
            }

            $this->type = $mime->ofFile($filepath);
        }

        if (!$data) {
            return $this->return_data = "Could not read the file.";
        }

        if ($this->wrap == 'yes') {
            $data = self::wrap_engine($data);
        }

        if ($this->base64_encode && $data) {
            $data = base64_encode($data);
        }

        return $this->return_data = $data;
    }

    /**
     * [pair description]
     *
     * @param [type]  $data [description]
     * @param [type]  $type [description]
     * @return [type]       [description]
     */
    public function pair($data, $type)
    {
        if ($this->base64_encode && $data) {
            $data = base64_encode($data);
        }
    }

    /**
     * [base64_data_encode description]
     *
     * @param [type]  $data [description]
     * @return [type]       [description]
     */
    private function base64_data_encode($data)
    {
    }

    /**
     * _createCacheFile function.
     *
     * @access private
     * @param mixed $data
     * @param mixed $key
     * @return void
     */
    private function _createCacheFile($data, $key)
    {
        $cache_path = str_replace('\\', '/', PATH_CACHE) . '' . $this->cache_dir;
        $filepath   = $cache_path . "/" . $key;

        if (!is_dir($cache_path)) {
            mkdir($cache_path . "", 0777, true);
        }
        if (!is_really_writable($cache_path)) {
            return;
        }
        if (!$fp = fopen($filepath, FOPEN_WRITE_CREATE_DESTRUCTIVE)) {
            self::logDebugMessage('error', "Unable to write cache file: " . $filepath);
            return;
        }

        flock($fp, LOCK_EX);
        fwrite($fp, $data);
        flock($fp, LOCK_UN);
        fclose($fp);
        chmod($filepath, DIR_WRITE_MODE);

     //   self::logDebugMessage('debug', "Cache file written: " . $filepath);
    }

    /**
     * _readCache function.
     *
     * @access private
     * @param mixed $key
     * @return void
     */
    private function _readCache($key)
    {
        $cache      = false;
        $cache_path = str_replace('\\', '/', PATH_CACHE) . '/' . $this->cache_dir;
        $filepath   = $cache_path . "/" . $key;

        if (!file_exists($filepath)) {
            return false;
        }
        if (!$fp = fopen($filepath, FOPEN_READ)) {
            @unlink($filepath);
            // self::logDebugMessage('debug', "Error reading cache file. File deleted");
            return false;
        }
        if (!filesize($filepath)) {
            @unlink($filepath);
            //  self::logDebugMessage('debug', "Error getting cache file size. File deleted");
            return false;
        }

        $cache_timeout = $this->cache_lifetime * 60;

        if ((filemtime($filepath) + $cache_timeout) < time()) {
            @unlink($filepath);
            //  self::logDebugMessage('debug', "Cache file has expired. File deleted");
            return false;
        }

        flock($fp, LOCK_SH);
        $cache = fread($fp, filesize($filepath));
        flock($fp, LOCK_UN);
        fclose($fp);

        return $cache;
    }

    /**
     * [wrap_engine description]
     *
     * @param [type]  $data [description]
     * @param [type]  $type [description]
     * @return [type]       [description]
     */
    private function wrap_engine($data)
    {
        switch ($this->type) {
            case 'application/javascript':
                $data = '<javascript ' . $this->attrStr . '>' . $data . '</javascript>';
                break;
            case 'application/json':
                //$data = $data;
                break;
            case 'text/css':
                $data = '<style ' . $this->attrStr . '>' . $data . '</style>';
                break;
            case 'image/svg':
                $data = str_replace('<svg', sprintf('<svg %s', $this->attrStr), $data);
                break;

            case 'image/webp':
                $data = '<img src="data:image/webp;base64,' . base64_encode($data) . '" ' . $this->attrStr . ' />';
                break;
            case 'image/png':
                $data = '<img src="data:image/png;base64,' . base64_encode($data) . '" ' . $this->attrStr . '/>';
                break;
            case 'image/jpeg':
                $data = '<img src="data:image/jpeg;base64,' . base64_encode($data) . '" ' . $this->attrStr . '/>';
                break;
        }

        return $data;
    }

    /**
     * Uses the file's mime-type to determine if the file is an image or not.
     *
     * @return bool TRUE if the file is an image, FALSE otherwise
     */
    public function isImage()
    {
        return strpos($this->mime_type, 'image/') === 0;
    }


    /**
     * [security_check description]
     *
     * @param [type]  $data [description]
     * @return [type]       [description]
     */
    private function security_check($data)
    {
        //return (strpos($this->mime_type, 'image/') === 0);
    }


    /**
     * Simple method to log a debug message to the EE Debug console.
     *
     * @param string $method
     * @param string $message
     */
    protected function logDebugMessage($method = '', $message = '')
    {
        ee()->TMPL->log_item('&nbsp;&nbsp;***&nbsp;&nbsp;' . $this->package . " - $method debug: " . $message);
    }

    // ----------------------------------------
    //  Plugin Usage
    // ----------------------------------------

    // This function describes how the plugin is used.
    //  Make sure and use output buffering

    public static function usage()
    {
        // for performance only load README if inside control panel
        return REQ === 'CP' ? file_get_contents(dirname(__FILE__) . '/README.md') : null;
    }
}
// END CLASS
