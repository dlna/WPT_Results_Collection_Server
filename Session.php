<?php
class Session
{
    public $id = false;
    private $dir = false;
    private $status = false;
    private $lock = false;

    static private $statusTypes = array('PASS', 'FAIL', 'TIMEOUT', 'ERROR');

    public function __construct($id)
    {
        if(!Session::isValidSession($id)) {
            throw new Exception('Not a valid session ID');
        }

        $this->id = $id;
        $this->dir = SESSION_DIR.'/'.$id;
        $this->loadState();
    }

    public function getResults($filterString = null, $pageIndex = null, $pageSize = null)
    {
        $results = array();

        $totalTests = 0;
        $totalResults = 0;

        $filters = (null !== $filterString) ? explode(',', $filterString) : self::$statusTypes;

        if ($dh = opendir($this->dir)) 
        {
            while (($file = readdir($dh)) !== false) 
            {
                if(is_numeric($file)) 
                {
                    $result = json_decode(file_get_contents($this->dir.'/'.$file), true);
                    $testStatus = $result['result'];
                    $totalTests += count($result['subtests']);

                    if(in_array($testStatus, $filters))
                    {
                        array_push($results, $result);
                        $totalResults++;
                    }
                }
            }
            closedir($dh);
        }

        usort($results, function ($a, $b) {
            return $a['id'] - $b['id'];
        });

        if(null !== $pageIndex && null !== $pageSize) {
            $results = array_slice($results, ($pageIndex - 1) * $pageSize, $pageSize);
        }

        return array('results' => $results,
                     'totals' => $this->status['totals'],
                     'numResults' => $totalResults
                     );
    }

    public function saveResult($result, $index = false)
    {
        $this->lock();
        $this->loadState();

        if(false === $index) {
            $index = $this->status['count'];
            $this->status['count']++;
        } else if($index >= $this->status['count']) {
            $this->status['count'] = $index + 1;
        }

        $result['id'] = $index;
        $this->updateTestStats($result, $this->status['totals']);
        file_put_contents($this->dir.'/'.$index, json_encode($result));

        $this->saveState();
        $this->unlock();

        return $index;
    }

    public function getName() 
    {
        return array_key_exists('name', $this->status) ? $this->status['name'] : '';
    }

    public function setName($newName) 
    {
        $this->lock();
        $this->loadState();
        if(!array_key_exists('name', $this->status) || 
           $newName != $this->status['name']) 
        {
            $this->status['name'] = $newName;
            $this->saveState();
        }
        $this->unlock();
    }

    public function getCount() 
    {
        return $this->status['count'];
    }

    public function getCreatedTime()
    {
        return filectime($this->dir.'/status');
    }

    public function getModifiedTime()
    {
        return filemtime($this->dir.'/status');
    }

    public function getInfo()
    {
        return array(
            'rel' => 'session', 
            'id' => $this->id,
            'name' => $this->getName(),
            'count' => $this->getCount(),
            'totals' => $this->status['totals'],
            'created' => $this->getCreatedTime(),
            'modified' => $this->getModifiedTime()
        );
    }

    public function delete()
    {
        // Delete the session file under lock to mark as invalid while we delete everything else
        $this->lock();
        unlink($this->dir.'/status');
        $this->unlock();

        if ($dh = opendir($this->dir)) 
        {
            while (($file = readdir($dh)) !== false) 
            {
                if('.' != $file && '..' != $file) 
                {
                    unlink($this->dir.'/'.$file);
                }
            }
            closedir($dh);
        }

        rmdir($this->dir);
    }

    public static function isValidSession($id)
    {
        if(is_numeric($id))
        {
            $dir = SESSION_DIR.'/'.$id;
            if(is_dir($dir)) 
            {
                if(is_file($dir.'/status')) 
                {
                    return true;
                }
            }
        }

        return false;
    }

    public static function createSession($id)
    {
        if(!is_numeric($id)) {
            throw new Exception('Not a valid session ID');
        }
        if(Session::isValidSession($id)) {
            throw new Exception('Session already exists');
        }

        $dir = SESSION_DIR.'/'.$id;
        mkdir($dir);
        file_put_contents($dir.'/status', json_encode(array(
            'count' => 0,
            'totals' => self::createStatsArray()
        )));

        return new Session($id);
    }

    private function loadState()
    {
        $this->status = json_decode(file_get_contents($this->dir.'/status'), true);
    }

    private function saveState()
    {
        file_put_contents($this->dir.'/status', json_encode($this->status));
    }

    private function lock()
    {
        $this->lock = fopen($this->dir.'/lock', "w");
        return flock($this->lock, LOCK_EX); // acquire an exclusive lock
    }

    private function unlock()
    {
        flock($this->lock, LOCK_UN);    // release the lock
        fclose($this->lock);

        $this->lock = false;
        unlink($this->dir.'/lock');
    }

    static private function &createStatsArray()
    {
        $stats = array();
        foreach(self::$statusTypes as $type) {
            $stats[$type] = 0;
        }
        $stats['ALL'] = 0;

        return $stats;
    }

    private function updateTestStats(&$result, &$totals)
    {
        $result['time'] = microtime(true);

        $status = 'PASS';
        $subTotals = self::createStatsArray();

        switch ($result['status'])
        {
            case "OK":
                foreach($result['subtests'] as $item)
                {
                    if ('PASS' != $item['status']) {
                        $status = $item['status'];
                    }
                    $subTotals[$item['status']]++;
                }
                break;
            default:
                $status = $result['status'];
                $subTotals[$result['status']]++;
        }

        $subTotals['ALL'] = count($result['subtests']);
        $result['totals'] = $subTotals;
        $result['result'] = $status;

        foreach(array_keys($totals) as $key) {
            $totals[$key] += $subTotals[$key];
        }
    }
}
?>