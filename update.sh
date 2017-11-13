#!/bin/bash

cd /home/kevin/www/regadata/current
./console regadata:dl $1
./console regadata:convert $1
./console regadata:export $1
./console vg:tweet $1
./console regadata:ping_sitemap $1
for a in $(find web/kml -name "*kml" | sed 's/\.kml//g'); do zip -r $a.kmz $a.kml; done
