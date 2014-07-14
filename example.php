<?php
# Load external database API (hamster time tracker)
require (__DIR__.'/../hamster/libs/ANS/Hamster/Hamster.php');
require (__DIR__.'/libs/ANS/TimeTrackerSync/TimeTrackerSync.php');
require (__DIR__.'/libs/curl.php');

$Sync = new \ANS\TimeTrackerSync\TimeTrackerSync();

$Hamster = new \ANS\Hamster\Hamster('/home/user/.local/share/hamster-applet/hamster.db');

$Curl = new Curl([
    'base' => 'https://time.domain.com/api',
    'cookie' => 'cookie.txt'
]);

$Curl->setAuth([
    'email' => 'user@domain.com',
    'hash' => 'a38142342423b7af21132c80677d739ec4b28ea'
]);

$Sync->setCurl($Curl);

$Sync->setActivities($Hamster->getAll('activities'));
$Sync->setCategories($Hamster->getAll('categories'));
$Sync->setFacts($Hamster->getAll('facts'));
$Sync->setTags($Hamster->getAll('tags'));

echo '<pre>';

var_dump($Sync->summary());

$Sync->sync();

exit;