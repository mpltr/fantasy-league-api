<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Players;
use App\Users;


class UnificationController extends Controller
{
    public function __construct()
    {
        //
    }

    public function players(Request $request)
    {
        $players = Players::all();
        $users   = Users::all()->toArray();

        $usersIndex = array_reduce($users, function ($carry, $user){
            $carry[$user['name']] = $user['id'];
            return $carry;
        }, []);
        foreach ($players as $player) {
            $name = $player->name;
            if (!array_key_exists($name, $usersIndex)){
                $result = Users::create([
                    'name' => $name
                ]);
                $usersIndex[$name] = $result['id'];
            }
            Players::where('id', $player->id)->update([
                'userId' => $usersIndex[$name]
            ]);
        }
        return $usersIndex;
    }

}
