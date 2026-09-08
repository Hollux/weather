<?php

namespace App\Entity;

use App\Repository\WeatherHWminutelyCRangeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Mesures brutes de la station CRange (import périodique de fichiers export,
 * voir CRangeImportCommand), mirroir de WeatherHWminutely pour une source distincte.
 *
 * @ORM\Entity(repositoryClass=WeatherHWminutelyCRangeRepository::class)
 * @ORM\Table(name="weather_hwminutely_crange", uniqueConstraints={
 *     @ORM\UniqueConstraint(name="uniq_crange_dt", columns={"dt"})
 * })
 */
class WeatherHWminutelyCRange
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

    /**
     * @ORM\Column(type="string", length=8, nullable=true)
     */
    private $wind_speed_kmh;

    /**
     * Pluviométrie instantanée (mm/h), colonne source "Pluviométrie(mm/h)".
     *
     * @ORM\Column(type="string", length=8, nullable=true)
     */
    private $rain_rate;

    /**
     * Chute de pluie sur l'heure glissante (mm), colonne source
     * "Chute de pluie par heure (mm)".
     *
     * @ORM\Column(type="string", length=8, nullable=true)
     */
    private $rain_hourly;

    /**
     * Cumul de pluie de la station depuis sa dernière remise à zéro (mm),
     * colonne source "Chute de pluie accumulée (mm)". Stockée pour permettre le
     * recalcul du total journalier (weather_daily_crange.rain_total) ; non
     * exposée dans toArray().
     *
     * @ORM\Column(type="string", length=12, nullable=true)
     */
    private $rain_accumulated;

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

    public function toArray()
    {
        return [
            "dt" => $this->dt,
            "temp" => $this->temp,
            "pressure" => $this->pressure,
            "humidity" => $this->humidity,
            "uvi" => $this->uvi,
            "wind_speed" => $this->wind_speed_kmh,
            "wind_deg" => $this->wind_deg,
            "rain_rate" => $this->rain_rate,
            "rain_hourly" => $this->rain_hourly,
        ];
    }

    public function getWindSpeedKmh(): ?string
    {
        return $this->wind_speed_kmh;
    }

    public function setWindSpeedKmh(?string $wind_speed_kmh): self
    {
        $this->wind_speed_kmh = $wind_speed_kmh;

        return $this;
    }

    public function getRainRate(): ?string
    {
        return $this->rain_rate;
    }

    public function setRainRate(?string $rain_rate): self
    {
        $this->rain_rate = $rain_rate;

        return $this;
    }

    public function getRainHourly(): ?string
    {
        return $this->rain_hourly;
    }

    public function setRainHourly(?string $rain_hourly): self
    {
        $this->rain_hourly = $rain_hourly;

        return $this;
    }

    public function getRainAccumulated(): ?string
    {
        return $this->rain_accumulated;
    }

    public function setRainAccumulated(?string $rain_accumulated): self
    {
        $this->rain_accumulated = $rain_accumulated;

        return $this;
    }
}
