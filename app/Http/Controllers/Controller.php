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

    function error($message, $responseCode = 200)
    {
        return response([
            'status' => false,
            'error' => $message
        ], $responseCode);
    }

    public function sortFixturesIntoGroups($fixtures, $players)
    {
        // sort the fixtures into groups and date
        foreach ($fixtures as $fixture) {
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
        foreach ($groups as $group => $dates) {
            foreach ($dates as $date => $fixtures) {
                usort($fixtures, function ($a, $b) {
                    if ($a['number'] !== $b['number']) {
                        return $a['number'] > $b['number'] ? 1 : -1;
                    }
                    return $a['homePlayerName'][0] > $b['homePlayerName'][0] ? 1 : -1;
                });
                $groups[$group][$date] = $fixtures;
            }
        }

        return $groups;
    }

    public function extractPlayersFromFixtures($fixtures)
    {
        foreach ($fixtures as $fixture) {
            $homePlayerId = $fixture['homePlayerId'];
            $awayPlayerId = $fixture['awayPlayerId'];
            $players[$homePlayerId] = $fixture['home_player'];
            $players[$awayPlayerId] = $fixture['away_player'];
        }
        return array_unique($players, SORT_REGULAR);
    }

    public function calculaterPlayerStats($players, $fixtures)
    {
        return array_map(function ($player) use ($fixtures) {
            return array_merge($player, $this->calculateFixtureTotals($fixtures, $player['id'], ['A', 'B', 'C', 'D']));
        }, $players);
    }

    public function getResultLetter($result, $home = true)
    {
        if ($result < 0) {
            $resultLetter = $home ? 0 : 3;
        } elseif ($result > 0) {
            $resultLetter = $home ? 3 : 0;
        } else {
            $resultLetter = 1;
        }
        return $resultLetter;
    }

    public function assignPlayersToTables($fixtures)
    {
        foreach ($fixtures as $group => $fixturesForDate) {
            if (in_array($group, ['Last 32', 'Last 16', 'Quarter Finals', 'Semi Finals', 'Final'])) continue;
            // get all player ids for group fixtures
            foreach ($fixturesForDate as $date => $fixtures) {
                foreach ($fixtures as $fixture) {
                    $home_player_id = $fixture['homePlayerId'];
                    $away_player_id = $fixture['awayPlayerId'];
                    // ensure group index exists
                    if (empty($tables[$group])) $tables[$group] = [];
                    // only put player IDS in array if unique
                    if (!in_array($home_player_id, $tables[$group]))  $tables[$group][] = $fixture['homePlayerId'];
                    if (!in_array($away_player_id, $tables[$group]))  $tables[$group][] = $fixture['awayPlayerId'];
                }
            }
        };
        ksort($tables);
        return $tables;
    }

    public function slugify($string)
    {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string)));
    }

    protected function getLastStage($currentStage)
    {
        return $this->stages[array_search($currentStage, $this->stages) - 1];
    }

    protected function getLowestScore($fixture)
    {
        $isHome = $fixture['homePlayerScore'] < $fixture['awayPlayerScore'];
        $score  = $isHome ? $fixture['homePlayerScore'] : $fixture['awayPlayerScore'];

        return [
            'isHome' => $isHome,
            'score'  => $score,
            'name' => $isHome ? $fixture['home_player']['name'] : $fixture['away_player']['name']
        ];
    }

    protected function getHighestScore($fixture)
    {
        $isHome = $fixture['homePlayerScore'] > $fixture['awayPlayerScore'];
        $score  = $isHome ? $fixture['homePlayerScore'] : $fixture['awayPlayerScore'];

        return [
            'isHome' => $isHome,
            'score'  => $score,
            'name' => $isHome ? $fixture['home_player']['name'] : $fixture['away_player']['name']
        ];
    }

    protected function calculateMostPoints($fixtures)
    {
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

    protected function calculateLeastPointsAndWon($fixtures)
    {
        usort($fixtures, function ($a, $b) {
            $aScore = $this->getHighestScore($a)['score'];
            $bScore = $this->getHighestScore($b)['score'];
            if ($aScore === null) return 1;
            if ($bScore === null) return -1;
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

    protected function calculateMostPointsAndLost($fixtures)
    {
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

    protected function filterPlayedFixtures($fixtures)
    {
        return array_filter($fixtures, function ($fixture) {
            return !is_null($fixture['homePlayerScore']) && !is_null($fixture['awayPlayerScore']);
        });
    }

    protected function calculateWinPercentage($fixtures, $playerId)
    {
        $winLossDraw = $this->calculateFixtureTotals($fixtures, $playerId);

        extract($winLossDraw);

        return $played ? round(100 / ($played) * $win) : 0;
    }

    protected function calculateFixtureTotals($fixtures, $playerId, $stages = null)
    {
        return array_reduce(
            $this->filterPlayedFixtures($fixtures),
            function ($carry, $fixture)
            use ($playerId, $stages) {
                if ($fixture['homePlayerId'] == $playerId || $fixture['awayPlayerId'] == $playerId) {
                    if (!$stages || in_array($fixture['group'], $stages)) {


                        $isHome = $playerId === $fixture['homePlayerId'];
                        extract($fixture);

                        if ($homePlayerScore > $awayPlayerScore) {
                            if ($isHome) {
                                $carry['win']++;
                                $carry['points'] = $carry['points'] + 3;
                                $carry['form'][] = 3;
                                $carry['formPoints'] = $carry['formPoints'] + 3;
                            } else {
                                $carry['loss']++;
                                $carry['form'][] = 0;
                            }
                        } elseif ($awayPlayerScore > $homePlayerScore) {
                            if ($isHome) {
                                $carry['loss']++;
                                $carry['form'][] = 0;
                            } else {
                                $carry['win']++;
                                $carry['points'] = $carry['points'] + 3;
                                $carry['form'][] = 3;
                                $carry['formPoints'] = $carry['formPoints'] + 3;
                            }
                        } else {
                            $carry['draw']++;
                            $carry['points']++;
                            $carry['form'][] = 1;
                            $carry['formPoints']++;
                        }

                        if ($isHome) {
                            $carry['for'] = $carry['for'] + $homePlayerScore;
                            $carry['against'] = $carry['against'] + $awayPlayerScore;
                            $carry['gd'] = $carry['gd'] + ($homePlayerScore - $awayPlayerScore);
                        } else {
                            $carry['for'] = $carry['for'] + $awayPlayerScore;
                            $carry['against'] = $carry['against'] + $homePlayerScore;
                            $carry['gd'] = $carry['gd'] + ($awayPlayerScore - $homePlayerScore);
                        }


                        $carry['played']++;
                    }
                }

                return $carry;
            },
            [
                'win' => 0,
                'loss' => 0,
                'draw' => 0,
                'for' => 0,
                'against' => 0,
                'played' => 0,
                'gd' => 0,
                'points' => 0,
                'form' => [],
                'formPoints' => 0
            ]
        );
    }

    protected function calculateFurthestStage($fixtures, $playerId)
    {
        $furthest = null;

        $fixtureStages = array_column($fixtures, 'group');

        foreach (array_reverse($this->stages) as $stage) {
            $stageIndex = array_search($stage, $fixtureStages);
            if ($stageIndex) {
                $furthest = $stage;
                break;
            }
        }

        if ($furthest === 'Final') {
            return $this->isWinner($fixtures[$stageIndex], $playerId) ? 'Winner' : 'Runner Up';
        }

        return $furthest ?? 'Group';
    }

    protected function isHomePlayer($fixture, $playerId)
    {
        return $playerId === $fixture['homePlayerId'];
    }

    protected function isWinner($fixture, $playerId)
    {
        $isHome = $this->isHomePlayer($fixture, $playerId);
        extract($fixture);

        if ($isHome) {
            return $homePlayerScore > $awayPlayerScore;
        } else {
            return $awayPlayerScore > $homePlayerScore;
        }
    }

    protected function calculateOutrightStats($fixtures, $players)
    {

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
