<?php
function autoload ($class) {
    require (realpath(__DIR__).'/'.str_replace('\\', '/', $class).'.php');
}

spl_autoload_register('autoload');
