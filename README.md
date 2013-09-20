VG2012 Race Data
================

This is a web application deticated to gather and present Regatta's Data. I first started this project for following the Vendée Globe 2012.
It is composed of 4 main pages :

* an "about" page explaining the reason why this project was started and the list of available reports (rankings + sail's analysis' pages),
* a documentation page so developpers can easily build their own applications on top of the available Race Data,
* ranking pages (directly coming from the generated json files) giving a detailled overview of the ranking,
* sail's pages (directly coming from the generated json files) giving a detailed view about the selected sail (including graphs showing the evolution of the boat) + a comparison option allowing the user to compare 2 boats' evolution over the course of the race.

The data is stored in MongDb and then converted to different formats :
* json
* kml
* (todo) georss
* (todo) geojson

It also comes with a set of command line commands to :

* download official ranking' spreadsheet in XLS format
* convert those XLS files into JSON + KML files
* automatically tweet about the latest ranking

Install
-------

```
wget https://bitbucket.org/kbsali/vg2012/composer.json
composer install
```

```
mkdir web/{icons,xml} ?
chmod 755 web/{icons,xml} ?
cp src/config.php.sample src/config.php
```

```
./console vg:rotate_icons # to create all the boat icons
```

Cron
----

```
./console vg:dl;
./console vg:convert;
./console vg:tweet;
FOR ... KML KMZ;
./console vg:sitemap
```

Prepare New Regatta
-------------------

* Prepare csv with full list of skippers/boats + import to mongodb
 *  `src/init/XXX-20XX.csv`
 *  `mongoimport -d regatta -c XXX20XX_sails --type csv --file src/init/XXX-20XX.csv --headerline --drop`
* Add race details
 *  `src/races.php`
* Create language files + adapt texts (if copied from previous language files) :
 *  `src/locales/XXX20XX_en.yml`
 *  `src/locales/XXX20XX_fr.yml`