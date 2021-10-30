<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\WeatherVille;
use App\Entity\WeatherHWminutely;

class WeatherTools
{
    private $entityManager;
    private $client;

    public function __construct(EntityManagerInterface $entityManager, HttpClientInterface $client)
    {
        $this->client = $client;
        $this->em = $entityManager;
    }

    public function GetRespFromData($data){
        if(!isset($data[0]) && !isset($data[1]) && count($data) !== 2){
            return ["error" => "Configuration de data incorrecte"];
        }
        $date = date("jnY");
        $resp = [];


        foreach ($data as $key => $city) {
            if($_ENV['weatherApiFreeVersion']){
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
                        $_ENV['weatherApiKey']."&lang=fr&units=metric";

                        $weatherArray = $this->getClientResponse($this->client, $weatherUrl);

                        if(!isset($weatherArray["daily"])){
                            return ["error" => "Ville ". $city . " introuvable"];
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
                    return ["error" => "Ville ". $city . " introuvable"];
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
                            $_ENV['weatherApiKey']."&lang=fr&units=metric";
                        
                        $weatherArray = $this->getClientResponse($this->client, $weatherUrl);

                        if(!isset($weatherArray["daily"])){
                            return ["error" => "Ville ". $city . " introuvable"];
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
        if($mode == "strict"){
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
        

        } else if ($mode == "degressif"){
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

    public function getAllFromVille($city) {
        
        //récupération des latLng de la ville
        $url = "https://api-adresse.data.gouv.fr/search/?q=" . $city;
        $cityArray = $this->getClientResponse($this->client, $url);
        if(!$cityArray){
            return ["error" => "Ville ". $city . " introuvable"];
        }

        if(isset($cityArray["features"]) && isset($cityArray["features"][0]['geometry']["coordinates"])){
            // verif si on a bien des infos des villes.

            $weatherUrl = "https://api.openweathermap.org/data/2.5/onecall?lat=".
            $cityArray["features"][0]['geometry']["coordinates"][1]."&lon=".
            $cityArray["features"][0]['geometry']["coordinates"][0]."&exclude=minutely,hourly&appid=".
            $_ENV['weatherApiKey']."&lang=fr&units=metric";

            $weatherArray = $this->getClientResponse($this->client, $weatherUrl);

            if(!isset($weatherArray["daily"])){
                return ["error" => "Ville ". $city . " introuvable"];
            }
        } else {
            return ["error" => "Ville ". $city . " introuvable"];
        } 

        return $weatherArray;
    }

    public function getTest2(){
            $dt = time();
            $historicalDT = $dt - 432000;

            $weatherUrl = "https://api.openweathermap.org/data/2.5/onecall/timemachine?lat=48.081&lon=7.4022&dt=".$historicalDT."&appid=".
            $_ENV['weatherApiKey']."&lang=fr&units=metric";

            $weatherArray = $this->getClientResponse($this->client, $weatherUrl);

            // if(!isset($weatherArray["daily"])){
            //     return ["error" => "Ville ". $city . " introuvable"];
            // }

            return $weatherArray;
    }


    public function setMinutelyHW(){
         $weatherUrl = "https://api.openweathermap.org/data/2.5/onecall?lat=48.081&lon=7.4022&exclude=minutely,hourly,daily&appid=".
            $_ENV['weatherApiKey']."&lang=fr&units=metric";

            $weatherArray = $this->getClientResponse($this->client, $weatherUrl);

            if($weatherArray){
                //save.
                $save = new WeatherHWminutely;
                $save->setDt($weatherArray["current"]["dt"]);
                $save->setTemp($weatherArray["current"]["temp"]);
                $save->setPressure($weatherArray["current"]["pressure"]);
                $save->setHumidity($weatherArray["current"]["humidity"]);
                $save->setUvi($weatherArray["current"]["uvi"]);
                $save->setWindSpeed($weatherArray["current"]["wind_speed"]);
                $save->setWindDeg($weatherArray["current"]["wind_deg"]);
                // a rajouter dans la bdd
                //$save->setWindDeg($weatherArray["current"]["dew_point"]);
                //$save->setWindDeg($weatherArray["current"]["feels_like"]);
                // /a rajouter dans la bdd
                $this->em->persist($save);
                $this->em->flush();
            }
    }

    public function setDailyHW($save = false){
        $dtNow = new \DateTime();
        $dtNow = $dtNow->getTimestamp();
        $dt24h = $dtNow - 86400;

        $result = $this->em->getRepository(WeatherHWminutely::class)->getAllInDtMinMax($dt24h, $dtNow);

        if($result){
            $min = 1000000;
            $maxi = -$min;
            $infoSouhaite = ["temp", "pressure", "humidity", "uvi", "wind_speed"];
            $finalInfos = [];
            foreach($infoSouhaite as $info){
                if($info == "wind_speed"){
                    $finalInfos[$info] = [$min, 0, $maxi, 0, 0, 0];
                    continue;
                }
                $finalInfos[$info] = [$min, 0, $maxi, 0];
            }

            foreach($result as $ligne){
                $ligne = $ligne->toArray();
                foreach($finalInfos as $key => $val){
                    if($finalInfos[$key][0] > $ligne[$key]){
                        $finalInfos[$key][0] = $ligne[$key];
                        $finalInfos[$key][1] = $ligne["dt"];
                        if($key == 'wind_speed'){
                            $finalInfos[$key][4] = $ligne["wind_deg"];
                        }
                    }
                    if($finalInfos[$key][2] < $ligne[$key]){
                        $finalInfos[$key][2] = $ligne[$key];
                        $finalInfos[$key][3] = $ligne["dt"];
                        if($key == 'wind_speed'){
                            $finalInfos[$key][5] = $ligne["wind_deg"];
                        }
                    }
                }
            }


            return $finalInfos;
        }


        return null;

    }


    public function getMinutlyWithMinMax($min, $max){
        $result = $this->em->getRepository(WeatherHWminutely::class)->getAllInDtMinMax($min, $max);

        return $result;
    }

}  