<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Users;

class UsersController extends Controller
{
    public function __construct()
    {
        //
    }

    public function index()
    {
        $data = Users::all();

        return $data;
    }

    public function show($id)
    {

        $user = Users::where('id', $id)
                    ->with('tournaments', 'players', 'players.fixtures')
                    ->first();
                    
        $allFixtures = array_reduce($user->players->toArray(), function ($carry, $player) {
            $carry = array_merge($carry, $player['fixtures']);
            return $carry;
        }, []);

        return [
            "outrights" => array_merge([["title" => "Seasons", "value" => count($user->tournaments)]], $this->getFixtureStats($allFixtures, $id))
        ];
    }

    private function getFixtureStats($fixtures, $playerId)
    {   
        extract($this->calculateFixtureTotals($fixtures, $playerId));

        return [
            [
                "title" => "Matches",
                "value" => $matches
            ],
            [
                "title" => "Win %",
                "value" => $this->calculateWinPercentage($fixtures, $playerId)  . "%"
            ],
            [
                'title' => "Won",
                "value" => $win
            ],
            [
                'title' => "Loss",
                "value" => $loss
            ],
            [
                'title' => "Drawn",
                "value" => $draw
            ],
            [
                'title' => "For",
                'value' => $for
            ],
            [
                'title' => "Against",
                'value' => $against
            ],
            [
                'title' => "P/D",
                'value' => $for - $against
            ],
        ];
    }
}