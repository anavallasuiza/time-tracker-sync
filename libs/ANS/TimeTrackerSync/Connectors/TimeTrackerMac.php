<?php
namespace ANS\TimeTrackerSync\Connectors;

class TimeTrackerMac implements Connector
{
    private $db;
    private $data = [];

    public function setDb($db)
    {
        $this->db = $this->parseFile($db);

        $this->loadData();

        return $this;
    }

    private function parseFile($db)
    {
        if (!is_file($db)) {
            throw new \Exception(sprintf(_('File "%s" not exists'), $db));
        }

        $db = file($db, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        array_shift($db);

        array_walk($db, function (&$value) {
            $value = array_map('trim', str_getcsv($value, ';', '"'));
        });

        return $db;
    }

    private function loadData()
    {
        $this->data = [
            'activities' => [],
            'tags' => [],
            'facts' => [],
            'facts_tags' => []
        ];

        foreach ($this->db as $row) {
            if (empty($row[4])) {
                continue;
            }

            $activity_id = $this->getId($row[0]);
            $tag_id = $this->getId($row[1]);
            $fact_id = strtotime($row[3]);

            $this->data['activities'][$activity_id] = [
                'id' => $activity_id,
                'name' => $row[0]
            ];

            $this->data['tags'][$tag_id] = [
                'id' => $tag_id,
                'name' => $row[1]
            ];

            $this->data['facts'][$fact_id] = [
                'id' => $fact_id,
                'start_time' => $row[3],
                'end_time' => $row[4],
                'description' => $row[6],
                'activity_id' => $activity_id
            ];

            $this->data['facts_tags'][$fact_id.'|'.$tag_id] = [
                'fact_id' => $fact_id,
                'tag_id' => $tag_id
            ];
        }

        foreach ($this->data as &$value) {
            $value = array_values($value);
        }

        unset($value);

        return $this;
    }

    private function getId($value)
    {
        return substr(preg_replace('/[^0-9]/', '', md5($value)), 2, 8);
    }

    public function getActivities()
    {
        return $this->data['activities'];
    }

    public function getCategories()
    {
        return [];
    }

    public function getFacts()
    {
        return $this->data['facts'];
    }

    public function getTags()
    {
        return $this->data['tags'];
    }

    public function getFactsTags()
    {
        return $this->data['facts_tags'];
    }
}