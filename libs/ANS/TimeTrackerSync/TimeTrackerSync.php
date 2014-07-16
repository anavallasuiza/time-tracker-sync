<?php
namespace ANS\TimeTrackerSync;

class TimeTrackerSync
{
    private $curl;

    private $hostname = '';

    private $activities = [];
    private $categories = [];
    private $facts = [];
    private $tags = [];

    public function __construct()
    {
        $this->hostname = gethostname();
    }

    public function setHostName($hostname)
    {
        $this->hostname = $hostname;
    }

    public function setCurl(\Curl $curl)
    {
        $this->curl = $curl;
    }

    public function setActivities($local)
    {
        $this->activities = $this->compare($local, $this->curl->get('/activities')->data, 'name', 'name');
    }

    public function setCategories($local)
    {
        $this->categories = $this->compare($local, $this->curl->get('/categories')->data, 'name', 'name');
    }

    public function setFacts($local)
    {
        $remote = $this->curl->get('/facts', [
            'hostname' => $this->hostname
        ]);

        $this->facts = $this->compare($local, $remote->data, 'id', 'remote_id');
    }

    public function setTags($local)
    {
        $this->tags = $this->compare($local, $this->curl->get('/tags')->data, 'name', 'name');
    }

    public function setFactsTags($local)
    {
        $this->facts_tags = $local;
    }

    private function compare($local, $remote, $local_key, $remote_key)
    {
        $local = json_decode(json_encode($local), true);
        $remote = json_decode(json_encode($remote), true);

        $local_keys = array_column($local, $local_key);
        $remote_keys = array_column($remote, $remote_key);

        $add = $del = $assign = [];

        foreach ($local as $row) {
            if (($key = array_search($row[$local_key], $remote_keys, true)) !== false) {
                $assign[$row['id']] = $remote[$key]['id'];
            } else {
                $add[] = $row;
            }
        }

        foreach ($remote as $row) {
            if (($key = array_search($row[$remote_key], $local_keys, true)) === false) {
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
            'categories' => $this->categories,
            'facts' => $this->facts,
            'tags' => $this->tags,
            'facts_tags' => $this->facts_tags
        ];
    }

    public function sync()
    {
        $this->syncCategories();
        $this->syncTags();

        $this->syncActivities();
        $this->syncFacts();

        $this->syncFactsTags();

        return $this;
    }

    public function syncCategories()
    {
        if (empty($this->categories) || empty($this->categories['add'])) {
            return $this;
        }

        foreach ($this->categories['add'] as $category) {
            $response = $this->curl->post('/categories', [
                'name' => $category['name']
            ]);

            $this->categories['assign'][$category['id']] = $response->id;
        }

        return $this;
    }

    public function syncTags()
    {
        if (empty($this->tags) || empty($this->tags['add'])) {
            return $this;
        }

        foreach ($this->tags['add'] as $tag) {
            $response = $this->curl->post('/tags', [
                'name' => $tag['name']
            ]);

            $this->tags['assign'][$tag['id']] = $response->id;
        }

        return $this;
    }

    public function syncActivities()
    {
        if (empty($this->activities) || empty($this->activities['add'])) {
            return $this;
        }

        foreach ($this->activities['add'] as $activity) {
            $response = $this->curl->post('/activities', [
                'name' => $activity['name'],
                'id_categories' => $this->categories['assign'][$activity['category_id']]
            ]);

            $this->activities['assign'][$activity['id']] = $response->id;
        }

        return $this;
    }

    public function syncFacts()
    {
        if (empty($this->facts)) {
            return $this;
        }

        if ($this->facts['add']) {
            foreach ($this->facts['add'] as $fact) {
                $response = $this->curl->post('/facts', [
                    'start_time' => $fact['start_time'],
                    'end_time' => $fact['end_time'],
                    'description' => $fact['description'],
                    'hostname' => $this->hostname,
                    'remote_id' => $fact['id'],
                    'id_activities' => $this->activities['assign'][$fact['activity_id']],
                ]);

                $this->facts['assign'][$fact['id']] = $response->id;
            }
        }

        if ($this->facts['del']) {
            foreach ($this->facts['del'] as $fact) {
                $response = $this->curl->delete('/facts', [
                    'remote_id' => $fact['remote_id'],
                    'hostname' => $this->hostname,
                ]);
            }
        }

        return $this;
    }

    public function syncFactsTags()
    {
        if (empty($this->facts_tags)) {
            return $this;
        }

        foreach ($this->facts_tags as &$value) {
            if (!isset($this->facts['assign'][$value['fact_id']])
            || !isset($this->tags['assign'][$value['tag_id']])) {
                $value = null;
                continue;
            }

            $value['id'] = $this->facts['assign'][$value['fact_id']].'|'.$this->tags['assign'][$value['tag_id']];
        }

        $this->facts_tags = array_filter($this->facts_tags);

        $remote = $this->curl->get('/facts-tags', [
            'hostname' => $this->hostname
        ])->data;

        foreach ($remote as &$value) {
            $value->id = $value->id_facts.'|'.$value->id_tags;
        }

        unset($value);

        $this->facts_tags = $this->compare($this->facts_tags, $remote, 'id', 'id');

        if (empty($this->facts_tags['add'])) {
            return $this;
        }

        foreach ($this->facts_tags['add'] as $fact_tag) {
            $response = $this->curl->post('/facts-tags', [
                'id_facts' => $this->facts['assign'][$fact_tag['fact_id']],
                'id_tags' => $this->tags['assign'][$fact_tag['tag_id']]
            ]);

            $this->facts_tags['assign'][$fact_tag['id']] = $response->id;
        }

        return $this;
    }
}
