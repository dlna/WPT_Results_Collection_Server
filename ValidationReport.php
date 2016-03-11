<?php
class ValidationReport
{
    public $id = false;
    private $dir = false;
    private $status = false;
    private $lock = false;

    static private $statusTypes = array('PASS', 'FAIL', 'TIMEOUT', 'ERROR');

    public function __construct($id)
    {
        if(!ValidationReport::isValidValidationReport($id)) {
            throw new Exception('Not a valid report ID');
        }

        $this->id = $id;
        $this->dir = VALIDATION_REPORT_DIR.'/'.$id;
        $this->loadState();
    }

    public function getReport($filterString = null, $pageIndex = null, $pageSize = null)
    {
        $filters = (null !== $filterString) ? explode(',', $filterString) : self::$statusTypes;

        $report = json_decode(file_get_contents($this->dir.'/report'), true);
        $log_total = count ($report);

/*
        usort($report, function ($a, $b) {
            return $a['id'] - $b['id'];
        });
*/

        if(null !== $pageIndex && null !== $pageSize) {
            $report = array_slice($report, ($pageIndex - 1) * $pageSize, $pageSize);
        }

        return array('session' => $this->status['session'],
                     'reference' => $this->status['reference'],
                     'report' => $report,
                     'totals' => array(
                          'tests_not_run' => $this->status['tests_not_run'],
                          'subtests_not_run' => $this->status['subtests_not_run'],
                          'tests_failed' => $this->status['tests_failed'],
                          'subtests_failed' => $this->status['subtests_failed'],
                          'log_total' => $log_total
                     ));
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
            'rel' => 'report',
            'id' => $this->id,
            'name' => $this->getName(),
            'session' => $this->status['session'],
            'reference' => $this->status['reference'],
            'subtests_not_run' => $this->status['subtests_not_run'],
            'tests_failed' => $this->status['tests_failed'],
            'subtests_failed' => $this->status['subtests_failed'],
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

    public static function isValidValidationReport($id)
    {
        if(is_numeric($id))
        {
            $dir = VALIDATION_REPORT_DIR.'/'.$id;
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

    public static function newReport($id, $sessionId, $referenceId)
    {
        if(false === Session::isValidSession($sessionId)) {
            throw new Exception('Session ID not valid');
        }
        if(false === Reference::isValidResults($referenceId)) {
            throw new Exception('Reference ID not valid');
        }
        if(!is_numeric($id)) {
            throw new Exception('Not a valid report ID');
        }
        if(ValidationReport::isValidValidationReport($id)) {
            throw new Exception('Report already exists');
        }

        $session = new Session($sessionId);
        $sessionResults = $session->getResults();

        $reference = new Reference($referenceId);
        $referenceResults = $reference->getResults();

        // Index the sessionResults so we can look them up
        $sessionResults['tests'] = array();
        foreach ($sessionResults['results'] as $test)
        {
            $result = new ResultSupport($test);
            $testName = $result->getTestName();
            if(!array_key_exists($testName, $sessionResults['tests'])) {
                $sessionResults['tests'][$testName] = array();
            }

            $testId = $test['id'];
            $sessionResults['tests'][$testName][$testId] = array();

            foreach ($test['subtests'] as $subtest) 
            {
                $subtestName = ResultSupport::getSubtestName($subtest);

                // Update the results
                $sessionResults['tests'][$testName][$testId][$subtestName] = $subtest['status'];
            }
        }

        // Test statistics
        $testsNotRun = 0;
        $subtestsNotRun = 0;
        $testsFailed = 0;
        $subtestsFailed = 0;
        $log = array();

        // Work through each test
        foreach ($referenceResults['results'] as $test)
        {
            $result = new ResultSupport($test);
            $testName = $result->getTestName();
            if(!array_key_exists($testName, $sessionResults['tests'])) 
            {
                array_push($log, array(
                    'type' => 'error',
                    'message' => 'FAILURE - TEST NOT RUN',
                    'test' => $testName,
                    'reference_test_id' => $test['id']
                ));
                
                $testsNotRun++;
                continue;
            }

            if('testharness' == $result->getTestType())
            {
                // Counter to see if any of the subtests fail
                $failCheck = $subtestsFailed;

                foreach ($test['subtests'] as $subtest) 
                {
                    $subtestName = ResultSupport::getSubtestName($subtest);

                    // Check the passed tests
                    if ($subtest['status'] === "PASS") 
                    {
                        $testRun = false;
                        foreach($sessionResults['tests'][$testName] as $testId => $subtestList)
                        {
                            if(array_key_exists($subtestName, $subtestList)) 
                            {
                                $testRun = $testId;
                                break;
                            }
                        }
                    
                        if(false === $testRun)
                        {
                            array_push($log, array(
                                'type' => 'error',
                                'message' => 'FAILURE - SUBTEST NOT RUN',
                                'test' => $testName,
                                'subtest' => $subtestName, 
                                'reference_test_id' => $test['id'],
                                'test_ids' => array_keys($sessionResults['tests'][$testName])
                            ));

                            $subtestsNotRun++;
                            continue;
                        }

                        if ($sessionResults['tests'][$testName][$testRun][$subtestName] === "PASS") {
                            //fprintf(STDOUT, "  PASS: %s /  %s\n", $testName, $subtestName);
                        } 
                        else 
                        {
                            array_push($log, array(
                                'type' => 'error',
                                'message' => 'FAILURE - SUBTEST FAILED',
                                'test' => $testName,
                                'subtest' => $subtestName,
                                'reference_test_id' => $test['id'],
                                'test_id' => $testRun,
                            ));

                            $subtestsFailed++;
                        }
                    }
                }

                // Check if any of the subtests failed
                if ($failCheck != $subtestsFailed) {
                    $testsFailed++;
                }
            }
            else
            {
            }
        }

        $status = array(
            'session' => $sessionId,
            'reference' => $referenceId,
            'tests_not_run' => $testsNotRun,
            'subtests_not_run' => $subtestsNotRun,
            'tests_failed' => $testsFailed,
            'subtests_failed' => $subtestsFailed,
            'log_total' => count($log)
        );

        $dir = VALIDATION_REPORT_DIR.'/'.$id;
        mkdir($dir);
        file_put_contents($dir.'/status', json_encode($status));
        file_put_contents($dir.'/report', json_encode($log));

        return new ValidationReport($id);
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
