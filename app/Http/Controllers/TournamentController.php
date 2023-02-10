<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Tournaments;
use App\Players;
use App\Fixtures;
use App\Users;

class TournamentController extends Controller
{
    public function __construct()
    {
        //
    }

    public function store(Request $request)
    {
        $data = $request->input('data');

        if (!empty($data)) {
            // decode actual args are specify expected args
            $args = json_decode($data, true);
            $expectedArgs = [
                'tournamentName',
                'numberOfGroups',
                'numberOfPlayers',
                'startDate',
                'numberOfPvpFixtures',
                'weeksBetweenFixtures',
                'numberOfKnockoutRounds',
                'tournamentPlayers'
            ];
            // verify and map args to vars
            foreach ($expectedArgs as $expectedArg) {
                if (empty($args[$expectedArg])) {
                    // ERROR RESPONSE: MISSING ARG
                    return $this->error("Missing $expectedArg Argument", 422);
                }
            }

            extract($args);

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

            $tournamentUid = $this->slugify($tournamentName);
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

            // USERS & PLAYERS
            $playerEntries = [];

            foreach ($tournamentPlayers as $seedGroup => $players) {
                foreach ($players as $player) {
                    $isNew = array_key_exists('__isNew__', $player) && $player['__isNew__'];
                    $user = null;
                    if (!$isNew) {
                        $user = [
                            'id'   => $player['value'],
                            'name' => $player['label']
                        ];
                    } else {
                        if (!Users::where('name', '=', $player['label'])->exists()) {
                            $user = Users::create([
                                'name'         => $player['label']
                            ]);
                        }
                    }

                    $playerEntries[] = Players::create([
                        'name'         => $player['label'],
                        'fplLink'      => $player['fplLink'] ?? 'default',
                        'seed'         => $seedGroup,
                        'userId'       => $user['id'],
                        'tournamentId' => $tournamentId
                    ]);
                }
            }

            // FIXTURES
            // get the fixture rows
            $fixtures = $this->getFixtureRows(
                $playerEntries,
                $numberOfGroups,
                $startDate,
                $weeksBetweenFixtures,
                $numberOfPvpFixtures,
                $tournamentId
            );

            foreach ($fixtures as $fixture) {
                // create new fixture row
                Fixtures::create($fixture);
            }

            // Return Success Response
            return response(['status' => true, 'tournamentUid' => $tournamentUid], 200);
        }
        // ERROR RESPONSE: NO DATA
        return $this->error("No Data Provided", 422);
    }

    public function show($uid)
    {

        $data = Tournaments::where('uid', $uid)
            ->with('fixtures', 'fixtures.home_player', 'fixtures.away_player', 'messages')
            ->first();

        $originalFixtures = $data['fixtures']->toArray();

        $players  = $this->extractPlayersFromFixtures($originalFixtures);

        $fixtures = $this->sortFixturesIntoGroups($originalFixtures, $players);

        $playersWithStats = $this->calculaterPlayerStats($players, $originalFixtures);

        $tables = $this->assignPlayersToTables($fixtures);

        $outrightStats = $this->calculateOutrightStats($originalFixtures, $players);

        return [
            'id'                           => $data['id'],
            'name'                         => $data['tournamentName'],
            'numberOfGroupTeamsToProgress' => $data['numberOfGroupTeamsToProgress'],
            'messages'                     => $data['messages'],
            'fixtures'                     => $fixtures,
            'players'                      => $playersWithStats,
            'tables'                       => $tables,
            'stage'                        => $data['stage'],
            'outrightStats'                => $outrightStats
        ];
    }

    public function index()
    {
        return Tournaments::orderByDesc('startDate')->get();
    }

    // HELPERS //
    private function getFixtureRows(
        $players,
        $numberOfGroups,
        $startDate,
        $weeksBetweenFixtures,
        $numberOfPvpFixtures,
        $tournamentId
    ) {
        // TODO: allow no seeding. New algorithm only allows seeded players


        // randomise the players
        shuffle($players);

        // split into seedings
        $seeded = [];
        foreach ($players as $player) {
            $seeded[$player['seed']][] = $player;
        }

        // seperate into groups
        $groups = [];
        $groupIndex = 0;
        foreach ($seeded as $seed_group) {
            foreach ($seed_group as $player) {
                $groups[$groupIndex][] = $player;
                $groupIndex = $groupIndex === ($numberOfGroups - 1) ? 0 : $groupIndex + 1;
            }
        }

        // get and return fixtures for groups
        $fixtures = [];
        $index = 0;
        foreach ($groups as $group) {
            $groupLetter = chr(64 + $index + 1);
            $groupFixtures =  $this->calculateFixtures(
                $group,
                $numberOfPvpFixtures,
                $weeksBetweenFixtures,
                $startDate,
                $groupLetter,
                $tournamentId
            );
            // return $groupFixtures;
            $fixtures = array_merge($fixtures, $groupFixtures);
            $index++;
        }
        return $fixtures;
    }

    private function calculateFixtures(
        $players,
        $numberOfPvpFixtures,
        $weeksBetweenFixtures,
        $startDate,
        $groupLetter,
        $tournamentId
    ) {
        $originalNumberOfPlayers = count($players);
        // calculate number of match weeks
        if ($originalNumberOfPlayers % 2 === 1) {
            // add a dummy player to keep algorithm balanced
            $players[] = ['name' => 'dummy'];
        }
        $numberOfPlayers = count($players);
        $matchWeeks = ($numberOfPlayers - 1) * $numberOfPvpFixtures;
        $breakPoint = count($players) / 2;
        // $fixtures = [];
        for ($i = 0; $i < $matchWeeks; $i++) {
            $insight[] = $players;
            $weekOffset = $i * $weeksBetweenFixtures;
            $fixtureDate = date('Y/m/d', strtotime("$startDate +$weekOffset weeks"));
            // $weeksFixtures = [];
            for ($k = 0; $k < $breakPoint; $k++) {
                $player1 = $players[$k];
                $player2 = $players[count($players) - 1 - $k];
                if ($player2['name'] !== "dummy" && $player1['name'] !== "dummy") {
                    $fixtures[] = [
                        'tournamentId' => $tournamentId,
                        'homePlayerId' => $player1['userId'],
                        'awayPlayerId' => $player2['userId'],
                        'group'        => $groupLetter,
                        'date'         => $fixtureDate
                    ];
                    // used for knockouts
                    // $this->dateOfLastFixture = $fixtureDate;
                }
            }
            $players[] = array_splice($players, 1, 1)[0];
        }
        return $fixtures;
    }

    public function revertStage($uid)
    {
        // get the current stage
        $tournament = Tournaments::where('uid', $uid)->select('uid', 'id', 'stage')->first();
        $stage = $tournament['stage'];
        $id = $tournament['id'];

        // skip if group
        if ($stage === 'Group') {
            return response([
                'status'  => false,
                'error' => 'Cannot revert a tournament when in Group Stage'
            ]);
        }

        // delete the fixtures from current round
        $deleteFixturesResult = Fixtures::where('tournamentId', $id)->where('group', $stage)->delete();

        // revert the tournament stage
        $lastStage = $this->getLastStage($stage);
        $revertTournamentResult = Tournaments::where('id', $id)->update([
            'stage' => $lastStage
        ]);

        return response([
            'status' => true,
            'message' => "Tournament successfully reverted to $lastStage stage"
        ]);
    }
}
