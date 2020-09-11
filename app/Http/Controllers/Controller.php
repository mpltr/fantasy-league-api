<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    function error($message, $response_code) {
        return response([
            'status' => false, 
            'error' => $message
        ], $response_code);
    }

    public function sortFixturesIntoGroups($fixtures) {
        foreach($fixtures as $fixture) {
            $groups[$fixture['group']][$fixture['date']][] = [
                'id'              => $fixture['id'],
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
            if(in_array($group, ['Last 16', 'Quarters', 'Semis', 'Final'])) continue;
            // get all player ids for group fixtures
            foreach($fixturesForDate as $date => $fixtures){
                foreach($fixtures as $fixture){
                    $home_player_id = $fixture['homePlayerId'];
                    $away_player_id = $fixture['awayPlayerId'];
                    // ensure group index exists
                    if(empty($tables[$group])) $tables[$group] = [];
                    // only put player IDS in array if unique
                    if(!in_array($home_player_id, $tables[$group]))  $tables[$group][] = $fixture['homePlayerId'];
                    if(!in_array($away_player_id, $tables[$group]))  $tables[$group][] = $fixture['awayPlayerId'];
                }
            }
        };
        return $tables;
    }

    public function slugify($string) {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string)));
    }
}
