<?php

namespace Neuralpin\Notifyli\Shared;

class LogHelper
{
    public static ?string $path = null;

    public static function log( string $txt ): ?bool{
        if( is_null(self::$path) ) return null;
        if( !is_dir(self::$path) ) return null;

        $file = self::$path . "/log.txt";
        return file_put_contents($file, $txt . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
