<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Pruebas de la lógica de la cola de sincronización (Fase D.4,
 * docs/SIGOA.md §56).
 *
 * A diferencia de `SincronizacionEstructuraTest`, que solo comprueba que
 * los archivos existen y que su texto contiene las piezas esperadas,
 * estas pruebas EJECUTAN `public/assets/js/components/sincronizacion.js`
 * en Node mediante un banco de pruebas simulado (`tests/js/`), sin
 * navegador y sin IndexedDB real.
 *
 * El script de Node se salta, en lugar de fallar, cuando el intérprete no
 * está disponible: en un entorno donde Node no forma parte de las
 * herramientas de PHP, la suite principal sigue siendo ejecutable y las
 * verificaciones estructurales cubren la capa equivalente.
 *
 * @internal
 */
final class SincronizacionLogicaTest extends CIUnitTestCase
{
    /**
     * Devuelve el comando capaz de ejecutar Node en este entorno, o null
     * si no hay ninguno disponible.
     *
     * Los candidatos con `wsl.exe` son un puente: en ellos la ruta del
     * guion debe traducirse antes de invocar Node, porque el intérprete
     * corre dentro de la distribución Linux y no ve las rutas de Windows.
     */
    private function comandoNode(): ?string
    {
        $candidatos = [
            'node' => false,
            'nodejs' => false,
            'wsl.exe -e node' => true,
            'wsl.exe -e /home/maxi/.nvm/versions/node/v24.19.0/bin/node' => true,
        ];

        foreach ($candidatos as $candidato => $esPuente) {
            $salida = [];
            $codigo = 1;

            @exec($candidato . ' --version 2>&1', $salida, $codigo);

            if ($codigo === 0 && isset($salida[0]) && str_starts_with(trim($salida[0]), 'v')) {
                return $esPuente ? $candidato . ' --desde-wsl' : $candidato;
            }
        }

        return null;
    }

    /**
     * Traduce la ruta del guion al sistema de archivos donde corre Node.
     */
    private function rutaGuion(string $comando): ?string
    {
        $guion = realpath(__DIR__ . '/../js/sincronizacion.test.js');

        if ($guion === false) {
            return null;
        }

        if (! str_contains($comando, '--desde-wsl')) {
            return $guion;
        }

        $salida = [];
        $codigo = 1;

        @exec('wsl.exe -e wslpath -a ' . escapeshellarg($guion) . ' 2>&1', $salida, $codigo);

        if ($codigo !== 0 || $salida === []) {
            return null;
        }

        return trim((string) end($salida));
    }

    public function testLogicaDeLaColaSeEjecutaSinErrores(): void
    {
        $comando = $this->comandoNode();

        if ($comando === null) {
            $this->markTestSkipped('Node no está disponible en este entorno.');
        }

        $guion = $this->rutaGuion($comando);

        $this->assertNotNull($guion, 'No se encontró tests/js/sincronizacion.test.js.');

        $interprete = str_replace(' --desde-wsl', '', $comando);
        $salida = [];
        $codigo = 1;

        /* El intérprete es una constante de la lista anterior y puede
           incluir argumentos (`wsl.exe -e node`), por lo que no se entrecomilla
           como una ruta única: solo se protege la ruta del guion. */
        exec(
            $interprete . ' ' . escapeshellarg((string) $guion) . ' 2>&1',
            $salida,
            $codigo
        );

        $this->assertSame(
            0,
            $codigo,
            "Las pruebas de lógica de sincronización fallaron:\n" . implode("\n", $salida)
        );

        $this->assertStringContainsString('0 fallos', implode("\n", $salida));
    }
}
