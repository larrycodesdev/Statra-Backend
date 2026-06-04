<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

class GenerateDocs extends Command
{
    protected $signature   = 'docs:generate';
    protected $description = 'Convert YAML specs in public/api-docs/ to JSON for Swagger UI';

    public function handle(): int
    {
        // Silence swagger-php warnings that Laravel would otherwise convert to exceptions
        set_error_handler(null);

        $specs = [
            'mobile-app' => [
                'yaml' => public_path('api-docs/mobile-app.yaml'),
                'json' => public_path('api-docs/mobile-app/mobile-app-api-docs.json'),
            ],
            'check-in' => [
                'yaml' => public_path('api-docs/check-in.yaml'),
                'json' => public_path('api-docs/check-in/check-in-api-docs.json'),
            ],
        ];

        foreach ($specs as $name => $paths) {
            if (!file_exists($paths['yaml'])) {
                $this->warn("Skipping {$name} — YAML not found at {$paths['yaml']}");
                continue;
            }

            @mkdir(dirname($paths['json']), 0755, true);
            $data = Yaml::parseFile($paths['yaml']);
            file_put_contents($paths['json'], json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info("Generated: {$paths['json']}");
        }

        $this->info('Done. Docs updated.');

        return self::SUCCESS;
    }
}
