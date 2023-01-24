<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Users extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name'
    ];

    public function players() {
        return $this->hasMany('App\Players', 'userId', 'id');
    }

    public function home_fixtures() {
        return $this->hasManyThrough('App\Fixtures', 'App\Players', 'userId', 'homePlayerId', 'id');
    }

    public function away_fixtures() {
        return $this->hasManyThrough('App\Fixtures', 'App\Players', 'userId', 'awayPlayerId', 'id');
    }
}
