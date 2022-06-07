#!/bin/bash

#
# Helper script to toggle Xdebug modes.
#

set -euo pipefail
export PATH=/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin

if [ "$#" -ne 1 ]; then
  echo "Xdebug has been turned off, please use the following syntax: 'lando xdebug <mode>'."
  echo "Valid modes: https://xdebug.org/docs/all_settings#mode."
  echo xdebug.mode = off > /usr/local/etc/php/conf.d/zzz-lando-xdebug.ini
  pkill -o -USR2 php-fpm
else
  mode="$1"
  echo xdebug.mode = "$mode" > /usr/local/etc/php/conf.d/zzz-lando-xdebug.ini
  pkill -o -USR2 php-fpm
  if [ "$mode" = 'off' ]; then
    echo "Xdebug is now disabled."
  else
    echo "Xdebug is loaded in "$mode" mode."
  fi
fi
