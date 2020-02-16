<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Tournaments;

class GetTournamentController extends Controller
{
    public function __construct()
    {
        //
    }

    public function getTournament($uid) {
        $data = Tournaments::where('uid', $uid)->with('fixtures', 'fixtures.home_player', 'fixtures.away_player')->first();
        $fixtures = $this->sortFixturesIntoGroups($data['fixtures']);

        $players  = $this->extractPlayersFromFixtures($data['fixtures']);

        $playersWithStats = $this->calculaterPlayerStats($players, $data['fixtures']);
        
        $tables = $this->assignPlayersToTables($fixtures);

        return [
            'fixtures' => $fixtures,
            'players' => $playersWithStats,
            'tables' => $tables
        ];
        
    }

    public function sortFixturesIntoGroups($fixtures) {
        foreach($fixtures as $fixture) {
            $groups[$fixture['group']][$fixture['date']][] = [
                'homePlayerId'    => $fixture['homePlayerId'],
                'homePlayerScore' => $fixture['homePlayerScore'],
                'awayPlayerId'    => $fixture['awayPlayerId'],
                'awayPlayerScore' => $fixture['awayPlayerScore']
            ];
        };
        return $groups;
    }

    public function extractPlayersFromFixtures($fixtures) {
        foreach($fixtures as $fixture) {
           $homePlayerId = $fixture['homePlayerId'];
           $awayPlayerId = $fixture['awayPlayerId'];
           $players[$homePlayerId] = $fixture['home_player'];
           $players[$awayPlayerId] = $fixture['away_player'];
        }
        return array_unique($players);
    }

    public function calculaterPlayerStats($players, $fixtures) {
        foreach($fixtures as $fixture) {
            $home = $fixture['homePlayerId'];
            $away = $fixture['awayPlayerId'];
            $homeScore =  $fixture['homePlayerScore'];
            $awayScore =  $fixture['awayPlayerScore'];
            if($homeScore && $awayScore){
                $result = $homeScore - $awayScore;
                $players[$home]['win']      += $result > 0 ? 1 : 0;
                $players[$home]['draw']     += $result == 0 ? 1 : 0;
                $players[$home]['loss']     += $result < 0 ? 1 : 0;
                $players[$home]['for']      += $homeScore;
                $players[$home]['against']  += $awayScore;
                $players[$away]['win']      += $result < 0 ? 1 : 0;
                $players[$away]['draw']     += $result == 0 ? 1 : 0;
                $players[$away]['loss']     += $result > 0 ? 1 : 0;
                $players[$away]['for']      += $awayScore;
                $players[$away]['against']  += $homeScore;
            }
        }
        // calculate ew points and played
        return array_map(function($player) {
            $win = $player['win'];
            $loss = $player['loss'];
            $draw = $player['draw'];
            if($win || $loss || $draw) {
                $player['played'] = $win + $loss + $draw;
                $player['points'] = $win * 3 + $draw;
                $player['gd']     = $player['for'] - $player['against'];
            }
            return $player;
        }, $players);
    }

    public function assignPlayersToTables($fixtures) {
        foreach($fixtures as $group => $fixturesForDate) {
            foreach($fixturesForDate as $date => $fixtures){
                foreach($fixtures as $fixture){
                    $tables[$group][] = $fixture['homePlayerId'];
                    $tables[$group][] = $fixture['awayPlayerId'];
                }
            }
        };
        foreach($tables as $index => $table) {
            $tables[$index] = array_unique($table);
        }
        return $tables;
    }
}
