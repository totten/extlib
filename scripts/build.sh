#!/usr/bin/env bash
###############################################################################
MAIN=$PWD
DIST=$PWD/dist
PACKAGE="extlib"
VERSION="$1"

function PHAR() {
 php -d phar.readonly=0 `which phar` "$@"
}

set -e

if [ -z "$VERSION" ]; then
  echo >&2 "usage: $0 <version-number>"
  echo >&2 "example: $0 1.2.3"
  exit 1
fi


###############################################################################
set -x

if [ ! -d "$DIST" ]; then
  mkdir "$DIST"
fi

###############################################################################

DIST_PHAR="${DIST}/${PACKAGE}@${VERSION}.phar"
DIST_PHP="${DIST}/${PACKAGE}@${VERSION}.php"

[ -f "$DIST_PHAR" ] && rm -f "$DIST_PHAR" || true
[ -f "$DIST_PHP" ] && rm -f "$DIST_PHP" || true

PHAR pack -f "$DIST_PHAR" -s "res/empty-stub.php" pathload.main.php src
php "$MAIN/scripts/concat-php.php" pathload.main.php $( find src -name '*.php' ) >"$DIST_PHP"
