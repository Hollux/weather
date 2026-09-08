<?php

namespace App\Entity;

use App\Repository\WeatherDailyRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=WeatherDailyRepository::class)
 * @ORM\Table(name="weather_daily", uniqueConstraints={
 *     @ORM\UniqueConstraint(name="uniq_day", columns={"day"})
 * })
 */
class WeatherDaily
{

    public function __construct()
    {
        // Initialisation des propriétés avec des valeurs par défaut
        $this->temp_0 = 0.0;
        $this->temp_12 = 0.0;
        $this->temp_avg = 0.0;
        $this->temp_max = 0.0;
        $this->temp_min = 0.0;
        $this->pressure_avg = 0.0;
        $this->pressure_max = 0.0;
        $this->pressure_min = 0.0;
        $this->humidity_avg = 0.0;
        $this->humidity_max = 0.0;
        $this->humidity_min = 0.0;
        $this->uvi_avg = 0.0;
        $this->uvi_max = 0.0;
        $this->wind_speed_avg = 0.0;
        $this->wind_speed_max = 0.0;
        $this->wind_deg_avg = 0.0;
        $this->temp_min_dt = null;
        $this->temp_max_dt = null;
        $this->pressure_min_dt = null;
        $this->pressure_max_dt = null;
        $this->humidity_min_dt = null;
        $this->humidity_max_dt = null;
        $this->uvi_max_dt = null;
        $this->wind_speed_max_dt = null;
    }


    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /** @ORM\Column(type="integer") */
    private int $day;

    // Température
    /** @ORM\Column(type="float") */
    private float $temp_avg;

    /** @ORM\Column(type="float") */
    private float $temp_max;

    /** @ORM\Column(type="float") */
    private float $temp_min;

    /** @ORM\Column(type="float") */
    private float $temp_0;

    /** @ORM\Column(type="float") */
    private float $temp_12;

    // Pression
    /** @ORM\Column(type="float") */
    private float $pressure_avg;

    /** @ORM\Column(type="float") */
    private float $pressure_max;

    /** @ORM\Column(type="float") */
    private float $pressure_min;

    // Humidité
    /** @ORM\Column(type="float") */
    private float $humidity_avg;

    /** @ORM\Column(type="float") */
    private float $humidity_max;

    /** @ORM\Column(type="float") */
    private float $humidity_min;

    // Indice UV
    /** @ORM\Column(type="float") */
    private float $uvi_avg;

    /** @ORM\Column(type="float") */
    private float $uvi_max;

    // Vent
    /** @ORM\Column(type="float") */
    private float $wind_speed_avg;

    /** @ORM\Column(type="float") */
    private float $wind_speed_max;

    /** @ORM\Column(type="float") */
    private float $wind_deg_avg;

    // Horodatage (timestamp Unix UTC) de la mesure minutely qui porte le mini / maxi
    // du jour. NULL = pas de minutely disponible pour ce jour (jour antérieur au
    // suivi, ou trou de collecte). Les moyennes n'ont pas d'heure : c'est le jour.
    /** @ORM\Column(type="integer", nullable=true) */
    private ?int $temp_min_dt;

    /** @ORM\Column(type="integer", nullable=true) */
    private ?int $temp_max_dt;

    /** @ORM\Column(type="integer", nullable=true) */
    private ?int $pressure_min_dt;

    /** @ORM\Column(type="integer", nullable=true) */
    private ?int $pressure_max_dt;

    /** @ORM\Column(type="integer", nullable=true) */
    private ?int $humidity_min_dt;

    /** @ORM\Column(type="integer", nullable=true) */
    private ?int $humidity_max_dt;

    /** @ORM\Column(type="integer", nullable=true) */
    private ?int $uvi_max_dt;

    /** @ORM\Column(type="integer", nullable=true) */
    private ?int $wind_speed_max_dt;


    // ================= Fonctions =================

