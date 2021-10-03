<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\WeatherVille; 
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class WeatherTools
{
    private $entityManager;
    private $client;
    private $params;

    public function __construct(EntityManagerInterface $entityManager, HttpClientInterface $client,ParameterBagInterface $params)
    {
        $this->client = $client;
        $this->em = $entityManager;
        $this->params = $params;
    }

    public function GetRespFromData($data){
        if(!isset($data[0]) && !isset($data[1]) && count($data) !== 2){
            return ["error" => "Configuration de data incorrecte"];
        }
        $date = date("jnY");
        $resp = [];


        foreach ($data as $key => $city) {
            if($this->params->get('weatherApiFreeVersion')){
                // code pour la version gratuite de l'api weather

                //récupération des latLng de la ville
                $url = "https://api-adresse.data.gouv.fr/search/?q=" . $city;
                $cityArray = $this->getClientResponse($this->client, $url);
                if(!$cityArray){
                    return ["error" => "Ville ". $city . " introuvable"];
                }

                if(isset($cityArray["features"]) && isset($cityArray["features"][0]['geometry']["coordinates"])){
                    // verif si on a bien des infos des villes.

                    // On cherche les infos en bdd si possible pour éviter de consommer l'api.
                    // Les infos sont journalières pour avoir des données météo fraiches, mais pas trop non plus
                    // (un équilibre entre consommation d'api et informations à jour)
                    $weatherVille = $this->em->getRepository(WeatherVille::class)
                                ->findOneBy(["date" => $date, "city" => $cityArray["features"][0]["properties"]["label"]]);

                    if($weatherVille) {
                        $resp[] = $weatherVille->getWeather();
                    } else {

                        $weatherUrl = "https://api.openweathermap.org/data/2.5/onecall?lat=".
                        $cityArray["features"][0]['geometry']["coordinates"][1]."&lon=".
                        $cityArray["features"][0]['geometry']["coordinates"][0]."&exclude=minutely,hourly&appid=".
                        $this->params->get('weatherApiKey')."&lang=fr&units=metric";

                        $weatherArray = $this->getClientResponse($this->client, $weatherUrl);

                        if(!isset($weatherArray["daily"])){
                            return ["error" => "Ville ". $value . " introuvable"];
                        }
                        $resp[] = $this->getTHN($weatherArray["daily"]);

                        //save des valeurs.
                        $weatherVille = new WeatherVille;
                        $weatherVille->setCity($cityArray["features"][0]["properties"]["label"]);
                        $weatherVille->setDate($date);
                        $weatherVille->setWeather($resp[$key]);
                        $this->em->persist($weatherVille);
                        $this->em->flush();
                    }

                } else {
                    return ["error" => "Ville ". $value . " introuvable"];
                } 

            } else {
                // code pour la version payante de l'api weather
                //TODO: tester le code avec la version payante.
                 $weatherVille = $this->em->getRepository(WeatherVille::class)
                                ->findOneBy(["date" => $date, "city" => $city]);
                    if($weatherVille) {
                        $resp[] = $weatherVille->getWeather();
                    } else {
                        $jday = jddayofweek($date);
                        // Calcul du nombre de jours à demander à l'api + nbr des premiers jours à retirer.
                        if($jday > 0 ){
                            $numberAskDay = 15 - $jday;
                            $numberDayCutInArray = 8 - $jday;
                        } else {
                            $numberAskDay = 8;
                            $numberDayCutInArray = 1;
                        }

                        $weatherUrl = "https://api.openweathermap.org/data/2.5/forecast/daily?q=".
                            $city.",FR&cnt=".$numberAskDay."&appid=".
                            $this->params->get('weatherApiKey')."&lang=fr&units=metric";
                        
                        $weatherArray = $this->getClientResponse($this->client, $weatherUrl);

                        if(!isset($weatherArray["daily"])){
                            return ["error" => "Ville ". $value . " introuvable"];
                        }

                        $nextWeakWeather = array_slice($weatherArray["daily"], $numberDayCutInArray);

                        $resp[] = $this->getTHN($nextWeakWeather);

                        //save des valeurs.
                        $weatherVille = new WeatherVille;
                        $weatherVille->setCity($city);
                        $weatherVille->setDate($date);
                        $weatherVille->setWeather($resp[$key]);
                        $this->em->persist($weatherVille);
                        $this->em->flush();
                    } 
            }
        }

        return $resp;
    }

    public function getClientResponse($client, $url){

        $response = $client->request(
                'GET',
                $url
            );

        if($response && $response->getStatusCode() == 200){
            $contentType = $response->getHeaders()['content-type'][0];
            // $contentType = 'application/json'
            $content = $response->getContent();
            $content = $response->toArray();

            return $content;
        }
            

        return false;
    }

    public function getTHN($array){
        // récupération t°, humidité, nuage
        $T = 0;
        $H = 0;
        $N = 0;

        foreach ($array as $key => $value) {
            
            $T += $value["temp"]["day"];
            $H += $value["humidity"];
            $N += $value["clouds"];
        }

        $T = $T / count($array);
        $H = $H / count($array);
        $N = $N / count($array);

        // renvois la moyenne de T, H, N
        return ["T" => $T, "H" => $H, "N" => $N];
    }

    public function calcDegressif($val, $valRef, $maxPoint, $echelle, $echellePoint){
        // calcul simple pour récupérer les points en fonction d'une echelle.
        // on calcul le nombre point qui nous séparer de l'échelle.


        $diff = 0;
        if($val > $valRef){
            while ($val >= $valRef) {
                $val = $val-$echelle;
                $diff++;
            }
        }


        if($val < $valRef){
            while ($val <= $valRef) {
                $val = $val+$echelle;
                $diff++;
            }
        }

        if($val == $valRef){
            return $maxPoint;
        }

        $point = $maxPoint - $diff * $echellePoint;
        if($point < 0){
            $point = 0;
        }

        return $point;
    }

    public function compareWeather($weatherArray, $mode){

        $pointsVille = [0, 0];
        if($mode = "strict"){
            //T°
            $notes = [$this->calcDegressif(intval(round($weatherArray[0]["T"], 0, PHP_ROUND_HALF_EVEN)), 27, 100, 1, 1), $this->calcDegressif(intval(round($weatherArray[1]["T"], 0, PHP_ROUND_HALF_EVEN)), 27, 100, 1, 1)];
            if($notes[0] > $notes[1]) {
                $pointsVille[0] += 20;
            } else if($notes[0] < $notes[1]) {
                $pointsVille[1] += 20;
            }  else {
                // les deux villes ont le même écart de la cible.
            } 

            //H
            $notes = [$this->calcDegressif(intval(round($weatherArray[0]["H"], 0, PHP_ROUND_HALF_EVEN)), 60, 100, 1, 1), $this->calcDegressif(intval(round($weatherArray[1]["H"], 0, PHP_ROUND_HALF_EVEN)), 60, 100, 1, 1)];
            if($notes[0] > $notes[1]) {
                $pointsVille[0] += 15;
            } else if($notes[0] < $notes[1]) {
                $pointsVille[1] += 15;
            }  else {
                // les deux villes ont le même écart de la cible.
            } 

            //N
            $notes = [$this->calcDegressif(intval(round($weatherArray[0]["N"], 0, PHP_ROUND_HALF_EVEN)), 15, 100, 1, 1), $this->calcDegressif(intval(round($weatherArray[0]["N"], 0, PHP_ROUND_HALF_EVEN)), 15, 100, 1, 1)];
            if($notes[0] > $notes[1]) {
                $pointsVille[0] += 10;
            } else if($notes[0] < $notes[1]) {
                $pointsVille[1] += 10;
            }  else {
                // les deux villes ont le même écart de la cible.
            } 
        

        } else if ($mode = "degressif"){
            //ici plus la valeure s'éloigne de la valeur escompté, plus la note baisse.


            //note perso : France t° moyenne 15, mini -37 maxi 44
            // humidité moyenne en france : 60 à 80%
            // taux de nuage pas trouvé

            foreach ($weatherArray as $key => $weatherVille) {
                //echelle t° 1° pour 2points
                $pointsVille[$key] += $this->calcDegressif(intval(round($weatherVille["T"], 0, PHP_ROUND_HALF_EVEN)), 27, 20, 1, 2);

                //echelle humidité 1 pour 1

                $pointsVille[$key] += $this->calcDegressif(intval(round($weatherVille["H"], 0, PHP_ROUND_HALF_EVEN)), 60, 15, 1, 1);

                //echelle nuage = 1 pour 1

                $pointsVille[$key] += $this->calcDegressif(intval(round($weatherVille["N"], 0, PHP_ROUND_HALF_EVEN)), 15, 10, 1, 1);
            }
        }

        if($pointsVille[0] > $pointsVille[1]){
            return 0;
        } else if($pointsVille[0] < $pointsVille[1]) {
            return 1;
        } else {
            return null;
        } 
    }

}  