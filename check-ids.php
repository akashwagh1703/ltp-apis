<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n📍 Turfs:\n";
$turfs = DB::table('turfs')->select('id', 'owner_id', 'name')->get();
foreach ($turfs as $t) {
    echo "   ID: {$t->id}, Owner: {$t->owner_id}, Name: {$t->name}\n";
}

echo "\n⏰ Slots:\n";
$slots = DB::table('turf_slots')->select('id', 'turf_id', 'date', 'status')->get();
foreach ($slots as $s) {
    echo "   ID: {$s->id}, Turf: {$s->turf_id}, Date: {$s->date}, Status: {$s->status}\n";
}

echo "\n👤 Owners:\n";
$owners = DB::table('owners')->select('id', 'phone')->get();
foreach ($owners as $o) {
    echo "   ID: {$o->id}, Phone: {$o->phone}\n";
}

echo "\n🎮 Players:\n";
$players = DB::table('players')->select('id', 'phone')->get();
foreach ($players as $p) {
    echo "   ID: {$p->id}, Phone: {$p->phone}\n";
}
