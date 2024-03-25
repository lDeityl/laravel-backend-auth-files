<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{

    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('first_name');
            $table->string('last_name');
            $table->timestamps();
        });

        DB::table('users')->insert([
           'email' => 'admin@admin.ru',
            'password' => \Illuminate\Support\Facades\Hash::make('admin'),
            'first_name' => 'admin',
            'last_name' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->insert([
            'email' => 'test@gmail.com',
            'password' => \Illuminate\Support\Facades\Hash::make('test'),
            'first_name' => 'test',
            'last_name' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }


    public function down()
    {
        Schema::dropIfExists('users');
    }
}