    public function toArray(): array
    {
        return [
            'dt' => $this->day,
            'temp' => $this->temp_avg,
            'temp_max' => $this->temp_max,
            'temp_min' => $this->temp_min,
            'temp_0' => $this->temp_0,
            'temp_12' => $this->temp_12,
            'pressure' => $this->pressure_avg,
            'pressure_max' => $this->pressure_max,
            'pressure_min' => $this->pressure_min,
            'humidity' => $this->humidity_avg,
            'humidity_max' => $this->humidity_max,
            'humidity_min' => $this->humidity_min,
            'uvi' => $this->uvi_avg,
            'uvi_max' => $this->uvi_max,
            'wind_speed' => $this->wind_speed_avg,
            'wind_speed_max' => $this->wind_speed_max,
            'wind_deg' => $this->wind_deg_avg,
            'temp_min_dt' => $this->temp_min_dt,
            'temp_max_dt' => $this->temp_max_dt,
            'pressure_min_dt' => $this->pressure_min_dt,
            'pressure_max_dt' => $this->pressure_max_dt,
            'humidity_min_dt' => $this->humidity_min_dt,
            'humidity_max_dt' => $this->humidity_max_dt,
            'uvi_max_dt' => $this->uvi_max_dt,
            'wind_speed_max_dt' => $this->wind_speed_max_dt,
        ];
    }

    public function toArrayForImport(): array
    {
        return [
            'day' => $this->day,
            'temp_avg' => $this->temp_avg,
            'temp_max' => $this->temp_max,
            'temp_min' => $this->temp_min,
            'temp_0' => $this->temp_0,
            'temp_12' => $this->temp_12,
            'pressure_avg' => $this->pressure_avg,
            'pressure_max' => $this->pressure_max,
            'pressure_min' => $this->pressure_min,
            'humidity_avg' => $this->humidity_avg,
            'humidity_max' => $this->humidity_max,
            'humidity_min' => $this->humidity_min,
            'uvi_avg' => $this->uvi_avg,
            'uvi_max' => $this->uvi_max,
            'wind_speed_avg' => $this->wind_speed_avg,
            'wind_speed_max' => $this->wind_speed_max,
            'wind_deg_avg' => $this->wind_deg_avg,
        ];
    }

    // ================= Getters / Setters =================

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getDay(): int
    {
        return $this->day;
    }
    public function setDay(int $day): self
    {
        $this->day = $day;
        return $this;
    }

    public function getTempAvg(): float
    {
        return $this->temp_avg;
    }
    public function setTempAvg(float $temp_avg): self
    {
        $this->temp_avg = $temp_avg;
        return $this;
    }

    public function getTempMax(): float
    {
        return $this->temp_max;
    }
    public function setTempMax(float $temp_max): self
    {
        $this->temp_max = $temp_max;
        return $this;
    }

    public function getTempMin(): float
    {
        return $this->temp_min;
    }
    public function setTempMin(float $temp_min): self
    {
        $this->temp_min = $temp_min;
        return $this;
    }

    public function getTemp0(): float
    {
        return $this->temp_0;
    }
    public function setTemp0(float $temp_0): self
    {
        $this->temp_0 = $temp_0;
        return $this;
    }

    public function getTemp12(): float
    {
        return $this->temp_12;
    }
    public function setTemp12(float $temp_12): self
    {
        $this->temp_12 = $temp_12;
        return $this;
    }

    public function getPressureAvg(): float
    {
        return $this->pressure_avg;
    }
    public function setPressureAvg(float $pressure_avg): self
    {
        $this->pressure_avg = $pressure_avg;
        return $this;
    }

    public function getPressureMax(): float
    {
        return $this->pressure_max;
    }
    public function setPressureMax(float $pressure_max): self
    {
        $this->pressure_max = $pressure_max;
        return $this;
    }

    public function getPressureMin(): float
    {
        return $this->pressure_min;
    }
    public function setPressureMin(float $pressure_min): self
    {
        $this->pressure_min = $pressure_min;
        return $this;
    }

