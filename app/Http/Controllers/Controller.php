<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    protected $stages = [
        'Group',
        'Last 32',
        'Last 16',
        'Quarter Finals',
        'Semi Finals',
        'Final',
        'Finished'
    ];

    function error($message, $response_code) {
        return response([
            'status' => false, 
            'error' => $message
        ], $response_code);
    }

    public function sortFixturesIntoGroups($fixtures, $players) {
        // sort the fixtures into groups and date 
        foreach($fixtures as $fixture) {
            $homePlayerName = $players[$fixture['homePlayerId']]['name'];
            $groupFixture = [
                'id'              => $fixture['id'],
                'homePlayerId'    => $fixture['homePlayerId'],
                'homePlayerScore' => $fixture['homePlayerScore'],
                'awayPlayerId'    => $fixture['awayPlayerId'],
                'awayPlayerScore' => $fixture['awayPlayerScore'],
                'homePlayerName'  => $homePlayerName,
                'number'          => $fixture['number'],
                'stage'           => in_array($fixture['group'], $this->stages) ? $fixture['group'] : 'Group'
            ];
            $groups[$fixture['group']][$fixture['date']][] = $groupFixture;
        };
        // alphabetise the fixtures in date arrays
        foreach($groups as $group => $dates) {
            foreach($dates as $date => $fixtures) {
                usort($fixtures, function($a, $b) {
                    if($a['number'] !== $b['number']) {
                        return $a['number'] > $b['number'] ? 1 : -1;
                    }
                    return $a['homePlayerName'][0] > $b['homePlayerName'][0] ? 1 : -1;
                });
                $groups[$group][$date] = $fixtures;
            }
        }

        return $groups;
    }

    public function extractPlayersFromFixtures($fixtures) {
        foreach($fixtures as $fixture) {
           $homePlayerId = $fixture['homePlayerId'];
           $awayPlayerId = $fixture['awayPlayerId'];
           $players[$homePlayerId] = $fixture['home_player'];
           $players[$awayPlayerId] = $fixture['away_player'];
        }
        return array_unique($players, SORT_REGULAR);
    }

    public function calculaterPlayerStats($players, $fixtures) {
        $form = [];

        // used to inisialse stats so += can be used
        $initial_results = [
            'win' => 0,
            'draw' => 0,
            'loss' => 0,
            'against' => 0,
            'for' => 0
        ];

        foreach($fixtures as $fixture) {
            // skip ko stage fixtures
            if(in_array($fixture['group'], $this->stages)) continue;
            $home = $fixture['homePlayerId'];
            $away = $fixture['awayPlayerId'];
            $homeScore =  $fixture['homePlayerScore'];
            $awayScore =  $fixture['awayPlayerScore'];

            if($homeScore && $awayScore){
                $result = $homeScore - $awayScore;
                $homeResult = $this->getResultLetter($result);
                $awayResult = $this->getResultLetter($result, false);
                $form[$home][] = $homeResult;
                $form[$away][] = $awayResult;
                // initialise results
                if(!isset($players[$home]['win'])) $players[$home] = array_merge($players[$home], $initial_results);
                if(!isset($players[$away]['win'])) $players[$away] = array_merge($players[$away], $initial_results);
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
        return array_map(function($player) use ($form) {
            $win = $player['win'] ?? null;
            $loss = $player['loss'] ?? null;
            $draw = $player['draw'] ?? null;
            if($win || $loss || $draw) {
                $player['played'] = $win + $loss + $draw;
                $player['points'] = $win * 3 + $draw;
                $player['gd']     = $player['for'] - $player['against'];
            }
            $player['form'] = !empty($form[$player['id']]) ? array_slice($form[$player['id']], -4) : [];
            $player['formPoints'] = array_reduce($player['form'], function($carry, $item) {
                return $carry + $item;
            }, 0);
            return $player;
        }, $players);
    }

    public function getResultLetter($result, $home = true) {
        if($result < 0) {
            $resultLetter = $home ? 0 : 3;
        } elseif ($result > 0 ){
            $resultLetter = $home ? 3 : 0;
        } else {
            $resultLetter = 1;
        }
        return $resultLetter;
    }

    public function assignPlayersToTables($fixtures) {
        foreach($fixtures as $group => $fixturesForDate) {
            if(in_array($group, ['Last 32', 'Last 16', 'Quarter Finals', 'Semi Finals', 'Final'])) continue;
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
        ksort($tables);
        return $tables;
    }

    public function slugify($string) {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string)));
    }

    protected function getLastStage($currentStage) {
        return $this->stages[array_search($currentStage, $this->stages) - 1];
    }

    protected function getLowestScore($fixture) {
        $isHome = $fixture['homePlayerScore'] < $fixture['awayPlayerScore'];
        $score  = $isHome ? $fixture['homePlayerScore'] : $fixture['awayPlayerScore'];

        return [
            'isHome' => $isHome,
            'score'  => $score,
            'name' => $isHome ? $fixture['home_player']['name'] : $fixture['away_player']['name']
        ];
    }

    protected function getHighestScore($fixture) {
        $isHome = $fixture['homePlayerScore'] > $fixture['awayPlayerScore'];
        $score  = $isHome ? $fixture['homePlayerScore'] : $fixture['awayPlayerScore'];

        return [
            'isHome' => $isHome,
            'score'  => $score,
            'name' => $isHome ? $fixture['home_player']['name'] : $fixture['away_player']['name']
        ];
    }

    protected function calculateMostPoints($fixtures) {
        // Most points in fixture
        usort($fixtures, function ($a, $b) {
            $aScore = $this->getHighestScore($a)['score'];
            $bScore = $this->getHighestScore($b)['score'];
            if ($aScore === null) return 1;
            if ($bScore === null) return -1;
            return $aScore > $bScore ? -1 : 1;
        });
        $mostPointsInFixture = reset($fixtures);
        $player = $this->getHighestScore($mostPointsInFixture);
        $opponent = $this->getLowestScore($mostPointsInFixture);

        return [
            'name'     => 'Most Points Scored',
            'stat'    => $player['score'],
            'primaryText'   => $player['name'],
            'secondaryText' => "vs " . $opponent['name'] . " (" . $opponent['score'] . ")",
        ];
      
    }

    protected function calculateLeastPointsAndWon($fixtures) {
        usort($fixtures, function ($a, $b) {
            $aScore = $this->getHighestScore($a)['score'];
            $bScore = $this->getHighestScore($b)['score'];
            if($aScore === null) return 1;
            if($bScore === null) return -1;
            return $aScore < $bScore ? -1 : 1;
        });
        $leastPointsAndWon = reset($fixtures);
        $player = $this->getHighestScore($leastPointsAndWon);
        $opponent = $this->getLowestScore($leastPointsAndWon);

        return [
            'name'     => 'Least Points and Won',
            'stat'    => $player['score'],
            'primaryText'   => $player['name'],
            'secondaryText' => "vs " . $opponent['name'] . " (" . $opponent['score'] . ")",
        ];
    }

    protected function calculateMostPointsAndLost($fixtures) {
        usort($fixtures, function ($a, $b) {
            $aLowest = $this->getLowestScore($a)['score'];
            $bLowest = $this->getLowestScore($b)['score'];
            return $aLowest > $bLowest ? -1 : 1;
        });
        $mostPointsWithoutWin = reset($fixtures);
        $player = $this->getLowestScore($mostPointsWithoutWin);
        $opponent = $this->getHighestScore($mostPointsWithoutWin);

        return [
            'name'     => 'Most Points and Lost',
            'stat'    => $player['score'],
            'primaryText'   => $player['name'],
            'secondaryText' => "vs " . $opponent['name'] . " (" . $opponent['score'] . ")",
        ];
    }

    protected function filterPlayedFixtures($fixtures) {
        return array_filter($fixtures, function ($fixture) {
            return !is_null($fixture['homePlayerScore']) && !is_null($fixture['awayPlayerScore']);
        });
    }

    protected function calculateWinPercentage($fixtures, $playerId) {
        $winLossDraw = $this->calculateFixtureTotals($fixtures, $playerId);

        extract($winLossDraw);

        // TODO: Division by 0 error if no matches!!
        return round(100 / ($matches) * $win, 2);
    }

    protected function calculateFixtureTotals($fixtures, $playerId) {

        return array_reduce($this->filterPlayedFixtures($fixtures), function ($carry, $fixture) use ($playerId) {
            $isHome = $playerId === $fixture['homePlayerId'];
            extract($fixture);

            if($homePlayerScore > $awayPlayerScore) {
                $isHome ? $carry['win']++ : $carry['loss']++;
            } else if ($awayPlayerScore > $homePlayerScore) {
                $isHome ? $carry['loss']++ : $carry['win']++;
            } else {
                $carry['draw']++;
            } 

            $isHome ? $carry['for'] = $carry['for'] + $homePlayerScore : $carry['for'] = $carry['for'] + $awayPlayerScore;
            $isHome ? $carry['against'] = $carry['against'] + $awayPlayerScore : $carry['against'] = $carry['against'] + $homePlayerScore;

            $carry['matches']++;

            return $carry;
        },[
            'win' => 0,
            'loss' => 0,
            'draw' => 0,
            'for' => 0,
            'against' => 0,
            'matches' => 0
        ]);
    }

    protected function calculateOutrightStats($fixtures, $players) {

        $stats = [];
        $stats[] = $this->calculateMostPoints($fixtures);
        $stats[] = $this->calculateLeastPointsAndWon($fixtures);
        $stats[] = $this->calculateMostPointsAndLost($fixtures);

        // Biggest points margin
        // usort($fixtures, function ($a, $b) {
        //     $aHighScore = $this->getHighestScore($a)['score'];
        //     $aLowScore  = $this->getLowestScore($a)['score'];
        //     $bHighScore = $this->getHighestScore($b)['score'];
        //     $bLowScore  = $this->getLowestScore($b)['score'];

        //     if (!$aHighScore || !$aLowScore) return 1;
        //     if (!$bHighScore || !$bLowScore) return -1;

        //     $aMargin = $aHighScore - $aLowScore;
        //     $bMargin = $bHighScore - $bLowScore;
        //     return $aMargin > $bMargin ? -1 : 1;
        // });
        // $biggestPointsMargin = reset($fixtures);
        
        return $stats;
     
        // return [
        //     'mostPointsInFixture'  => $mostPointsInFixture,
        //     'leastPointsAndWon' => $leastPointsAndWon,
        //     'mostPointsWithoutWin' => $mostPointsWithoutWin,
        //     'biggestPointsMargin' => $biggestPointsMargin
        // ];
    }
}
