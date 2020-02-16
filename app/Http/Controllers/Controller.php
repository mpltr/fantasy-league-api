<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    function error($message, $response_code) {
        return response([
            'status' => false, 
            'error' => $message
        ], $response_code);
    }
}
