<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

#[Signature('composer:bump')]
#[Description('Bump Composer constraints to the installed versions, then trim them down to major.minor')]
class BumpDependencies extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $file = base_path('composer.json');

        if (! is_file($file)) {
            $this->components->error("Unable to read [{$file}].");

            return self::FAILURE;
        }

        $result = Process::path(base_path())
            ->run('composer bump', fn (string $type, string $output) => $this->output->write($output));

        if ($result->failed()) {
            $this->components->error('Composer bump failed, constraints were left untouched.');

            return self::FAILURE;
        }

        $trimmed = $this->trimPatchConstraints($file);

        $this->components->info("Trimmed {$trimmed} constraint(s) down to major.minor.");

        return self::SUCCESS;
    }

    /**
     * Rewrite the "^X.Y.Z" constraints written by Composer as "^X.Y"
     */
    private function trimPatchConstraints(string $file): int
    {
        $manifest = json_decode(file_get_contents($file), true);
        $trimmed = 0;

        foreach (['require', 'require-dev'] as $section) {
            foreach ($manifest[$section] ?? [] as $package => $constraint) {
                if (! preg_match('/^\^(\d+)\.(\d+)\.\d+$/', $constraint, $matches)) {
                    continue;
                }

                $manifest[$section][$package] = "^{$matches[1]}.{$matches[2]}";
                $trimmed++;
            }
        }

        file_put_contents($file, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        return $trimmed;
    }
}
