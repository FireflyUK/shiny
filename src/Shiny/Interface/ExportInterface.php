<?php declare(strict_types=1);

namespace Firefly\Shiny\Interface;

interface ExportInterface
{
    public function export(array $data): void;
}