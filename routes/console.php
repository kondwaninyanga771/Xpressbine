<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('escrow:auto-release')->everyFiveMinutes();
