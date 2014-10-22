<?php
namespace ANS\TimeTrackerSync\Connectors;

use PDO;

class Hamster implements Connector
{
    private $db;

    public function setDb($db)
    {
        $this->db = new PDO('sqlite:'.$db);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $this;
    }

    private function query($query, $params = array())
    {
        if (empty($params)) {
            return $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
        }

        $query = $this->db->prepare($query);

        $query->execute($params);

        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActivities()
    {
        return $this->query('SELECT * FROM `activities` WHERE deleted IS NULL;');
    }

    public function getFacts()
    {
        return $this->query('SELECT * FROM `facts` WHERE start_time >= :start_time AND end_time NOT NULL;', [
            ':start_time' => date('Y-m-d 00:00:00', strtotime(self::FACTS_TIME_LIMIT))
        ]);
    }

    public function getTags()
    {
        return $this->query('SELECT * FROM `tags`;');
    }

    public function getFactsTags()
    {
        return $this->query('SELECT `fact_tags`.* FROM `fact_tags` INNER JOIN (`facts`) ON (`fact_tags`.`fact_id` = `facts`.`id`) WHERE `facts`.`start_time` >= :start_time AND `facts`.`end_time` NOT NULL;', [
            ':start_time' => date('Y-m-d 00:00:00', strtotime(self::FACTS_TIME_LIMIT))
        ]);
    }
}