<?php

namespace App\Entity;

use App\Repository\WeatherHWminutelyRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=WeatherHWminutelyRepository::class)
 */
class WeatherHWminutely
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $dt;

    /**
     * @ORM\Column(type="string", length=8)
     */
    private $temp;

    /**
     * @ORM\Column(type="string", length=8)
     */
    private $pressure;

    /**
     * @ORM\Column(type="string", length=8)
     */
    private $humidity;

    /**
     * @ORM\Column(type="string", length=8)
     */
    private $uvi;

    /**
     * @ORM\Column(type="string", length=8)
     */
    private $wind_speed;

    /**
     * @ORM\Column(type="string", length=8)
     */
    private $wind_deg;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDt(): ?int
    {
        return $this->dt;
    }

    public function setDt(int $dt): self
    {
        $this->dt = $dt;

        return $this;
    }

    public function getTemp(): ?string
    {
        return $this->temp;
    }

    public function setTemp(string $temp): self
    {
        $this->temp = $temp;

        return $this;
    }

    public function getPressure(): ?string
    {
        return $this->pressure;
    }

    public function setPressure(string $pressure): self
    {
        $this->pressure = $pressure;

        return $this;
    }

    public function getHumidity(): ?string
    {
        return $this->humidity;
    }

    public function setHumidity(string $humidity): self
    {
        $this->humidity = $humidity;

        return $this;
    }

    public function getUvi(): ?string
    {
        return $this->uvi;
    }

    public function setUvi(string $uvi): self
    {
        $this->uvi = $uvi;

        return $this;
    }

    public function getWindSpeed(): ?string
    {
        return $this->wind_speed;
    }

    public function setWindSpeed(string $wind_speed): self
    {
        $this->wind_speed = $wind_speed;

        return $this;
    }

    public function getWindDeg(): ?string
    {
        return $this->wind_deg;
    }

    public function setWindDeg(string $wind_deg): self
    {
        $this->wind_deg = $wind_deg;

        return $this;
    }

    public function toArray(){
        $array = [
            "dt" => $this->dt,
            "temp" => $this->temp,
            "pressure" => $this->pressure,
            "humidity" => $this->humidity,
            "uvi" => $this->uvi,
            "wind_speed" => $this->wind_speed,
            "wind_deg" => $this->wind_deg
        ];

        return $array;
    }
    
}
