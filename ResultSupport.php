<?php

/**
 * Result short summary.
 *
 * Result description.
 *
 * @version 1.0
 * @author jeremy
 */
class ResultSupport
{
    public $test = null;

    public function __construct($test)
    {
        $this->test = &$test;
    }

    public function getTestName()
    {
        return $this->test['test']['url'];
    }

    public function getTestType()
    {
        return $this->test['test']['type'];
    }

    public static function getSubtestName(&$subtest)
    {
        return str_replace(array('web-platform.test:8000',
                                 'WEB-PLATFORM.TEST:8000'),
                           array('w3c-test.org',
                                 'W3C-TEST.ORG'),
                           $subtest['name']);
    }

}