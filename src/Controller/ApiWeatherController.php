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
    private $weatherTools;

    public function __construct(WeatherTools $weatherTools)
    {
        $this->weatherTools = $weatherTools;
    }

     /**
     * @Route("/api_weather_villes/{mode}", 
     * defaults={"mode": "strict"} ,
     * name="api_weather_villes", 
     * requirements={"mode"="strict|degressif"},
     * methods="POST")
     */
    public function api_weather_villes($mode, Request $request)
    {
        //pour fonctionner : "data" => ["ville1", "ville2"]
        $data = json_decode($request->getContent(), true)["data"];

        $resp = $this->weatherTools->GetRespFromData($data);
        if(isset($resp['error'])) {
            return $this->json([
                    "error" => $resp['error']
                ]);
        }

        $villeTop = $this->weatherTools->compareWeather($resp, $mode);
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
     * @Route("/test", name="test")
     */
    public function test()
    {
        return $this->json([
            "success" => "success",
        ]);
    } 

} 