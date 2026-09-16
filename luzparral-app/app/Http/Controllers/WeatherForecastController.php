<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class WeatherForecastController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('WeatherForecast', [
            'embedUrl' => (string) config('services.windy.embed_url'),
        ]);
    }
}
