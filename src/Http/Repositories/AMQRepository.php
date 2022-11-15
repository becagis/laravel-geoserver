<?php

namespace BecaGIS\LaravelGeoserver\Http\Repositories;

use BecaGIS\LaravelGeoserver\Jobs\AMQBecaGISJob;
use Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class AMQRepository {
    const ChannelLayer = 'BecaGIS_Layer';
    const ChannelMap = 'BecaGIS_Map';
    const ChannelFeature = 'BecaGIS_Feature';

    const ActionCreate = 'Create';
    const ActionUpdate = 'Update';
    const ActionDelete = 'Delete';

    protected static $instance;

    protected $connection;
    protected $exchange;

    function __construct() {
        //$this->createConnection();
    }

    function __destruct() {
        $this->closeConnection();
    }

    public static function instance() {
        if (!isset($instance)) {
            $instance = new AMQRepository();
        }

        return $instance;
    }

    public function asyncSend($channel, $action, $data, $meta, $typeName) {
        try {
            AMQBecaGISJob::dispatch($channel, $action, $data, $meta, $typeName);
        } catch(Exception $ex) {
            //dd($ex);
        }
    }

        
    /**
     * send
     *
     * @param  mixed $channelName
     * @param  mixed $payload {action: 'Create/Update/Delete', data: {}, meta: {}, created_at: timestamp number}
     * @return void
     */
    function send($channelName, $routingKey, $payload) {
        $conn = $this->getConnection();

        $channel = $conn->channel();
        $channel->exchange_declare($channelName, 'topic', false, false, false);

        $message = new AMQPMessage(json_encode($payload));
        $channel->basic_publish($message, $channelName, $routingKey);
        $channel->close();
    }

    public function sendTargetAction($channel, $action, $data, $meta, $typeName) {
        match ($channel) {
            self::ChannelLayer => $this->sendLayerAction($action, $data, $meta),
            self::ChannelFeature => $this->sendFeatureAction($action, $typeName, $data, $meta),
            self::ChannelMap => $this->sendMapAction($action, $data, $meta)
        };
    }

    public function sendFeatureAction($action, $typeName, $data, $meta) {
        $payload = $this->createPayload($action, $data, $meta);
        $payload['typename'] = $typeName;
        $this->send(self::ChannelFeature, "$typeName.$action", $payload);
    }

    public function sendLayerAction($action, $data, $meta) {
        $payload = $this->createPayload($action, $data, $meta);
        $this->send(self::ChannelLayer, self::ChannelLayer, $payload);
    }

    public function sendMapAction($action, $data, $meta) {
        $payload = $this->createPayload($action, $data, $meta);
        $this->send(self::ChannelMap, self::ChannelMap, $payload);
    }

    function createPayload($action, $data, $meta) {
        return [
            'action' => $action,
            'data' => $data,
            'meta' => $meta,
            'created_at' => time() 
        ];
    }

    function getConnection() {
        if ($this->isConnected()) {
            return $this->connection;
        } else {
            $this->createConnection();
        }
        return $this->connection;
    }

    function isConnected() {
        return isset($connection) && $connection->isConnected();
    }

    function createConnection() {
        $host = config('geoserver.rabbitmq.host');
        $port = config('geoserver.rabbitmq.port');
        $username = config('geoserver.rabbitmq.username');
        $password = config('geoserver.rabbitmq.password');

        $this->exchange = config('geoserver.rabbitmq.exchange');

        $this->connection = new AMQPStreamConnection($host, $port, $username, $password);
        return $this->connection;
    }

    function closeConnection() {
        if ($this->isConnected()) {
            $this->connection->close();
        }
    }
}