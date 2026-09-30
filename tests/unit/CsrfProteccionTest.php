<?php

use App\Filters\CsrfApi;
use CodeIgniter\Config\App;
use CodeIgniter\HTTP\Response;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Protección CSRF global (Fase B).
 *
 * Verifica que la protección CSRF esté activa sobre las peticiones que
 * modifican estado (POST) y que los mecanismos de envío habilitados en la
 * aplicación funcionen: campo oculto en formularios (`csrf_field()`) y
 * cabecera `X-CSRF-TOKEN` en las peticiones fetch().
 *
 * El filtro CSRF de CodeIgniter lanza SecurityException cuando el token falta
 * o no coincide (en entorno de pruebas y para peticiones no-AJAX no hay
 * redirección). Por eso los casos inválidos se verifican con expectException.
 *
 * @internal
 */
final class CsrfProteccionTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testPostSinTokenEsRechazado(): void
    {
        $this->expectException(SecurityException::class);

        $this->post('/login', []);
    }

    public function testPostConTokenInvalidoEnCampoEsRechazado(): void
    {
        $this->expectException(SecurityException::class);

        $this->post('/login', ['csrf_test_name' => 'token-invalido']);
    }

    public function testPostConTokenInvalidoEnCabeceraEsRechazado(): void
    {
        $this->expectException(SecurityException::class);

        $this->withHeaders(['X-CSRF-TOKEN' => 'token-invalido'])->post('/login', []);
    }

    public function testPostConTokenValidoEnCampoEsAceptado(): void
    {
        $resultado = $this->post('/login', ['csrf_test_name' => csrf_hash()]);

        $resultado->assertStatus(302);
    }

    public function testPostConTokenValidoEnCabeceraEsAceptado(): void
    {
        $resultado = $this->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])->post('/login', []);

        $resultado->assertStatus(302);
    }

    public function testTokenSeRegeneraTrasPeticionExitosa(): void
    {
        $token = csrf_hash();

        $this->post('/login', ['csrf_test_name' => $token]);

        $this->assertNotSame($token, csrf_hash());
    }

    public function testTokenAntiguoEsRechazadoTrasRegeneracion(): void
    {
        $token = csrf_hash();

        $this->post('/login', ['csrf_test_name' => $token]);

        $this->expectException(SecurityException::class);

        $this->post('/login', ['csrf_test_name' => $token]);
    }

    public function testPeticionesGetNoSonBloqueadasPorCsrf(): void
    {
        $resultado = $this->get('/login');

        $resultado->assertStatus(200);
    }

    /* ==================================================================
       Fase D.6.3 — publicación del token vigente (docs/SIGOA.md §61)
       ================================================================== */

    /**
     * Es el contrato que rompe el drenaje de fotografías: la respuesta dice
     * con qué token puede hacerse la siguiente petición.
     */
    public function testRespuestaApiPublicaElTokenVigenteEnLaCabecera(): void
    {
        $enviado = csrf_hash();

        $resultado = $this->withHeaders([
            'Accept'       => 'application/json',
            'X-CSRF-TOKEN' => $enviado,
        ])->post('/login', []);

        $publicado = $resultado->response()->getHeaderLine('X-CSRF-TOKEN');

        $this->assertNotSame('', $publicado, 'La respuesta API debe traer el token vigente.');
        $this->assertSame(
            csrf_hash(),
            $publicado,
            'La cabecera debe llevar el token que quedó vigente tras regenerar.'
        );
        $this->assertNotSame(
            $enviado,
            $publicado,
            'Debe publicarse el token nuevo: publicar el que envió el cliente dejaría la cola igual de atascada.'
        );
    }

    /**
     * Dos peticiones encadenadas con el token publicado: es exactamente lo
     * que hace la cola al subir varias fotografías seguidas.
     */
    public function testElTokenPublicadoPermiteLaPeticionSiguiente(): void
    {
        $primero = csrf_hash();

        $respuesta = $this->withHeaders([
            'Accept'       => 'application/json',
            'X-CSRF-TOKEN' => $primero,
        ])->post('/login', []);

        $vigente = $respuesta->response()->getHeaderLine('X-CSRF-TOKEN');

        $this->assertNotSame($primero, $vigente);

        $segunda = $this->withHeaders([
            'Accept'       => 'application/json',
            'X-CSRF-TOKEN' => $vigente,
        ])->post('/login', []);

        /* 302: el login responde con `redirect()->back()`; lo relevante es que
           la segunda petición superó la verificación CSRF. */
        $segunda->assertStatus(302);

        $this->assertNotSame(
            $vigente,
            $segunda->response()->getHeaderLine('X-CSRF-TOKEN'),
            'La segunda petición también debe rotar el token.'
        );
    }

    /**
     * El HTML ya lleva el token en el `<meta>`; la cabecera se reserva a las
     * respuestas que la cola consume con `fetch()`.
     *
     * El filtro se invoca directamente sobre respuestas limpias porque el banco
     * de pruebas reutiliza el mismo objeto `Response` entre peticiones del
     * mismo proceso: una cabecera publicada por una prueba anterior aparecería
     * como si la hubiera puesto esta.
     */
    public function testElFiltroSoloPublicaLaCabeceraEnRespuestasApi(): void
    {
        $this->post('/login', ['csrf_test_name' => csrf_hash()]);

        $request = service('request');
        $filtro  = new CsrfApi();

        $this->assertFalse($request->isAJAX());
        $this->assertStringNotContainsString('application/json', $request->getHeaderLine('Accept'));

        $html = $this->respuestaLimpia();
        $filtro->after($request, $html);
        $this->assertFalse($html->hasHeader('X-CSRF-TOKEN'), 'Una respuesta HTML no debe llevar la cabecera.');

        $peticionApi = clone $request;
        $peticionApi->setHeader('Accept', 'application/json');

        $api = $this->respuestaLimpia();
        $filtro->after($peticionApi, $api);
        $this->assertTrue($api->hasHeader('X-CSRF-TOKEN'), 'Una respuesta de una petición API debe llevar la cabecera.');
    }

    private function respuestaLimpia(): Response
    {
        return new Response(config(App::class));
    }

    /**
     * Un token rechazado no renueva nada: la pausa de la cola es real y no un
     * reintento en bucle (§56.6).
     *
     * No se comprueba la ausencia de la cabecera con `assertHeaderMissing()`
     * porque el banco de pruebas comparte el objeto `Response` entre clases
     * (`CIUnitTestCase::$app` es estático), de modo que una cabecera publicada
     * por otra prueba aparecería como si la hubiera puesto esta. La ausencia se
     * deduce de lo que sí es observable: `before()` devuelve la respuesta 403 y
     * CodeIgniter no ejecuta los filtros `after`, así que nada rota el token.
     */
    public function testRechazoCsrfNoRenuevaElTokenVigente(): void
    {
        $vigente = csrf_hash();

        $resultado = $this->withHeaders([
            'Accept'       => 'application/json',
            'X-CSRF-TOKEN' => 'token-invalido',
        ])->post('/login', []);

        $resultado->assertStatus(403);
        $resultado->assertJSONFragment(['ok' => false, 'error' => 'CSRF_INVALID']);

        $this->assertSame(
            $vigente,
            csrf_hash(),
            'Un token rechazado no debe rotar el vigente: el cliente tiene que poder reintentar con él.'
        );
    }
}