# WP Quick Install

WP Quick Install is the easiest way to install WordPress.

This project is a lite rewrite of main [WP Quick Install](https://github.com/GeekPress/WP-Quick-Install) for even easier install without knowledge of technical things like database access, etc.

Just 5 mandatory fields to fill and you're good to go !

![wp quick install](https://cloud.githubusercontent.com/assets/3679080/21081945/4c842694-bfd1-11e6-8639-a3b3ed2fa656.png)


## Install

1. This version of WPQI works for subdomain URL installation, which is more comfortable/cleanier wordpress management, so you must configure DNS with special wildcard subdomains, i.e. add `*.wp` name record. This should endly have this line configured : `*.wp.example.com. 1800 IN A <YOUR_SERVER_IP>`
1. Unzip the installator under root web directory of your choice, and create another empty directory which will contain all your wordpress farm sites
1. Set-up your nginx/apache server from *server-confs* directory, one site for the installator, another for wordpress farm sites. This last one must be writeable by unix web user (commonly www-data). For the installator, set write permissions only for cache folder. The installator access must be keep secret and protected by your own care.
1. Finally create *data.ini* file by copy from *data.sample.ini*, and set useful informations, as mainly master domain, wordpress sites directory, language, root mysql user access (needed for automatic database creation), default theme and plugins. You can add your custom/premium themes and plugins into corresponding installator subdirectories.


## License

This project is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).
