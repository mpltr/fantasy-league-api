<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Players extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'tournamentId',
        'name',
        'fplLink',
        'seed',
        'userId'
    ];
}
