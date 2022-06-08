<?php

require_once 'PionexToCointrackerClass.php';

$convert = new PionexToCointrackerClass();
$convert->convert('deposit-withdraw.csv', 'trading.csv', $timezone = 'Europe/Tallinn');

echo "Done!";
