<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckInResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'pid'          => $this->pid,
            'name'         => $this->name,
            'genotype'     => $this->genotype,
            'meds'         => $this->meds,
            'pain'         => $this->pain,
            'fatigue'      => $this->fatigue,
            'sleep'        => $this->sleep,
            'hydration'    => $this->hydration,
            'condition'    => $this->condition,
            'safety'       => $this->safety,
            'notes'        => $this->notes,
            'symptoms'     => $this->symptoms  ?? [],
            'flags'        => $this->flags     ?? [],
            'triggers'     => $this->triggers  ?? [],
            'total'        => $this->total,
            'displayScore' => $this->display_score,
            'status'       => $this->status,
            'redFlag'      => $this->red_flag,
            'reason'       => $this->reason,
            'scores'       => $this->scores,
            'genoMult'     => $this->geno_mult,
            'ts'           => $this->checked_in_at,
        ];
    }
}
