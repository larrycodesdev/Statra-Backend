<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'name'           => 'STATRA Band',
                'description'    => 'Real-time sickle cell monitoring on your wrist. 24/7 biometric data, instant alerts, and direct connection to your care team.',
                'price'          => 149.00,
                'original_price' => 199.00,
                'currency'       => 'USD',
                'plans'          => [
                    [
                        'id'    => 'band_only',
                        'label' => 'Band Only',
                        'price' => 149.00,
                        'note'  => null,
                    ],
                    [
                        'id'    => 'band_care_plan',
                        'label' => 'Band + Care Plan',
                        'price' => 149.00,
                        'note'  => '3 months Care Plan included free',
                    ],
                ],
                'sizes' => [
                    ['id' => 'S', 'label' => 'S · 130–160mm'],
                    ['id' => 'M', 'label' => 'M · 155–185mm'],
                    ['id' => 'L', 'label' => 'L · 181–210mm'],
                ],
                'shipping' => [
                    'cost'            => 0,
                    'label'           => 'Free',
                    'estimated_days'  => '5–8 business days',
                ],
                'badges' => [
                    'Free shipping worldwide',
                    'Clinically validated',
                    '2-year warranty',
                ],
            ],
        ]);
    }
}
