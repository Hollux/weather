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
use App\Service\WeatherTools;

class ApiWeatherController extends AbstractController
{

     /**
     * @Route("/api_weather_villes/{mode}", 
     * defaults={"mode": "strict"} ,
     * name="api_weather_villes", 
     * requirements={"mode"="strict|degressif"},
     * methods="POST")
     */
    public function api_weather_villes($mode, Request $request, WeatherTools $weatherTools)
    {
        //pour fonctionner : "data" => ["ville1", "ville2"]
        //Attention, si on met des fautes d'orthographe l'api du gouvernement rattrape et peu trouver la ville.
        // Par contre elle peux aussi en trouver une autre de ville et on ne verra pas la différence car je répond avec 
        // la nom de ville demandé par le client pour que lui n'est pas d'erreur d'interprétation au retour.
        $data = json_decode($request->getContent(), true)["data"];

        $resp = $weatherTools->GetRespFromData($data);
        if(isset($resp['error'])) {
            return $this->json([
                    "error" => $resp['error']
                ]);
        }

        $villeTop = $weatherTools->compareWeather($resp, $mode);
        if($villeTop === null){
            return $this->json([
                "success" => "Les villes sont egales",
            ]);
        }

        return $this->json([
            "success" => $data[$villeTop],
        ]);
    } 


    /**
     * @Route("/api_weather_detail/{ville}", 
     * defaults={"ville": "colmar"},
     * name="api_weather_detail")
     */
    public function api_weather_detail($ville, Request $request, WeatherTools $weatherTools)
    {

        if($ville == "taville"){
            return $this->json([
                    "error" => "MAIS NON PUNAISE METS LA VILLE QUE TU VEUX PAS #TAVILLE#"
                ]);
        }

        $resp = $weatherTools->getAllFromVille($ville);
        if(isset($resp['error'])) {
            return $this->json([
                    "error" => $resp['error']
                ]);
        }

        return $this->json([
            "success" => $resp,
        ]);
    } 

     /**
     * @Route("/api_weather_test2", 
     * name="api_weather_test2")
     */
    public function api_weather_test2(WeatherTools $weatherTools)
    {
        $resp = $weatherTools->getTest2();
        if(isset($resp['error'])) {
            return $this->json([
                    "error" => $resp['error']
                ]);
        }

        return $this->json([
            "success" => $resp,
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