    public function getHumidityAvg(): float
    {
        return $this->humidity_avg;
    }
    public function setHumidityAvg(float $humidity_avg): self
    {
        $this->humidity_avg = $humidity_avg;
        return $this;
    }

    public function getHumidityMax(): float
    {
        return $this->humidity_max;
    }
    public function setHumidityMax(float $humidity_max): self
    {
        $this->humidity_max = $humidity_max;
        return $this;
    }

    public function getHumidityMin(): float
    {
        return $this->humidity_min;
    }
    public function setHumidityMin(float $humidity_min): self
    {
        $this->humidity_min = $humidity_min;
        return $this;
    }

    public function getUviAvg(): float
    {
        return $this->uvi_avg;
    }
    public function setUviAvg(float $uvi_avg): self
    {
        $this->uvi_avg = $uvi_avg;
        return $this;
    }

    public function getUviMax(): float
    {
        return $this->uvi_max;
    }
    public function setUviMax(float $uvi_max): self
    {
        $this->uvi_max = $uvi_max;
        return $this;
    }

    public function getWindSpeedAvg(): float
    {
        return $this->wind_speed_avg;
    }
    public function setWindSpeedAvg(float $wind_speed_avg): self
    {
        $this->wind_speed_avg = $wind_speed_avg;
        return $this;
    }

    public function getWindSpeedMax(): float
    {
        return $this->wind_speed_max;
    }
    public function setWindSpeedMax(float $wind_speed_max): self
    {
        $this->wind_speed_max = $wind_speed_max;
        return $this;
    }

    public function getWindDegAvg(): float
    {
        return $this->wind_deg_avg;
    }
    public function setWindDegAvg(float $wind_deg_avg): self
    {
        $this->wind_deg_avg = $wind_deg_avg;
        return $this;
    }

    public function getTempMinDt(): ?int
    {
        return $this->temp_min_dt;
    }
    public function setTempMinDt(?int $temp_min_dt): self
    {
        $this->temp_min_dt = $temp_min_dt;
        return $this;
    }

    public function getTempMaxDt(): ?int
    {
        return $this->temp_max_dt;
    }
    public function setTempMaxDt(?int $temp_max_dt): self
    {
        $this->temp_max_dt = $temp_max_dt;
        return $this;
    }

    public function getPressureMinDt(): ?int
    {
        return $this->pressure_min_dt;
    }
    public function setPressureMinDt(?int $pressure_min_dt): self
    {
        $this->pressure_min_dt = $pressure_min_dt;
        return $this;
    }

    public function getPressureMaxDt(): ?int
    {
        return $this->pressure_max_dt;
    }
    public function setPressureMaxDt(?int $pressure_max_dt): self
    {
        $this->pressure_max_dt = $pressure_max_dt;
        return $this;
    }

    public function getHumidityMinDt(): ?int
    {
        return $this->humidity_min_dt;
    }
    public function setHumidityMinDt(?int $humidity_min_dt): self
    {
        $this->humidity_min_dt = $humidity_min_dt;
        return $this;
    }

    public function getHumidityMaxDt(): ?int
    {
        return $this->humidity_max_dt;
    }
    public function setHumidityMaxDt(?int $humidity_max_dt): self
    {
        $this->humidity_max_dt = $humidity_max_dt;
        return $this;
    }

    public function getUviMaxDt(): ?int
    {
        return $this->uvi_max_dt;
    }
    public function setUviMaxDt(?int $uvi_max_dt): self
    {
        $this->uvi_max_dt = $uvi_max_dt;
        return $this;
    }

    public function getWindSpeedMaxDt(): ?int
    {
        return $this->wind_speed_max_dt;
    }
    public function setWindSpeedMaxDt(?int $wind_speed_max_dt): self
    {
        $this->wind_speed_max_dt = $wind_speed_max_dt;
        return $this;
    }
}
