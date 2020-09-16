<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Players;

class PlayerController extends Controller
{
    public function __construct()
    {
        //
    }

    public function index() {
        return Players::orderBy('id')->get();
    }

    public function update(Request $request, $id) {
        $data = $request->input('data');

        if(!empty($data)) {
            $args = json_decode($data, true);
            $result = Players::where('id', $id)
                ->update($args);

                return response(['status' => true, 'result' => $result]);
        }

        return $this->error("No data provided");
    }
}
