<?php

namespace App\Services;

use App\Libraries\PlazoObra;
use App\Models\DocumentoModel;
use App\Models\TipoDocumentoModel;

/**
 * Reglas de validación compartidas por el alta y la corrección de
 * recepciones (BLOQUES 3 a 5). Devuelven el mensaje de error o null.
 */
final class RecepcionValidacion
{
    public const PLAZO_MAXIMO_DIAS = 36500;

    public const TIPO_ACTA_PROVISORIA = 'ACTA DE RECEPCIÓN PROVISORIA';

    public const TIPO_ACTA_DEFINITIVA = 'ACTA DE RECEPCIÓN DEFINITIVA';

    /**
     * Fecha de la recepción provisoria: obligatoria, no futura y no anterior
     * al inicio de la obra cuando existe.
     */
    public static function fechaProvisoria(?string $fecha, ?string $fechaInicioObra): ?string
    {
        if ($fecha === null) {
            return 'La fecha de recepción provisoria no es válida.';
        }

        if (strcmp($fecha, date('Y-m-d')) > 0) {
            return 'La fecha de recepción provisoria no puede ser posterior a la fecha actual.';
        }

        if ($fechaInicioObra !== null && $fechaInicioObra !== '' && strcmp($fecha, $fechaInicioObra) < 0) {
            return 'La fecha de recepción provisoria no puede ser anterior al inicio de la obra.';
        }

        return null;
    }

    public static function plazo(?int $plazo): ?string
    {
        if ($plazo === null || $plazo < 1) {
            return 'El plazo de conservación debe ser un número entero mayor a cero.';
        }

        if ($plazo > self::PLAZO_MAXIMO_DIAS) {
            return 'El plazo de conservación no puede superar los ' . self::PLAZO_MAXIMO_DIAS . ' días.';
        }

        return null;
    }

    /**
     * Fecha de la recepción definitiva: no futura y no anterior a la fecha
     * prevista calculada con la provisoria indicada (la nueva, si se corrige).
     */
    public static function fechaDefinitiva(?string $fecha, string $fechaProvisoria, int $plazo): ?string
    {
        if ($fecha === null) {
            return 'La fecha de recepción definitiva no es válida.';
        }

        if (strcmp($fecha, date('Y-m-d')) > 0) {
            return 'La fecha de recepción definitiva no puede ser posterior a la fecha actual.';
        }

        $prevista = PlazoObra::fechaPrevistaDefinitiva($fechaProvisoria, $plazo);

        if ($prevista === null) {
            return 'La recepción provisoria registrada no tiene una fecha prevista válida.';
        }

        if (! PlazoObra::permiteRecepcionDefinitiva($fechaProvisoria, $plazo, $fecha)) {
            return 'La recepción definitiva no puede registrarse antes de la fecha prevista ('
                . (PlazoObra::formatearFecha($prevista) ?? $prevista) . ').';
        }

        return null;
    }

    /**
     * Un acta vinculada debe ser la versión vigente del tipo indicado de la
     * misma obra. Sin acta no hay error.
     */
    public static function acta(int $obraId, ?int $documentoId, string $tipoNombre): ?string
    {
        if ($documentoId === null) {
            return null;
        }

        $tipo      = (new TipoDocumentoModel())->findByNombre($tipoNombre);
        $documento = (new DocumentoModel())->find($documentoId);

        if (
            $tipo === null
            || $documento === null
            || (int) $documento->obra_id !== $obraId
            || (int) $documento->tipo_documento_id !== (int) $tipo->id
            || (int) $documento->es_version_actual !== 1
        ) {
            return 'El acta seleccionada no corresponde a una versión vigente de ' . mb_strtolower($tipoNombre) . ' de esta obra.';
        }

        return null;
    }
}
