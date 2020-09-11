<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Messages;

class MessageController extends Controller
{
    public function __construct()
    {
        //
    }

    public function index() {
        return Messages::all();
    }

    public function store(Request $request) {
        $data = $request->input('data');

        if(!empty($data)) {
            $args = json_decode($data, true);
            $expectedArgs = [
                'tournamentId',
                'title',
                'date',
                'expiry',
                'body'
            ];
            foreach($expectedArgs as $expectedArg) {
                if(empty($args[$expectedArg])) return $this->error("Missing $expectedArg Argument", 422);
            }
            extract($args);

            $messageResult = Messages::create($args);

            return response(['status' => true, 'result' => $messageResult], 200);
        }
    }

    public function update(Request $request, $id) {
        $data = $request->input('data');

        $args = json_decode($data, true);
        $expectedArgs = [
            'title',
            'date',
            'expiry',
            'body'
        ];
        foreach($expectedArgs as $expectedArg) {
            if(empty($args[$expectedArg])) return $this->error("Missing $expectedArg Argument", 422);
        }
        extract($args);

        $messageResult = Messages::find($id)->update($args);

        return response(['status' => true, 'result' => $messageResult], 200);
    }

    
}
