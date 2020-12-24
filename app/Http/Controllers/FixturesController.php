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

            // generate next fixtures if all are complete
            if($success && $fixturesWithScores == $totalFixtures) {
                $tournament = Tournaments::where('id', $id)
                    ->with('fixtures', 'fixtures.home_player', 'fixtures.away_player')
                    ->first();
                $current_stage = $tournament['stage'] ?? null;

                if($current_stage) {
                    if($current_stage === 'Group') {
                        $fixtures = $this->createFixturesForFirstKnockoutRound($tournament);
                    }
                    foreach($fixtures as $fixture) {
                        // create new fixture rows
                        Fixtures::create($fixture);
                    }
                    // set the tournaments next stage
                    Tournaments::where('id', $id)->update([
                        'stage' => 'Last 32'
                    ]);

                    if($success) {
                        return response([
                            'status' => true, 
                            'message' => 'Fixtures Updated and New Fixtures Generated',
                        ], 200);
                    } else {
                        return $this->error("One or more fixures failed to update", 422);
                    }
                }
            }
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

    public function createFixturesForFirstKnockoutRound($tournament) {
        // get the qualified players seperated into their groups
        $qualifiedPlayers = $this->getGroupQualifiers($tournament);
        $fixtureDate = $this->getNextFixtureDate($tournament['fixtures'], $tournament['weeksBetweenFixtures']);

        $fixtures = [];

        $tournamentId = $tournament['id'];
        
        // 32 teams
        $fixtureNumber = 0; // used to order the fixtures for KO table
        for($i = 0; $i < 4; $i++) {
            $fixtures[] = [
                'tournamentId' => $tournamentId,
                'homePlayerId' => $qualifiedPlayers[0][0],
                'awayPlayerId' => $qualifiedPlayers[3][7],
                'group'        => 'last32',
                'number'       => $fixtureNumber, 
                'date'         => $fixtureDate
            ];
            $fixtures[] = [
                'tournamentId' => $tournamentId,
                'homePlayerId' => $qualifiedPlayers[1][3],
                'awayPlayerId' => $qualifiedPlayers[2][4],
                'group'        => 'last32',
                'number'       => $fixtureNumber + 1,
                'date'         => $fixtureDate
            ];  
            $fixtures[] = [
                'tournamentId' => $tournamentId,
                'homePlayerId' => $qualifiedPlayers[0][2],
                'awayPlayerId' => $qualifiedPlayers[3][5],
                'group'        => 'last32',
                'number'       => $fixtureNumber + 2,
                'date'         => $fixtureDate
            ];  
            $fixtures[] = [
                'tournamentId' => $tournamentId,
                'homePlayerId' => $qualifiedPlayers[1][1],
                'awayPlayerId' => $qualifiedPlayers[2][6],
                'group'        => 'last32',
                'number'       => $fixtureNumber + 3,
                'date'         => $fixtureDate
            ];  
            $fixtureNumber += 4;
            // moves the last group to the beginning of array 
            // so we can do all 4 quarters of the draw
            array_unshift( $qualifiedPlayers, array_pop( $qualifiedPlayers ) );
        }

        return $fixtures;
    }

    private function getGroupQualifiers($tournament) {
        // get the players with stats
        $players          = $this->extractPlayersFromFixtures($tournament['fixtures']);
        $fixtures         = $this->sortFixturesIntoGroups($tournament['fixtures'], $players);
        $playersWithStats = $this->calculaterPlayerStats($players, $tournament['fixtures']);
        $tables           = $this->assignPlayersToTables($fixtures);

        // sort tables
        foreach($tables as $groupLetter => $group) {
            usort($tables[$groupLetter], function($a, $b) use($playersWithStats) {
                $aStats = $playersWithStats[$a];
                $bStats = $playersWithStats[$b];

                $aPoints = $aStats['points'];
                $bPoints = $bStats['points'];
                $aGd     = $aStats['gd'];
                $bGd     = $bStats['gd'];
                if($aPoints === $bPoints) return $bGd - $aGd;
                return $bPoints - $aPoints;
            });
            // reduce to only qualified players
            $tables[$groupLetter] = array_slice($tables[$groupLetter], 0, $tournament['numberOfGroupTeamsToProgress']);
        }

        // make sure tables array is in order: A,B,C,D etc...
        ksort($tables);

        // return groups without letter keys
        return array_values($tables);
    }

    private function getNextFixtureDate($fixtures, $weeksBetweenFixtures) {
        $latestFixtureDate = max(array_map(function($fixture){
            return $fixture['date'];
        }, json_decode($fixtures, true)));

        return date('Y-m-d', strtotime("+$weeksBetweenFixtures weeks", strtotime($latestFixtureDate)));
    }
}
