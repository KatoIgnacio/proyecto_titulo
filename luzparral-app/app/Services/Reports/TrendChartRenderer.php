<?php

namespace App\Services\Reports;

use Illuminate\Support\Collection;

class TrendChartRenderer
{
    public function render(Collection $trend): string
    {
        $width = 930;
        $height = 245;
        $left = 58;
        $top = 26;
        $right = 20;
        $bottom = 42;
        $plotWidth = $width - $left - $right;
        $plotHeight = $height - $top - $bottom;
        $max = max(1, (int) $trend->max(fn (array $point) => max($point['affected'], $point['restored'])));
        $count = max(1, $trend->count());
        $step = $count > 1 ? $plotWidth / ($count - 1) : 0;
        $affectedPoints = [];
        $restoredPoints = [];
        $grid = '';
        $labels = '';
        $markers = '';

        for ($index = 0; $index <= 4; $index++) {
            $value = (int) round($max * (4 - $index) / 4);
            $y = $top + ($plotHeight * $index / 4);
            $grid .= '<line x1="'.$left.'" y1="'.$y.'" x2="'.($width - $right).'" y2="'.$y.'" stroke="#dbe4f0" stroke-width="1" />';
            $grid .= '<text x="'.($left - 8).'" y="'.($y + 4).'" text-anchor="end" font-size="9" fill="#64748b">'.number_format($value, 0, ',', '.').'</text>';
        }

        foreach ($trend->values() as $index => $point) {
            $x = $count === 1 ? $left + ($plotWidth / 2) : $left + ($step * $index);
            $affectedY = $top + $plotHeight - (($point['affected'] / $max) * $plotHeight);
            $restoredY = $top + $plotHeight - (($point['restored'] / $max) * $plotHeight);
            $affectedPoints[] = round($x, 1).','.round($affectedY, 1);
            $restoredPoints[] = round($x, 1).','.round($restoredY, 1);
            $safeLabel = htmlspecialchars($point['label'], ENT_QUOTES | ENT_XML1, 'UTF-8');
            $labels .= '<text x="'.round($x, 1).'" y="'.($height - 15).'" text-anchor="middle" font-size="8" fill="#64748b">'.$safeLabel.'</text>';
            $markers .= '<circle cx="'.round($x, 1).'" cy="'.round($affectedY, 1).'" r="3" fill="#ef4444" stroke="#ffffff" stroke-width="1.2" />';
            $markers .= '<circle cx="'.round($x, 1).'" cy="'.round($restoredY, 1).'" r="3" fill="#10b981" stroke="#ffffff" stroke-width="1.2" />';
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'">'
            .'<rect width="100%" height="100%" fill="#ffffff" />'
            .$grid
            .'<line x1="'.$left.'" y1="'.($top + $plotHeight).'" x2="'.($width - $right).'" y2="'.($top + $plotHeight).'" stroke="#94a3b8" />'
            .'<polyline points="'.implode(' ', $affectedPoints).'" fill="none" stroke="#ef4444" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" />'
            .'<polyline points="'.implode(' ', $restoredPoints).'" fill="none" stroke="#10b981" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" />'
            .$markers.$labels
            .'<circle cx="650" cy="12" r="4" fill="#ef4444" /><text x="660" y="15" font-size="9" fill="#475569">Afectados</text>'
            .'<circle cx="760" cy="12" r="4" fill="#10b981" /><text x="770" y="15" font-size="9" fill="#475569">Repuestos</text>'
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
