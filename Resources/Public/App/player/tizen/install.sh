#!/bin/sh
set -eu
if [ "$#" -ne 2 ]; then echo 'Aufruf: ./install.sh ZERTIFIKATSPROFIL TV-GERAETENAME'; exit 2; fi
command -v tizen >/dev/null 2>&1 || { echo 'Tizen Studio CLI fehlt im PATH. Zuerst README.md lesen.'; exit 1; }
cd "$(dirname "$0")"
tizen_work=$(mktemp -d)
trap 'rm -rf "$tizen_work"' EXIT HUP INT TERM
mkdir -p "$tizen_work/build" "$tizen_work/signed"
tizen build-web --output "$tizen_work/build" -- app
tizen package -t wgt -s "$1" -o "$tizen_work/signed" -- "$tizen_work/build"
set -- "$2" "$tizen_work/signed/"*.wgt
if [ "$#" -ne 2 ] || [ ! -f "$2" ]; then echo 'Kein eindeutiges signiertes WGT erzeugt.'; exit 1; fi
mkdir -p signed
cp "$2" signed/Kiosky-Player.wgt
tizen install -n Kiosky-Player.wgt -t "$1" -- signed
tizen run -p KioskyTv01.Player -t "$1"
