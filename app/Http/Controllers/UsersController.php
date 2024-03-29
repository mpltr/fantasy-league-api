<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Users;

class UsersController extends Controller
{
    public function __construct()
    {
        // nothing required
    }

    public function index()
    {
        return Users::orderBy('name')->get();
    }

    public function show($id)
    {
        $user = Users::where('id', $id)
            ->with('tournaments')
            ->with('tournaments.players', function ($playersQ) use ($id) {
                $playersQ->where('userId', '=', $id);
            })
            ->with('tournaments.fixtures', function ($fixturesQ) use ($id) {
                $fixturesQ->where('homePlayerId', '=', $id)
                    ->orWhere('awayPlayerId', '=', $id)
                    ->with('home_player', 'away_player');
            })
            ->first()->toArray();


        // Outrights
        $allFixtures = array_reduce($user['tournaments'], function ($carry, $tournament) {
            return array_merge($carry, $tournament['fixtures']);
        }, []);

        $user['outrights'] = array_merge(
            [
                ["title" => "Seasons", "value" => count($user['tournaments'])]
            ],
            $this->getFixtureStats($allFixtures, $id)
        );

        // Tournament stats
        foreach ($user['tournaments'] as $key => $tournament) {

            $fixtures = $tournament['fixtures'];
            $user['tournaments'][$key]['stats'] = $this->calculateFixtureTotals($fixtures, $id);
            $user['tournaments'][$key]['stage'] = $this->calculateFurthestStage($fixtures, $id);
        }

        return $user;
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
