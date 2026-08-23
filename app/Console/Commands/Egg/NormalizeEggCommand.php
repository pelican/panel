<?php

namespace App\Console\Commands\Egg;

use App\Enums\EggFormat;
use App\Exceptions\Service\InvalidFileUploadException;
use App\Models\Egg;
use App\Services\Eggs\Sharing\EggExporterService;
use App\Services\Eggs\Sharing\EggImporterService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Symfony\Component\Yaml\Yaml;

class NormalizeEggCommand extends Command
{
    protected $description = 'Upgrades egg json/yaml to latest format as yaml';

    protected $signature = 'p:egg:normalize {file} {--delete-original}';

    public function __construct(
        private EggExporterService $exporter,
    ) {
        parent::__construct();
    }

    public function handle(EggImporterService $importer): int
    {
        $inputFile = $this->argument('file');
        $unparsed = file_get_contents($inputFile);
        if ($unparsed === false) {
            $this->error("Failed to read file: {$inputFile}");

            return Command::FAILURE;
        }
        $extension = strtolower(pathinfo($inputFile, PATHINFO_EXTENSION));

        $this->info("Importing {$inputFile}");

        $format = match ($extension) {
            'yaml', 'yml' => EggFormat::YAML,
            'json' => EggFormat::JSON,
            default => null,
        };
        if (is_null($format)) {
            $this->error(" -> unsupported extension {$extension} for {$inputFile}");

            return Command::FAILURE;
        }

        try {
            $eggArray = $importer->parse($unparsed, $format);
        } catch (InvalidFileUploadException) {
            $this->error(" -> unsupported file version, is it actually an egg? ({$inputFile})");

            return Command::FAILURE;
        }

        if (
            !array_key_exists('meta', $eggArray) ||
            !is_array($eggArray['meta'])
        ) {
            $this->error(" -> does not contain existing meta or meta is not array, is it actually an egg? ({$inputFile})");

            return Command::FAILURE;
        }
        if (!array_key_exists('exported_at', $eggArray)) {
            $this->error(" -> does not contain existing exported_at, is it actually an egg? ({$inputFile})");

            return Command::FAILURE;
        }
        if (
            !array_key_exists('update_url', $eggArray['meta']) ||
            !is_string($eggArray['meta']['update_url'])
        ) {
            $this->error(" -> does not contain existing meta.update_url or is not a string, is it actually an egg? ({$inputFile})");

            return Command::FAILURE;
        }

        // We upgraded our in-memory array when we imported, so set to latest version before export
        $eggArray['meta']['version'] = Egg::EXPORT_VERSION;
        $eggArray['meta']['update_url'] = self::replaceExtension($eggArray['meta']['update_url']);
        self::fixVariableRules($eggArray);

        $outputFile = self::replaceExtension($inputFile);

        if ($outputFile === $inputFile && !$this->hasFileChanged($unparsed, $eggArray)) {
            $this->info(' -> no changes required');

            return Command::SUCCESS;
        } else {
            $eggArray['exported_at'] = Carbon::now()->toAtomString();
        }

        $this->info(" -> exporting to {$outputFile}");
        $yaml = $this->eggToYaml($eggArray);
        if (file_put_contents($outputFile, $yaml) === false) {
            $this->error(" -> failed to write output file: {$outputFile}");

            return Command::FAILURE;
        }

        if ($this->option('delete-original') && $outputFile !== $inputFile) {
            $this->info(' -> deleting input file as requested');
            if (!unlink($inputFile)) {
                $this->warn(" -> failed to delete original file: {$inputFile}");
            }
        }

        return Command::SUCCESS;
    }

    private static function replaceExtension(string $path): string
    {
        return preg_replace('/^(.*\.)(?:yml|json|yaml)$/', '$1yaml', $path) ?? $path;
    }

    /**
     * @param  array<string, mixed>  $eggArray
     */
    private static function fixVariableRules(array &$eggArray): void
    {
        if (!array_key_exists('variables', $eggArray)) {
            return;
        }
        foreach ($eggArray['variables'] as &$var) {
            unset($var['field_type']);
            if (!array_key_exists('rules', $var)) {
                continue;
            }
            $var['rules'] = is_array($var['rules']) ? $var['rules'] : explode('|', $var['rules']);
        }
    }

    /**
     * @param  array<string, mixed>  $eggArray
     */
    private function hasFileChanged(string $unparsed, array $eggArray): bool
    {
        $yaml = $this->eggToYaml($eggArray);

        return $unparsed !== $yaml;
    }

    /**
     * @param  array<string, mixed>  $eggArray
     */
    private function eggToYaml(array $eggArray): string
    {
        return Yaml::dump($this->exporter->yamlExport($eggArray), 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_OBJECT_AS_MAP);
    }
}
