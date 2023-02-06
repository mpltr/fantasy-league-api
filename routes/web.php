<?php

use Illuminate\Support\Facades\Artisan;


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
    echo $router->app->version();
    // echo phpinfo();
    return null;
});

// tournament
$router->post('/tournament', 'TournamentController@store');
$router->get('/tournament', 'TournamentController@index');
$router->get('/tournament/{uid}', 'TournamentController@show');
$router->put('/tournament/revert/{uid}', 'TournamentController@revertStage');
// fixture
$router->post('/fixtures', 'FixturesController@store');
$router->get('/fixtures', 'FixturesController@index');
$router->put('/fixtures/{id}', 'FixturesController@update');
$router->put('/fixtures/bulk/{ids}', 'FixturesController@updateInBulk');
// message
$router->get('/message', 'MessageController@index');
$router->post('/message', 'MessageController@store');
$router->put('/message/{id}', 'MessageController@update');
// player
$router->get('/player', 'PlayerController@index');
$router->put('/player/{id}', 'PlayerController@update');
// users
$router->get('/users', 'UsersController@index');
$router->get('/users/{id}', 'UsersController@show');

// migrate
$router->get('/migrate/tournaments', 'MigrateController@tournaments');
$router->get('/migrate/players', 'MigrateController@players');
$router->get('/migrate/fixtures', 'MigrateController@fixtures');
$router->get('/migrate/messages', 'MigrateController@messages');

$router->get('/version', function () {
    return response()->json([
        'stuff' => phpinfo()
    ]);
});

// Unify
$router->get('/unify/players', 'UnificationController@players');
$router->get('/unify/fixtures', 'UnificationController@fixtures');


// For use on HelioHost, where we have no SSH to CLI
// Can run artisan commands in this way

// $router->get('/key', function() {
//     return \Illuminate\Support\Str::random(32);
// });

// $router->get('/generate-key', function () {
// 	Artisan::call('key:generate');
// });

$router->get('/migrate', function () {
    Artisan::call('migrate', array(
        '--force' => true,
        '--path' => 'database/migrations'
    ));
});

$router->get('/update-index/{table}', function ($table) {
    $latestId = DB::table($table)->orderBy('id', 'DESC')->first()->id;
    $newId = $latestId + 1;
    $sequence = $table . "_id_seq";

    DB::statement("ALTER SEQUENCE $sequence RESTART WITH $newId");

    echo "Updated ID to $newId";
});
