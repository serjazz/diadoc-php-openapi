<?php

declare(strict_types=1);

namespace Test\helpers;

/**
 * Минимальная замена DOFileCache для тестов без dev-зависимостей.
 */
class SimpleFileCache
{
    /**
     * @var string
     */
    private $cacheDirectory = '';

    public function changeConfig(array $config)
    {
        if (isset($config['cacheDirectory'])) {
            $this->cacheDirectory = rtrim($config['cacheDirectory'], DIRECTORY_SEPARATOR);
            if (!is_dir($this->cacheDirectory)) {
                mkdir($this->cacheDirectory, 0777, true);
            }
        }
    }

    /**
     * @param string $key
     *
     * @return mixed|false
     */
    public function get($key)
    {
        $path = $this->pathForKey($key);
        if (!is_file($path)) {
            return false;
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return false;
        }
        $data = unserialize($raw);
        if (!is_array($data) || !array_key_exists('exp', $data) || !array_key_exists('val', $data)) {
            return false;
        }
        if ($data['exp'] < time()) {
            @unlink($path);

            return false;
        }

        return $data['val'];
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param int    $expiresAt Unix timestamp
     */
    public function set($key, $value, $expiresAt)
    {
        $path = $this->pathForKey($key);
        $payload = serialize(['exp' => (int) $expiresAt, 'val' => $value]);
        file_put_contents($path, $payload);
    }

    /**
     * @param string $key
     *
     * @return string
     */
    private function pathForKey($key)
    {
        return $this->cacheDirectory . DIRECTORY_SEPARATOR . md5((string) $key) . '.cache';
    }
}
