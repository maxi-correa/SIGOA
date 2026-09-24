<?php

use App\Libraries\Uuid;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Validación y generación de UUID (Fase C / F.2).
 *
 * @internal
 */
final class UuidTest extends CIUnitTestCase
{
    public function testV4EsUnUuidValido(): void
    {
        $this->assertTrue(Uuid::isValid(Uuid::v4()));
    }

    public function testV4GeneraValoresDistintos(): void
    {
        $this->assertNotSame(Uuid::v4(), Uuid::v4());
    }

    public function testIsValidAceptaFormatoCanonico(): void
    {
        $this->assertTrue(Uuid::isValid('123e4567-e89b-42d3-a456-426614174000'));
    }

    public function testIsValidRechazaLargoIncorrecto(): void
    {
        $this->assertFalse(Uuid::isValid('123e4567-e89b-42d3-a456-42661417400'));
        $this->assertFalse(Uuid::isValid(str_repeat('a', 36)));
    }

    public function testIsValidRechazaCaracteresNoHexadecimales(): void
    {
        $this->assertFalse(Uuid::isValid('123e4567-e89b-42d3-a456-4266141740gg'));
    }

    public function testIsValidRechazaFaltaDeGuiones(): void
    {
        $this->assertFalse(Uuid::isValid('123e4567e89b42d3a456426614174000'));
    }
}