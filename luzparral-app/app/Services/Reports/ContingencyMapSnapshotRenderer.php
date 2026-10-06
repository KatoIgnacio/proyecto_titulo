<?php

namespace App\Services\Reports;

use App\Models\Contingency;
use Illuminate\Support\Collection;

class ContingencyMapSnapshotRenderer
{
    public function render(Collection $contingencies): string
    {
        $width = 930;
        $height = 300;
        $left = 62;
        $top = 30;
        $right = 28;
        $bottom = 46;
        $plotWidth = $width - $left - $right;
        $plotHeight = $height - $top - $bottom;
        $points = $contingencies
            ->filter(fn (Contingency $contingency): bool => $contingency->latitude !== null && $contingency->longitude !== null)
            ->take(250)
            ->values();

        if ($points->isEmpty()) {
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'">'
                .'<rect width="100%" height="100%" fill="#f8fafc"/>'
                .'<rect x="1" y="1" width="'.($width - 2).'" height="'.($height - 2).'" fill="none" stroke="#cbd5e1"/>'
                .'<text x="'.($width / 2).'" y="'.($height / 2).'" text-anchor="middle" font-size="18" fill="#64748b">Sin contingencias georreferenciadas para los filtros seleccionados</text>'
                .'</svg>';

            return 'data:image/svg+xml;base64,'.base64_encode($svg);
        }

        $latitudes = $points->map(fn (Contingency $item): float => (float) $item->latitude);
        $longitudes = $points->map(fn (Contingency $item): float => (float) $item->longitude);
        $minLatitude = (float) $latitudes->min();
        $maxLatitude = (float) $latitudes->max();
        $minLongitude = (float) $longitudes->min();
        $maxLongitude = (float) $longitudes->max();
        $latitudeSpan = max(0.01, $maxLatitude - $minLatitude);
        $longitudeSpan = max(0.01, $maxLongitude - $minLongitude);
        $minLatitude -= $latitudeSpan * 0.08;
        $maxLatitude += $latitudeSpan * 0.08;
        $minLongitude -= $longitudeSpan * 0.08;
        $maxLongitude += $longitudeSpan * 0.08;
        $latitudeSpan = $maxLatitude - $minLatitude;
        $longitudeSpan = $maxLongitude - $minLongitude;

        $grid = '';
        for ($index = 0; $index <= 4; $index++) {
            $x = $left + ($plotWidth * $index / 4);
            $y = $top + ($plotHeight * $index / 4);
            $longitude = $minLongitude + ($longitudeSpan * $index / 4);
            $latitude = $maxLatitude - ($latitudeSpan * $index / 4);
            $grid .= '<line x1="'.$x.'" y1="'.$top.'" x2="'.$x.'" y2="'.($top + $plotHeight).'" stroke="#dbe4f0" stroke-width="1"/>';
            $grid .= '<line x1="'.$left.'" y1="'.$y.'" x2="'.($left + $plotWidth).'" y2="'.$y.'" stroke="#dbe4f0" stroke-width="1"/>';
            $grid .= '<text x="'.$x.'" y="'.($height - 17).'" text-anchor="middle" font-size="8" fill="#64748b">'.number_format($longitude, 3, ',', '.').'°</text>';
            $grid .= '<text x="'.($left - 8).'" y="'.($y + 3).'" text-anchor="end" font-size="8" fill="#64748b">'.number_format($latitude, 3, ',', '.').'°</text>';
        }

        $markers = '';
        $showLabels = $points->count() <= 12;
        foreach ($points as $contingency) {
            $longitude = (float) $contingency->longitude;
            $latitude = (float) $contingency->latitude;
            $x = $left + (($longitude - $minLongitude) / $longitudeSpan * $plotWidth);
            $y = $top + (($maxLatitude - $latitude) / $latitudeSpan * $plotHeight);
            $radius = min(11, 4 + log10(max(1, (int) $contingency->affected_total)));
            $color = match ($contingency->priority) {
                'critical' => '#e11d48',
                'high' => '#f97316',
                'medium' => '#2563eb',
                default => '#64748b',
            };
            $markers .= '<circle cx="'.round($x, 1).'" cy="'.round($y, 1).'" r="'.round($radius, 1).'" fill="'.$color.'" fill-opacity="0.82" stroke="#ffffff" stroke-width="1.5"/>';
            if ($showLabels) {
                $label = htmlspecialchars((string) $contingency->code, ENT_QUOTES | ENT_XML1, 'UTF-8');
                $markers .= '<text x="'.round($x + $radius + 3, 1).'" y="'.round($y + 3, 1).'" font-size="8" fill="#334155">'.$label.'</text>';
            }
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'">'
            .'<rect width="100%" height="100%" fill="#f8fafc"/>'
            .'<rect x="'.$left.'" y="'.$top.'" width="'.$plotWidth.'" height="'.$plotHeight.'" fill="#eef6ef" stroke="#94a3b8"/>'
            .$grid.$markers
            .'<path d="M875 56 L875 32 L868 43 M875 32 L882 43" fill="none" stroke="#0f172a" stroke-width="2"/>'
            .'<text x="875" y="25" text-anchor="middle" font-size="10" font-weight="bold" fill="#0f172a">N</text>'
            .'<circle cx="600" cy="16" r="4" fill="#e11d48"/><text x="610" y="19" font-size="8" fill="#475569">Crítica</text>'
            .'<circle cx="670" cy="16" r="4" fill="#f97316"/><text x="680" y="19" font-size="8" fill="#475569">Alta</text>'
            .'<circle cx="725" cy="16" r="4" fill="#2563eb"/><text x="735" y="19" font-size="8" fill="#475569">Media</text>'
            .'<circle cx="790" cy="16" r="4" fill="#64748b"/><text x="800" y="19" font-size="8" fill="#475569">Baja</text>'
            .'<text x="'.$left.'" y="'.($height - 4).'" font-size="7" fill="#64748b">Mapa esquemático · coordenadas sintéticas · '.number_format($points->count(), 0, ',', '.').' puntos</text>'
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
