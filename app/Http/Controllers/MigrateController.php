<?php
// Controller designed for migrating data from one deployed API to local or another host
// Originally written to migrate from Heroku to HelioHost

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Fixtures;
use App\Tournaments;
use App\Players;
use App\Messages;
// use App\User;



class MigrateController extends Controller
{
    public function __contstruct()
    {
        //
    }

    private function fetchMigrationData($url)
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->request("GET", $url);
        $body = json_decode($response->getBody(), true);

        return $body;
    }
    
    public function tournaments(Request $request)
    { 
        $data = $this->fetchMigrationData('https://stormy-gorge-28890.herokuapp.com/tournament');
        $result = Tournaments::insert($data);

        return response($result);
    }

    public function players(Request $request)
    {
        $data = $this->fetchMigrationData('https://stormy-gorge-28890.herokuapp.com/player');
        $result = Players::insert($data);

        return response($result);
    }

    public function fixtures (Request $request)
    {
        $data = $this->fetchMigrationData('https://stormy-gorge-28890.herokuapp.com/fixtures');
        $result = Fixtures::insert($data);

        return response($result);
    }

    public function messages(Request $request)
    {
        $data = $this->fetchMigrationData('https://stormy-gorge-28890.herokuapp.com/message');
        $result = Messages::insert($data);

        return response($result);
    }

    // Not sure users is even used!
    // public function users(Request $request) 
    // {
    //     $data = $this->fetchMigrationData('https://stormy-gorge-28890.herokuapp.com/user');
    //     $result = User::insert($data);

    //     return response($result);
    // }
}

?>