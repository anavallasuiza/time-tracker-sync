<?php
namespace ANS\TimeTrackerSync;

class TimeTrackerSync
{
    private $curl;

    private $activities = [];
    private $categories = [];
    private $facts = [];
    private $tags = [];

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
        $this->facts = $this->compare($local, $this->curl->get('/facts')->data, 'id', 'remote_id');
    }

    public function setTags($local)
    {
        $this->tags = $this->compare($local, $this->curl->get('/tags')->data, 'name', 'name');
    }

    private function compare($local, $remote, $local_key, $remote_key)
    {
        $local = json_decode(json_encode($local), true);
        $remote = json_decode(json_encode($remote), true);

        $remote_keys = array_column($remote, $remote_key);

        $add = $assign = [];

        foreach ($local as $row) {
            if (($key = array_search($row[$local_key], $remote_keys, true)) !== false) {
                $assign[$row['id']] = $remote[$key]['id'];
            } else {
                $add[] = $row;
            }
        }

        return [
            'assign' => $assign,
            'add' => $add
        ];
    }

    public function summary()
    {
        return [
            'activities' => $this->activities,
            'categories' => $this->categories,
            'facts' => $this->facts,
            'tags' => $this->tags
        ];
    }

    public function sync()
    {
        $this->syncCategories();
        $this->syncActivities();
        $this->syncFacts();

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
        if (empty($this->facts) || empty($this->facts['add'])) {
            return $this;
        }

        foreach ($this->facts['add'] as $fact) {
            $response = $this->curl->post('/facts', [
                'start_time' => $fact['start_time'],
                'end_time' => $fact['end_time'],
                'remote_id' => $fact['id'],
                'id_activities' => $this->activities['assign'][$fact['activity_id']],
            ]);

            $this->facts['assign'][$fact['id']] = $response->id;
        }

        return $this;
    }
}
