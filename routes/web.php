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

// tournament
$router->post('/tournament', 'TournamentController@store');
$router->get('/tournament', 'TournamentController@index');
$router->get('/tournament/{uid}', 'TournamentController@show');
// fixture
$router->post('/fixtures', 'FixturesController@store');
// message
$router->get('/message', 'MessageController@index');
$router->post('/message', 'MessageController@store');
$router->put('/message/{id}', 'MessageController@update');
// player
$router->get('/player', 'PlayerController@index');
$router->put('/player/{id}', 'PlayerController@update');

$router->get('/version', function() {
    return response()->json([
        'stuff' => phpinfo()
    ]);
});


