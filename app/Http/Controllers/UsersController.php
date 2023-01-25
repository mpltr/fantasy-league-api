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

        $tournaments = array_reduce($user->tournaments->toArray(), function ($carry, $tournament) use ($user) {
            $players = $user->players->toArray();
            $matchingPlayer = $players[array_search($tournament['id'], array_column($players, 'tournamentId'))];
            $tournament['player'] = $matchingPlayer;
            $totals = $this->calculateFixtureTotals($matchingPlayer['fixtures'], $matchingPlayer['id']);
            $carry[$matchingPlayer['id']] = array_merge([
                'name'  => $tournament['uid'],
                'stage' => $this->calculateFurthestStage($matchingPlayer['fixtures'], $matchingPlayer['id']) 
            ], $totals);
            return $carry;
        }, []);

        return [
            "name" => $user->name,
            "outrights" => array_merge([["title" => "Seasons", "value" => count($user->tournaments)]], $this->getFixtureStats($allFixtures, $id)),
            "seasons" => $tournaments,
            'players' => $user->players
        ];
    }

    private function getFixtureStats($fixtures, $playerId)
    {   
        extract($this->calculateFixtureTotals($fixtures, $playerId));

        return [
            [
                "title" => "Played",
                "value" => $played
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
                'title' => "Lost",
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
                'title' => "PD",
                'value' => $for - $against
            ],
        ];
    }
}