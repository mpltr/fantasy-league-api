<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFixturesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create("fixtures", function (Blueprint $table) {
            $table->bigIncrements("id");
            $table->integer("tournamentId");
            $table->integer("homePlayerId");
            $table->integer("homePlayerScore")->nullable();
            $table->integer("awayPlayerId");
            $table->integer("awayPlayerScore")->nullable();
            $table->text("group");
            $table->date("date");
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
        Schema::dropIfExists("fixtures");
    }
}
