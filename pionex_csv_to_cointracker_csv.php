<?php

$pionex_csv_file_path = 'pionex.csv';
$cointracker_csv_file_path = 'cointracker.csv';
// According to cointracker.io docs, numbers can be up to eight decimals places.
$decimal_precision = 18;

// Set timezone.
// See list of timezones here https://www.php.net/manual/en/timezones.php.
// @todo: Verify that these match with actual transactions.
date_default_timezone_set('Europe/Tallinn');

if (($handle = fopen($pionex_csv_file_path, 'r')) === FALSE) {
  die('Input file not found.');
}

// Read in column keys from Pionex CSV.
while (($pionex_csv_column_names = fgetcsv($handle, 0, ",")) !== FALSE) {
  # Original CSV does have empty column for line numbers but for our
  # it's better to have human-readable key.
  if (empty($pionex_csv_column_names[0])) {
    $pionex_csv_column_names[0] = 'line_number';
  }
  break;
}

// Read in rest of the Pionex file and write to Cointracker csv.
$first_row = TRUE;
while (($pionex_csv_line = fgetcsv($handle, 0, ",")) !== FALSE) {
  // Replace integer keys with column names from CSV for better readability.
  $pionex_csv_line = array_combine($pionex_csv_column_names, $pionex_csv_line);

  // Skip canceled transactions.
  if ($pionex_csv_line['state'] === 'CANCELED') {
    continue;
  }

  $cointracker_line = [
    'Date' => date("d/m/Y H:i:s", $pionex_csv_line['create_timestamp']),
  ];

  if ($pionex_csv_line['side'] === 'SELL') {
    $cointracker_line += [
      'Received Quantity' => $pionex_csv_line['filledAmount'],
      'Received Currency' => explode('_', $pionex_csv_line['symbol'])[1],
      'Sent Quantity' => $pionex_csv_line['executedQty'],
      'Sent Currency' => explode('_', $pionex_csv_line['symbol'])[0],
      'Fee Amount' => $pionex_csv_line['fee'],
      'Fee Currency' => explode('_', $pionex_csv_line['symbol'])[1],
      'Tag' => '',
    ];
  }
  elseif($pionex_csv_line['side'] === 'BUY') {
    $cointracker_line += [
      'Received Quantity' => $pionex_csv_line['executedQty'],
      'Received Currency' => explode('_', $pionex_csv_line['symbol'])[0],
      'Sent Quantity' => $pionex_csv_line['filledAmount'],
      'Sent Currency' => explode('_', $pionex_csv_line['symbol'])[1],
      'Fee Amount' => $pionex_csv_line['fee'],
      'Fee Currency' => explode('_', $pionex_csv_line['symbol'])[0],
      'Tag' => '',
    ];
  }

  // Convert mBTC (thousandth of a BTC) to Bitcoin because we don't have mBTC in Cointracker.
  if ($cointracker_line['Sent Currency'] === 'MBTC') {
    $cointracker_line['Sent Currency'] = 'BTC';
    $cointracker_line['Sent Quantity'] = number_format($cointracker_line['Sent Quantity'] / 1000, $decimal_precision);
  }

  // @todo: Untested.
  if ($cointracker_line['Received Currency'] === 'MBTC') {
    $cointracker_line['Received Currency'] = 'BTC';
    $cointracker_line['Received Quantity'] = number_format($cointracker_line['Received Quantity'] / 1000, $decimal_precision);
    $cointracker_line['Fee Amount'] = number_format($cointracker_line['Fee Amount'] / 1000, $decimal_precision);
    $cointracker_line['Fee Currency'] = 'BTC';
  }

  // Create cointracker.csv with first line (column names).
  if ($first_row) {
    file_put_contents(
      $cointracker_csv_file_path,
      implode(',', array_keys($cointracker_line)) . "\n"
    );
    $first_row = FALSE;
  }

  // Write data to cointracker.csv.
  file_put_contents(
    $cointracker_csv_file_path,
    implode(',', $cointracker_line) . "\n",
    FILE_APPEND
  );
}
fclose($handle);

# 06/14/2017 20:57:35
# 2022-02-24 12:47:10
function pionex_create_time_to_cointracker_date($pionex_create_time) {
  $year = substr($pionex_create_time, 0, 4);
  $month = substr($pionex_create_time, 5, 2);
  return $year;
}

//
//// get first line
//$keys = fgetcsv($pionex_csv_file_path, 0, "\n");
//xdebug_break();
//while (($line = fgetcsv($pionex_csv_file_path, 0, "\t")) !== FALSE) {
//  $array[] = array_combine($keys, $line);
//}
//
////$file = fopen($pionex_csv_file_path, 'r');
////while (($line = fgetcsv($file)) !== FALSE) {
////  print_r($line);
////}
////fclose($file);
//
//
//
//if (($handle = fopen($pionex_csv_file_path, 'r')) !== FALSE) {
//  while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
//    // get create_timestamp
//    // get
//
////    $num = count($data);
////    echo "<p> $num fields in line $row: <br /></p>\n";
////    $row++;
////    for ($c=0; $c < $num; $c++) {
////      echo $data[$c] . "<br />\n";
////    }
////    die();
////    xdebug_break();
//  }
//  fclose($handle);
//}
//

echo "Done!";
