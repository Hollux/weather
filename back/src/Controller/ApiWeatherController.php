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
use App\Service\WeatherDaily;

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
        if (isset($resp['error'])) {
            return $this->json([
                "error" => $resp['error']
            ]);
        }

        $villeTop = $weatherTools->compareWeather($resp, $mode);
        if ($villeTop === null) {
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
        // Version HW only : on renvoie la dernière mesure de la station, quel que soit $ville.
        $resp = $weatherTools->getAllFromhw();
        if (isset($resp['error'])) {
            return $this->json([
                "error" => $resp['error']
            ]);
        }

        return $this->json([
            "success" => $resp,
        ]);
    }

    /**
     * @Route("/saveminutly/{savkey}", methods={"GET","HEAD"})
     *
     * @deprecated La collecte OpenWeatherMap toutes les 5 min passe désormais par
     * la commande interne app:weather:collect (service "cron"), sans HTTP ni secret
     * dans l'URL. Cette route est conservée en secours le temps de la bascule ;
     * l'ancien cron hôte est désactivé (commenté), pas supprimé.
     */
    public function saveminutly($savkey, WeatherTools $weatherTools): Response
    {
        if ($savkey == $_ENV['savKey']) {
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
        if ($savkey == $_ENV["savKey"] || $savkey == "toto2") {
            $resp = $weatherTools->setDailyHW(true);

            return $this->json([
                "success" => $resp,
            ]);
        } else if ($savkey == "toto") {
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
     * @Route("/getminutly")
     */
    public function getminutly(WeatherTools $weatherTools, WeatherDaily $weatherDaily, Request $request): Response
    {
        $data = json_decode($request->getContent(), true)["data"];
        $min = strtotime($data[0]);
        $max = strtotime($data[1]) + 86399;

        // pas de dates min max
        if (!$min || !$max) {
            return $this->json([
                "error" => "no min max",
            ]);
        }

        // SI on dépasse les 2 semaines il faut les infos daily sinon minutly
        if (($max - $min) < 1209600) {

            $resp = $weatherTools->getMinutlyWithMinMax($min, $max);
            $arrayResp = [];
            foreach ($resp as $key => $value) {
                $arrayResp[] = $value->toArray();
            }

            return $this->json([
                "success" => "success",
                "infos" => $arrayResp
            ]);
        } else {

            $resp = $weatherDaily->getDailyWithMinMax($min, $max);
            $arrayResp = [];
            foreach ($resp as $key => $value) {
                $arrayResp[] = $value->toArray();
            }

            return $this->json([
                "success" => "success",
                "infos" => $arrayResp
            ]);
        }
    }

    /**
     * @Route("/generateWeatherDaily", name="generateWeatherDaily")
     *
     * Génère les lignes weather_daily manquantes à partir de weather_hwminutely.
     *
     * @deprecated L'agrégation journalière passe désormais par la commande interne
     * app:weather:aggregate (service "cron"). Route conservée en secours le temps
     * de la bascule ; l'ancien cron hôte est désactivé (commenté), pas supprimé.
     */
    public function generateWeatherDaily(WeatherDaily $weatherDaily)
    {
        $count = $weatherDaily->generateMissingDaily();

        return new Response("Daily OK : $count jours générés");
    }

    /**
     * @Route("/getCompare", name="getCompare")
     */
    public function getCompare(WeatherTools $weatherTools, Request $request): Response
    {
        $data = json_decode($request->getContent(), true)["data"];
        $years = $data['years'];
        $year1 = $years[0] ?? null;
        $year2 = $years[1] ?? null;
        $year3 = $years[2] ?? null;

        //dd('getCompare controller :', $years);

        // nullable interdit
        if (!$year1 || !$year2 || !$year3) {
            return $this->json([
                "error" => "Il doit manquer une année",
            ]);
        }


        $resp = $weatherTools->getCompare($year1, $year2, $year3);
        if (isset($resp['error'])) {
            return $this->json([
                "error" => $resp['error']
            ]);
        }

        return $this->json([
            "success" => "success",
            "infos" => $resp
        ]);
    }
}
