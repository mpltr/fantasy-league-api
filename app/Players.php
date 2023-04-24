<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Korridor\LaravelHasManyMerged\HasManyMerged;
use Korridor\LaravelHasManyMerged\HasManyMergedRelation;

class Players extends Model
{
    use HasManyMergedRelation;
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

    public function fixtures()
    {
        return $this->hasManyMerged('App\Fixtures', ['homePlayerId', 'awayPlayerId']);
    }

    public function tournaments()
    {
        return $this->belongsToMany('App\Tournaments', 'tournamentId');
    }
}
