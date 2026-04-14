<?php

declare(strict_types=1);

namespace Test\helpers;

class Cache
{
    /**
     * @var SimpleFileCache|null
     */
    private static $cache;

    /**
     * @return SimpleFileCache
     */
    public static function getCache()
    {
        if (self::$cache === null) {
            $cache = new SimpleFileCache();
            $cache->changeConfig(
                [
                    'cacheDirectory' => dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR,
                    'gzipCompression' => false,
                ]
            );
            self::$cache = $cache;
        }

        return self::$cache;
    }
}
