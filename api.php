<?php

// Using;
// - https://github.com/slimphp/Slim
// - https://github.com/entomb/slim-json-api

require 'vendor/autoload.php';

require 'Session.php';
require 'Reference.php';
require 'ValidationReport.php';
require 'ResultSupport.php';

error_reporting(E_ALL);
ini_set('memory_limit', -1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

// Configuration
define('DATA_DIR', dirname(__FILE__).'/data');
define('SESSION_DIR', DATA_DIR.'/results');
define('VALIDATION_REPORT_DIR', DATA_DIR.'/reports');
define('STATUS_FILE', DATA_DIR.'/status');
define('REFERENCE_DIR', dirname(__FILE__).'/reference');

// Make sure our data/session dir is setup correctly
if(!file_exists(DATA_DIR)) {
    mkdir(DATA_DIR);
}

if(!file_exists(SESSION_DIR)) {
    mkdir(SESSION_DIR);
}

if(!file_exists(VALIDATION_REPORT_DIR)) {
    mkdir(VALIDATION_REPORT_DIR);
}

if(!file_exists(REFERENCE_DIR)) {
    throw new Exception("Reference results dir '".REFERENCE_DIR."' not found");
}

// Load our status
if(file_exists(STATUS_FILE)) 
{
    $status = json_decode(file_get_contents(STATUS_FILE), true);
    $statusModified = false;

    // Bit of backward compatability
    if(array_key_exists('count', $status)) 
    {
        $statusModified = true;
        $status['results'] = $status['count'];
        $status['reports'] = 0;
        unset($status['count']);
    }

    if(!array_key_exists('results', $status)) { $status['results'] = 0; }
    if(!array_key_exists('reports', $status)) { $status['reports'] = 0; }
} 
else  
{
    $status = array(
        'results' => 0,
        'reports' => 0
    );
    $statusModified = true;
}
if(!array_key_exists('reference', $status))
{
    $ref = 0;
    if ($dh = opendir(REFERENCE_DIR))
    {
        while (($file = readdir($dh)) !== false)
        {
            if(Reference::isValidResults($file) && (int)$file >= $ref) {
                $ref = (int)$file + 1;
            }
        }
        closedir($dh);
    }

    $statusModified = true;
    $status['reference'] = $ref;
}

$app = new \Slim\Slim();
$app->config('debug', false);
$app->view(new \JsonApiView());
$app->add(new \JsonApiMiddleware());

$app->group('/results', function() use ($app) 
{
    $app->post('/', function () use ($app)  
    {
        global $status, $statusModified;
        // Create a new session
        $session = Session::createSession($status['results']);
        $status['results']++;
        $statusModified = true;
        $sessionInfo = $session->getInfo();
        $sessionInfo['href'] = $app->urlFor('results', array('id' => $session->id));
        Notify(array(
            'action' => 'create',
            'session' => $sessionInfo
        ));
        $app->render(200,array(
            'session' => $sessionInfo
        ));
    });
    $app->get('/', function () use ($app) 
    {
        $sessions = array();

        if ($dh = opendir(SESSION_DIR)) 
        {
            while (($file = readdir($dh)) !== false) 
            {
                if(Session::isValidSession($file))
                {
                    $session = new Session($file);
                    $sessionInfo = $session->getInfo();
                    $sessionInfo['href'] = $app->urlFor('results', array('id' => $file));
                    array_push($sessions, $sessionInfo);
                }
            }
            closedir($dh);
        }

        usort($sessions, function ($a, $b) {
            return $a['id'] - $b['id'];
        });

        $app->render(200, array(
            'sessions' => $sessions
        ));
    })->name('resultIndex');
    $app->get('/:id', function ($id) use($app) 
    {
        $session = new Session($id);
        $download = $app->request()->params('download');
        if(null != $download && $download) {
            header("Content-Disposition: attachment; filename=\"result-$id.json\"");
        }
        
        $app->render(200, $session->GetResults($app->request()->params('filters'), 
                                               $app->request()->params('pageIndex'), 
                                               $app->request()->params('pageSize')));
    })->name('results');
    $app->post('/:id', function ($id) use($app) 
    {
        $session = new Session($id);
        $result = json_decode($app->request->getBody(), true);
        if(false != $result)
        {
            if(array_key_exists('name', $result))
            {
                $index = $session->setName($result['name']);
                $app->render(200, array());
            }
            else
            {
                $index = $session->saveResult($result);
                $result['id'] = $index;
                Notify(array(
                    'action' => 'result',
                    'session' => $session->getInfo(),
                    'result'  => $result,
                ));
                $app->render(200, array());
            }
        } else {
            $app->render(400, array(
                'error' => true,
                'msg'   => 'Not JSON',
            ));
        }
    });
    $app->delete('/:id', function ($id) use($app) 
    {
        $session = new Session($id);
        $session->delete();
        Notify(array(
            'action' => 'delete',
            'session' => $id,
        ));
        $app->render(200, array());
    });
    $app->put('/:id/:index', function ($id, $index) use($app) 
    {
        $session = new Session($id);
        $result = json_decode($app->request->getBody(), true);
        if(false != $result)
        {
            $index = $session->saveResult($result, $index);
            $result['id'] = $index;
            Notify(array(
                'action' => 'result',
                'session' => $session->getInfo(),
                'result'  => $result,
            ));
            $app->render(200, array());
        } else {
            $app->render(400, array(
                'error' => true,
                'msg'   => 'Not JSON',
            ));
        }
    });
    $app->options('/:param+', function ($param) use($app) {
        $app->render(200, array());
    });
});
$app->group('/references', function() use ($app)
{
    $app->get('/', function () use ($app)
    {
        $references = array();

        if ($dh = opendir(REFERENCE_DIR))
        {
            while (($file = readdir($dh)) !== false)
            {
                if(Reference::isValidResults($file))
                {
                    $reference = new Reference($file);
                    $referenceInfo = $reference->getInfo();
                    $referenceInfo['href'] = $app->urlFor('references', array('id' => $file));
                    array_push($references, $referenceInfo);
                }
            }
            closedir($dh);
        }

        sort($references);

        $app->render(200, array(
            'references' => $references
        ));
    })->name('referenceIndex');
    $app->post('/', function () use ($app)
    {
        global $status, $statusModified;

        // Create a new session
        $reference = Reference::createReference($status['reference'], 
                                                $app->request()->params('sessions'),
                                                $app->request()->params('name'),
                                                $app->request()->params('minPass'));
        $status['reference']++;
        $statusModified = true;
        $referenceInfo = $reference->getInfo();
        $referenceInfo['href'] = $app->urlFor('references', array('id' => $referenceInfo['id']));
        Notify(array(
            'action' => 'create',
            'reference' => $referenceInfo
        ));
        $app->render(200,array(
            'reference' => $referenceInfo
        ));
    });
    $app->get('/:id', function ($id) use($app)
    {
        $reference = new Reference($id);
        $download = $app->request()->params('download');
        if(null != $download && $download) {
            header("Content-Disposition: attachment; filename=\"reference-$id.json\"");
        }

        $app->render(200, $reference->GetResults($app->request()->params('filters'),
                                                 $app->request()->params('pageIndex'),
                                                 $app->request()->params('pageSize')));
    })->name('references');
    $app->post('/:id', function ($id) use($app)
    {
        $reference = new Reference($id);
        $result = json_decode($app->request->getBody(), true);
        if(false != $result)
        {
            if(array_key_exists('name', $result))
            {
                $index = $reference->setName($result['name']);
                $app->render(200, array());
            }
        } else {
            $app->render(400, array(
                'error' => true,
                'msg'   => 'Not JSON',
            ));
        }
    });
    $app->delete('/:id', function ($id) use($app)
    {
        $reference = new Reference($id);
        $reference->delete();
        Notify(array(
            'action' => 'delete',
            'reference' => $id,
        ));
        $app->render(200, array());
    });
});
$app->group('/reports', function() use ($app)
{
    $app->post('/', function () use ($app)
    {
        global $status, $statusModified;
        // Create a new session
        $report = ValidationReport::newReport($status['reports'], 
                                              $app->request()->params('session'), 
                                              $app->request()->params('reference'));
        $status['reports']++;
        $statusModified = true;
        $reportInfo = $report->getInfo();
        $reportInfo['href'] = $app->urlFor('reports', array('id' => $report->id));
        Notify(array(
            'action' => 'create',
            'report' => $reportInfo
        ));
        $app->render(200,array(
            'report' => $reportInfo
        ));
    });
    $app->get('/', function () use ($app)
    {
        $reports = array();

        if ($dh = opendir(VALIDATION_REPORT_DIR))
        {
            while (($file = readdir($dh)) !== false)
            {
                if(ValidationReport::isValidValidationReport($file))
                {
                    $report = new ValidationReport($file);
                    $reportInfo = $report->getInfo();
                    $reportInfo['href'] = $app->urlFor('reports', array('id' => $file));
                    array_push($reports, $reportInfo);
                }
            }
            closedir($dh);
        }

        usort($reports, function ($a, $b) {
            return $a['id'] - $b['id'];
        });

        $app->render(200, array(
            'reports' => $reports
        ));
    })->name('reportIndex');
    $app->get('/:id', function ($id) use($app)
    {
        $report = new ValidationReport($id);
        $download = $app->request()->params('download');
        if(null != $download && $download) {
            header("Content-Disposition: attachment; filename=\"report-$id.json\"");
        }

        $app->render(200, $report->GetReport($app->request()->params('filters'),
                                             $app->request()->params('pageIndex'),
                                             $app->request()->params('pageSize')));
    })->name('reports');
    $app->post('/:id', function ($id) use($app)
    {
        $report = new ValidationReport($id);
        $result = json_decode($app->request->getBody(), true);
        if(false != $result)
        {
            if(array_key_exists('name', $result))
            {
                $index = $report->setName($result['name']);
                $app->render(200, array());
            }
        } else {
            $app->render(400, array(
                'error' => true,
                'msg'   => 'Not JSON',
            ));
        }
    });
    $app->delete('/:id', function ($id) use($app)
    {
        $report = new ValidationReport($id);
        $report->delete();
        Notify(array(
            'action' => 'delete',
            'report' => $id,
        ));
        $app->render(200, array());
    });
    $app->options('/:param+', function ($param) use($app) {
        $app->render(200, array());
    });
});
$app->get('/', function () use ($app)  
{
    global $status, $statusModified;
    $app->render(200, array_merge($status, array(
        'links' => array(
            array(
                'rel' => 'results',
                'href' => $app->urlFor('resultIndex')
            ),
            array(
                'rel' => 'reports',
                'href' => $app->urlFor('reportIndex')
            ),
            array(
                'rel' => 'references',
                'href' => $app->urlFor('referenceIndex')
            )
        )
    )));
});
$app->run();

if($statusModified)
{
    file_put_contents(STATUS_FILE, json_encode($status));
}

function Notify($entryData)
{
    $context = new ZMQContext();
    $socket = $context->getSocket(ZMQ::SOCKET_PUSH, 'my pusher');
    $socket->connect("tcp://localhost:5555");

    $socket->send(json_encode($entryData));
}

?>
