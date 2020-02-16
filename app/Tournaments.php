<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Tournaments extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 
        'uid',
        'tournamentName',
        'numberOfPlayers', 
        'numberOfGroups',
        'numberOfPvpFixtures',
        'weeksBetweenFixtures',
        'playersToProgress',
        'startDate'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
    ];

    public function players() {
        return $this->hasMany('App\Players', 'tournamentId', 'id');
    }

    public function fixtures() {
        return $this->hasMany('App\Fixtures', 'tournamentId', 'id');
    }

}
