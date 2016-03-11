<?php
class Reference
{
    public $id = false;
    private $dir = false;
    private $status = false;
    private $lock = false;

    static private $statusTypes = array('PASS', 'FAIL', 'TIMEOUT', 'ERROR');

    public function __construct($id)
    {
        if(!Reference::isValidResults($id)) {
            throw new Exception('Not a valid reference ID');
        }

        $this->id = $id;
        $this->dir = REFERENCE_DIR.'/'.$id;
        $this->loadState();
    }

    public function getResults($filterString = null, $pageIndex = null, $pageSize = null)
    {
        $results = array();

        $totalTests = 0;
        $totalResults = 0;

        $filters = (null !== $filterString) ? explode(',', $filterString) : self::$statusTypes;

        $temp = json_decode(file_get_contents($this->dir.'/results'), true);
        foreach($temp['results'] as $result)
        {
            $testStatus = $result['result'];
            $totalTests += count($result['subtests']);

            if(in_array($testStatus, $filters))
            {
                array_push($results, $result);
                $totalResults++;
            }
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

    public function verify($session)
    {
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
            'rel' => 'reference',
            'id' => $this->id,
            'name' => $this->getName(),
            'count' => $this->getCount(),
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

    public static function isValidResults($id)
    {
        if(is_numeric($id))
        {
            $dir = REFERENCE_DIR.'/'.$id;
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

    public static function createReference($referenceId, $sessionIds, $name, $minPassRate = 2)
    {
        // Check the input
        foreach($sessionIds as $sessionId)
        {
            if(false === Session::isValidSession($sessionId)) {
                throw new Exception('Session ID not valid');
            }
        }
        if(!is_numeric($referenceId)) {
            throw new Exception('Not a valid report ID');
        }
        if(Reference::isValidResults($referenceId)) {
            throw new Exception('Reference already exists');
        }

        $results = array();
        
        foreach($sessionIds as $sessionId)
        {
            // Load the results
            $session = new Session($sessionId);
            $testResults = $session->getResults();
            
            // Get stats per sub-test
            foreach ($testResults['results'] as $test)
            {
                $result = new ResultSupport($test);

                $testName = $result->getTestName();
                if(!array_key_exists($testName, $results))
                {
                    $obj = new stdClass();
                    $obj->test = $test['test'];
                    $obj->passCount = 0;
                    $obj->subtests = array();
                    $results[$testName] = $obj;
                }

                if('OK' == $test['status']) 
                {
                    // If it is a testharnes test then 
                    if('testharness' == $result->getTestType())
                    {
                        $results[$testName]->passCount++;
                        foreach ($test['subtests'] as $subtest)
                        {
                            $subtestName = ResultSupport::getSubtestName($subtest);
                            if(!array_key_exists($subtestName, $results[$testName]->subtests)) {
                                $results[$testName]->subtests[$subtestName] = 0;
                            }

                            // If the test status is something other than a PASS or FAIL we can not be
                            // certain of which sub test caused a problem so we fail them all
                            if(('OK' == $test['status'] || 'PASS' == $test['status']) && 'PASS' == $subtest['status']) {
                                $results[$testName]->subtests[$subtestName]++;
                            }
                        }
                    } else if('PASS' == $test['result']) {
                        $results[$testName]->passCount++;
                    }
                }
            }
        }

        $combined = array();
        $id = 0;

        ksort($results);
        foreach ($results as $test=>$info)
        {
            if($info->passCount >= $minPassRate)
            {
                $testObj = new stdClass();
                $testObj->test = $test;
                $testObj->status = 'OK';
                $testObj->subtests = array();
                $testObj->id = $id++;
                $testObj->result = 'PASS';

                ksort($info->subtests);
                foreach ($info->subtests as $subtest => $result)
                {
                    $subtestObj = new stdClass();
                    $subtestObj->name = $subtest;
                    $subtestObj->status = ($result >= $minPassRate) ? 'PASS' : 'FAIL';
                    if('FAIL' == $subtestObj->status) {
                        $testObj->result = 'FAIL';
                    }

                    $testObj->subtests[] = $subtestObj;
                }

                $combined[] = $testObj;
            }
        }

        $dir = REFERENCE_DIR.'/'.$referenceId;
        mkdir($dir);
        file_put_contents($dir.'/status', json_encode(array(
            'count' => count($results),
            'name' => $name
        )));
        file_put_contents($dir.'/results', json_encode(array(
            'results' => $results
        )));

        return new Reference($referenceId);
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
}
?>
