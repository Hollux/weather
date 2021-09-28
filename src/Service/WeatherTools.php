<?php
namespace App\Service;

class WeatherTools
{
    public function GetClientResponse($client, $url){
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

}  