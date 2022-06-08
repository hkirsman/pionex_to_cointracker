<?php

class PionexToCointrackerClass {

  private $cointracker_csv_file_path = 'cointracker.csv';

  private $timezone = FALSE;

  // According to cointracker.io docs, numbers can be up to eight decimals
  // places so there's seems no point in having more. Still, as we're converting
  // these mBTC, we'll use as high precision as possible
  private $decimal_precision = 18;

  public function convert($pionex_csv_path_deposits_widthdraws = FALSE, $pionex_csv_path_trading = FALSE, $timezone = FALSE) {
    $out = [];

    if ($timezone !== FALSE) {
      $this->timezone = $timezone;
    }

    if (FALSE !== $pionex_csv_path_deposits_widthdraws) {
      $out = array_merge($out, $this->convert_deposits_withdraws($pionex_csv_path_deposits_widthdraws));
    }

    if (FALSE !== $pionex_csv_path_trading) {
      $out = array_merge($out, $this->convert_trades($pionex_csv_path_trading));
    }

    $this->write_cointracker_csv($out);
  }

  private function write_cointracker_csv($csv_file_contents) {
    $first_row = TRUE;
    foreach($csv_file_contents as $line) {
      // Create cointracker.csv with first line (column names).
      if ($first_row) {
        file_put_contents(
          $this->cointracker_csv_file_path,
          implode(',', array_keys($line)) . "\n"
        );
        $first_row = FALSE;
      }

      // Write data to cointracker.csv.
      file_put_contents(
        $this->cointracker_csv_file_path,
        implode(',', $line) . "\n",
        FILE_APPEND
      );
    }

    echo 'Wrote to file ' . $this->cointracker_csv_file_path . "\n";
  }

  private function convert_trades($pionex_csv_path_trading) {
    $handle = $this->get_file_handle($pionex_csv_path_trading);
    $pionex_csv_column_names = $this->get_column_names($handle);
    $out = [];

    while (($pionex_csv_line = fgetcsv($handle, 0, ",")) !== FALSE) {
      // Replace integer keys with column names from CSV for better readability.
      $pionex_csv_line = array_combine($pionex_csv_column_names, $pionex_csv_line);

      $cointracker_line = [
        'Date' => $this->pionex_date_to_cointracker($pionex_csv_line['date(UTC+0)']),
      ];

      if ($pionex_csv_line['side'] === 'SELL') {
        $cointracker_line += [
          'Received Quantity' => $pionex_csv_line['amount'],
          'Received Currency' => explode('_', $pionex_csv_line['symbol'])[1],
          'Sent Quantity' => $pionex_csv_line['amount']/$pionex_csv_line['price'],
          'Sent Currency' => explode('_', $pionex_csv_line['symbol'])[0],
          'Fee Amount' => $pionex_csv_line['fee'],
          'Fee Currency' => explode('_', $pionex_csv_line['symbol'])[1],
          'Tag' => '',
        ];
      }
      elseif($pionex_csv_line['side'] === 'BUY') {
        $cointracker_line += [
          'Received Quantity' => $pionex_csv_line['amount']/$pionex_csv_line['price'],
          'Received Currency' => explode('_', $pionex_csv_line['symbol'])[0],
          'Sent Quantity' => $pionex_csv_line['amount'],
          'Sent Currency' => explode('_', $pionex_csv_line['symbol'])[1],
          'Fee Amount' => $pionex_csv_line['fee'],
          'Fee Currency' => explode('_', $pionex_csv_line['symbol'])[0],
          'Tag' => '',
        ];
      }

      // Convert mBTC (thousandth of a BTC) to Bitcoin because we don't have mBTC in Cointracker.
      if ($cointracker_line['Sent Currency'] === 'MBTC') {
        $cointracker_line['Sent Currency'] = 'BTC';
        $cointracker_line['Sent Quantity'] = number_format($cointracker_line['Sent Quantity'] / 1000, $this->decimal_precision);
      }

      // @todo: Untested.
      if ($cointracker_line['Received Currency'] === 'MBTC') {
        $cointracker_line['Received Currency'] = 'BTC';
        $cointracker_line['Received Quantity'] = number_format($cointracker_line['Received Quantity'] / 1000, $this->decimal_precision);
        $cointracker_line['Fee Amount'] = number_format($cointracker_line['Fee Amount'] / 1000, $this->decimal_precision);
        $cointracker_line['Fee Currency'] = 'BTC';
      }

      $out[] = $cointracker_line;
    }

    return $out;
  }

  private function convert_deposits_withdraws($pionex_csv_path_deposits_widthdraws) {
    $handle = $this->get_file_handle($pionex_csv_path_deposits_widthdraws);
    $pionex_csv_column_names = $this->get_column_names($handle);

    while (($pionex_csv_line = fgetcsv($handle, 0, ",")) !== FALSE) {
      // Replace integer keys with column names from CSV for better readability.
      $pionex_csv_line = array_combine($pionex_csv_column_names, $pionex_csv_line);

      $cointracker_line = [
        'Date' => $this->pionex_date_to_cointracker($pionex_csv_line['date(UTC+0)']),
      ];

      // Not sure why USDT is called TUSDT in the exported CSV from Pionex.
      if ($pionex_csv_line['coin'] === 'TUSDT') {
        $pionex_csv_line['coin'] = 'USDT';
      }

      if ($pionex_csv_line['tx_type'] === 'DEPOSIT') {
        $cointracker_line += [
          'Received Quantity' => $pionex_csv_line['amount'],
          'Received Currency' => $pionex_csv_line['coin'],
          'Sent Quantity' => '',
          'Sent Currency' => '',
          'Fee Amount' => '',
          'Fee Currency' => '',
          'Tag' => '',
        ];
      }
      elseif($pionex_csv_line['tx_type'] === 'WITHDRAW') {
        $cointracker_line += [
          'Received Quantity' => '',
          'Received Currency' => '',
          'Sent Quantity' => $pionex_csv_line['amount'],
          'Sent Currency' => $pionex_csv_line['coin'],
          'Fee Amount' => $pionex_csv_line['fee'],
          'Fee Currency' => $pionex_csv_line['coin'],
          'Tag' => '',
        ];
      }

      $out[] = $cointracker_line;
    }

    return $out;
  }

  /**
   * Changes date from 2022-02-24 07:33:19 to 24/02/2022 07:33:19 format.
   *
   * @param string $pionex_date
   *
   * @return string
   */
  private function pionex_date_to_cointracker(string $pionex_date) {
    $d = DateTime::createFromFormat(
      'Y-m-d H:i:s',
      $pionex_date,
      new DateTimeZone('UTC')
    );
    if ($this->timezone !== FALSE) {
      $d->setTimezone(new DateTimeZone($this->timezone));
    }
    return $d->format('d/m/Y H:i:s');
  }

  private function get_file_handle($pionex_csv_file_path) {
    if (($handle = fopen($pionex_csv_file_path, 'r')) === FALSE) {
      return FALSE;
      // @todo: add error somewhere?
      //die('Input file not found.');
    }
    return $handle;
  }

  private function get_column_names($handle) {
    // Read in column keys from Pionex CSV.
    while (($pionex_csv_column_names = fgetcsv($handle, 0, ",")) !== FALSE) {
      break;
    }
    return $pionex_csv_column_names;
  }

}
