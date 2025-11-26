<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use App\Models\Message;
use App\Models\User;
use App\Models\OauthAccessTokens;
use Auth;
use DB;
use Carbon\Carbon;

class WebSocketController implements MessageComponentInterface {

    protected $clients;
    private $subscriptions;
    private $users;
    private $userresources;
    protected $intime;
    
    public function __construct() 
    {
        $this->clients = new \SplObjectStorage;
        $this->subscriptions = [];
        $this->users = [];
        $this->userresources = [];
        $this->intime = \Carbon\Carbon::now();
    }

    /**
     * [onOpen description]
     * @method onOpen
     * @param  ConnectionInterface $conn [description]
     * @return [JSON]                    [description]
     * @example connection               var conn = new WebSocket('ws://localhost:8090');
     */
    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $this->users[$conn->resourceId] = $conn;
        $conn->send(json_encode('You have established a connection via websocket. Now you can start other operations.'));
    }

    /**
     * [onMessage description]
     * @method onMessage
     * @param  ConnectionInterface $conn [description]
     * @param  [JSON.stringify]              $msg  [description]
     * @return [JSON]                    [description]
     * @example subscribe                conn.send(JSON.stringify({"command": "subscribe", channel: "global"}));
     * @example groupchat                conn.send(JSON.stringify({command: "groupchat", message: "hello glob", channel: "global"}));
     * @example message                  conn.send(JSON.stringify({"command":"message", "token":"vytcdytuvib6f55sdxr76tc7uvikg8f7", "to": "1", "from":"2", "message":"it needs xss protection","file_path":"","file_type":""}));
     * @example register                 conn.send(JSON.stringify({"command": "register", "userId": "1", "token":"vytcdytuvib6f55sdxr76tc7uvikg8f7"}));
     * @example getusers                 conn.send(JSON.stringify({"command": "getusers", "userId": "1", "token":"vytcdytuvib6f55sdxr76tc7uvikg8f7"}));
     * @example getuserwithmessage       conn.send(JSON.stringify({"command": "getuserwithmessage", "userId": "1", "token":"vytcdytuvib6f55sdxr76tc7uvikg8f7"}));
     * @example getmessages              conn.send(JSON.stringify({"command": "getmessages", "logged_in_user_id": "2", "other_user_id": "1", "from_date": null, "end_date": null, "per_page": 5, "page": 1, "token":"vytcdytuvib6f55sdxr76tc7uvikg8f7"}));
     * @example disconnect               conn.send(JSON.stringify({"command": "disconnect", "userId": "2"}));
     * @example readmessages             conn.send(JSON.stringify({"command": "readmessages", "logged_in_user_id": "2", "other_user_id": "1", "token":"vytcdytuvib6f55sdxr76tc7uvikg8f7"}));
     * @example totalunreadmessage             conn.send(JSON.stringify({"command": "totalunreadmessage", "userId": "1", "token":"vytcdytuvib6f55sdxr76tc7uvikg8f7"}));
     */
    public function onMessage(ConnectionInterface $conn, $msg) 
    {
        $data = json_decode($msg);
        if (isset($data->command)) {
            if (isset($data->token)) {
                $userToken = checkUserToken($data->token);
                if (!empty($userToken)) {
                    $checkToken = OauthAccessTokens::where([
                        ['id', '=', $userToken['user_token']],
                        ['expires_at', '>', Carbon::now()->toDateTimeString()]
                    ])->first();
                    if ( isset($checkToken) && !$checkToken) {
                    $conn->send(json_encode('Token time has expired. Please log in again'));
                    }
                } else {
                    $conn->send(json_encode('Token not valid'));
                }
                
                switch ($data->command) {
                    case "message":
                        $req = json_decode($msg, true);
                        if ($userToken['user_id'] != $data->from ) {
                            $conn->send(json_encode('user not matched'));
                        } else  {
                            $message = new Message();
                            $message->sender_id = $data->from;
                            $message->receiver_id = $data->to;
                            $message->message = $data->message;
                            $message->file_path = $data->file_path;
                            $message->file_type = $data->file_type;
                            $message->read_at = null;
                            $message->save();
                            
                            $req['created_at'] = date('Y-m-d H:i:s');
                            $req['id'] = $message->id;
                            if (isset($this->userresources[$data->to])) {
                                foreach ($this->userresources[$data->to] as $key => $resourceId) {
                                    if (isset($this->users[$resourceId])) {
                                        $this->users[$resourceId]->send(json_encode($req));
                                        //Total unread message
                                        $totalunreadmessage = $this->totalunreadmessage($data->to);
                                        $returnData = [
                                            'command'   => 'totalunreadmessage',
                                            'userId'   => $data->to,
                                            'data'      => $totalunreadmessage
                                        ];
                                        $this->users[$resourceId]->send(json_encode($returnData));
                                    }
                                }
                                $conn->send(json_encode($this->userresources[$data->to]));
                            }
                            if (isset($this->userresources[$data->from])) {
                                foreach ($this->userresources[$data->from] as $key => $resourceId) {
                                    if (isset($this->users[$resourceId]) && $conn->resourceId != $resourceId) {
                                        $this->users[$resourceId]->send(json_encode($req));
                                    }
                                }
                            }
                        }
                        $conn->send(json_encode($req));
                        break;
                    case "getusers":
                        if ($userToken['user_id'] != $data->userId ) {
                            $conn->send(json_encode('user not matched'));
                        } else  {
                            $getUsers = $this->getUsers($data->userId);
                            $returnData = [
                                'command'   => 'getusers',
                                'data'      => $getUsers
                            ];
                            $conn->send(json_encode($returnData));
                        }
                        break;
                    case "getuserwithmessage":
                        if ($userToken['user_id'] != $data->userId ) {
                            $conn->send(json_encode('user not matched'));
                        } else  {
                            $getuserwithmessage = $this->getuserwithmessage($data->userId);
                            $returnData = [
                                'command'   => 'getuserwithmessage',
                                'data'      => $getuserwithmessage
                            ];
                            $conn->send(json_encode($returnData));
                        }
                        break;
                    case "getmessages":
                        if ($userToken['user_id'] != $data->logged_in_user_id ) {
                            $conn->send(json_encode('user not matched'));
                        } else  {
                            $getmessages = $this->getmessages($data->logged_in_user_id,$data->other_user_id,$data->from_date,$data->end_date, $data->per_page, $data->page);
                            $from_date = $data->from_date;
                            $end_date = $data->end_date;
                            $returnData = [
                                'command'   => 'getmessages',
                                'userId'    => $data->other_user_id,
                                'data'      => $getmessages,
                                'from_date' => $from_date,
                                'end_date'  => $end_date,
                                'per_page'  => $data->per_page,
                                'page'  => $data->page
                            ];
                            $conn->send(json_encode($returnData));
                        }
                        break;
                    case "readmessages":
                        if ($userToken['user_id'] != $data->logged_in_user_id ) {
                            $conn->send(json_encode('user not matched'));
                        } else  {
                            $readmessages = $this->readmessages($data->logged_in_user_id,$data->other_user_id);
                            $returnData = [
                                'command'   => 'readmessages',
                                'userId'   => $data->other_user_id,
                                'data'      => null
                            ];
                            $conn->send(json_encode($returnData));

                            if (isset($this->userresources[$data->other_user_id])) {
                                $returnData = [
                                    'command'   => 'readmessagesforother',
                                    'userId'    => $data->other_user_id,
                                    'data'      => $readmessages
                                ];
                                foreach ($this->userresources[$data->other_user_id] as $key => $resourceId) {
                                    if (isset($this->users[$resourceId])) {
                                        $this->users[$resourceId]->send(json_encode($returnData));
                                    }
                                }
                            }
                        }
                        break;
                    case "totalunreadmessage":
                        if ($userToken['user_id'] != $data->userId ) {
                            $conn->send(json_encode('user not matched'));
                        } else  {
                            $totalunreadmessage = $this->totalunreadmessage($data->userId);
                            $returnData = [
                                'command'   => 'totalunreadmessage',
                                'userId'   => $data->userId,
                                'data'      => $totalunreadmessage
                            ];
                            $conn->send(json_encode($returnData));
                        }
                        break;
                    case "register":
                        //
                        if (isset($data->userId)) {
                            if ($userToken['user_id'] != $data->userId ) {
                                $conn->send(json_encode('user not matched'));
                            } else  {
                                if (isset($this->userresources[$data->userId])) {
                                    if (!in_array($conn->resourceId, $this->userresources[$data->userId])) {
                                        $this->userresources[$data->userId][] = $conn->resourceId;
                                    }
                                } else {
                                    $this->userresources[$data->userId] = [];
                                    $this->userresources[$data->userId][] = $conn->resourceId;
                                }
                            }
                        }

                        //for resend all connected user info
                        foreach ($this->users as $client) {
                            $returnData = [
                                'command'   => 'connectedusers',
                                'data'      => $this->userresources
                            ];
                            $client->send(json_encode($returnData));
                        }

                        //Total unread message
                        $totalunreadmessage = $this->totalunreadmessage($data->userId);
                        $returnData = [
                            'command'   => 'totalunreadmessage',
                            'userId'   => $data->userId,
                            'data'      => $totalunreadmessage
                        ];
                        $conn->send(json_encode($returnData));

                        //$conn->send(json_encode($this->users));
                        //$conn->send(json_encode($this->userresources));
                        break;
                    case "disconnect":
                        if ($userToken['user_id'] != $data->userId ) {
                            $conn->send(json_encode('user not matched'));
                        } else  {
                            
                            $this->clients->detach($conn);
                            unset($this->users[$conn->resourceId]);
                            unset($this->subscriptions[$conn->resourceId]);

                            foreach ($this->userresources as &$userId) {
                                foreach ($userId as $key => $resourceId) {
                                    if ($resourceId == $conn->resourceId) {
                                        unset($userId[$key]);
                                    }
                                }
                            }
                        }
                        break;
                    default:
                        $conn->send(json_encode('Invalid message format'));
                        break;
                }
                
            } else {
               $conn->send(json_encode('Please pass token'));
            }
        } else {
          
            $conn->send(json_encode('Invalid message format'));
        }
    }

    public function onClose(ConnectionInterface $conn) 
    {
        $this->clients->detach($conn);
        //$conn->send(json_encode('Connection '.$conn->resourceId.' has disconnected'));
        unset($this->users[$conn->resourceId]);
        unset($this->subscriptions[$conn->resourceId]);

        foreach ($this->userresources as &$userId) {
            foreach ($userId as $key => $resourceId) {
                if ($resourceId == $conn->resourceId) {
                    unset($userId[$key]);
                }
            }
        }

        //for resend all connected user info
        foreach ($this->users as $client) {
            $returnData = [
                'command'   => 'connectedusers',
                'data'      => $this->userresources
            ];
            $client->send(json_encode($returnData));
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e) 
    {
        $conn->send(json_encode('An error has occurred '.$e->getMessage().''));
        $conn->close();
    }

    private function getUsers($userId)
    {
        $parent_id = \DB::table('users')->select('parent_id')->where('id', $userId)->first()->parent_id;
        $query = User::select('users.id', 'users.name','users.userType')
                ->where('users.status', '1')
                ->where('users.id', '!=', $userId);
        if(!in_array($parent_id, ['', null, 1]))
        { 
            $query->where(function($q) use ($parent_id) {
                $q->where('users.current_parent_id', $parent_id)
                    ->orWhere('users.parent_id', $parent_id);
            });
        }
        $query = $query->get();
        foreach ($query as $key => $user) {
            $data = Message::select(DB::raw("(SELECT count(*) from messages WHERE messages.receiver_id = ".$userId." AND messages.sender_id = ".$user->id." AND messages.read_at IS NULL) unread_messages_count"))->first();
            $query[$key]['unread_messages_count'] = ($data) ? $data->unread_messages_count : 0;
        }

        if(!in_array($parent_id, ['', null, 1]))
        {
            $checkResellerClient = User::select('userType')->find($userId);
            if($checkResellerClient && in_array($checkResellerClient->userType, [1,2]))
            {
                $adminInfo = User::select('users.id', 'users.name','users.userType')
                    ->where('userType', 1)
                    ->withoutGlobalScope('parent_id')
                    ->first();
                $getAdminCount = Message::where('sender_id', $adminInfo->id)
                    ->where('receiver_id', $userId)
                    ->whereNull('read_at')
                    ->withoutGlobalScope('parent_id')
                    ->count();
                $query[$query->count()] = [
                    'id' => $adminInfo->id,
                    'name' => $adminInfo->name,
                    'unread_messages_count' => $getAdminCount
                ];
            }
        }
        return $query;
    }

    private function getuserwithmessage($userId)
    {
        $query = Message::with('sender:id,name,userType', 'receiver:id,name,userType')
            ->orderBy('id', 'DESC')
            ->where(function($q) use ($userId) {
                $q->where('sender_id', $userId)
                ->orWhere('receiver_id', $userId);
            })
            ->get()
            ->unique('receiver_id');
        foreach ($query as $key => $user) {
            $data = Message::select(DB::raw("(SELECT count(*) from messages WHERE messages.receiver_id = ".$userId." AND messages.sender_id = ".$user->sender_id." AND messages.read_at IS NULL) unread_messages_count"))->first();
            $query[$key]['unread_messages_count'] = ($data) ? $data->unread_messages_count : 0;
        }
        return $query;
    }

    private function getmessages($logged_in_user_id, $other_user_id, $from_date, $end_date, $perPage, $page)
    {
        $query = Message::with('sender:id,name,userType', 'receiver:id,name,userType')
            ->whereIn('sender_id', [$logged_in_user_id, $other_user_id])
            ->whereIn('receiver_id', [$logged_in_user_id, $other_user_id]);

        if (!empty($from_date) && !empty($end_date)) {
            $query->whereBetween('created_at', [$from_date.' 00:00:00', $end_date.' 23:59:59']);
            
        }

        if(!empty($perPage))
        {
            $perPage = $perPage;
            $page = !empty($page) ? $page : 1;
            $total = $query->count();
            $result = $query->offset(($page - 1) * $perPage)->limit($perPage)->orderBy('id', 'DESC')->get()->reverse()->values();

            $pagination =  [
                'data' => $result,
                'total' => $total,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage)
            ];
            $query = $pagination;
        }
        else
        {
            // last 7 days
            $query->whereBetween('created_at', [(new Carbon)->subDays(7)->startOfDay()->toDateString(), (new Carbon)->now()->endOfDay()->toDateString()]);
            $query = $query->orderBy('id', 'ASC')->get();
            //if message count is less than 20 then load all messages
            if ($query->count() < 20) {
                $query = Message::with('sender:id,name,userType', 'receiver:id,name,userType')
                ->whereIn('sender_id', [$logged_in_user_id, $other_user_id])
                ->whereIn('receiver_id', [$logged_in_user_id, $other_user_id])
                ->orderBy('id', 'ASC')
                ->get();
            }
            $query = $query->orderBy('id', 'ASC')->get();
        }
        return $query;
    }

    private function readmessages($logged_in_user_id, $other_user_id)
    {
        $query = Message::where('sender_id', $other_user_id)
            ->where('receiver_id', $logged_in_user_id)
            ->update([
                'read_at' => date('Y-m-d H:i:s')
            ]);
        return $query;
    }

    private function totalunreadmessage($userId)
    {
        $query = Message::where('receiver_id', $userId)->whereNull('read_at')->count();
        return $query;
    }
}
