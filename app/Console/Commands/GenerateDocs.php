<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenApi\Generator;

class GenerateDocs extends Command
{
    protected $signature   = 'docs:generate';
    protected $description = 'Scan OpenApi annotation files and write JSON specs + Swagger UI assets to public/';

    private array $specs = [
        'mobile-app' => [
            'scan' => 'app/OpenApi/MobileApp',
            'out'  => 'public/api-docs/mobile-app/mobile-app-api-docs.json',
        ],
        'check-in' => [
            'scan' => 'app/OpenApi/CheckIn',
            'out'  => 'public/api-docs/check-in/check-in-api-docs.json',
        ],
    ];

    public function handle(): int
    {
        $this->publishSwaggerUiAssets();

        foreach ($this->specs as $name => $cfg) {
            $scanPath = base_path($cfg['scan']);
            $outFile  = base_path($cfg['out']);

            if (! is_dir($scanPath)) {
                $this->warn("Skipping [{$name}] — directory not found: {$scanPath}");
                continue;
            }

            @mkdir(dirname($outFile), 0755, true);

            $openapi = Generator::scan([$scanPath]);
            file_put_contents($outFile, $openapi->toJson());

            $this->info("Generated [{$name}] → {$cfg['out']}");
        }

        return self::SUCCESS;
    }

    private function publishSwaggerUiAssets(): void
    {
        $src  = base_path('vendor/swagger-api/swagger-ui/dist');
        $dest = public_path('docs/assets');

        if (! is_dir($src)) {
            $this->warn('swagger-ui dist not found in vendor — HTML pages will fall back to CDN.');
            return;
        }

        @mkdir($dest, 0755, true);

        foreach (['swagger-ui.css', 'swagger-ui-bundle.js'] as $file) {
            copy("{$src}/{$file}", "{$dest}/{$file}");
        }

        $this->info('Swagger UI assets copied to public/docs/assets/');
    }
}
