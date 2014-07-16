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

    public function getCategories()
    {
        return $this->query('SELECT * FROM `categories`;');
    }

    public function getFacts()
    {
        return $this->query('SELECT * FROM `facts` WHERE end_time NOT NULL;');
    }

    public function getTags()
    {
        return $this->query('SELECT * FROM `tags`;');
    }

    public function getFactsTags()
    {
        return $this->query('SELECT `fact_tags`.* FROM `fact_tags` INNER JOIN (`facts`) ON (`fact_tags`.`fact_id` = `facts`.`id`) WHERE `facts`.`end_time` NOT NULL;');
    }
}