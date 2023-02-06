<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Korridor\LaravelHasManyMerged\HasManyMerged;
use Korridor\LaravelHasManyMerged\HasManyMergedRelation;

class Users extends Model
{
    use HasManyMergedRelation;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name'
    ];

    public function players()
    {
        return $this->hasMany('App\Players', 'userId', 'id');
    }

    public function fixtures()
    {
        return $this->hasManyMerged('App\Fixtures', ['homePlayerId', 'awayPlayerId'], 'id');
    }

    // public function home_fixtures() {
    //     return $this->hasManyThrough('App\Fixtures', 'App\Players', 'userId', 'homePlayerId', 'id');
    // }

    // public function away_fixtures() {
    //     return $this->hasManyThrough('App\Fixtures', 'App\Players', 'userId', 'awayPlayerId', 'id');
    // }

    public function tournaments()
    {
        return $this->hasManyThrough(
            'App\Tournaments',
            'App\Players',
            'userId',
            'id',
            'id',
            'tournamentId'
        );
    }
}
