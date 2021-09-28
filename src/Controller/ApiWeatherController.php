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
        
        $data = json_decode($request->getContent(), true)["data"];

        $weatherArray = [];
        foreach ($data as $key => $value) {

            $url = "https://api-adresse.data.gouv.fr/search/?q=" . $value;
            $cityArray = $this->weatherTools->GetClientResponse($this->client, $url);
            if(!$cityArray){
                return $this->json([
                    "error" => "Ville ". $value . " introuvable",
                ]);
            }

            if(isset($cityArray["features"]) && isset($cityArray["features"][0]['geometry']["coordinates"])){

                $weatherUrl = "https://api.openweathermap.org/data/2.5/onecall?lat=".
                    $cityArray["features"][0]['geometry']["coordinates"][1]."&lon=".
                    $cityArray["features"][0]['geometry']["coordinates"][0]."&exclude=minutely,hourly&appid=".
                    $this->getParameter('weatherApiKey')."&lang=fr&units=metric";

                $weatherArray[] = $this->weatherTools->GetClientResponse($this->client, $weatherUrl);

            } else {
                return $this->json([
                    "error" => "Ville ". $value . " introuvable",
                ]);
            } 
        }

        dump($weatherArray);exit;

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