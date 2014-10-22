<?php
namespace ANS\TimeTrackerSync;

use Exception;

class TimeTrackerSync
{
    const FACTS_TIME_LIMIT = '-1 month';

    private $curl;

    private $hostname = '';

    private $activities = [];
    private $facts = [];
    private $tags = [];
    private $log = [];

    public function __construct()
    {
        $this->hostname = gethostname();
    }

    public function getConnector($connector)
    {
        if (!class_exists($class = '\\ANS\\TimeTrackerSync\\Connectors\\'.$connector)) {
            throw new \Exception(sprintf(_('Connector "%s" not exists'), $connector));
        }

        return new $class;
    }

    public function setHostName($hostname)
    {
        $this->hostname = $hostname;
    }

    public function setCurl(Curl $curl)
    {
        $this->curl = $curl;
    }

    public function setConnector($connector)
    {
        $this->log = [];

        $this->setActivities($connector->getActivities());
        $this->setFacts($connector->getFacts());
        $this->setTags($connector->getTags());
        $this->setFactsTags($connector->getFactsTags());

        return $this;
    }

    public function setActivities($local)
    {
        $this->activities = ['add' => [], 'del' => [], 'assign' => []];

        try {
            $remote = $this->curl->get('/activities');
        } catch (Exception $e) {
            return $this->setLog(sprintf('Activities could not be loaded: %s', $e->getMessage()), 'danger');
        }

        $this->activities = $this->compare($local, $remote->data, 'name', 'name');

        return $this;
    }

    public function setFacts($local)
    {
        $this->facts = ['add' => [], 'del' => [], 'assign' => []];

        try {
            $remote = $this->curl->get('/facts', [
                'hostname' => $this->hostname
            ]);
        } catch (Exception $e) {
            return $this->setLog(sprintf('Facts could not be loaded: %s', $e->getMessage()), 'danger');
        }

        $this->facts = $this->compare($local, $remote->data, 'id', 'remote_id');

        return $this;
    }

    public function setTags($local)
    {
        $this->tags = ['add' => [], 'del' => [], 'assign' => []];

        try {
            $remote = $this->curl->get('/tags');
        } catch (Exception $e) {
            return $this->setLog(sprintf('Tags could not be loaded: %s', $e->getMessage()), 'danger');
        }

        $this->tags = $this->compare($local, $remote->data, 'name', 'name');

        return $this;
    }

    public function setFactsTags($local)
    {
        $this->facts_tags = $local;
    }

    private static function toUTF($value)
    {
        if (is_object($value) || is_array($value)) {
            if (is_object($value)) {
                $value = (array)$value;
            }

            return array_map(array(__CLASS__, 'toUTF'), $value);
        }

        if ((mb_detect_encoding($value) === 'UTF-8') && mb_check_encoding($value, 'UTF-8')) {
            return $value;
        } else {
            return utf8_encode($value);
        }
    }

    private static function toArray($data)
    {
        return json_decode(json_encode(self::toUTF($data)), true);
    }

    private function compare($local, $remote, $local_key, $remote_key)
    {
        $local = self::toArray($local);
        $remote = self::toArray($remote);

        $local_keys = array_column($local, $local_key);
        $remote_keys = array_column($remote, $remote_key);

        $add = $del = $assign = [];

        foreach ($local as $row) {
            if (($key = array_search($row[$local_key], $remote_keys)) !== false) {
                $assign[$row['id']] = $remote[$key]['id'];
            } else {
                $add[] = $row;
            }
        }

        foreach ($remote as $row) {
            if (($key = array_search($row[$remote_key], $local_keys)) === false) {
                $del[] = $row;
            }
        }

        return [
            'assign' => $assign,
            'add' => $add,
            'del' => $del
        ];
    }

    public function summary()
    {
        return [
            'activities' => $this->activities,
            'facts' => $this->facts,
            'tags' => $this->tags,
            'facts_tags' => $this->facts_tags
        ];
    }

    private function setLog($message, $status) {
        $this->log[] = [
            'message' => $message,
            'status' => $status
        ];

        return $this;
    }

    public function getLog() {
        return $this->log;
    }

    public function sync()
    {
        $this->syncTags();
        $this->syncActivities();
        $this->syncFacts();

        $this->syncFactsTags();

        return $this;
    }

    public function syncTags()
    {
        if (empty($this->tags) || empty($this->tags['add'])) {
            return $this;
        }

        $assign = &$this->tags['assign'];

        foreach ($this->tags['add'] as $tag) {
            if (empty($tag['name'])) {
                $this->setLog('Tag can not be added because has an emtpy name', 'danger');
                continue;
            }

            try {
                $response = $this->curl->post('/tags', [
                    'name' => $tag['name']
                ]);
            } catch (Exception $e) {
                $this->setLog(sprintf('Tag %s could not be added: %s', $tag['name'], $e->getMessage()), 'danger');
                continue;
            }

            if (empty($response->id)) {
                $this->setLog(sprintf('Tag %s could not be added. Empty response', $tag['name']), 'danger');
                continue;
            }

            $this->setLog(sprintf('Tag %s added successfully', $tag['name']), 'success');

            $assign[$tag['id']] = $response->id;
        }

        return $this;
    }

