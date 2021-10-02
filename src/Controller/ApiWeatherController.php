<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Service\WeatherTools;
use App\Entity\WeatherVille; 

class ApiWeatherController extends AbstractController
{
    private $client;
    private $weatherTools;

    public function __construct(HttpClientInterface $client, WeatherTools $weatherTools)
    {
        $this->client = $client;
        $this->weatherTools = $weatherTools;
    }

     /**
     * @Route("/api_weather_villes", name="api_weather_villes")
     */
    public function api_weather_villes(Request $request)
    {
        $date = date("jnY");
        $data = json_decode($request->getContent(), true)["data"];

        $resp = [];
        foreach ($data as $key => $city) {

            $url = "https://api-adresse.data.gouv.fr/search/?q=" . $city;
            $cityArray = $this->weatherTools->getClientResponse($this->client, $url);
            if(!$cityArray){
                return $this->json([
                    "error" => "Ville ". $city . " introuvable",
                ]);
            }

            if(isset($cityArray["features"]) && isset($cityArray["features"][0]['geometry']["coordinates"])){
                // verif si on a bien des infos des villes.

                // On cherche les infos en bdd si possible pour éviter de consommer l'api.
                // Les infos sont journalières pour avoir des données météo fraiches, mais pas trop non plus
                // (un équilibre entre consommation d'api et informations à jour)
                $weatherVille = $this->getDoctrine()
                    ->getRepository(WeatherVille::class)
                    ->findOneBy(["date" => $date, "city" => $cityArray["features"][0]["properties"]["label"]]);

                if($weatherVille) {
                    $resp[] = $weatherVille->getWeather();
                } else {

                    $weatherUrl = "https://api.openweathermap.org/data/2.5/onecall?lat=".
                    $cityArray["features"][0]['geometry']["coordinates"][1]."&lon=".
                    $cityArray["features"][0]['geometry']["coordinates"][0]."&exclude=minutely,hourly&appid=".
                    $this->getParameter('weatherApiKey')."&lang=fr&units=metric";

                    $weatherArray = $this->weatherTools->getClientResponse($this->client, $weatherUrl);

                    //TODO tester le retour de l'api

                    $resp[] = $this->weatherTools->getTHN($weatherArray["daily"]);

                    //save des valeurs.
                    $weatherVille = new WeatherVille;
                    $weatherVille->setCity($cityArray["features"][0]["properties"]["label"]);
                    $weatherVille->setDate($date);
                    $weatherVille->setWeather($resp[$key]);
                    $entityManager = $this->getDoctrine()->getManager();
                    $entityManager->persist($weatherVille);
                    $entityManager->flush();
                }

            } else {
                return $this->json([
                    "error" => "Ville ". $value . " introuvable",
                ]);
            } 
        }

        dump($resp);exit;

        return $this->json([
            "success" => "success",
        ]);
    } 


    /**
     * @Route("/test", name="test")
     */
    public function test()
    {
        return $this->json([
            "success" => "success",
        ]);
    } 

} 