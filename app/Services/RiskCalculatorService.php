<?php

namespace App\Services;

/**
 * Direct PHP port of the frontend's calcRisk.ts.
 * Both sides must produce identical scores — backend always recalculates,
 * never trusts scores submitted from the client.
 */
class RiskCalculatorService
{
    private const RED_FLAG_SYMPTOMS = [
        'Chest pain',
        'Shortness of breath',
        'Fever',
        'Confusion or weakness',
        'Severe chest pain',
        'Difficulty breathing',
        // §8 v2.0 haematologist additions:
        'Sudden left-sided abdominal pain',   // splenic sequestration — life-threatening in children
        'Painful erection lasting over 2 hours', // priapism — medical emergency in SCD
    ];

    public function calculate(array $input): array
    {
        $flags    = $input['flags']    ?? [];
        $symptoms = $input['symptoms'] ?? [];
        $triggers = $input['triggers'] ?? [];
        $safety   = $input['safety']   ?? 'None';
        $genotype = $input['genotype'] ?? 'Unknown';

        // Immediate URGENT override if any red-flag symptom is present
        $hasRedFlag = !empty(array_intersect($flags, self::RED_FLAG_SYMPTOMS))
            || in_array($safety, self::RED_FLAG_SYMPTOMS, true);

        if ($hasRedFlag) {
            return [
                'total'         => 999,
                'display_score' => 'OVERRIDE',
                'status'        => 'Urgent',
                'red_flag'      => true,
                'reason'        => 'Red-flag symptom reported — immediate medical attention required',
                'geno_mult'     => 1.0,
                'scores'        => [
                    'pain'      => 0,
                    'fatigue'   => 0,
                    'sleep'     => 0,
                    'hydration' => 0,
                    'symptoms'  => 0,
                    'triggers'  => 0,
                ],
            ];
        }

        // Pain
        $pain      = (int) ($input['pain'] ?? 0);
        $painScore = match (true) {
            $pain === 0 => 0,
            $pain <= 3  => 1,
            $pain <= 6  => 3,
            $pain <= 8  => 5,
            default     => 6,
        };

        // Fatigue
        $fatScore = match ($input['fatigue'] ?? '') {
            'Low'    => 0,
            'Medium' => 1,
            default  => 2,
        };

        // Sleep
        $sleepScore = match ($input['sleep'] ?? '') {
            'Good'  => 0,
            'Okay'  => 0.5,
            default => 1,
        };

        // Hydration
        $hydScore = match ($input['hydration'] ?? '') {
            'Good'  => 0,
            'Okay'  => 1,
            default => 2,
        };

        // Symptoms + condition
        $condition = $input['condition'] ?? '';
        $symScore  = 0;
        if (in_array('Joint pain', $symptoms, true))  $symScore += 2;
        if (in_array('Headache', $symptoms, true))    $symScore += 1;
        if (in_array('Dizziness', $symptoms, true))   $symScore += 2;
        if ($condition === 'Very different')           $symScore += 2;
        if ($condition === 'Slightly different')       $symScore += 1;

        // Triggers
        $trigScore = 0;
        if (in_array('Stress', $triggers, true))                $trigScore += 1;
        if (in_array('Physical exertion', $triggers, true))     $trigScore += 1;
        if (in_array('Cold weather exposure', $triggers, true)) $trigScore += 1;
        if (in_array('Dehydration', $triggers, true))           $trigScore += 2;
        if (in_array('Illness/infection', $triggers, true))     $trigScore += 3;
        if (in_array('Poor sleep', $triggers, true))            $trigScore += 1;
        if (in_array('Travel', $triggers, true))                $trigScore += 1;

        // Genotype multiplier
        $genoMult = match (true) {
            in_array($genotype, ['SS', 'SB0'], true) => 1.2,
            $genotype === 'SB+'                      => 1.1,
            default                                  => 1.0,
        };

        $raw   = $painScore + $fatScore + $sleepScore + $hydScore + $symScore + $trigScore;
        $total = (int) round($raw * $genoMult);

        $status = match (true) {
            $total <= 3  => 'Stable',
            $total <= 7  => 'Watch closely',
            $total <= 11 => 'Elevated',
            default      => 'Urgent',
        };

        $reason = match (true) {
            $symScore >= max($painScore, $trigScore) && $symScore > 0
                => 'Multiple symptoms present beyond pain level',
            $trigScore >= max($painScore, $symScore) && $trigScore > 0
                => 'Trigger exposure increasing risk today',
            $painScore > 0
                => 'Pain level is the primary risk driver today',
            default
                => 'Combination of mild factors contributing to score',
        };

        if ($genoMult > 1.0) {
            $reason .= " · Genotype {$genotype} (×{$genoMult}) applied";
        }

        return [
            'total'         => $total,
            'display_score' => (string) $total,
            'status'        => $status,
            'red_flag'      => false,
            'reason'        => $reason,
            'geno_mult'     => $genoMult,
            'scores'        => [
                'pain'      => $painScore,
                'fatigue'   => $fatScore,
                'sleep'     => $sleepScore,
                'hydration' => $hydScore,
                'symptoms'  => $symScore,
                'triggers'  => $trigScore,
            ],
        ];
    }
}
