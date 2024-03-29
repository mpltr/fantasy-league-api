<?php

namespace App\Http\Controllers;

use App\Fixtures;
use Illuminate\Http\Request;
use App\Players;
use App\Users;

/**
 * Controlled created to unify players into a new table: users,
 * so we can more easily combine player stats into a user view
 * Also adds additional row values to existing tables.
 *
 */
class UnificationController extends Controller
{
    public function __construct()
    {
        //
    }

    public function players(Request $request)
    {
        $players = Players::all();
        $users   = Users::all()->toArray();

        $usersIndex = array_reduce($users, function ($carry, $user) {
            $carry[$this->slugify($user['name'])] = $user['id'];
            return $carry;
        }, []);
        foreach ($players as $player) {
            $name = $this->slugify($player->name);
            $firstFixture = $player->fixtures()->first();
            $tournamentId = $firstFixture->tournamentId;
            if (!array_key_exists($name, $usersIndex)) {
                $result = Users::create([
                    'name' => $player->name
                ]);
                $usersIndex[$name] = $result['id'];
            }
            if ($player->userId === 1) {
                Players::where('id', $player->id)->update([
                    'userId' => $usersIndex[$name],
                    'tournamentId' => $tournamentId
                ]);
            }
        }

        return $users;
    }

    public function fixtures(Request $request)
    {
        $players = Players::orderBy('id')->get()->keyBy('id');
        $fixtures = Fixtures::all()->toArray();

        $newFixtures = [];

        foreach ($fixtures as $fixture) {
            $id = $fixture['id'];
            $newHomePlayerId = $players[$fixture['homePlayerId']]['userId'];
            $newAwayPlayerId = $players[$fixture['awayPlayerId']]['userId'];

            $fixture['homePlayerId'] = $newHomePlayerId;
            $fixture['awayPlayerId'] = $newAwayPlayerId;

            $newFixtures[] = $fixture;

            Fixtures::where('id', $id)->update($fixture);
        }

        return $newFixtures;
    }
}
