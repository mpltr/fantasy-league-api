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

                if($current_stage && $current_stage) {
                    if($current_stage === 'Group') {
                        $newFixtures = $this->createFixturesForFirstKnockoutRound($tournament);
                    } elseif($current_stage !== 'Final') {
                        $newFixtures = $this->getFixturesForNextKnockoutRound($tournament);
                    }
                    
                    if(!empty($newFixtures)) {
                        foreach($newFixtures as $fixture) {
                            // create new fixture rows
                            Fixtures::create($fixture);
                        }
                        $message = "Fixtures Updated and New Fixtures Generated for " . $this->getNextStage($current_stage);
                    }

                    $this->setNextTournamentStage($id, $current_stage);

                    if($success) {
                        return response([
                            'status' => true, 
                            'message' => $message ?? 'Fixtures uodated and Tournament Complete!',
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
        $tournamentFixtures = json_decode($tournament['fixtures'], true);
        $currentStage = $tournament['stage']; 

        $fixtures = [];

        $tournamentId = $tournament['id'];
        
        // 32 teams
        $nextStage = $this->getNextStage($currentStage);
        $fixtureNumber = 0; // used to order the fixtures for KO table
        for($i = 0; $i < 4; $i++) {
            $homeId = $qualifiedPlayers[0][0];
            $awayId = $qualifiedPlayers[3][7];
            $fixtures[] = [
                'tournamentId' => $tournamentId,
                'homePlayerId' => $homeId,
                'awayPlayerId' => $awayId,
                'winnerIfDraw' => $this->getWinnerIfDraw($homeId, $awayId, $tournamentFixtures, $currentStage),
                'group'        => $nextStage,
                'number'       => $fixtureNumber, 
                'date'         => $fixtureDate
            ];
            $homeId = $qualifiedPlayers[1][3];
            $awayId = $qualifiedPlayers[2][4];
            $fixtures[] = [
                'tournamentId' => $tournamentId,
                'homePlayerId' => $homeId,
                'awayPlayerId' => $awayId,
                'winnerIfDraw' => $this->getWinnerIfDraw($homeId, $awayId, $tournamentFixtures, $currentStage),
                'group'        => $nextStage,
                'number'       => $fixtureNumber + 1,
                'date'         => $fixtureDate
            ];  
            $homeId = $qualifiedPlayers[0][2];
            $awayId = $qualifiedPlayers[3][5];
            $fixtures[] = [
                'tournamentId' => $tournamentId,
                'homePlayerId' => $homeId,
                'awayPlayerId' => $awayId,
                'winnerIfDraw' => $this->getWinnerIfDraw($homeId, $awayId, $tournamentFixtures, $currentStage),
                'group'        => $nextStage,
                'number'       => $fixtureNumber + 2,
                'date'         => $fixtureDate
            ];  
            $homeId = $qualifiedPlayers[1][1];
            $awayId = $qualifiedPlayers[2][6];
            $fixtures[] = [
                'tournamentId' => $tournamentId,
                'homePlayerId' => $homeId,
                'awayPlayerId' => $awayId,
                'winnerIfDraw' => $this->getWinnerIfDraw($homeId, $awayId, $tournamentFixtures, $currentStage),
                'group'        => $nextStage,
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

    private function getFixturesForNextKnockoutRound($tournament) {
        $currentStage = $tournament['stage'];
        $tournamentId = $tournament['id'];
        $tournamentFixtures = json_decode($tournament['fixtures'], true);

        $currentStageFixtures = $this->getFixturesForStage($tournamentFixtures, $currentStage);
        $fixtureDate = $this->getNextFixtureDate($tournament['fixtures'], $tournament['weeksBetweenFixtures']);

        $fixtures = [];

        $fixtureNumber = 0;
        for($i = 0; $i < count($currentStageFixtures); $i +=2) {
            $firstWinner = $this->getFixtureWinner($currentStageFixtures[$i]);
            $secondWinner = $this->getFixtureWinner($currentStageFixtures[$i + 1]);

            $fixtures[] = [
                'tournamentId' => $tournamentId,
                'homePlayerId' => $firstWinner,
                'awayPlayerId' => $secondWinner,
                'winnerIfDraw' => $this->getWinnerIfDraw($firstWinner, $secondWinner, $tournamentFixtures, $currentStage),
                'group'        => $this->getNextStage($currentStage), // TODO: Switch for next stage from stages
                'number'       => $fixtureNumber,
                'date'         => $fixtureDate
            ];

            $fixtureNumber++;
        }

        return $fixtures;
    }

    private function getFixtureWinner($fixture) {
        extract($fixture);

        if($homePlayerScore !== $awayPlayerScore) {
            if($awayPlayerScore > $homePlayerScore) {
                return $awayPlayerId;
            } else {
                return $homePlayerId;
            }
        }
        
        return $homePlayerId;
    }

    private function getWinnerIfDraw($homeId, $awayId, $fixtures, $stage) {
        // try and get if not group 
        if($stage !== 'Group') {
            // get fixtures for both home and away players
            $homeFixtures = $this->getKnockoutFixturesByPlayerId($fixtures, $homeId);
            $awayFixtures = $this->getKnockoutFixturesByPlayerId($fixtures, $awayId);

            // compare to see who had more points in each round counting back
            // return a winner if one is bett
            for($i = 0; $i < count($homeFixtures); $i++) {
                $fixture = $homeFixtures[$i];
                $homePoints = $fixture[$homeId === $fixture['homePlayerId'] ? 'homePlayerScore' : 'awayPlayerScore'];
                $awayPoints = $fixture[$awayId === $fixture['homePlayerId'] ? 'homePlayerScore' : 'awayPlayerScore'];

                if($homePoints !== $awayPoints) return $homePoints > $awayPoints ? $homeId : $awayId;
            }
        } else {
            // Temp: fake tournament object for use with methods
            // Will be solved by decoding fixtures in relationship
            $tournament       = ['fixtures' => json_encode($fixtures)];
            $players          = $this->extractPlayersFromFixtures($fixtures);
            $playersWithStats = $this->calculaterPlayerStats($players, $fixtures);
            
            $homePlayer = $playersWithStats[$homeId];
            $awayPlayer = $playersWithStats[$awayId];

            // check points first 
            $homeGroupPoints = $homePlayer['points'];
            $awayGroupPoints = $awayPlayer['points'];
            if($homeGroupPoints !== $awayGroupPoints) return $homeGroupPoints > $awayGroupPoints ? $homeId : $awayId;

            // then goal difference
            $homeGd = $homePlayer['gd'];
            $awayGd = $awayPlayer['gd'];
            if($homeGd !== $awayGd) return $homeGd > $awayGd ? $homeId : $awayId;

            // then goals for
            $homeFor = $homePlayer['for'];
            $awayFor = $awayPlayer['for'];
            if($homeFor !== $awayFor) return $homeFor > $awayFor ? $homeId : $awayId;

        }

        return null;
    }

    private function sortFixturesByDate($a, $b) {
        return $a['date'] > $b['date'] ? 1 : -1;
    }

    private function sortFixturesByNumber($a, $b) {
        return $a['number'] > $b['number'] ? 1 : -1;
    }

    private function getFixturesForStage($fixtures, $stage) {
        $stageFixtures = array_values(array_filter($fixtures, function($fixture) use($stage) {
            return $fixture['group'] === $stage;
        }));
        // TODO: sort by NUMBER! not date
        usort($stageFixtures, array($this, 'sortFixturesByNumber'));
        return $stageFixtures;
    }

    private function getKnockoutFixturesByPlayerId($fixtures, $playerId) {
        $knockoutFixtures = array_values(array_filter($fixtures, function($fixture) use($playerId){
            return in_array($playerId, [$fixture['homePlayerId'], $fixture['awayPlayerId']]) && in_array($fixture['group'], $this->stages);
        }));
        usort($knockoutFixtures, array($this, 'sortFixturesByDate'));
        return $knockoutFixtures;
    }

    private function getNextStage($currentStage) {
        return $this->stages[array_search($currentStage, $this->stages) + 1];
    }

    private function setNextTournamentStage($id, $currentStage) {
        Tournaments::where('id', $id)->update([
            'stage' => $this->getNextStage($currentStage)
        ]);
    }
}
