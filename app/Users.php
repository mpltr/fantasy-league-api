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


    public function fixtures()
    {
        return $this->hasManyMerged('App\Fixtures', ['homePlayerId', 'awayPlayerId'], 'id');
    }

    public function players()
    {
        return $this->hasMany('App\Players', 'userId');
    }


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