    public function syncActivities()
    {
        if (empty($this->activities) || empty($this->activities['add'])) {
            return $this;
        }

        $assign = &$this->activities['assign'];

        foreach ($this->activities['add'] as $activity) {
            if (empty($activity['name'])) {
                $this->setLog('Activity can not be added because has an emtpy name', 'danger');
                continue;
            }

            try {
                $response = $this->curl->post('/activities', [
                    'name' => $activity['name']
                ]);
            } catch (Exception $e) {
                $this->setLog(sprintf('Activity %s could not be added: %s', $activity['name'], $e->getMessage()), 'danger');
                continue;
            }

            if (empty($response->id)) {
                $this->setLog(sprintf('Activity %s could not be added. Empty response', $activity['name']), 'danger');
                continue;
            }

            $this->setLog(sprintf('Activity %s added successfully', $activity['name']), 'success');

            $assign[$activity['id']] = $response->id;
        }

        return $this;
    }

    public function syncFacts()
    {
        if (empty($this->facts)) {
            return $this;
        }

        $activities = $this->activities['assign'];
        $assign = &$this->facts['assign'];

        if ($this->facts['add']) {
            foreach ($this->facts['add'] as $fact) {
                $name = $fact['start_time'].' - '.$fact['end_time'];

                if (empty($fact['end_time'])) {
                    $this->setLog(sprintf('Fact %s can not be added because is not finished', $name), 'warning');
                    continue;
                }

                if (empty($fact['activity_id'])) {
                    $this->setLog(sprintf('Fact %s can not be added because has not activity', $name), 'danger');
                    continue;
                }

                if (empty($activities[$fact['activity_id']])) {
                    $this->setLog(sprintf('Fact %s can not be added because activity not exists', $name), 'danger');
                    continue;
                }

                $total = (int)round(((new \Datetime($fact['end_time']))->getTimestamp() - (new \Datetime($fact['start_time']))->getTimestamp()) / 60);

                try {
                    $response = $this->curl->post('/facts', [
                        'start_time' => $fact['start_time'],
                        'end_time' => $fact['end_time'],
                        'time' => sprintf('%01d:%02d', floor($total / 60), ($total % 60)),
                        'description' => $fact['description'],
                        'hostname' => $this->hostname,
                        'remote_id' => $fact['id'],
                        'id_activities' => $activities[$fact['activity_id']],
                    ]);
                } catch (Exception $e) {
                    $this->setLog(sprintf('Fact %s could not be added: %s', $name, $e->getMessage()), 'danger');
                    continue;
                }

                if (empty($response->id)) {
                    $this->setLog(sprintf('Fact %s could not be added. Empty response', $name), 'warning');
                    continue;
                }

                $this->setLog(sprintf('Fact %s added successfully', $name), 'success');

                $assign[$fact['id']] = $response->id;
            }
        }

        if ($this->facts['del']) {
            foreach ($this->facts['del'] as $fact) {
                $name = $fact['start_time']['date'].' - '.$fact['end_time']['date'];

                try {
                    $response = $this->curl->delete('/facts', [
                        'remote_id' => $fact['remote_id'],
                        'hostname' => $this->hostname,
                    ]);
                } catch (Exception $e) {
                    $this->setLog(sprintf('Fact %s could not be deleted: %s', $name, $e->getMessage()), 'danger');
                }
            }
        }

        return $this;
    }

    public function syncFactsTags()
    {
        if (empty($this->facts_tags)) {
            return $this;
        }

        $facts = $this->facts['assign'];
        $tags = $this->tags['assign'];

        foreach ($this->facts_tags as &$value) {
            if (empty($value['fact_id'])
            || empty($value['tag_id'])
            || empty($facts[$value['fact_id']])
            || empty($tags[$value['tag_id']])) {
                $value = null;
                continue;
            }

            $value['id'] = $facts[$value['fact_id']].'|'.$tags[$value['tag_id']];
        }

        $this->facts_tags = array_filter($this->facts_tags);

        try {
            $remote = $this->curl->get('/facts-tags', [
                'hostname' => $this->hostname
            ])->data;
        } catch (Exception $e) {
            $this->setLog(sprintf('Facts and Tags relation could not be loaded', $e->getMessage()), 'danger');
            return $this;
        }

        foreach ($remote as &$value) {
            $value->id = $value->id_facts.'|'.$value->id_tags;
        }

        unset($value);

        $this->facts_tags = $this->compare($this->facts_tags, $remote, 'id', 'id');

        if (empty($this->facts_tags['add'])) {
            return $this;
        }

        $assign = &$this->facts_tags['assign'];

        foreach ($this->facts_tags['add'] as $fact_tag) {
            if (empty($fact_tag['fact_id'])
            || empty($fact_tag['tag_id'])
            || empty($facts[$fact_tag['fact_id']])
            || empty($tags[$fact_tag['tag_id']])) {
                continue;
            }

            try{
                $response = $this->curl->post('/facts-tags', [
                    'id_facts' => $facts[$fact_tag['fact_id']],
                    'id_tags' => $tags[$fact_tag['tag_id']]
                ]);
            } catch (Exception $e) {
                continue;
            }

            if (empty($response->id)) {
                continue;
            }

            $assign[$fact_tag['id']] = $response->id;
        }

        return $this;
    }
}
