<?= $this->extend('layouts/auth') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/inspector-obra.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$estadoNombre = strtoupper((string) ($obra->estado_nombre ?? ''));

$estadoClase = match ($estadoNombre) {
    'EN EJECUCIÓN'             => 'estado-ejecucion',
    'NEUTRALIZADA'             => 'estado-neutralizada',
    'EN PLAZO DE CONSERVACIÓN' => 'estado-conservacion',
    'FINALIZADA'               => 'estado-finalizada',
    default                    => 'estado-previo',
};

$puedeInspeccionar = (bool) ($puede_inspeccionar ?? false);
?>

<div class="io-page">

    <!-- ============================================================
         Orden de la pantalla (Fase D.6.2, ajustado en E.3):
         1. volver a "Mis obras";
         2. detalles de la obra;
         3. acción "Nueva inspección";
         4. explicación asociada a esa acción;
         5. acción "Inspecciones" (consulta histórica, E.3);
         6. sincronización: cola pendiente de este dispositivo.
         ============================================================ -->
    <div class="io-barra-acciones">
        <a href="<?= site_url('/inspector/dashboard') ?>" class="btn btn-secondary io-btn-volver">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Mis obras
        </a>
    </div>

    <?php if (session()->getFlashdata('warning')): ?>
        <div class="alert alert-warning" role="alert">
            <span class="alert-icon"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></span>
            <span><?= esc(session()->getFlashdata('warning')) ?></span>
        </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger" role="alert">
            <span class="alert-icon"><i class="bi bi-exclamation-circle" aria-hidden="true"></i></span>
            <span><?= esc(session()->getFlashdata('error')) ?></span>
        </div>
    <?php endif; ?>

    <!-- ============================================================
         Encabezado de identificación de la obra
         ============================================================ -->
    <section class="io-identidad">
        <div class="io-identidad-principal">
            <h1 class="io-nombre"><?= esc($obra->nombre) ?></h1>
            <span class="estado-badge <?= $estadoClase ?>"><?= esc($estadoNombre) ?></span>
        </div>

        <dl class="io-grid">
            <div class="io-dato">
                <dt>N° de expediente</dt>
                <dd><?= esc($obra->expediente_municipal) ?></dd>
            </div>

            <div class="io-dato">
                <dt>Tipo de licitación</dt>
                <dd>
                    <?php if ($obra->tipo_licitacion_nombre): ?>
                        <?= esc($obra->tipo_licitacion_nombre) ?>
                    <?php else: ?>
                        <span class="io-sd">S/D</span>
                    <?php endif; ?>
                </dd>
            </div>

            <div class="io-dato">
                <dt>N° de licitación</dt>
                <dd>
                    <?php if ($obra->numero_licitacion): ?>
                        <?= esc($obra->numero_licitacion) ?>
                    <?php else: ?>
                        <span class="io-sd">S/D</span>
                    <?php endif; ?>
                </dd>
            </div>
        </dl>
    </section>

    <?php if ($puedeInspeccionar): ?>

        <!-- ============================================================
             Herramienta de inspección: la acción y su explicación
             forman un único bloque, inmediatamente debajo de los
             datos de la obra.
             ============================================================ -->
        <div class="io-nueva">
            <a href="<?= site_url('/inspector/inspecciones/nueva/' . (int) $obra->id) ?>"
               class="btn btn-primary io-btn-nueva">
                <i class="bi bi-plus-circle" aria-hidden="true"></i>
                Nueva inspección
            </a>

            <section class="io-aviso">
                <div class="io-aviso-icono" aria-hidden="true">
                    <i class="bi bi-camera"></i>
                </div>
                <div class="io-aviso-texto">
                    <h2 class="io-aviso-titulo">Nueva inspección</h2>
                    <p>
                        Puede iniciar una inspección para esta obra. La inspección y las
                        fotografías se guardan en este dispositivo y quedan pendientes de
                        la futura sincronización con el servidor.
                    </p>
                </div>
            </section>
        </div>

    <?php else: ?>

        <!-- ============================================================
             Aviso: estado que no permite nuevas inspecciones
             ============================================================ -->
        <section class="io-aviso io-aviso-bloqueado">
            <div class="io-aviso-icono" aria-hidden="true">
                <i class="bi bi-pause-circle"></i>
            </div>
            <div class="io-aviso-texto">
                <h2 class="io-aviso-titulo">No se pueden iniciar inspecciones</h2>
                <p>
                    Esta obra se encuentra en estado <?= esc($estadoNombre) ?>. Solo se
                    pueden iniciar nuevas inspecciones en obras en ejecución,
                    neutralizadas o en plazo de conservación.
                </p>
            </div>
        </section>

    <?php endif; ?>

    <!-- ============================================================
         Acción "Inspecciones": punto de entrada a la consulta
         histórica de la obra.

         Es independiente de "Nueva inspección", que solo crea. La
         navegación por fechas, inspecciones y fotografías todavía no
         existe (§62): la vista de destino únicamente informa el estado
         actual. Se muestra en cualquier estado de obra, porque consultar
         el historial no depende de poder iniciar inspecciones.
         ============================================================ -->
    <section class="io-consulta">
        <a href="<?= site_url('/inspector/inspecciones/ver/' . (int) $obra->id) ?>"
           class="btn btn-secondary io-btn-consulta">
            <i class="bi bi-journal-text" aria-hidden="true"></i>
            Inspecciones
        </a>

        <p class="io-consulta-descripcion">
            Inspecciones registradas para esta obra.
        </p>
    </section>

    <!-- ============================================================
         Sincronización: cola de este dispositivo.

         Muestra únicamente lo que falta enviar al servidor
         (inspecciones, fotografías, estado de la cola y reintentos).
         Los identificadores `inspeccionesLocales` / `.io-locales*` son
         los históricos de D.2–D.4 y se conservan para no tocar el
         contrato DOM que ya verifica la suite (§62).
         ============================================================ -->
    <section class="io-locales"
             id="inspeccionesLocales"
             data-obra-id="<?= (int) $obra->id ?>"
             hidden>
        <div class="io-locales-encabezado">
            <h2 class="io-locales-titulo">
                <i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>
                Sincronización
            </h2>
            <p class="io-locales-subtitulo" id="sincronizacionResumen">
                Elementos de esta obra guardados en este dispositivo y pendientes de enviar al servidor.
            </p>
        </div>

        <div class="io-locales-barra">
            <button type="button" class="btn btn-secondary io-btn-sincronizar" id="btnSincronizar">
                <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                Sincronizar
            </button>
        </div>

        <div id="sincronizacionEstado" class="io-locales-alerta" hidden></div>

        <ul class="io-locales-lista" id="inspeccionesLocalesLista"></ul>
    </section>

</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= base_url('assets/js/pages/obra-inspecciones.js') ?>"></script>
<?= $this->endSection() ?>