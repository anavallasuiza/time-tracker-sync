<?php
require (__DIR__.'/libs/autoload.php');

$Sync = new ANS\TimeTrackerSync\TimeTrackerSync();

$Curl = new ANS\TimeTrackerSync\Curl([
    'base' => 'https://time.domain.com/api',
    'cookie' => 'cookie.txt'
]);

$Curl->setAuth([
    'email' => 'user@domain.com',
    'hash' => 'a38142342423b7af21132c80677d739ec4b28ea'
]);

$Sync->setCurl($Curl);

$Db = $Sync->getConnector('Hamster');

$Db->setDb('/home/user/.local/share/hamster-applet/hamster.db');

$Sync->setConnector($Db);

echo '<pre>';

var_dump($Sync->summary());

$Sync->sync();

exit;