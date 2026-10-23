<?php

/**
 * Documentación disponible de la obra (vista del inspector).
 *
 * La pantalla muestra la **versión vigente** de cada documento lógico de la
 * obra. El contenido se resuelve en el cliente: con conexión se usa el
 * listado que entrega el servidor y los PDF se abren desde el servidor; sin
 * conexión se usan las copias descargadas en el dispositivo (IndexedDB).
 *
 * El listado y el detalle de cada documento se dibujan con JavaScript
 * (`assets/js/pages/documentacion-inspector.js`) para que ambos modos
 * compartan exactamente el mismo render. Esta vista solo aporta la
 * estructura, el contexto de la obra y los datos iniciales.
 */
$obra       = $obra ?? null;
$documentos = is_array($documentos ?? null) ? $documentos : [];
$payload    = is_array($payload ?? null) ? $payload : [];
$estadoNombre = strtoupper((string) ($obra->estado_nombre ?? ''));

$estadoClase = match ($estadoNombre) {
    'EN EJECUCIÓN'             => 'estado-ejecucion',
    'NEUTRALIZADA'             => 'estado-neutralizada',
    'EN PLAZO DE CONSERVACIÓN' => 'estado-conservacion',
    'FINALIZADA'               => 'estado-finalizada',
    default                    => 'estado-previo',
};
?>
<?= $this->extend('layouts/auth') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/documentacion-inspector.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="did-page" id="documentacionDisponible">

    <div class="did-barra-acciones">
        <a href="<?= site_url('/inspector/obras/ver/' . (int) ($obra->id ?? 0)) ?>" class="btn btn-volver">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Volver a la obra
        </a>
    </div>

    <header class="did-encabezado">
        <div class="did-contexto">
            <p class="did-obra"><?= esc((string) ($obra->nombre ?? '')) ?></p>
            <?php if ($estadoNombre !== ''): ?>
                <span class="estado-badge <?= $estadoClase ?>"><?= esc($estadoNombre) ?></span>
            <?php endif; ?>
        </div>

        <h1 class="did-titulo">Documentación disponible</h1>

        <p class="did-resumen" id="didResumen">
            <?= count($documentos) === 1
                ? '1 documento vigente'
                : count($documentos) . ' documentos vigentes' ?>
        </p>
    </header>

    <!-- ============================================================
         Estado de conexión y acción de recarga
         ============================================================ -->
    <section class="did-conexion" id="didConexion" role="status" aria-live="polite">
        <span class="did-conexion-icono" aria-hidden="true">
            <i class="bi bi-cloud-check"></i>
        </span>
        <span class="did-conexion-texto" id="didConexionTexto">Comprobando conexión con el servidor…</span>
        <button type="button" class="did-conexion-accion" id="didRefrescar">
            <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
            Actualizar
        </button>
    </section>

    <!-- ============================================================
         Filtros (RNF §35: orden visual, sin sobrecargar la pantalla)
         ============================================================ -->
    <nav class="did-filtros" id="didFiltros" aria-label="Filtros de la documentación">
        <button type="button" class="did-filtro is-activo" data-filtro="todos" aria-pressed="true">
            Todos
        </button>
        <button type="button" class="did-filtro" data-filtro="offline" aria-pressed="false">
            Disponibles offline
        </button>
        <button type="button" class="did-filtro" data-filtro="pendientes" aria-pressed="false">
            Pendientes de descargar
        </button>
    </nav>

    <div class="alert alert-info did-aviso" id="didAviso" role="alert" hidden>
        <span class="alert-icon"><i class="bi bi-info-circle" aria-hidden="true"></i></span>
        <span id="didAvisoTexto"></span>
    </div>

    <!-- ============================================================
         Listado de documentos
         ============================================================ -->
    <ul class="did-lista" id="didLista"></ul>

    <div class="did-vacio" id="didVacio" hidden>
        <div class="did-vacio-icono" aria-hidden="true">
            <i class="bi bi-folder2-open"></i>
        </div>
        <h2 class="did-vacio-titulo" id="didVacioTitulo">No hay documentos para mostrar</h2>
        <p class="did-vacio-texto" id="didVacioTexto"></p>
    </div>

</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script type="application/json" id="didDatos"><?= json_encode(
        $payload,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    ) ?></script>
    <script src="<?= base_url('assets/js/pages/documentacion-inspector.js') ?>"></script>
<?= $this->endSection() ?>
