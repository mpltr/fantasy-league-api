<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Fixtures extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'tournamentId',
        'homePlayerId',
        'homePlayerScore',
        'awayPlayerId',
        'awayPlayerScore',
        'group',
        'date',
        'number'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];

    public function home_player()
    {
        return $this->hasOne('App\Users', 'id', 'homePlayerId');
    }

    public function away_player()
    {
        return $this->hasOne('App\Users', 'id', 'awayPlayerId');
    }

    public function tournament()
    {
        return $this->belongsTo('App\Tournaments', 'tournamentId', 'id');
    }
}
