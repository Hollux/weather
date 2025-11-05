# weather
Applications comparrant 3 elements de meteo sur les 7 prochains jours ou la semaine suivante si on prend l'application payante de l'api https://openweathermap.org/price

Utilisation en POST au niveau de l'url api_weather_villes. Deux modes de calculs, l'un strict ( ok ou nok). L'autre avec des points dégressif accessible avec l'url api_weather_villes/degressif


 Installation

 0) j'utilise un environnemen apache avec mysql.
 1) cloner l'application.
 2) créer le fichier .env à la racine avec vos informations bdd + api key openweathermap
 3) à la racine faites la cmd "composer install"
 4) puis création de la bdd avec la commande "bin/console d:d:c"
 5) et enfin "bin/console d:mi:mi" et yes.

 Pour la suite différentes façons de faire pour appeler et utiliser l'applications.