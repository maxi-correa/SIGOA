<?php

if (! function_exists('titulo_profesional_badge_clase')) {
    function titulo_profesional_badge_clase(?string $titulo): string
    {
        $titulo = strtoupper(trim((string) $titulo));

        return match ($titulo) {
            'INGENIERO'                                   => 'badge-titulo-ingeniero',
            'ARQUITECTO'                                  => 'badge-titulo-arquitecto',
            'MAESTRO MAYOR DE OBRA',
            'MAESTRO MAYOR DE OBRAS'                      => 'badge-titulo-maestro-mayor-obra',
            default                                       => '',
        };
    }
}
