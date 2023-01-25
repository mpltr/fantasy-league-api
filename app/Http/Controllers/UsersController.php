<?php

namespace App\Http\Controllers;

use App\Tournaments;
use Illuminate\Http\Request;
use App\Users;

class UsersController extends Controller
{
    public function __construct()
    {
        //
    }

    public function index()
    {
        $data = Users::all();

        return $data;
    }

    public function show($id)
    {
        $user = Users::where('id', $id)
                    ->with('players', 'players.fixtures', 'players.fixtures.tournament')->first();

        //             // ->with('home_fixtures')
        //             // ->with('away_fixtures')
        //             ->with('tournaments')
        //             ->first();
        
        // $combinedFixtures = $user->home_fixtures->merge($user->away_fixtures)->toArray();

        // $tournamentIds = array_reduce($combinedFixtures, function($carry, $fixture){
        //     $id = $fixture['tournamentId'];
        //     if (!in_array($id, $carry)) $carry[] = $id;
        //     return $carry;
        // }, []);
        
        // $tournaments = Tournaments::whereIn('id', $tournamentIds)
        //     ->get()
        //     ->sortByDesc('startDate')
        //     ->values();

        return $user; 
    }
}