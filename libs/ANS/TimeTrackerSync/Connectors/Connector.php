<?php
namespace ANS\TimeTrackerSync\Connectors;

use PDO;

interface Connector
{
    const FACTS_TIME_LIMIT = '-1 month';

    public function setDb($db);
    public function getActivities();
    public function getCategories();
    public function getFacts();
    public function getTags();
    public function getFactsTags();
}
