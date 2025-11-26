<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ParallelHttpAndDbCommand extends Command
{
    protected $signature = 'http:parallel-db';

    protected $description = 'Check more then 1 parallel HTTP requests with a database connection';

    public function handle()
    {
        // SELECT * FROM `INFORMATION_SCHEMA`.`PROCESSLIST` ORDER BY `Info` ASC;
        echo \Carbon\Carbon::now();
        $uniqueKeys = \DB::table('send_sms_queues')
            ->select('unique_key')
            ->where('is_auto', 0)
            ->where('stat', 'Pending')
            ->limit(1000)
            //->inRandomOrder()
            ->pluck('unique_key');

        // Prepare promises for parallel HTTP requests
        $promises = [];
        foreach ($uniqueKeys as $uniqueKey) {
            // IF laravel update database, uncomment this
            $url = route('json-response', $uniqueKey);

            // IF redis server update database, uncomment this
            //$url = "http://localhost:8009/DLR?msgid=1689657829736061676&d=8&oo=00&ff=0c5e160d-5604-4586-93aa-1d9af55d6aad&s=&ss=&aa=ACK/"
            
            $promises[$url] = $this->makeHttpRequest($url);
        }

        // Wait for all promises to complete
        $results = \GuzzleHttp\Promise\Utils::unwrap($promises);

        // Process the HTTP responses and perform database queries
        foreach ($results as $url => $response) {
            $responseData = $this->processResponse($response);
            $this->storeOrUpdateDataInDatabase($responseData);
        }
        echo '<br>';
        echo \Carbon\Carbon::now();
    }

    // Function to make an HTTP request
    private function makeHttpRequest($url)
    {
        $client = new \GuzzleHttp\Client();
        return $client->getAsync($url);
    }

    // Function to handle the response
    private function processResponse($response)
    {
        // Process the HTTP response if needed and return data to store in the database
        return $response->getBody()->getContents();
    }

    // Function to store data in the database
    private function storeOrUpdateDataInDatabase($data)
    {
        $data = json_decode($data, true);
        if($data['d']==8)
        {
            // submitted (ACCEPTED)
            \DB::table('send_sms_queues')
            ->where('unique_key', $data['msgid'])
            ->update([
                'response_token' => $data['ff'],
                'stat' => 'ACCEPTED',
                'submit_date' => $data['finalDateTime'],
                'status' => 'Completed'
            ]);
        }
        elseif($data['d']==16)
        {
            // due to any error (REJECTED)
            \DB::table('send_sms_queues')
            ->where('unique_key', $data['msgid'])
            ->update([
                'stat' => 'REJECTED',
                'submit_date' => $data['finalDateTime']
            ]);
        }
        else
        {
            // final delivery (DELIVRD, FAILED, EXPIRED...)
            $val = explode(' ', $data['aa']);
            $response_token = str_replace('id:', '', $val[0]);
            $err = str_replace('err:', '', $val[8]);
            $dlvrd = str_replace('dlvrd:', '', $val[2]);
            $stat = str_replace('stat:', '', $val[7]);
            $sub = str_replace('sub:', '', $val[1]);

            \DB::table('send_sms_queues')
            ->where('unique_key', $data['msgid'])
            ->update([
                'response_token' => $response_token,
                'err' => $err,
                'done_date' => $data['finalDateTime'],
                'stat' => $stat,
                'sub' => $sub,
                'dlvrd' => $dlvrd,
            ]);
        }
        return;
    }
}
