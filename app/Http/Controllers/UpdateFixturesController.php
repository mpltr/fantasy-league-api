<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Fixtures;

class UpdateFixturesController extends Controller
{
    public function __construct()
    {
        //
    }

    public function updateFixtures(Request $request) {
        $data = $request->input('data');
        if(!empty($data)) {
            $dates = json_decode($data, true);
            $totalFixtures = 0;
            foreach($dates as $date => $fixtures) {
                foreach($fixtures as $fixture){
                    $totalFixtures++;
                    $fixtureUpdates[] = Fixtures::where('id', $fixture['id'])->update(
                        [
                            'homePlayerScore' => $fixture['homePlayerScore'],
                            'awayPlayerScore' => $fixture['awayPlayerScore'],
                            'date' => $date
                        ]
                    );
                }
            }
            $success = count($fixtureUpdates) == $totalFixtures;
            if($success) {
                return response([
                    'status' => true, 
                    'message' => 'Fixtures Updated',
                ], 200);
            } else {
                return $this->error("One or more fixures failed to update", 422);
            }
        }
        // ERROR RESPONSE: NO DATA
        return $this->error("No Data Provided", 422);
    }
}
