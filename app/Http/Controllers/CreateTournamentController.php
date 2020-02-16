<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Tournaments;
use App\Players;
use App\Fixtures;

class CreateTournamentController extends Controller
{
    public function __construct()
    {
        //
    }

    public function createTournament(Request $request) {
        $data = $request->input('data');
        if(!empty($data)) {
            // decode actual args are specify expected args
            $args = json_decode($data, true);
            $expectedArgs = [
                'tournamentName',
                'numberOfPlayers',
                'numberOfGroups',
                'startDate',
                'numberOfPvpFixtures',
                'weeksBetweenFixtures',
                'playersToProgress',
                'newPlayers'
            ];
            // verify and map args to vars
            foreach($expectedArgs as $expectedArg) {
                if(!empty($args[$expectedArg])) {
                    $$expectedArg = $args[$expectedArg];
                } else {
                    // ERROR RESPONSE: MISSING ARG
                    return $this->error("Missing $expectedArg Argument", 422);
                }
            }
            // TOURNAMENT
            // create new tournament entry
            $tournamentResult = Tournaments::create([
                // TODO: better random uid generation solution
                'uid'                  => substr(md5(time()), 0, 16),
                'tournamentName'       => $tournamentName,
                'numberOfPlayers'      => $numberOfPlayers, 
                'numberOfGroups'       => $numberOfGroups,
                'numberOfPvpFixtures'  => $numberOfPvpFixtures,
                'weeksBetweenFixtures' => $weeksBetweenFixtures,
                'playersToProgress'    => $playersToProgress,
                'startDate'            => $startDate,
            ]);
            // TODO: verify tournament entry
            // get tournamentId
            $tournamentId = $tournamentResult['id'];

            // PLAYERS
            foreach($newPlayers as $player) {
                // verify player 
                if(empty($player['name'])) return $this->error("1 or more players are missing data", 422);
                // create new player entry
                $playerEntries[] = Players::create([
                    'tournamentId' => $tournamentId,
                    'name'         => $player['name'],
                    'fplLink'      => $player['fplLink'] ?? 'default',
                    'userId'       => 1
                ]);
            }

            // FIXTURES
            // get the fixture rows
            $fixtures = $this->getFixtureRows($playerEntries, 
                                              $numberOfGroups, 
                                              $startDate, 
                                              $weeksBetweenFixtures, 
                                              $numberOfPvpFixtures, 
                                              $tournamentId);
            foreach($fixtures as $fixture) {
                // create new fixture row
                Fixtures::create($fixture);
            }

            // Return Success Response
            return response(['status' => true], 200);
        }
        // ERROR RESPONSE: NO DATA
        return $this->error("No Data Provided", 422);
    }

    private function getFixtureRows($players, 
                                    $numberOfGroups, 
                                    $startDate, 
                                    $weeksBetweenFixtures, 
                                    $numberOfPvpFixtures,
                                    $tournamentId) {

        // seperate players into groups
        shuffle($players);
        $maxPlayersPerGroup = ceil(count($players) / $numberOfGroups);
        $groups = array_chunk($players, $maxPlayersPerGroup);
        // calculate number of match weeks
        $matchWeeks = ($maxPlayersPerGroup - 1) * $numberOfPvpFixtures;
        // get and return fixtures for groups
        $fixtures = [];
        $index = 0;
        foreach($groups as $group) {
            $groupLetter = chr(64 + $index + 1);
            $fixtures = array_merge($fixtures, $this->calculateFixtures($group, 
                                                                        $matchWeeks, 
                                                                        $weeksBetweenFixtures, 
                                                                        $startDate, 
                                                                        $groupLetter, 
                                                                        $tournamentId));
            $index++;
        }  
        return $fixtures;                               
    }

    private function calculateFixtures($players, 
                                       $matchWeeks, 
                                       $weeksBetweenFixtures, 
                                       $startDate, 
                                       $groupLetter, 
                                       $tournamentId) {
        $numberOfPlayers = count($players);
        if($numberOfPlayers % 2 === 1) {
            // add a dummy player to keep algorithm balanced
            $players[] = ['name' => 'dummy'];
        }
        $breakPoint = count($players) / 2;
        $fixtures = [];
        for($i = 0; $i < $matchWeeks; $i++) {
            $weekOffset = $i * $weeksBetweenFixtures;
            $fixtureDate = date('Y/m/d', strtotime("$startDate +$weekOffset weeks"));
            $weeksFixtures = [];
            for($k = 0; $k < $breakPoint; $k++) {
                $player1 = $players[$k];
                $player2 = $players[count($players) - 1 - $k];
                if($player2['name'] !== "dummy" && $player1['name'] !== "dummy"){
                    // $weeksFixtures[] = $player1 . " vs " . $player2;  
                    $fixtures[] = [
                        'tournamentId' => $tournamentId,
                        'homePlayerId' => $player1['id'],
                        'awayPlayerId' => $player2['id'],
                        'group'        => $groupLetter,
                        'date'         => $fixtureDate
                    ];
                }
            }
            // $fixtures[$fixtureDate] = $weeksFixtures;
            $players[] = array_shift($players);
        }
        
        return $fixtures;
    }
}
