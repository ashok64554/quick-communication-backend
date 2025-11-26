<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory as LoopFactory;
use React\Socket\Server as Reactor;
use App\Http\Controllers\Api\WebSocketController;
use Log;

class WebSocketServer extends Command
{
    protected $signature = 'websocket:init';
    protected $description = 'Command description';


    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $host = env('RATCHET_HOST') ? env('RATCHET_HOST') : 'ws://localhost';
        $port = env('RATCHET_PORT') ? env('RATCHET_PORT') : 8090;
        echo "Ratchet server started on $host:$port \n";
        $loop = LoopFactory::create();
        $socket = new Reactor('0.0.0.0:' . $port, $loop);
        $wsServer = new WsServer(new WebSocketController($loop));
        $server = new IoServer(new HttpServer($wsServer), $socket, $loop);
        $wsServer->enableKeepAlive($server->loop, 10);
        $server->run();
    }   
}
