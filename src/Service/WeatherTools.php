<?php
namespace App\Service;

class WeatherTools
{
    public function getClientResponse($client, $url){
        $response = $client->request(
                'GET',
                $url
            );

            $statusCode = $response->getStatusCode();
            if($statusCode = 200){
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

}  