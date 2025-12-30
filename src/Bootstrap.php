<?php
use App\Support\Env;
Env::load(__DIR__ . '/../.env');
date_default_timezone_set(Env::get('APP_TIMEZONE','America/Sao_Paulo'));
