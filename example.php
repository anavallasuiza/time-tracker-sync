<?php
require (__DIR__.'/vendor/autoload.php');

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

$Hamster = new ANS\Hamster\Hamster('/home/user/.local/share/hamster-applet/hamster.db');

$Sync->setActivities($Hamster->getAll('activities'));
$Sync->setCategories($Hamster->getAll('categories'));
$Sync->setFacts($Hamster->getAll('facts'));
$Sync->setTags($Hamster->getAll('tags'));
$Sync->setFactsTags($Hamster->getAll('fact_tags'));

echo '<pre>';

var_dump($Sync->summary());

$Sync->sync();

exit;