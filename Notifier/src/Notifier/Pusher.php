<?php

namespace Notifier;

use Ratchet\ConnectionInterface;
use Ratchet\Wamp\WampServerInterface;

define('TOPIC', 'html5_test_tool.dlna.org');

class Pusher implements WampServerInterface 
{
    /**
     * A lookup of all the topics clients have subscribed to
     */
    protected $subscribedTopics = array();

    public function onSubscribe(ConnectionInterface $conn, $topic) {
        echo "onSubscribe! ({$conn->resourceId})\n";
        $this->subscribedTopics[$topic->getId()] = $topic;
    }
    public function onUnSubscribe(ConnectionInterface $conn, $topic) {
        echo "onUnSubscribe! ({$conn->resourceId})\n";
    }
    public function onOpen(ConnectionInterface $conn) {
        echo "New connection! ({$conn->resourceId})\n";
    }
    public function onClose(ConnectionInterface $conn) {
        echo "Connection {$conn->resourceId} has disconnected\n";
    }
    public function onCall(ConnectionInterface $conn, $id, $topic, array $params) {
        // In this application if clients send data it's because the user hacked around in console
        $conn->callError($id, $topic, 'You are not allowed to make calls')->close();
    }
    public function onPublish(ConnectionInterface $conn, $topic, $event, array $exclude, array $eligible) {
        // In this application if clients send data it's because the user hacked around in console
        $conn->close();
    }
    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";

        $conn->close();
    }

    /**
     * @param string JSON'ified string we'll receive from ZeroMQ
     */
    public function onBlogEntry($entry) 
    {
        $entryData = json_decode($entry, true);

        // If the lookup topic object isn't set there is no one to publish to
        if (!array_key_exists(TOPIC, $this->subscribedTopics)) {
            return;
        }

        $topic = $this->subscribedTopics[TOPIC];

        // re-send the data to all the clients subscribed to that action
        $topic->broadcast($entryData);
    }
}
