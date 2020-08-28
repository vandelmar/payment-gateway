<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \App\User::create([
            'name'  => Str::random(20),
            'email' => Str::random(10) . '@codepolitan.com',
            'password'  => bcrypt('secret')
        ]);
    }
}
