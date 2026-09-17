<?php

namespace App\Reportes;

use Closure;
use Generator;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Una hoja exportable (XLSX o CSV): título, encabezados, filas por generador y formato de columnas. */
class HojaReporte implements FromGenerator, ShouldAutoSize, WithColumnFormatting, WithCustomCsvSettings, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  list<string>  $encabezados
     * @param  Closure(): iterable<list<mixed>>  $filas
     * @param  array<string, string>  $formatos  columna => formato de número de Excel
     */
    public function __construct(
        private readonly string $titulo,
        private readonly array $encabezados,
        private readonly Closure $filas,
        private readonly array $formatos = [],
    ) {}

    public function generator(): Generator
    {
        yield from ($this->filas)();
    }

    public function headings(): array
    {
        return $this->encabezados;
    }

    public function title(): string
    {
        // Excel limita el nombre de la hoja a 31 caracteres.
        return mb_substr($this->titulo, 0, 31);
    }

    public function columnFormats(): array
    {
        return $this->formatos;
    }

    public function styles(Worksheet $hoja): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    /** Punto y coma y BOM: así lo abre bien Excel con configuración regional de Chile. */
    public function getCsvSettings(): array
    {
        return ['delimiter' => ';', 'use_bom' => true];
    }
}
