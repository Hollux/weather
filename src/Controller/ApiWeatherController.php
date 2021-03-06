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

        //modif version hw only
        $resp = $weatherTools->getAllFromhw();
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
    public function test(WeatherTools $weatherTools)
    {
        $weatherTools->setMinutelyHW();

        return $this->json([
            "success" => "success",
        ]);
    }


    /**
     * @Route("/saveminutly/{savkey}", methods={"GET","HEAD"})
     */
    public function saveminutly($savkey, WeatherTools $weatherTools): Response
    {
        
        if($savkey == $_ENV['savKey']){
            $weatherTools->setMinutelyHW();

            return $this->json([
            "success" => "success",
        ]);
        }

        return $this->json([
            "error" => "error",
        ]);
    }


    /**
     * @Route("/savedaily/{savkey}", methods={"GET","HEAD"})
     */
    public function savedaily($savkey, WeatherTools $weatherTools): Response
    {
        if($savkey == $_ENV["savKey"] || $savkey == "toto2"){
            $resp = $weatherTools->setDailyHW(true);

            return $this->json([
                "success" => $resp,
            ]);
        } else if($savkey == "toto"){
            $resp = $weatherTools->setDailyHW();

            return $this->json([
                "success" => $resp,
            ]);
        } 

        return $this->json([
            "error" => "error",
        ]);
    } 


    /**
     * @Route("/api/getminutly")
     */
    public function getminutly(WeatherTools $weatherTools, Request $request): Response
    {
        $data = json_decode($request->getContent(), true)["data"];
        $min = strtotime($data[0]);
        $max = strtotime($data[1])+86399;

        if($min && $max){
            $resp = $weatherTools->getMinutlyWithMinMax($min, $max);
            $arrayResp = [];
            foreach ($resp as $key => $value) {
               $arrayResp[] = $value->toArray(); 
            }

            return $this->json([
                "success" => "success",
                "infos" => $arrayResp
            ]);
        }

        return $this->json([
            "error" => "no min max",
        ]);
    } 


    

} 