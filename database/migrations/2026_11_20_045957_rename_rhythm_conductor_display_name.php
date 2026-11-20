<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')->where('slug', 'rhythm-conductor')->where('name', 'Rhythm Conductor')
            ->update(['name' => 'TheSoundMine Pattern Conductor']);
    }

    public function down(): void
    {
        DB::table('products')->where('slug', 'rhythm-conductor')->where('name', 'TheSoundMine Pattern Conductor')
            ->update(['name' => 'Rhythm Conductor']);
    }
};
