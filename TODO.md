* -DONE- sitemap
 * gzip files
 * config routes' freq/priority in config file?
* Centralize config skippers
 * color
 * twitter
 * picture?
 * gather all social media info (twitter, facebook, youtube, dailymotion, etc...) for skippers/sponsors
  * implement a sipmlement backoffice of easy maintenance
* openStreetMap rather than Google Maps (options to switch?)
 * -IN PROGRESS- routes as geoJson
* refactor
 * class VG : make it more "ORM-like" (getAll(), find()...)
 * class VGXLS : split into "parse XLS" + "generate JSON/KML ..."
  * Parser base class extend to create more race files parsers
 * Controllers as a service
 * CACHE!
 * translation files (base translation file to cover most of the translation strings)
 * translation : use generic translation keys rather than raw text in templates
* New page : skipper table (all the positions in a nice table)
* translate to spanish?

v2
* migrate to django! (or Flask for its similarity with Silex)