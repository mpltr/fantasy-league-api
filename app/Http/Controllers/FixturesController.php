<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Fixtures;
use App\Tournaments;

class FixturesController extends Controller
{
    public function __construct()
    {
        //
    }

    public function store(Request $request) {
        $data = $request->input('data');
        // return $data;
        if(!empty($data)) {
            $args = json_decode($data, true);
            $dates = $args['fixtures'];
            $id = $args['id'];
            $totalFixtures = 0;
            $fixturesWithScores = 0;
            foreach($dates as $date => $fixtures) {
                foreach($fixtures as $fixture) {
                    $totalFixtures++;
                    $homePlayerScore = $fixture['homePlayerScore'] ? $fixture['homePlayerScore'] : null;
                    $awayPlayerScore = $fixture['awayPlayerScore'] ? $fixture['awayPlayerScore'] : null;
                    if(($homePlayerScore || $homePlayerScore === 0) && ($awayPlayerScore || $homePlayerScore === 0)) {
                        $fixturesWithScores++;
                    };
                    $fixtureUpdates[] = Fixtures::where('id', $fixture['id'])->update(
                        [
                            'homePlayerScore' => $homePlayerScore,
                            'awayPlayerScore' => $awayPlayerScore,
                            'date' => $date
                        ]
                    );
                }
            }
            $success = count($fixtureUpdates) == $totalFixtures;
            // if($success && $fixturesWithScores == $totalFixtures) {
            //     // generate knockouts
            //     $knockoutFixtures = $this->generateKnockoutFixtures($id);
            //     foreach($knockoutFixtures as $knockoutFixture) {
            //         Fixtures::create($knockoutFixture);
            //     }
            // }
            if($success) {
                return response([
                    'status' => true, 
                    'message' => 'Fixtures Updated',
                ], 200);
            } else {
                return $this->error("One or more fixures failed to update", 422);
            }
        }
        // ERROR RESPONSE: NO DATA
        return $this->error("No Data Provided", 422);
    }

    private function generateKnockoutFixtures($id) {
        $data = Tournaments::where('id', $id)
                    ->with('fixtures', 'fixtures.home_player', 'fixtures.away_player')
                    ->first();
                
        $fixtures = $this->sortFixturesIntoGroups($data['fixtures']);

        $players  = $this->extractPlayersFromFixtures($data['fixtures']);

        $playersWithStats = $this->calculaterPlayerStats($players, $data['fixtures']);
        
        $tables = $this->assignPlayersToTables($fixtures);

        // TODO: make dynamic for different tournaments
        $topPlayers = [];
        $originalFixtureDates = [];
        foreach($data['fixtures'] as $fixture) {
            // return $fixture;
            $originalFixtureDates[] = $fixture['date'];
        }
        sort($originalFixtureDates);
        $lastFixtureDate = end($originalFixtureDates);
        $weeksBetweenFixtures = $data['weeksBetweenFixtures'];
        $date = date('Y-m-d', strtotime("$lastFixtureDate +$weeksBetweenFixtures weeks"));

        foreach($tables as $groupLetter => $playerIds) {
            foreach($playerIds as $playerId) {
                $topPlayers[$groupLetter][] = $playersWithStats[$playerId];
            }
            usort($topPlayers[$groupLetter], function($a, $b) {
                $aPoints = $a['points'];
                $bPoints = $b['points'];
                $aGd     = $a['gd'];
                $bGd     = $b['gd'];
                if($aPoints === $bPoints) return $bGd - $aGd;
                return $bPoints - $aPoints;
            });
        }

        $knockoutFixtures = [];
       
        foreach($topPlayers as $groupLetter => $players){
            if($groupLetter === 'C') break;
            switch ($groupLetter) {
                case 'A':
                    $oppositionGroupLetter = 'C';
                    break;
                default:
                    $oppositionGroupLetter = 'D';
                    break;
            }
            $indexMatchUps = [
                [0,3],
                [1,2],
                [2,1],
                [3,0]
            ];
            foreach($indexMatchUps as $matchUp) {
                $knockoutFixtures[] = [
                    'tournamentId' => $id, 
                    'homePlayerId' => $topPlayers[$groupLetter][$matchUp[0]]['id'],
                    'awayPlayerId' => $topPlayers[$oppositionGroupLetter][$matchUp[1]]['id'],
                    'group'        => 'Last 16',
                    'date'         => $date
                ];
            }
        }

        return $knockoutFixtures;
    }
}
