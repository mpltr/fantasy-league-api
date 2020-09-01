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
                'numberOfKnockoutRounds',
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
            $numberOfKnockoutFixturesMap = [
                '1' => 1,
                '2' => 2,
                '3' => 4,
                '4' => 8,
                '5' => 16,
                '6' => 32
            ];
            $numberOfKnockoutFixtures     = $numberOfKnockoutFixturesMap[$numberOfKnockoutRounds];
            $numberOfTeamsToProgress      = $numberOfKnockoutFixtures * 2;
            $numberOfGroupTeamsToProgress = $numberOfTeamsToProgress / $numberOfGroups;

            $tournamentUid = substr(md5(time()), 0, 16);
            $tournamentResult = Tournaments::create([
                // TODO: better random uid generation solution
                'uid'                          => $tournamentUid,
                'tournamentName'               => $tournamentName,
                'numberOfGroups'               => $numberOfGroups,
                'numberOfPvpFixtures'          => $numberOfPvpFixtures,
                'weeksBetweenFixtures'         => $weeksBetweenFixtures,
                'numberOfKnockoutRounds'       => $numberOfKnockoutRounds,
                'numberOfGroupTeamsToProgress' => $numberOfGroupTeamsToProgress,
                'startDate'                    => $startDate
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
            return response(['status' => true, 'tournamentUid' => $tournamentUid], 200);
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
        $maxPlayersPerGroup = ceil(count($players) / $numberOfGroups) ;
        $groups = array_chunk($players, $maxPlayersPerGroup);
        // get and return fixtures for groups
        $fixtures = [];
        $index = 0;
        foreach($groups as $group) {
            $groupLetter = chr(64 + $index + 1);
            $groupFixtures =  $this->calculateFixtures($group, 
                                                       $numberOfPvpFixtures,
                                                       $weeksBetweenFixtures, 
                                                       $startDate, 
                                                       $groupLetter, 
                                                       $tournamentId);
            // return $groupFixtures;
            $fixtures = array_merge($fixtures, $groupFixtures);
            $index++;
        }  
        return $fixtures;                               
    }

    private function calculateFixtures($players, 
                                       $numberOfPvpFixtures, 
                                       $weeksBetweenFixtures, 
                                       $startDate, 
                                       $groupLetter, 
                                       $tournamentId) {
        $originalNumberOfPlayers = count($players);
        // calculate number of match weeks
        if($originalNumberOfPlayers % 2 === 1) {
            // add a dummy player to keep algorithm balanced
            $players[] = ['name' => 'dummy'];
        }
        $numberOfPlayers = count($players);
        $matchWeeks = ($numberOfPlayers - 1) * $numberOfPvpFixtures;
        $breakPoint = count($players) / 2;
        // $fixtures = [];
        for($i = 0; $i < $matchWeeks; $i++) {
            $insight[] = $players;
            $weekOffset = $i * $weeksBetweenFixtures;
            $fixtureDate = date('Y/m/d', strtotime("$startDate +$weekOffset weeks"));
            $weeksFixtures = [];
            for($k = 0; $k < $breakPoint; $k++) {
                $player1 = $players[$k];
                $player2 = $players[count($players) - 1 - $k];
                if($player2['name'] !== "dummy" && $player1['name'] !== "dummy"){
                    $fixtures[] = [
                        'tournamentId' => $tournamentId,
                        'homePlayerId' => $player1['id'],
                        'awayPlayerId' => $player2['id'],
                        'group'        => $groupLetter,
                        'date'         => $fixtureDate
                    ];
                    // used for knockouts
                    $this->dateOfLastFixture = $fixtureDate;
                }
            }
            $players[] = array_splice($players, 1, 1)[0];
        }
        return $fixtures;
    }
}
