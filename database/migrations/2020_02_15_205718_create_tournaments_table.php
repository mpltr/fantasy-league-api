<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTournamentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create("tournaments", function (Blueprint $table) {
            $table->bigIncrements("id");
            $table->string("uid", 30)->unique();
            $table->longtext("tournamentName");
            $table->integer("numberOfGroups");
            $table->integer("numberOfPvpFixtures");
            $table->integer("weeksBetweenFixtures");
            $table->integer("numberOfKnockoutRounds");
            $table->integer("numberOfGroupTeamsToProgress");
            $table->date("startDate");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists("tournaments");
    }
}
