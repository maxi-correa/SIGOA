# AGENTS.md — SIGOA

## 1. Proyecto

Este repositorio corresponde a **SIGOA — Sistema para Inspección de Obras de Arquitectura**.

Es una aplicación desarrollada con:

* PHP 8.3.x
* CodeIgniter 4.7.4
* MariaDB/MySQL
* arquitectura web/PWA

La documentación técnica y funcional principal se encuentra en:

`docs/SIGOA.md`

Consultar ese documento cuando una tarea requiera contexto del sistema, reglas de negocio, estructura de datos o decisiones previamente tomadas.

---

## 2. Regla principal

**Antes de modificar código existente, inspeccionar primero cómo está implementado.**

No asumir:

* nombres de tablas;
* nombres de columnas;
* relaciones;
* rutas;
* modelos;
* controladores;
* estados;
* reglas de negocio.

Verificar el código y, cuando corresponda, la estructura real de la base de datos.

---

## 3. Base de datos

Las migraciones ya ejecutadas forman parte de la estructura consolidada del proyecto.

### NO modificar migraciones ejecutadas.

Si se necesita cambiar la estructura:

1. analizar la estructura actual;
2. crear una nueva migración;
3. ejecutar la nueva migración;
4. verificar el resultado.

No utilizar sin autorización explícita:

* `migrate:fresh`
* `migrate:refresh`
* rollback de migraciones consolidadas.

Nunca eliminar datos o estructura existente para simplificar una tarea sin autorización explícita.

---

## 4. Documentación

`docs/SIGOA.md` es la referencia técnica y funcional del proyecto.

Sus decisiones consolidadas deben respetarse.

Si el código existente contradice la documentación:

1. investigar la causa;
2. verificar migraciones y estructura real;
3. determinar qué está realmente implementado;
4. informar el conflicto si puede afectar datos o arquitectura.

No corregir automáticamente mediante cambios destructivos.

---

## 5. Datos maestros y operativos

Los seeders se utilizan principalmente para catálogos estables.

No crear seeders para datos operativos reales salvo que se soliciten expresamente para pruebas.

Los datos operativos, como obras, inspecciones, fotografías, certificados y actuaciones, deberán generarse mediante la aplicación.

---

## 6. Convenciones importantes

Respetar las siguientes decisiones:

* `obras.id` es el identificador principal.
* `obras.codigo` es único.
* El expediente municipal es único.
* Una empresa puede participar en varias obras.
* El monto contractual original debe conservarse.
* El monto contractual vigente se gestiona separadamente.
* El plazo original debe conservarse.
* Las ampliaciones no deben sobrescribir el plazo original.
* Los inspectores son usuarios.
* Las asignaciones de inspectores deben conservar historial.
* Las fotografías pertenecen a inspecciones.
* Las inspecciones pertenecen a obras.
* Los nombres administrativos se almacenan en mayúsculas cuando corresponda.
* Los importes monetarios utilizan `DECIMAL(15,3)`.

---

## 7. Desarrollo incremental

Realizar cambios pequeños y verificables.

Cada modificación debe:

* resolver la tarea solicitada;
* evitar cambios innecesarios;
* conservar la funcionalidad existente;
* respetar la arquitectura;
* evitar dependencias innecesarias;
* ser verificada antes de considerarse terminada.

No realizar refactorizaciones amplias que no sean necesarias para la tarea.

---

## 8. Funcionalidades pendientes

No tratar como implementadas las funcionalidades que `docs/SIGOA.md` identifica como pendientes.

Entre ellas se encuentran actualmente:

* PWA;
* funcionamiento offline;
* sincronización;
* inspecciones completas;
* fotografías;
* gestión documental;
* certificados;
* recepciones;
* alertas;
* auditoría funcional;
* API/mecanismo de comunicación definitivo.

---

## 9. Seguridad

La seguridad debe considerarse desde el desarrollo inicial.

No omitir deliberadamente:

* validaciones;
* autorización;
* protección de sesiones;
* control de acceso;
* validación de archivos;
* protección de datos.

No implementar atajos inseguros simplemente para acelerar el desarrollo.

---

## 10. Criterio ante dudas

Si una tarea presenta una decisión arquitectónica o de negocio que no está definida:

**no inventar una solución permanente.**

Primero:

1. revisar `docs/SIGOA.md`;
2. revisar el código existente;
3. revisar la base de datos si corresponde;
4. identificar las alternativas;
5. solicitar definición cuando la decisión pueda afectar la arquitectura o los datos.

---

## 11. Principio general

Prioridades del proyecto:

**Integridad de datos → trazabilidad → seguridad → simplicidad → funcionalidad.**

El objetivo es construir un sistema mantenible y confiable para la gestión real de obras de arquitectura.
