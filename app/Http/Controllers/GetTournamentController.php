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
            'id'                           => $data['id'],
            'name'                         => $data['tournamentName'],
            'numberOfGroupTeamsToProgress' => $data['numberOfGroupTeamsToProgress'],
            'fixtures'                     => $fixtures,
            'players'                      => $playersWithStats,
            'tables'                       => $tables
        ];
    }

    public function getTournaments() {
        $data = Tournaments::all('uid');

        return $data;
    }
}
