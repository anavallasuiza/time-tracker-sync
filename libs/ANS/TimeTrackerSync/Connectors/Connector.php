<?php
namespace ANS\TimeTrackerSync\Connectors;

use PDO;

interface Connector
{
    public function setDb($db);
    public function getActivities();
    public function getCategories();
    public function getFacts();
    public function getTags();
    public function getFactsTags();
}
