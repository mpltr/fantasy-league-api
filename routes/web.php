<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

use App\Tournaments;

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->post('/createTournament', 'CreateTournamentController@createTournament');

$router->post('/updateFixtures', 'UpdateFixturesController@updateFixtures');

$router->get('/get-tournament/{id}', 'GetTournamentController@getTournament');

$router->get('/get-tournaments', 'GetTournamentController@getTournaments');

$router->get('/version', function() {
    return response()->json([
        'stuff' => phpinfo()
    ]);
});


