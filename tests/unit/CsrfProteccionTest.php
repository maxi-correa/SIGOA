<?php

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
}