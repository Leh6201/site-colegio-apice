<?php

namespace App\Services;

class Utils
{
    static function  rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
                        self::rrmdir($dir. DIRECTORY_SEPARATOR .$object);
                    else
                        unlink($dir. DIRECTORY_SEPARATOR .$object);
                }
            }
            rmdir($dir);
        }
    }

    static function convertToAssociativeArray($input) {
        $result = [];

        foreach ($input as $key => $value) {
            $result[$key] = $value[0];
        }

        return $result;
    }
}