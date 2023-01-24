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

    private $host = 'https://mpltr.helioho.st';

    private function fetchMigrationData($url)
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->request("GET", $url);
        $body = json_decode($response->getBody(), true);

        return $body;
    }
    
    public function tournaments(Request $request)
    { 
        $data = $this->fetchMigrationData("$this->host/tournament");
        $result = Tournaments::insert($data);

        return response($result);
    }

    public function players(Request $request)
    {
        $data = $this->fetchMigrationData("$this->host/player");
        $result = Players::insert($data);

        return response($result);
    }

    public function fixtures (Request $request)
    {
        $data = $this->fetchMigrationData("$this->host/fixtures");
        $result = Fixtures::insert($data);

        return response($result);
    }

    public function messages(Request $request)
    {
        $data = $this->fetchMigrationData("$this->host/message");
        $result = Messages::insert($data);

        return response($result);
    }

    // Not sure users is even used!
    // public function users(Request $request) 
    // {
    //     $data = $this->fetchMigrationData('$this->hostuser');
    //     $result = User::insert($data);

    //     return response($result);
    // }
}

?>