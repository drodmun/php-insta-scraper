# php-insta-scraper
Simple scrapper to retrieve latest feed of public user, and store it in a cached file

Add it to your PHP server and you'll have a fully working IG feed API on the fly (wel, since it isn't finished, maybe not FULLY working, but better than nothing ^^U)

#TODO
- Paginate infinite scroll , nowadays only gets the first 12 elements

# Available calls/params

retrieveFeed.php?username=drodmun&force&onlyPics
Param:
 - username: REQUIRED (I assume no description required for this field...)
 - force: indicates wheter to get the information from the cached file, or to retrieve current information and update said file
 - onlyPics: Instead of RAW full public of user, returns formatted array of pics only.
