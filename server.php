<?php

include __DIR__ . '/config.php';
include __DIR__ . '/functions.php';

use Swoole\Http\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Database\RedisConfig;
use Swoole\Database\RedisPool;

// configure Redis Pool
$redisPool = new RedisPool(
    (new RedisConfig())
        ->withHost(REDIS_HOST)
        ->withPort(REDIS_PORT)
        ->withTimeout(1)
);

// 0.0.0.0 = accept all clients
$server = new Server('0.0.0.0', LISTEN_PORT);

$server->on('start', function(Server $server) {
    echo date('Y-m-d H:i:s', time()) . ' :: Server started' . "\n";
});

$server->on('shutdown', function(Server $server) {
    echo date('Y-m-d H:i:s', time()) . ' :: Server shutting down' . "\n";
});

$server->on('request', function(Request $request, Response $response) use ($redisPool) {
    echo date('Y-m-d H:i:s', time()) . ' :: Incoming request from ' . $request->server['remote_addr'] . "\n";

    $start = microtime(true);

    // delegate to function so we use early returns and still easily capture execution time
    $redis = $redisPool->get();
    handleIncomingRequest($redis, $request,$response);
    $redisPool->put($redis);

    $time = (microtime(true) - $start) * 1000;
    echo date('Y-m-d H:i:s', time()) . ' :: Request processed in ' . number_format($time,4) . "s, memory usage " . number_format(memory_get_usage(true)/1024/2014, 4) . "mb\n";
});

$server->start();
