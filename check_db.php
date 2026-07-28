<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (App\Models\Prescription::all() as $p) {
    echo "ID: {$p->id}, Doctor: {$p->doctor_name}, Date: {$p->prescription_date}, Created At: {$p->created_at}\n";
}
