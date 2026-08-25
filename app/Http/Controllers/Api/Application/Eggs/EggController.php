<?php

namespace App\Http\Controllers\Api\Application\Eggs;

use App\Data\Api\Application\EggData;
use App\Enums\EggFormat;
use App\Http\Controllers\Api\Application\ApplicationApiController;
use App\Http\Requests\Api\Application\Eggs\ExportEggRequest;
use App\Http\Requests\Api\Application\Eggs\GetEggRequest;
use App\Http\Requests\Api\Application\Eggs\GetEggsRequest;
use App\Http\Requests\Api\Application\Eggs\ImportEggRequest;
use App\Models\Egg;
use App\Services\Eggs\Sharing\EggExporterService;
use App\Services\Eggs\Sharing\EggImporterService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class EggController extends ApplicationApiController
{
    public function __construct(
        private EggExporterService $exporterService,
        private EggImporterService $importService
    ) {
        parent::__construct();
    }

    /**
     * List eggs
     *
     * Return all eggs
     *
     * @return array<mixed>
     */
    public function index(GetEggsRequest $request): array
    {
        return $this->response->collection(Egg::all())
            ->transformWith(EggData::class)
            ->toArray();
    }

    /**
     * View egg
     *
     * Return a single egg that exists
     *
     * @return array<mixed>
     */
    public function view(GetEggRequest $request, Egg $egg): array
    {
        return $this->response->item($egg)
            ->transformWith(EggData::class)
            ->toArray();
    }

    /**
     * Delete egg
     *
     * Delete an egg from the Panel.
     *
     * @throws Exception
     */
    public function delete(GetEggRequest $request, Egg $egg): Response
    {
        $egg->delete();

        return $this->returnNoContent();
    }

    /**
     * Export egg
     *
     * Return a single egg as yaml or json file (defaults to YAML)
     */
    public function export(ExportEggRequest $request, Egg $egg): StreamedResponse
    {
        $format = EggFormat::tryFrom($request->input('format')) ?? EggFormat::YAML;

        return response()->streamDownload(function () use ($egg, $format) {
            echo $this->exporterService->handle($egg->id, $format);
        }, 'egg-' . $egg->getKebabName() . '.' . $format->value, [
            'Content-Type' => 'application/' . $format->value,
        ]);
    }

    /**
     * Import egg
     *
     * Create a new egg on the Panel. Returns the created egg and an HTTP/201 status response on success
     * If no uuid is supplied a new one will be generated
     * If an uuid is supplied, and it already exists the old configuration get overwritten
     *
     * @throws Exception|Throwable
     */
    public function import(ImportEggRequest $request): JsonResponse
    {
        $egg = $this->importService->fromContent($request->getContent());

        return $this->response->item($egg)
            ->transformWith(EggData::class)
            ->respond(201);
    }
}
