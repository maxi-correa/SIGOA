# SIGOA

## Sistema para Inspección de Obras de Arquitectura

**Documento técnico y de referencia del proyecto**

---

## 1. Identificación del proyecto

**Nombre:** SIGOA
**Descripción:** Sistema para la gestión, seguimiento, inspección y documentación de obras de arquitectura.

SIGOA está destinado principalmente a la Dirección General de Obras de Arquitectura.

El sistema contempla una aplicación web/PWA para la gestión administrativa y una utilización desde dispositivos móviles por parte de los inspectores en tareas de campo.

---

## 2. Objetivo general

Centralizar la información relacionada con las obras de arquitectura, permitiendo:

* administrar las obras;
* registrar empresas contratistas;
* administrar inspectores y sus asignaciones;
* registrar inspecciones;
* tomar y asociar fotografías desde dispositivos móviles;
* almacenar documentación relacionada con las obras;
* registrar resoluciones, neutralizaciones, reinicios y recepciones;
* gestionar certificados;
* mantener trazabilidad de las actuaciones;
* trabajar con conectividad limitada o inexistente en campo;
* sincronizar posteriormente la información generada fuera de línea.

---

## 3. Contexto de utilización

SIGOA será utilizado principalmente por:

* personal administrativo;
* inspectores de obra;
* usuarios encargados de supervisión o consulta;
* administradores del sistema.

La cantidad inicial de usuarios es reducida, por lo que no se requiere una arquitectura orientada inicialmente a grandes volúmenes de usuarios concurrentes.

La aplicación debe priorizar:

* simplicidad;
* confiabilidad;
* trazabilidad;
* facilidad de mantenimiento;
* seguridad;
* funcionamiento correcto desde dispositivos móviles.

---

# 4. Estado actual del proyecto

## 4.1 IMPLEMENTADO

Actualmente se encuentra implementada y ejecutada la estructura inicial de base de datos mediante migraciones de CodeIgniter 4.

La base de datos contiene, entre otras, las siguientes tablas:

* `estados_obra`
* `barrios`
* `tipos_licitacion`
* `tipos_resolucion`
* `tipos_documento`
* `roles`
* `empresas`
* `usuarios`
* `usuarios_roles`
* `obras`
* `resoluciones`
* `inspectores_obras`
* `inspecciones`
* `fotografias`
* `documentos`
* `neutralizaciones`
* `recepciones_provisorias`
* `recepciones_definitivas`
* `certificados`
* `elementos_entrega`
* `auditoria`
* `operaciones_sincronizacion`

Las migraciones existentes fueron ejecutadas correctamente.

Los datos maestros iniciales también fueron cargados mediante seeders.

Adicionalmente, se encuentra implementada la infraestructura de autenticación y autorización:

* autenticación mediante usuario y contraseña;
* carga de roles desde `usuarios_roles` durante el login;
* sesión enriquecida con roles como array;
* regeneración segura de sesión después del login;
* `AuthFilter` — protección de rutas autenticadas;
* `RoleFilter` — autorización por rol mediante filtros paramétricos de CI4;
* routing separado por área/rol mediante route groups;
* redirección automática al dashboard correspondiente según jerarquía de roles;
* dashboards mínimos independientes para cada rol;
* logout con destrucción de sesión;
* módulo "Mis Datos" — pantalla de datos personales para todos los roles;
* modificación de correo electrónico (solo ADMINISTRADOR y SUPERADMINISTRADOR);
* cambio de contraseña (operativo tras verificar la contraseña actual — ver §45.5);
* verificación de contraseña mediante modal (ojo), con estado "Verificada";
* estructura de navegación autenticada con **sidebar** lateral (escritorio) y drawer/off-canvas responsive (móvil) — ver §46;
* topbar de identidad: la navegación principal ya no vive en la barra superior; "Mis Datos" y "Cerrar sesión" se movieron a la zona inferior del sidebar;
* página **Gestión de usuarios** (consulta/listado) para SUPERADMINISTRADOR y ADMINISTRADOR — ver §46;
* refactor de `getDashboardPath()` y `getRolPrincipal()` a `BaseController`;
* **dashboard administrativo** (SUPERADMINISTRADOR y ADMINISTRADOR) con la sección **Obras** como eje principal — listado, paginación y alta inicial de obras — ver §47;
* **edición de datos básicos de obras** — modal reutilizable de alta/edición, estado libre en esta etapa, unicidad de expediente con exclusión — ver §48;
* **Fase D.1** — fundación PWA/offline: manifest, Service Worker de app shell, registro de SW, capa propia de IndexedDB (base `SIGOA`, stores `inspecciones`/`fotografias`/`operaciones`), UUID v4 en el cliente y estado online/offline en la topbar — ver §53;
* **Fase D.2** — nueva inspección 100 % local del inspector móvil: autorización en servidor (estado F.7 + asignación vigente), guardado en IndexedDB (v2), captura/optimización/thumbnail de fotografías en el cliente y vista de inspecciones locales — ver §54.

---

## 4.2 PENDIENTE

La existencia de una tabla o estructura de base de datos no implica que la funcionalidad correspondiente esté desarrollada.

Quedan pendientes, entre otras:

* interfaz de gestión de obras;
* gestión completa de usuarios (CRUD: creación, edición, eliminación, cambio de roles, activación/desactivación);
* interfaces;
* gestión de inspectores;
* registro de inspecciones en servidor (en dispositivo, local, desde D.2 — §54);
* carga de fotografías al servidor (optimización y visualización local desde D.2 — §54);
* funcionamiento offline completo;
* sincronización;
* gestión documental;
* gestión de resoluciones;
* neutralizaciones y reinicios;
* recepciones;
* certificados;
* auditoría;
* alertas;
* API o mecanismos de comunicación necesarios;
* interfaz PWA completa.

Estas funcionalidades deberán desarrollarse progresivamente.

---

# 5. REGLAS FUNDAMENTALES DEL PROYECTO

## 5.1 Migraciones

Las migraciones que ya fueron ejecutadas se consideran parte consolidada de la estructura existente.

**NO modificar migraciones ya ejecutadas.**

Si posteriormente es necesario modificar la estructura de una tabla:

1. verificar primero la estructura actual;
2. crear una nueva migración;
3. aplicar la nueva migración;
4. verificar el resultado.

No utilizar sin autorización explícita:

* `migrate:fresh`
* `migrate:refresh`
* rollback de migraciones existentes.

---

## 5.2 No asumir estructuras

Antes de modificar código relacionado con la base de datos, verificar:

* migraciones;
* modelos;
* controladores;
* relaciones;
* nombres reales de columnas;
* claves foráneas;
* restricciones;
* código existente.

No inventar nombres de tablas o columnas.

---

## 5.3 Datos maestros y datos operativos

Los seeders se utilizan principalmente para datos maestros y catálogos estables.

### Datos maestros

Actualmente incluyen:

* estados de obra;
* barrios;
* tipos de licitación;
* tipos de resolución;
* tipos de documento;
* roles.

### Datos operativos

No deben generarse mediante seeders en la aplicación real.

Ejemplos:

* empresas;
* usuarios reales;
* obras;
* inspecciones;
* fotografías;
* certificados;
* resoluciones;
* recepciones;
* actuaciones.

Estos datos deberán generarse mediante las funcionalidades correspondientes de la aplicación.

---

# 6. CONVENCIONES

## 6.1 Idioma

La aplicación está orientada al idioma español.

Los nombres visibles para el usuario deben utilizar terminología administrativa y técnica adecuada al contexto argentino.

---

## 6.2 Mayúsculas

Los nombres administrativos almacenados en la base de datos deben conservarse en mayúsculas cuando corresponda.

Se deben conservar correctamente:

* tildes;
* Ñ;
* caracteres especiales del idioma español.

---

## 6.3 Importes

Los importes monetarios representan pesos argentinos.

Se utilizará:

`DECIMAL(15,3)`

cuando corresponda a importes monetarios.

---

## 6.4 Porcentajes

Los porcentajes se manejarán con tres decimales cuando corresponda.

Ejemplo:

`75.250`

---

## 6.5 Identificación de obras

La clave primaria de una obra es:

`obras.id`

Además existe:

`obras.codigo`

que debe ser único.

El expediente municipal también debe ser único.

Ejemplo de formato de expediente:

`4356-M-2026`

---

# 7. ROLES

Los roles iniciales son:

1. SUPERADMINISTRADOR
2. ADMINISTRADOR
3. INSPECTOR
4. CONSULTA

La relación entre usuarios y roles se encuentra normalizada mediante:

`usuarios_roles`

Un usuario puede tener uno o más roles según las reglas que posteriormente se establezcan.

---

# 8. ESTADOS DE OBRA

Los estados actualmente definidos son:

1. PREVIO INICIO
2. EN EJECUCIÓN
3. NEUTRALIZADA
4. EN PLAZO DE CONSERVACIÓN
5. FINALIZADA

Flujo conceptual:

`PREVIO INICIO → EN EJECUCIÓN → EN PLAZO DE CONSERVACIÓN → FINALIZADA`

La neutralización representa una situación especial durante la ejecución:

`EN EJECUCIÓN ↔ NEUTRALIZADA`

El sistema deberá impedir transiciones inválidas cuando se implemente la lógica correspondiente.

---

# 9. BARRIOS

Los barrios se identifican mediante un número de catastro y un nombre.

Actualmente existen 77 barrios cargados en la base de datos.

El número de catastro es único.

---

# 10. TIPOS DE LICITACIÓN

Los tipos definidos son:

1. LICITACIÓN PÚBLICA
2. LICITACIÓN PRIVADA
3. CONCURSO PÚBLICO
4. CONCURSO PRIVADO DE PRECIOS
5. CONTRATACIÓN DIRECTA

---

# 11. TIPOS DE RESOLUCIÓN

Los tipos definidos son:

1. ADJUDICACIÓN
2. APROBACIÓN DE CUADRO COMPARATIVO
3. APROBACIÓN DE AMPLIACIÓN DE PLAZO
4. OTRO

---

# 12. TIPOS DE DOCUMENTO

Los tipos definidos son:

1. ACTA DE ENTREGA DE VEHÍCULO
2. ACTA DE DEVOLUCIÓN DE VEHÍCULO
3. ACTA DE INICIO
4. ACTA DE NEUTRALIZACIÓN
5. ACTA DE REINICIO
6. ACTA DE RECEPCIÓN PROVISORIA
7. ACTA DE RECEPCIÓN DEFINITIVA
8. DESIGNACIÓN DE INSPECCIÓN
9. CERTIFICADO
10. FOJA DE MEDICIÓN
11. ANTICIPO FINANCIERO
12. FONDO DE REPARO
13. PÓLIZA
14. PLIEGO
15. PLANO
16. OTROS

---

# 13. EMPRESAS

Una empresa contratista puede participar en más de una obra.

Por lo tanto, la relación conceptual es:

**Empresa 1 → muchas obras**

No debe asumirse que una empresa pertenece exclusivamente a una obra.

El CUIT es opcional según la definición actual.

El logo de la empresa también es opcional.

### Almacenamiento del logo

* Los archivos se almacenan bajo `<RAIZ_SIGOA>/EMPRESAS/`.
* La raíz física (`SIGOA_STORAGE_PATH`) es configurable en `.env` e implementada en `Config\SigoaStorage`.
* La tabla `empresas.ruta_logo` almacena únicamente una ruta relativa, nunca una ruta absoluta.
* El formato de referencia es `EMPRESAS/000001-logo.ext` (el `id` de la empresa a seis dígitos, cero-padded).
* El nombre físico del archivo se genera a partir del `id` de la empresa.
* Se admiten únicamente los formatos JPG, JPEG, PNG y WEBP (validados por MIME real vía `finfo`).
* El tamaño máximo por archivo es 2 MB.
* Cada empresa tiene un único logo vigente.
* Al reemplazar un logo, el archivo anterior se elimina del disco.
* Al eliminar el logo, la referencia `ruta_logo` pasa a `NULL` en la base de datos.
* Las operaciones sobre el logo actualizan `updated_at` pero no modifican `created_at`.

---

# 14. OBRAS

La tabla `obras` representa la entidad principal del sistema.

Actualmente contempla:

* `id`
* `codigo`
* `expediente_municipal`
* `numero_licitacion`
* `tipo_licitacion_id`
* `nombre`
* `barrio_id`
* `empresa_id`
* `monto_contrato`
* `monto_contractual_vigente`
* `expediente_contable`
* `fecha_inicio`
* `plazo_original_valor`
* `plazo_original_unidad`
* `plazo_original_dias`
* `estado_obra_id`
* `observacion_general`
* `created_at`
* `updated_at`

Los campos relacionados con barrio y empresa pueden ser nulos según la estructura actual.

---

# 15. MONTOS CONTRACTUALES

Se distinguen:

### Monto contractual original

`monto_contrato`

Representa el monto originalmente contratado.

### Monto contractual vigente

`monto_contractual_vigente`

Representa el monto actualmente aprobado considerando las modificaciones contractuales correspondientes.

El monto original no debe sobrescribirse por modificaciones posteriores.

Esto permite conservar la historia contractual.

---

# 16. PLAZO DE OBRA

El plazo original debe conservarse.

Actualmente se dispone de:

* valor;
* unidad;
* cantidad de días calculada.

Las ampliaciones de plazo posteriores no deben modificar el plazo original.

La información de ampliaciones debe conservarse separadamente.

---

# 17. INSPECTORES

Los inspectores son usuarios del sistema.

La relación entre inspectores y obras debe contemplar historial.

Un inspector puede ser reemplazado durante la ejecución de una obra.

Por lo tanto, la asignación no debe modelarse simplemente como un dato permanente de la obra.

La tabla:

`inspectores_obras`

permite conservar las asignaciones correspondientes.

---

# 18. INSPECCIONES

Una inspección pertenece a una obra.

Conceptualmente:

**Obra → muchas inspecciones**

Cada inspección representa una actuación realizada en una fecha determinada.

Las inspecciones constituyen el contexto para las fotografías y otros registros generados durante una visita.

Una obra puede tener **múltiples inspecciones en la misma fecha**: la fecha sirve para agrupar documentalmente las inspecciones y cada inspección se identifica mediante su `uuid` (decisión consolidada en §52.3). La restricción `UNIQUE (obra_id, fecha_inspeccion)` existente se eliminará mediante una migración futura, sin modificar la migración histórica que la creó.

---

# 19. FOTOGRAFÍAS

Las fotografías pertenecen a inspecciones.

Conceptualmente:

**Inspección → muchas fotografías**

Cada fotografía debe poder mantener información suficiente para:

* identificarla;
* asociarla con la obra;
* asociarla con la inspección;
* conservar fecha;
* conservar información relevante de sincronización;
* localizar el archivo correspondiente.

El almacenamiento físico de los archivos debe mantenerse separado de la información descriptiva almacenada en la base de datos.

---

# 20. FUNCIONAMIENTO OFFLINE

## DECIDIDO

El funcionamiento sin conexión es un requisito fundamental de SIGOA.

El inspector debe poder realizar determinadas tareas desde el dispositivo móvil aun cuando no exista conexión a Internet.

Como mínimo, el diseño deberá contemplar:

* captura de información;
* captura de fotografías;
* almacenamiento local temporal;
* identificación de operaciones pendientes;
* posterior sincronización.

## PENDIENTE

La estrategia técnica definitiva de almacenamiento local y sincronización fue definida en §52 y todavía debe implementarse y validarse.

No asumir una tecnología concreta distinta a la definida en §52.

---

# 21. SINCRONIZACIÓN

## DECIDIDO

La sincronización debe evitar:

* duplicación de registros;
* pérdida de fotografías;
* pérdida de inspecciones;
* inconsistencias entre dispositivo y servidor.

Las operaciones de sincronización deben poder distinguir estados tales como:

* pendiente;
* procesada correctamente;
* error.

La tabla:

`operaciones_sincronizacion`

forma parte de la estructura prevista para este mecanismo.

## PENDIENTE

La lógica completa de sincronización todavía no está implementada. El diseño técnico definido está documentado en §52.

---

# 22. DOCUMENTACIÓN

Las obras pueden tener documentación asociada.

La tabla:

`documentos`

permitirá relacionar documentos con las entidades correspondientes.

Entre los documentos previstos se encuentran:

* actas;
* certificados;
* pólizas;
* pliegos;
* planos;
* fojas;
* resoluciones;
* otros documentos administrativos.

El almacenamiento físico de archivos y la metadata de documentos deben mantenerse conceptualmente separados.

---

# 23. RESOLUCIONES

Las resoluciones se relacionan con las obras y utilizan los tipos definidos en:

`tipos_resolucion`

Se contempla, entre otros casos:

* adjudicaciones;
* aprobación de cuadros comparativos;
* ampliaciones de plazo;
* otros actos administrativos.

---

# 24. NEUTRALIZACIONES Y REINICIOS

Una obra puede ser neutralizada durante su ejecución.

La neutralización debe conservar:

* fecha;
* documentación correspondiente;
* información relevante del período;
* posterior reinicio.

La neutralización no debe eliminar ni sobrescribir la historia de ejecución.

---

# 25. RECEPCIONES

El sistema contempla:

* recepción provisoria;
* recepción definitiva.

Las recepciones forman parte de la historia administrativa de la obra.

La recepción definitiva también constituye un hito relevante para alertas y seguimiento.

---

# 26. CERTIFICADOS

El sistema contempla el registro de certificados de obra.

Los certificados deberán permitir conservar información suficiente para relacionarlos con:

* obra;
* período;
* documentación;
* montos;
* avance correspondiente.

La implementación funcional completa todavía está pendiente.

---

# 27. ELEMENTOS DE ENTREGA

La estructura contempla:

`elementos_entrega`

para registrar elementos que deban ser entregados o verificados dentro del contexto de la obra.

La funcionalidad completa se encuentra pendiente.

---

# 28. ALERTAS

El sistema deberá contemplar alertas relacionadas con hitos importantes, especialmente:

* finalización del plazo de obra;
* neutralizaciones;
* reinicios;
* recepción provisoria;
* recepción definitiva;
* vencimiento del período de conservación.

La implementación de alertas se encuentra pendiente.

---

# 29. AUDITORÍA

La estructura contempla una tabla:

`auditoria`

para registrar acciones relevantes realizadas dentro del sistema.

La auditoría deberá permitir determinar, cuando corresponda:

* quién realizó una acción;
* qué acción realizó;
* cuándo ocurrió;
* sobre qué entidad actuó;
* información relevante de la operación.

La implementación funcional completa se encuentra pendiente.

---

# 30. INTEGRIDAD REFERENCIAL

Las relaciones entre entidades deben respetar las claves foráneas existentes.

No se deben eliminar registros arbitrariamente si existen dependencias que puedan provocar pérdida de información histórica.

Cuando una entidad deje de estar disponible para nuevas operaciones, debe evaluarse preferentemente el uso de estados de actividad en lugar de eliminación física.

---

# 31. ARQUITECTURA

SIGOA se desarrolla utilizando:

* PHP 8.3.x;
* CodeIgniter 4.7.4;
* MariaDB/MySQL;
* arquitectura web/PWA.

El sistema debe mantener una separación clara entre:

* presentación;
* lógica de negocio;
* acceso a datos;
* almacenamiento de archivos;
* sincronización.

No incorporar complejidad arquitectónica innecesaria.

## 31.1 Frontend

* CSS vanilla con variables CSS (design tokens).
* JavaScript vanilla, sin frameworks ni bundlers.
* CodeIgniter 4 layout system para páginas autenticadas.
* Organización de archivos: `public/assets/css/`, `public/assets/js/`, `public/assets/fonts/`, `public/assets/vendor/`.
* Fuentes e iconos almacenados localmente.
* Decisiones visuales detalladas en `docs/REQUERIMIENTOS_NO_FUNCIONALES.md`.

---

# 32. SEGURIDAD

El sistema deberá contemplar:

* autenticación;
* autorización por roles;
* protección de sesiones;
* validación de datos;
* protección contra acceso no autorizado;
* protección de archivos;
* control de permisos;
* auditoría de operaciones relevantes.

La seguridad debe considerarse desde el desarrollo inicial y no como una etapa posterior.

### Implementado

* autenticación mediante usuario y contraseña (`AuthFilter`);
* autorización por rol (`RoleFilter` paramétrico);
* protección de rutas autenticadas;
* regeneración de sesión después del login (`session()->regenerate(true)`);
* logout con destrucción de sesión;
* verificación de usuario activo en cada request autenticado.

### Pendiente (seguridad)

* CSRF (activación global especificada en §52.7);
* recuperación de contraseña;
* 2FA;
* bloqueo por intentos;
* auditoría avanzada;
* permisos granulares;
* protección de archivos;

---

# 33. ARCHIVOS Y FOTOGRAFÍAS

Los archivos no deben tratarse como simples cadenas almacenadas en la base de datos.

La base de datos debe conservar metadata y referencias.

El sistema deberá considerar:

* nombres seguros;
* rutas controladas;
* validación de tipo;
* validación de tamaño;
* permisos;
* asociación con la entidad correspondiente.

Las fotografías tomadas desde dispositivos móviles constituyen un caso especialmente importante.

---

# 34. DATOS MAESTROS

Los catálogos actualmente consolidados son:

| Catálogo            | Cantidad |
| ------------------- | -------: |
| Estados de obra     |        5 |
| Barrios             |       77 |
| Tipos de licitación |        5 |
| Tipos de resolución |        4 |
| Tipos de documento  |       16 |
| Roles               |        4 |

Los seeders correspondientes ya fueron ejecutados y verificados.

---

# 35. ESTADO DE LOS SEEDERS

Los seeders de datos maestros existentes corresponden a:

* `EstadosObraSeeder`
* `BarriosSeeder`
* `TiposLicitacionSeeder`
* `TiposResolucionSeeder`
* `TiposDocumentoSeeder`
* `RolesSeeder`

No crear seeders para datos operativos reales salvo que exista una necesidad explícita de pruebas.

---

# 36. DECISIONES CONSOLIDADAS

Las siguientes decisiones deben considerarse consolidadas:

1. `obras.id` es el identificador principal de la obra.
2. `obras.codigo` es único.
3. El expediente municipal es único.
4. Una empresa puede participar en varias obras.
5. Una obra representa conceptualmente un contrato.
6. Se conserva el monto contractual original.
7. Se conserva separadamente el monto contractual vigente.
8. Se conserva el plazo contractual original.
9. Las ampliaciones no deben sobrescribir el plazo original.
10. Los inspectores son usuarios.
11. Las asignaciones de inspectores requieren historial.
12. Las fotografías pertenecen a inspecciones.
13. Las inspecciones pertenecen a obras.
14. El funcionamiento offline es un requisito fundamental.
15. La sincronización es necesaria para el funcionamiento en campo.
16. Los datos maestros se cargan mediante seeders.
17. Los datos operativos se generan mediante la aplicación.
18. Las migraciones ejecutadas no deben modificarse.
19. El frontend utiliza CSS vanilla con variables CSS y JavaScript vanilla.
20. Los recursos de interfaz (tipografía, iconos) se almacenan localmente.
21. Las páginas autenticadas extienden un layout común de CodeIgniter 4.
22. Los estilos y scripts se organizan en `components/` (reutilizables) y `pages/` (específicos).

---

# 37. IMPLEMENTADO / DECIDIDO / PENDIENTE

Para evitar interpretaciones incorrectas durante el desarrollo:

### IMPLEMENTADO

* proyecto CodeIgniter 4;
* estructura inicial de base de datos;
* migraciones ejecutadas;
* tablas principales;
* claves foráneas correspondientes;
* catálogos;
* seeders de datos maestros;
* 77 barrios;
* 5 estados de obra;
* 5 tipos de licitación;
* 4 tipos de resolución;
* 16 tipos de documento;
* 4 roles;
* autenticación mediante usuario y contraseña;
* sesión enriquecida con roles;
* `AuthFilter` — protección de rutas autenticadas;
* `RoleFilter` — autorización por rol;
* routing por área/rol mediante route groups;
* redirección al dashboard correspondiente;
* dashboards mínimos por rol;
* logout con destrucción de sesión;

### DECIDIDO

* funcionamiento PWA;
* utilización móvil por inspectores;
* funcionamiento offline;
* sincronización;
* HTTPS obligatorio para las funcionalidades PWA/offline (§52.2);
* una obra puede tener múltiples inspecciones en la misma fecha (§52.3);
* la fotografía original de cámara no se conserva; se almacena la versión optimizada del dispositivo (§52.9);
* historial de inspectores;
* conservación de información contractual original;
* separación entre datos maestros y operativos;
* trazabilidad;
* auditoría;
* gestión documental.

### PENDIENTE

* interfaces;
* CRUD de entidades;
* inspecciones;
* fotografías;
* almacenamiento de archivos;
* PWA;
* offline;
* sincronización;
* certificados;
* recepciones;
* alertas;
* auditoría funcional;
* API/mecanismo de comunicación definitivo;

### NO HACER

* modificar migraciones ya ejecutadas;
* utilizar `migrate:fresh` sin autorización;
* utilizar `migrate:refresh` sin autorización;
* realizar rollback de migraciones consolidadas sin autorización;
* inventar columnas o tablas;
* sobrescribir información contractual histórica;
* utilizar seeders para generar datos operativos reales;
* eliminar información histórica solamente para simplificar una implementación;
* almacenar contraseñas, cookies o credenciales en IndexedDB (§52.6).

---

# 38. CRITERIO PARA EL DESARROLLO CON IA

SIGOA será desarrollado parcialmente con asistencia de agentes de programación.

Por este motivo, cualquier agente deberá:

1. leer `AGENTS.md`;
2. consultar `docs/SIGOA.md` cuando la tarea requiera contexto del proyecto;
3. inspeccionar primero el código existente;
4. inspeccionar la estructura real de la base de datos cuando corresponda;
5. respetar las decisiones consolidadas;
6. realizar cambios incrementales;
7. evitar modificaciones destructivas;
8. verificar los cambios realizados;
9. informar conflictos entre documentación y código existente.

---

# 39. NO HACER SUPOSICIONES

La documentación representa las decisiones conocidas del proyecto, pero no reemplaza la inspección del código real.

Si existe una contradicción entre:

* documentación;
* migraciones;
* base de datos;
* modelos;
* controladores;
* vistas;

el agente debe detenerse y analizar la situación antes de realizar una modificación potencialmente destructiva.

No debe asumir automáticamente que una de las fuentes es correcta.

---

# 40. CAMBIOS INCREMENTALES

El desarrollo debe realizarse por etapas pequeñas y verificables.

Cada cambio debe procurar:

* modificar solamente lo necesario;
* mantener funcionando lo existente;
* evitar introducir dependencias innecesarias;
* verificar errores;
* verificar la base de datos cuando corresponda;
* mantener coherencia con la arquitectura.

---

# 41. DOCUMENTACIÓN VIVA

Este documento no es un registro histórico de conversaciones.

Es la documentación técnica viva del proyecto.

Cuando una decisión importante cambie, este documento deberá actualizarse.

No debe utilizarse para describir como implementada una funcionalidad que solamente fue planificada.

---

# 42. RELACIÓN CON AGENTS.MD

`AGENTS.md` contiene las instrucciones operativas que los agentes de programación deben seguir.

`docs/SIGOA.md` contiene el contexto técnico y funcional del sistema.

En términos simples:

**AGENTS.md = cómo debe trabajar el agente.**

**docs/SIGOA.md = qué es SIGOA y qué decisiones debe respetar.**

---

# 43. PRINCIPIO GENERAL

El objetivo del desarrollo no es simplemente producir código que funcione.

El objetivo es construir un sistema mantenible, trazable y confiable para la gestión real de obras de arquitectura.

Ante cualquier duda, se debe priorizar:

**integridad de los datos → trazabilidad → seguridad → simplicidad → funcionalidad.**

---

# 44. PRÓXIMA ETAPA

Con la estructura de base de datos y los datos maestros consolidados, la siguiente etapa consiste en comenzar el desarrollo funcional de SIGOA.

El desarrollo debe comenzar sobre la estructura existente y avanzar incrementalmente.

No se debe reconstruir la base de datos desde cero para continuar el desarrollo.

---

# 45. DECISIONES — MÓDULO "MIS DATOS"

## 45.1 Obtención del rol

El rol del usuario se obtiene desde la sesión (`session('roles')`), que se carga durante el login mediante la tabla `usuarios_roles`. El "rol principal" se calcula siguiendo la jerarquía: SUPERADMINISTRADOR → ADMINISTRADOR → INSPECTOR → CONSULTA.

La función `getRolPrincipal()` fue implementada en `BaseController` para reutilización. El método `getDashboardPath()` también fue movido a `BaseController` desde `Auth`.

## 45.2 Identificación del usuario autenticado

El usuario se identifica mediante `session('user_id')`. Nunca se confía en un ID enviado desde el frontend. Se consulta `UsuarioModel::find()` con el ID de sesión para obtener datos frescos y verificar que el usuario esté activo.

## 45.3 Validación de contraseña actual

La verificación se realiza mediante `password_verify()` de PHP, que compara la contraseña ingresada con el `password_hash` almacenado. Se utiliza en dos contextos:
- Verificación AJAX (modal de ojo): retorna JSON con resultado. Al verificar exitosamente, establece `session('contrasena_verificada') = true`.
- Cambio de contraseña (POST): no solicita la contraseña actual. Valida que exista la bandera de sesión `contrasena_verificada`; de lo contrario rechaza la operación.

En ambos contextos se usa el mismo mensaje genérico "La contraseña ingresada no es correcta" para no revelar información adicional.

## 45.4 Imposibilidad de mostrar la contraseña original

Las contraseñas se almacenan mediante `password_hash()` (bcrypt/argon2), que es una función de una sola dirección. La contraseña original NO puede recuperarse del hash. El modal de verificación (ícono ojo) confirma la contraseña actual pero no puede mostrarla. Esta es una decisión técnica de seguridad fundamental.

## 45.5 Cambio de contraseña

Disponible para **todos** los usuarios autenticados (ADMINISTRADOR, SUPERADMINISTRADOR, INSPECTOR, CONSULTA). El flujo es:

1. El usuario presiona el ícono de ojo y verifica su contraseña actual.
2. Al verificar exitosamente, se habilitan los botones "Cambiar contraseña" y se muestra el estado "Verificada".
3. El modal de cambio contiene dos campos: **Nueva contraseña** y **Repetir nueva contraseña** (sin solicitar nuevamente la contraseña actual).
4. Al enviar, el servidor valida la bandera de sesión `contrasena_verificada`, las reglas de contraseña, la coincidencia entre los dos campos, y que la nueva sea distinta de la actual (mediante `password_verify()`).
5. Al modificar exitosamente, la bandera `contrasena_verificada` se elimina de la sesión.

**Protección server-side**: el cambio de contraseña no acepta la contraseña actual por POST. Solo permite la operación si la sesión indica que la contraseña fue validada exitosamente previamente (`session('contrasena_verificada')`), evitando que la funcionalidad pueda activarse manipulando únicamente el frontend.

Reglas de contraseña (consistente con el login):
- Mínimo 9 caracteres.
- Al menos una letra mayúscula.
- Al menos un número.
- Debe ser distinta de la actual.

El hash se genera con `password_hash($nueva, PASSWORD_DEFAULT)`.

## 45.6 Roles que pueden modificar email

Solo **ADMINISTRADOR** y **SUPERADMINISTRADOR**. Controlado en:
- Ruta: filtro `role:ADMINISTRADOR,SUPERADMINISTRADOR`.
- Controlador: verificación adicional de roles (defensa en profundista).
- Vista: botón "Modificar" condicional (`$puede_modificar_email`).

## 45.7 Actualización de `updated_at`

El modelo `UsuarioModel` tiene `useTimestamps = false` (timestamps automáticos de CI4 desactivados). Cuando se modifica email o contraseña, `updated_at` se asigna explícitamente en los métodos del modelo: `updateEmail()` y `updatePassword()`.

Esto es consistente con el patrón existente del proyecto, donde `created_at` y `updated_at` se manejan manualmente en seeders y migraciones.

## 45.8 CSRF

Protección CSRF está **desactivada** globalmente en el proyecto actual (`Config\Filters::$globals['before']` tiene CSRF comentado). Todos los formularios existentes (login, etc.) operan sin token CSRF. El módulo Mis Datos mantiene esta coherencia.

La protección CSRF está documentada como pendiente en SIGOA.md §32. Habilitarla es una decisión global que debe implementarse de forma transversal y afecta todos los formularios del sistema. La activación global y el manejo con la sincronización offline están especificados en §52.7.

## 45.9 Regla de verificación previa a cambio de contraseña

Para poder cambiar la contraseña, el usuario debe pasar el "test del ojo": un modal que verifica la contraseña actual mediante AJAX. La contraseña original NO se muestra nunca, solo se indica "Verificada" con un ícono de check.

Al verificar exitosamente:
- Se establece `session('contrasena_verificada') = true` (protección server-side).
- Se habilitan los botones "Cambiar contraseña" en la interfaz.
- Se oculta la nota informativa para usuarios sin permiso de modificar email.

## 45.10 Corrección de la verificación AJAX de contraseña

Se detectó que al ingresar la contraseña correcta en el modal del ojo aparecía un error. **Causa raíz**: el formulario `#formVerificar` no declaraba el atributo `action`. En JavaScript, `formVerificar.action` (propiedad IDL del formulario) resuelve a la URL de la página actual (`/mis-datos`) — y nunca a cadena vacía —, por lo que el `fetch` enviaba el POST a `/mis-datos` (ruta solo GET) en lugar de `/mis-datos/verificar-password`. El servidor respondía con error/no-JSON y el bloque `.catch` mostraba el mensaje de verificación fallida.

**Corrección**: declarar `action="<?= site_url('/mis-datos/verificar-password') ?>"` en `#formVerificar`. El fallback `|| '/mis-datos/verificar-password'` del frontend nunca podía activarse porque `form.action` nunca es falsy.

Regla para el futuro: todo formulario que se envíe por `fetch` debe declarar su `action` explícito; no depender de la propiedad `.action` como fallback.

## 45.11 Componente de alertas y carga en el layout

`components/alerts.css` define los estilos para `.alert`, `.alert-success`, `.alert-danger`, `.alert-warning` e `.alert-info`. Cada variante utiliza colores de fondo, borde y color de texto del Design Token del sistema, con un **borde izquierdo de 4px** en color funcional para una diferenciación clara del tipo de mensaje.

Problema detectado: `alerts.css` estaba incluido en la página pública de login pero **no** en `layouts/auth.php`. Las páginas autenticadas (incluyendo Mis Datos) mostraban los mensajes `.alert-success` y `.alert-danger` sin estilos — el navegador mostraba solo el texto con color por defecto, que parecía azul de enlace.

**Corrección**: incluir `alerts.css` en `layouts/auth.php` después de `buttons.css`. Esto aplica el componente de alertas a todas las páginas autenticadas. Los colores Success (verde) y Danger (rojo) quedan claramente diferenciados. El login, que ya carga sus propios estilos, no se afecta.

## 45.12 Componente de formularios compartido (patterns de contraseña)

`components/forms.css` define los estilos base del sistema de formularios: `.form-group`, `.form-control`, `.field-error`, `.input-icon-wrapper` y `.input-icon-action`. El patrón de campo de contraseña con ícono de ojo integrado (input a todo el ancho + ícono absoluto a la derecha) se define aquí y es **la referencia única** para cualquier campo de contraseña.

Problema detectado: `forms.css` estaba incluido únicamente en el login (`auth/login.php`), **no** en `layouts/auth.php`. Los modales de Mis Datos reutilizaban las mismas clases (`.input-icon-wrapper` + `.input-icon-action`), pero al no cargarse el componente en páginas autenticadas, el ojo aparecía como un botón independiente al costado del input en lugar de quedar integrado.

**Corrección**: incluir `forms.css` en `layouts/auth.php` después de `app.css`. Así, todos los campos de contraseña (Login, Verificar contraseña y Cambiar contraseña) comparten el mismo patrón visual y funcional. El login, que ya carga sus propios estilos, no se afecta.

Regla para el futuro: cualquier campo de contraseña del sistema debe usar `.input-icon-wrapper` + `.form-control` (con padding derecho suficiente para el ícono) + `.input-icon-action` de `forms.css` — no debe crearse un patrón visual alternativo. En modales, el padding vertical del campo puede ajustarse por página (`.modal .form-control`), conservando el espacio reservado para el ícono mediante `.input-icon-wrapper .form-control { padding-right: 2.75rem }`.

---

# 46. DECISIONES — NAVEGACIÓN AUTENTICADA Y GESTIÓN DE USUARIOS

## 46.1 Estructura de navegación autenticada

La aplicación autenticada adopta un **shell de navegación** con dos zonas bien diferenciadas:

1. **Topbar (barra superior)**: identidad institucional (marca SIGOA) y datos del usuario en sesión. Ya no contiene navegación funcional ni acciones personales.
2. **Sidebar (barra lateral izquierda)**: navegación principal de la aplicación autenticada. Reemplaza a los enlaces que antes vivían en el topbar.

La sección 13 de `docs/REQUERIMIENTOS_NO_FUNCIONALES.md` fue actualizada para reflejar esta estructura.

## 46.2 Sidebar centralizada y reutilizable

La sidebar se define una única vez en:

* `app/Views/layouts/partials/sidebar.php` — definición de navegación (reglas de roles + enlaces);
* `app/Views/layouts/auth.php` — inserción de la sidebar dentro del shell (`aside.sidebar` dentro de `.app-layout`).

Toda página autenticada hereda la sidebar desde el layout. No se duplica en cada vista.

## 46.3 Zonas y separador conceptual

La sidebar se divide en dos zonas separadas por una línea divisoria (`.sidebar-divider`):

* **Zona funcional** (por encima de la línea): `Inicio`, `Gestión de usuarios`. Toda nueva funcionalidad del sistema debe incorporarse aquí, por encima del separador.
* **Zona personal/sesión** (por debajo de la línea): `Mis datos`, `Cerrar sesión`. Reservada para acciones personales o de sesión, comunes a todos los usuarios autenticados.

## 46.4 Enlace "Inicio"

El ítem "Inicio" apunta a `/dashboard`, que es la ruta genérica existente (`Auth::redirectToDashboard()`) y resuelve el dashboard según la jerarquía de roles de `getDashboardPath()`. Se reutiliza la infraestructura existente en lugar de duplicar la lógica de jerarquía dentro de la sidebar. Los dashboards por rol no fueron modificados en esta fase.

## 46.5 Visibilidad por rol y protección en backend

La visibilidad de los ítems en la sidebar se calcula contra `session('roles')` (array):

* SUPERADMINISTRADOR y ADMINISTRADOR: Inicio, Gestión de usuarios, Mis datos, Cerrar sesión.
* INSPECTOR y CONSULTA: Inicio, Mis datos, Cerrar sesión (no ven Gestión de usuarios).

La protección real se realiza en **backend** mediante `RoleFilter` en la ruta:

```php
$routes->get('/usuarios', 'Usuarios::index', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR,ADMINISTRADOR'],
]);
```

Un INSPECTOR o CONSULTA que ingrese manualmente a `/usuarios` es redirigido a su dashboard con mensaje de advertencia. El ocultamiento del enlace es solamente una mejora de usabilidad, nunca el mecanismo de seguridad.

## 46.6 Comportamiento responsive (una única navegación)

La sidebar es **una única estructura lógica**; cambia únicamente su presentación según el viewport:

* **Escritorio** (`> 768px`): sidebar lateral fija (`.sidebar` con `position: sticky` y `height: 100vh`), fija respecto del scroll del contenido; el contenido principal ocupa el espacio restante a la derecha; el botón hamburguesa está oculto.
* **Tablets pequeñas y celulares** (`≤ 768px`): la sidebar se oculta fuera de pantalla (off-canvas) y se despliega mediante el botón hamburguesa (`☰`) del topbar. Incluye backdrop que oscurece el fondo, y cierre mediante: botón `×` del drawer, clic en el backdrop, tecla `Escape`, o al pulsar un enlace de navegación.

El estado abierto/cerrado se refleja en `aria-expanded` y en `aria-label` del botón hamburguesa. La lógica vive en `public/assets/js/components/sidebar.js`.

## 46.7 Página "Gestión de usuarios" (consulta/listado)

Primera funcionalidad administrativa accesible desde la sidebar. **Solo consulta**: no implementa CRUD.

* Ruta: `GET /usuarios` (protegida — ver §46.5).
* Controlador: `app/Controllers/Usuarios.php` (`Usuarios::index`).
* Dato: `UsuarioModel::findAllConRoles()` — obtiene todos los usuarios ordenados por apellido/nombre y agrega `roles` por usuario reutilizando `findRolesByUsuarioId()`. No se duplica lógica de acceso a datos.
* Vista: `app/Views/usuarios/index.php`.

Columnas de la tabla: Nombre · Apellido · Usuario · Email · Rol · Estado · Acciones. No se muestran `password_hash` ni campos técnicos.

* **Rol**: se muestran todos los roles del usuario como badges (la relación `usuarios_roles` es muchos a muchos; no se fuerza un único rol).
* **Estado**: indicador circular de color (verde = Activo, rojo = Inactivo) siempre acompañado por texto — no se depende exclusivamente del color (REQUERIMIENTOS §34).
* **Acciones**: reservada para funcionalidad futura. Para usuarios distintos del autenticado se muestra un chip "En preparación"; para el propio usuario se muestra un guion ("—") sin acción especial (la acción específica para el propio usuario queda abierta a futura definición).

No se implementan búsqueda, filtros, paginación ni selección masiva (la cantidad actual de usuarios no lo justifica); la estructura queda preparada para incorporarlos.

## 46.8 Componentes CSS/JS nuevos

* `public/assets/css/components/sidebar.css` — shell `.app-layout`, sidebar, drawer móvil, backdrop y botón hamburguesa.
* `public/assets/css/components/tables.css` — primer componente de tabla real del sistema (REQUERIMIENTOS §24), con desplazamiento horizontal en pantallas pequeñas (REQUERIMIENTOS §33).
* `public/assets/css/components/badges.css` — badge de rol extraído de `mis-datos.css`: al existir reutilización real entre Mis Datos y Gestión de usuarios, el estilo se consolida en un componente compartido.
* `public/assets/css/pages/usuarios.css` — estilos específicos de la página (estado, rol, acciones).
* `public/assets/js/components/sidebar.js` — toggle del drawer responsive, cargado desde el layout (reutilización real en todas las páginas autenticadas).

El topbar (`components/navbar.css`) se simplificó: conserva la identidad y la información de sesión, y ya no contiene enlaces de "Mis Datos" ni "Cerrar sesión". El bloque `.content` (dimensionamiento del área principal) pasó al shell de `sidebar.css`.

---

# 47. DECISIONES — DASHBOARD ADMINISTRATIVO Y ALTA INICIAL DE OBRAS

## 47.1 Dashboard administrativo compartido

El dashboard de **SUPERADMINISTRADOR** y **ADMINISTRADOR** dejó de ser un placeholder y pasó a tener como eje principal la sección **Obras**.

* Encabezado: **Dirección General de Ejecución de Obras de Arquitectura**.
* Subtítulo: **Obras**.
* Ambos roles comparten la misma pantalla en esta fase, pero mantienen **controladores independientes** (`Admin\Dashboard` y `Superadmin\Dashboard`) para permitir diferenciación futura de permisos.
* La vista es única y compartida: `app/Views/obras/index.php`. Se integra al shell autenticado y al sidebar de la Fase 7 (sigue accediéndose mediante el ítem **Inicio**).
* Los datos del listado y los catálogos se ensamblan en `BaseController::datosDashboardObras()`, reutilizado por ambos controladores.
* Las vistas placeholder `admin/dashboard.php` y `superadmin/dashboard.php` fueron eliminadas al quedar sin referencia.

## 47.2 Listado de Obras

Columnas (en este orden): **N° Expte. · Nombre de obra · Barrio · Empresa · Tipo Licitación · N° de Licitación · Estado · Acciones**.

* Las columnas opcionales sin información se muestran como **S/D (Sin datos)**, con estilo secundario. No se utilizan valores ambiguos como `-`, `N/A` o `S/I`.
* La columna **Estado** se implementa desde esta versión con los cinco estados documentados, respetando exactamente los colores oficiales de REQUERIMIENTOS §8/§9.
* La columna **Acciones** queda reservada y muestra la etiqueta neutra "En preparación", consistente con la página de usuarios. No se implementan acciones en esta fase.
* **Orden por defecto**: `created_at DESC` (las obras más recientes primero). No se implementa ordenamiento manual por columnas en esta fase.
* **Paginación**: 10 obras por página, realizada por consulta backend (`ObraModel::listarPaginado()` usa `paginate()`). Permite avanzar/retroceder y acceder a páginas concretas, con información contextual "Mostrando X–Y de Z obras".
* **Estado vacío**: si no existen obras se muestra "Aún no hay obras cargadas." con indicación de usar "Agregar obra". El botón de alta permanece visible.

## 47.3 Zona reservada para buscador y filtros

Debajo del encabezado existe una zona preparada (visualmente deshabilitada) para el futuro **buscador** y **filtros** (principalmente el filtro por **Estado**; más adelante también Barrio, Empresa y Tipo de Licitación). No hay búsqueda ni filtrado funcional en esta fase.

## 47.4 Alta inicial de obras

El botón **+ Agregar obra** (disponible para SUPERADMINISTRADOR y ADMINISTRADOR, con autorización backend mediante `RoleFilter`) abre un modal con el **alta inicial** de la obra. Representa solo el expediente inicial; los datos de adjudicación, documentación, inspecciones, certificados, etc. se completarán en fases futuras.

Campos:

| Campo               | Opcional | Notas |
| ------------------- | -------- | ----- |
| N° de Expte.        | No       | Obligatorio. Placeholder `Ej.: 1234-M-2026`. Máximo 30. Único. |
| Nombre de obra      | No       | Obligatorio. Máximo 255. |
| Barrio              | Sí       | Selector desde el catálogo de barrios activos (`barrios`). |
| Empresa             | Sí       | Selector desde el catálogo de empresas activas (`empresas`). No se permite tipeo libre. |
| Tipo de Licitación  | Sí       | Selector desde el catálogo (`tipos_licitacion`). |
| N° de Licitación    | Sí       | Placeholder `Ej.: 34/2026`. Máximo 30. |
| Estado              | Sistema  | Se muestra `PREVIO INICIO` deshabilitado y en gris; lo impone el backend. |

La obra puede crearse únicamente con **N° de Expte. + Nombre de obra** (regla: el expediente puede existir previo a la adjudicación).

Validaciones backend:

* obligatoriedad de N° de Expte. y Nombre;
* duplicidad de expediente municipal (único);
* integridad referencial de los catálogos seleccionados.

## 47.5 Estado inicial obligatorio: PREVIO INICIO

Toda obra nueva nace **indefectiblemente** en **PREVIO INICIO**:

* El modal lo muestra deshabilitado ("Valor asignado automáticamente por el sistema"), sin permitir edición.
* El backend impone `estado_obra_id` = id de `PREVIO INICIO` (consultado desde `estados_obra`) **independientemente** de cualquier dato enviado por el cliente. El formulario no envía el estado.

## 47.6 Código SIGOA

`obras.codigo` se genera automáticamente en el backend con formato secuencial:

```text
OBR-000001, OBR-000002, ...
```

El usuario no lo ingresa. Implementado en `ObraModel::generarCodigo()`.

## 47.7 Estructura reutilizada y ajustes

* Se reutilizaron las tablas existentes `obras`, `empresas`, `barrios`, `tipos_licitacion` y `estados_obra` (migraciones consolidadas). No se crearon tablas ni catálogos nuevos.
* **Migración nueva** `ObrasTipoLicitacionOpcional`: `obras.tipo_licitacion_id` pasó de `NOT NULL` a `NULL`, porque en el alta inicial el tipo de licitación es opcional (el expediente puede existir antes de la licitación). Las migraciones anteriores no fueron modificadas.
* `obras.barrio_id` y `obras.empresa_id` ya eran opcionales (`NULL`), por lo que no requirieron cambios.
* **Modelos nuevos**: `ObraModel`, `EstadoObraModel`, `BarrioModel`, `EmpresaModel`, `TipoLicitacionModel` (patrón de `UsuarioModel`: `returnType = 'object'`, timestamps manuales).
* **Componente modal** (`components/modal.css`): los estilos de modal de Mis Datos se promovieron a componente compartido al existir reutilización real entre dos páginas, y se incluyen desde el layout autenticado.

## 47.8 Rutas

```php
$routes->post('/obras/crear', 'Obras::crear', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR,ADMINISTRADOR'],
]);
```

La creación solo es accesible para SUPERADMINISTRADOR y ADMINISTRADOR. El listado se sirve desde los dashboards existentes (`/admin/dashboard`, `/superadmin/dashboard`).

## 47.9 Alcance actual y pendiente

**Implementado en esta fase:**

* dashboard administrativo con listado de obras;
* paginación (10/página) y orden `created_at DESC`;
* estado de obra con colores oficiales;
* presentación `S/D` para datos faltantes;
* zona reservada para buscador/filtros;
* alta inicial de obra (modal);
* estado obligatorio PREVIO INICIO;
* generación automática de código OBR-XXXXXX;
* componentes CSS/JS nuevos solo donde fueron necesarios.

**Fuera de alcance (fases futuras):** edición completa de obras, eliminación, cambio manual de estado, gestión completa de estados/transiciones, detalle completo, certificados/certificaciones, inspecciones, fotografías, documentación, contratos, actas, búsquedas y filtros avanzados, acciones masivas, y dashboards diferenciados entre roles.

---

# 48. DECISIONES — EDICIÓN DE DATOS BÁSICOS DE OBRA

## 48.1 Acción Editar

La columna **Acciones** del listado incorpora la primera acción funcional: **Editar**, con el ícono `bi-pencil` (lápiz) de Bootstrap Icons, en el formato `[ ICONO ] TEXTO` establecido en REQUERIMIENTOS §28.

La acción es visible únicamente para usuarios con rol `ADMINISTRADOR` o `SUPERADMINISTRADOR`. La vista verifica la sesión (`$roles`). En backend, la ruta está protegida por `RoleFilter` con el mismo criterio que el alta (`role:SUPERADMINISTRADOR,ADMINISTRADOR`).

La columna mantiene flexibilidad visual para incorporar nuevas acciones futuras mediante contenedor `.obras-acciones` (flex wrap con gap).

## 48.2 Modal reutilizable (alta + edición)

No existe una pantalla independiente para editar. El **mismo modal** de Fase 8 se reutiliza en dos modos:

| Característica | Alta | Edición |
| --- | --- | --- |
| Título | Agregar obra | Editar obra |
| Ícono del título | `bi-plus-square` | `bi-pencil` |
| Texto del botón de guardado | Guardar | Guardar cambios |
| Acción del formulario | `POST /obras/crear` | `POST /obras/actualizar` |
| Código de obra visible | No | Sí, informativo (`OBR-XXXXXX`) |
| Estado | Campo deshabilitado (PREVIO INICIO) | Selector activo con todos los estados |
| `obra_id` (hidden) | Vacío | ID interno de la obra |

El modal se identifica ahora con el id `modalObra` (antes `modalAgregarObra`). Los IDs de los elementos internos (`formObra`, `obra_id`, `expediente_municipal`, etc.) son compartidos y el JavaScript distingue los modos mediante las funciones `configurarModoAlta()` y `configurarModoEdicion()`.

Al hacer clic en **[ lápiz ] Editar** en la tabla, JavaScript captura los atributos `data-*` de la fila y abre el modal en modo edición con los valores precargados.

## 48.3 Campos editables

Los campos editables son los mismos que el alta, más el **estado**, que en edición sí puede modificarse libremente:

* N.º de Expediente (obligatorio)
* Nombre de obra (obligatorio)
* Barrio (opcional)
* Empresa (opcional)
* Tipo de Licitación (opcional)
* N.º de Licitación (opcional)
* Estado (editable, del catálogo completo)

El código `OBR-XXXXXX` se muestra como dato identificatorio informativo, pero nunca se incluye como campo editable ni se envía para modificación.

## 48.4 Código de obra no editable

El código generado por SIGOA se muestra en el modal de edición como:

```
Código de obra: OBR-000023
```

Visualmente deshabilitado, no se envía al backend como campo modificable. La obra se identifica mediante su `id` interno. No se regenera el código al editar.

## 48.5 Estado: edición libre

En esta etapa inicial del sistema, el **estado sí puede modificarse libremente**.

La primera carga de SIGOA requiere registrar obras existentes que pueden encontrarse en cualquier estado. Por eso el backend acepta cualquier `estado_obra_id` válido del catálogo durante la edición.

Esto puede cambiar en fases futuras cuando se implementen transiciones controladas con actas.

Los estados disponibles son: `PREVIO INICIO`, `EN EJECUCIÓN`, `NEUTRALIZADA`, `EN PLAZO DE CONSERVACIÓN`, `FINALIZADA`.

## 48.6 Validaciones

Las validaciones para edición se reutilizan del alta con una única diferencia en unicidad de expediente:

**Expediente único — con exclusión**: la obra debe poder conservar su propio expediente. Se verifica `existeExpediente(expediente, exceptoId)` que excluye la obra actual del conteo. La comparación se normaliza a mayúsculas (`mb_strtoupper`) porque el campo se almacena en mayúsculas.

**Catálogos opcionales**: la integridad referencial de `barrio_id`, `empresa_id` y `tipo_licitacion_id` se valida como en el alta.

**Estado obligatorio en edición**: `estado_obra_id` es requerido y se valida existencia en `estados_obra`.

**Número de licitación**: no tiene restricción de unicidad en base de datos. Se acepta cualquier valor, incluido NULL.

## 48.7 Backend

### Endpoint de edición

```php
$routes->post('/obras/actualizar', 'Obras::actualizar', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR,ADMINISTRADOR'],
]);
```

El controlador `Obras` tiene ahora dos métodos públicos separados: `crear()` (alta) y `actualizar()` (edición), con validación compartida privada (`validarDatosObra()`).

`actualizar()` recibe el `id` de la obra como campo `obra_id` en POST. Valida la existencia de la obra, aplica las validaciones con exclusión de expediente, ejecuta la actualización, y actualiza `updated_at` sin modificar `created_at`.

### Extracción de validación

La lógica de sanitización de entradas y validación está compartida entre alta y edición mediante los métodos privados:

* `tomarDatosObra()` — normaliza las entradas del formulario.
* `validarDatosObra(array $datos, ?int $exceptoObraId = null)` — valida obligatoriedad, unicidad (con/sin exclusión) e integridad referencial.

### Timestamps

`ObraModel` utiliza `useTimestamps = false` con timestamps manuales, siguiendo la convención establecida en Fase 8.

* `created_at`: se asigna al crear y **no se modifica** nunca durante la edición.
* `updated_at`: se actualiza a la fecha/hora de la modificación en cada `actualizar()`.

### Modelo

`ObraModel::actualizar(int $id, array $datos): bool` ejecuta `update()` con los campos permitidos, excluyendo `id`, `codigo` y `created_at`.

## 48.8 Reapertura del modal tras errores

El sistema de reapertura del modal tras error de validación distingue los modos:

* `'alta'`: campos conservan `old()` del alta, el modal se reabre en modo alta.
* `'edicion'`: campos conservan `old()` de la edición y el código de obra viene en flashdata. El modal se reabre en modo edición con el código visible.

## 48.9 Alcance actual

**Implementado en Fase 9:**

* edición de datos básicos de obras mediante modal reutilizable;
* botón **[ lápiz ] Editar** visible solo para ADMINISTRADOR/SUPERADMINISTRADOR;
* código OBR-XXXXXX como dato informativo no editable;
* estado editable libremente (carga inicial);
* unicidad de expediente con exclusión de la propia obra;
* actualización correcta de `updated_at` sin modificar `created_at`;
* protección por roles en backend y vista;
* validaciones reutilizadas de Fase 8, con la diferencia de exclusión por ID.

**Fuera de alcance:** eliminación de obras, baja lógica, cambio automático de estado, restricciones de transición, historial de estados, actas, inspecciones, fotografías, certificados, documentación, ficha completa, auditoría, nuevas acciones adicionales.

---

# 49. DECISIONES — FICHA DE OBRA

## 49.1 Acción "Ver obra"

Cada fila del listado de obras incorpora la acción **[ ojo ] Ver obra**, que abre la ficha de la obra en modo lectura/edición según el rol.

* Disponible para SUPERADMINISTRADOR, ADMINISTRADOR y CONSULTA.
* La acción "Editar" (modal de datos básicos) permanece visible solo para SUPERADMINISTRADOR/ADMINISTRADOR.
* El botón "Agregar obra" se muestra solo si el rol puede editar obras.

## 49.2 Alcance del dashboard de CONSULTA

El rol CONSULTA accede al listado de obras (`obras/index`) en modo lectura.

* Reutiliza la misma vista que ADMINISTRADOR/SUPERADMINISTRADOR.
* No muestra "Agregar obra" ni "Editar".
* Puede ingresar a la ficha de obra mediante "Ver obra".

## 49.3 Estructura de la ficha

La ficha se compone de:

1. barra superior con **Volver a obras** y **[ documento ] Ver Certificados** (deshabilitado, sin funcionalidad);
2. encabezado de identificación: código, nombre, estado, N° de Expte. municipal, barrio, empresa, tipo y N° de licitación y **Expte. contable**;
3. datos operativos: fecha de inicio y plazo de obra (valor + unidad), con **plazo en días corridos** y **fecha de finalización** calculados;
4. inspector vigente y acción de cambio de inspector;
5. representante técnico vigente y acción de cambio de representante técnico.

## 49.4 Expte. contable

El Expte. contable (`obras.expediente_contable`) se muestra en el encabezado y es editable por SUPERADMINISTRADOR/ADMINISTRADOR dentro del mismo formulario de datos operativos. Para CONSULTA se muestra como texto. La columna ya existía en la estructura consolidada; no se creó migración.

## 49.5 Plazo de obra

Se conserva la regla de conversión **1 mes = 30 días corridos**.

* La unidad se almacena como código (`DIAS`, `MES`).
* `plazo_original_dias` se calcula en el backend y se persiste.
* La **fecha de finalización** es un dato derivado (`fecha_inicio + plazo_original_dias`), no se almacena y se calcula server-side.
* Las ampliaciones de plazo posteriores no forman parte de esta fase.

## 49.6 Edición de datos operativos

* El encabezado y los datos operativos comparten un único formulario (`POST /obras/ficha/actualizar`).
* Fechas: entrada `dd/mm/aaaa` con máscara en JS; el backend valida estrictamente con `DateTime::createFromFormat('d/m/Y')`.
* Si no hay cambios reales, no se actualiza `updated_at`.
* `created_at` nunca se modifica.

## 49.7 Cambio de inspector

El cambio de inspector conserva el historial en `inspectores_obras`:

* cierra la asignación vigente (`fecha_fin = fecha del cambio`);
* inserta una nueva asignación (`fecha_inicio = fecha del cambio`, `fecha_fin = NULL`);
* marca la obra como modificada (`obras.updated_at`).

Ambas operaciones sobre `inspectores_obras` se ejecutan dentro de una transacción para no dejar la obra sin inspector vigente si una falla.

El nuevo inspector debe ser un usuario activo con rol INSPECTOR y distinto del vigente. Las asignaciones previas no se eliminan.

## 49.8 Rutas y autorización

| Método | Ruta | Controlador | Roles |
| ------ | ---- | ----------- | ----- |
| GET  | `/obras/ver/(:num)`         | `Obras::ver`               | SUPERADMINISTRADOR, ADMINISTRADOR, CONSULTA |
| POST | `/obras/ficha/actualizar`   | `Obras::actualizarFicha`   | SUPERADMINISTRADOR, ADMINISTRADOR |
| POST | `/obras/inspector/actualizar` | `Obras::actualizarInspector` | SUPERADMINISTRADOR, ADMINISTRADOR |
| POST | `/obras/representante/actualizar` | `Obras::actualizarRepresentante` | SUPERADMINISTRADOR, ADMINISTRADOR |

La autorización se resuelve con los filtros `auth` y `role`, y se refuerza en la vista (campos y acciones editables solo para roles habilitados).

## 49.9 Componentes nuevos

* `App\Libraries\PlazoObra`: conversión de plazo y manejo de fechas.
* `App\Models\InspectoresObrasModel`: asignaciones y vigencia de inspector.
* `App\Models\RepresentanteTecnicoModel` y `App\Models\ObrasRepresentantesTecnicosModel`: catálogo y asignaciones de representantes técnicos.
* `ObraModel::findDetalle`, `ObraModel::actualizarFicha`, `ObraModel::touch`.
* `UsuarioModel::findInspectoresActivos`, `RepresentanteTecnicoModel::listarActivasConTitulo`.
* `obras/ficha` (vista), `ficha-obra.css`, `ficha-obra.js`.
* `tests/unit/PlazoObraTest.php`.

## 49.10 Cambio de representante técnico

El representante técnico vigente se muestra en la ficha y se gestiona con el mismo patrón que el inspector, conservando el historial en `obras_representantes_tecnicos`:

* la asignación vigente se cierra con `fecha_inicio - 1 día` de la nueva asignación, porque los períodos son inclusivos (sin superposición ni huecos);
* se inserta una nueva asignación (`fecha_inicio = fecha del cambio`, `fecha_fin = NULL`);
* la obra se marca como modificada (`obras.updated_at`).

La fecha del cambio debe ser válida, no posterior a la fecha actual y estrictamente posterior al inicio de la asignación vigente. El nuevo representante debe existir en `representantes_tecnicos`, estar activo y ser distinto del vigente.

La asignación de representantes técnicos a obras se apoya en las tablas consolidadas `representantes_tecnicos` y `obras_representantes_tecnicos`. El alta y la edición del catálogo de representantes técnicos no forman parte de esta fase.

**Fuera de alcance:** certificados (botón deshabilitado), inspecciones, fotografías, ampliaciones de plazo, historial visible de inspectores o representantes, auditoría, eliminación de obras.

---

# 50. Gestión de representantes técnicos (padrón)

Módulo de administración del catálogo de representantes técnicos. Complementa la asignación de representantes a obras de la ficha de obra (sección 49), que no se modifica.

## 50.1 Alcance

* Listado, alta, edición y activación/desactivación de representantes técnicos.
* La asignación a obras sigue siendo responsabilidad de la ficha de obra (`ObrasRepresentantesTecnicosModel`).

## 50.2 Acceso y autorización

* Solo SUPERADMINISTRADOR y ADMINISTRADOR. CONSULTA e INSPECTOR quedan bloqueados.
* La autorización se aplica en el backend con los filtros `auth` y `role:SUPERADMINISTRADOR,ADMINISTRADOR`; la opción del sidebar se oculta a los demás roles.

## 50.3 Listado

* Ordenado por apellido y nombre (`RepresentanteTecnicoModel::listarTodasConTitulo()`).
* Columnas: apellido y nombre, título profesional, matrícula y estado.
* La matrícula es opcional; si es NULL se muestra como “Sin matrícula”.
* El estado se muestra como Activo/Inactivo con el mismo patrón visual que usuarios/empresas.
* Acciones: Editar y Desactivar (si está activo) o Reactivar (si está inactivo). Botón “Agregar representante técnico”.

## 50.4 Alta y edición

* Campos: nombre (obligatorio), apellido (obligatorio), título profesional (obligatorio, del catálogo `tipos_titulo_profesional`) y matrícula (opcional).
* Los nombres administrativos se almacenan en mayúsculas. La matrícula vacía se persiste como NULL.
* La matrícula no nula es única: un duplicado produce un mensaje claro. En edición se excluye al propio representante.
* La edición refresca `updated_at` y conserva `created_at`. No modifica el estado.
* El alta crea siempre el representante como activo.

## 50.5 Estado Activo/Inactivo

* El padrón no se elimina físicamente: se activa o desactiva mediante el estado `representantes_tecnicos.activo` (TINYINT NOT NULL, por defecto 1), agregado en la migración `2026-09-18-120000_AddActivoToRepresentantesTecnicos`.
* Un representante inactivo conserva sus datos y todo su historial de asignaciones en `obras_representantes_tecnicos`. Las asignaciones vigentes e históricas de las obras siguen siendo consultables.
* No se permite desactivar un representante con asignación vigente (`fecha_fin` NULL) en `obras_representantes_tecnicos`: primero debe reemplazarse en esas obras. Las asignaciones históricas no impiden desactivarlo.
* El cambio de estado se solicita con confirmación previa desde el frontend y no afecta la lógica ni los datos de las asignaciones.
* La restricción `ON DELETE RESTRICT` de `obras_representantes_tecnicos` se mantiene como salvaguarda de integridad.

## 50.6 Nuevas asignaciones en la ficha de obra

* La ficha de obra ofrece para nuevas asignaciones únicamente representantes activos (`RepresentanteTecnicoModel::listarActivasConTitulo()`).
* La validación del cambio de representante exige que el representante exista y esté activo (`listarActivas()`).
* El representante vigente o histórico de una obra se sigue mostrando aunque luego quede inactivo, porque las consultas de asignaciones no filtran por `activo`.

## 50.7 Rutas

| Método | Ruta | Controlador |
| ------ | ---- | ----------- |
| GET  | `/representantes`              | `RepresentantesTecnicos::index` |
| GET  | `/representantes/nuevo`        | `RepresentantesTecnicos::nuevo` |
| POST | `/representantes/crear`        | `RepresentantesTecnicos::crear` |
| GET  | `/representantes/editar/(:num)`| `RepresentantesTecnicos::editar` |
| POST | `/representantes/actualizar/(:num)` | `RepresentantesTecnicos::actualizar` |
| POST | `/representantes/estado`       | `RepresentantesTecnicos::cambiarEstado` |

No existe ruta de eliminación física.

## 50.8 Componentes nuevos

* `App\Controllers\RepresentantesTecnicos`.
* `RepresentanteTecnicoModel::crear`, `actualizar`, `existeMatricula`, `listarActivas`, `listarActivasConTitulo`, `cambiarActivo`.
* `ObrasRepresentantesTecnicosModel::tieneAsignacionVigente`.
* Migración `2026-09-18-120000_AddActivoToRepresentantesTecnicos` (`representantes_tecnicos.activo`).
* `representantes/index`, `representantes/formulario` (vistas), `representantes.css`, `representantes.js`.

---

# 51. DECISIONES — DASHBOARD DEL INSPECTOR (MIS OBRAS)

Primera vista para el rol INSPECTOR. Sustituye la pantalla de bienvenida previa por un dashboard mobile-first con las obras asignadas al inspector autenticado.

## 51.1 Alcance

* Dashboard "Mis obras" (encabezado DGEOA) con las obras cuya asignación vigente corresponde al usuario autenticado.
* Navegación propia para INSPECTOR en el sidebar: "Mis obras" y "Otras obras" (deshabilitado, sin funcionalidad).
* Ruta destino por obra (`/inspector/obras/ver/(:num)`) con validación de pertenencia; la vista operativa completa queda preparada para una etapa posterior.

**Fuera de alcance:** vista operativa de inspección, inspecciones, fotografías, sincronización, búsqueda global de obras.

## 51.2 Obtención de las obras

Las obras se obtienen a partir de la asignación vigente del inspector en `inspectores_obras` (`usuario_id` del usuario autenticado y `fecha_fin` NULL). Es una consulta de **solo lectura**: no se agregan columnas a `obras` ni se modifican datos para mostrar el dashboard.

* `InspectoresObrasModel::listarVigentesConObra(int $usuarioId)` resuelve los nombres de catálogo (tipo de licitación y estado) y ordena por nombre de obra.
* `InspectoresObrasModel::esVigente(int $obraId, int $usuarioId)` verifica que el usuario sea la asignación vigente de la obra antes de permitirle el acceso a la ruta destino.

## 51.3 Contenido de cada card

Cada obra se muestra como una tarjeta cuyo vínculo completo es el área táctil:

* nombre de la obra (jerarquía principal);
* N° de expediente municipal;
* tipo de licitación (S/D si no se informó);
* N° de licitación (S/D si no se informó);
* estado de la obra como badge (informativo, incluye todos los estados).

No se muestra el código interno `OBR-XXXXXX`. Estado vacío: mensaje orientativo indicando que no hay obras asignadas y que se contacte a un administrador.

## 51.4 Ruta destino y pertenencia

| Método | Ruta | Controlador | Roles |
| ------ | ---- | ----------- | ----- |
| GET | `/inspector/dashboard`     | `Inspector\Dashboard::index` | INSPECTOR |
| GET | `/inspector/obras/ver/(:num)` | `Inspector\Obras::ver`     | INSPECTOR |

`Obras::ver` valida que la obra exista (`ObraModel::findDetalle`) y que el usuario sea la asignación vigente (`esVigente`). Si no corresponde, redirige a `/inspector/dashboard` con una advertencia. El acceso directo por URL a obras no asignadas queda bloqueado.

La pantalla `/inspector/obras/ver/:id` se prepara como página de destino (encabezado de identificación + aviso "Vista operativa en preparación") para la futura vista de trabajo del inspector.

## 51.5 Navegación del sidebar

* Para el rol INSPECTOR, el ítem "Inicio" se reemplaza por "Mis obras" (activo cuando el segmento de URL es `inspector`).
* "Otras obras" se muestra como ítem deshabilitado (`.sidebar-link.is-disabled`, sin `href`, sin acción) hasta que exista búsqueda global.
* Los ítems administrativos ("Gestión de usuarios", "Empresas", "Representantes") se mantienen para usuarios con rol administrativo adicional; "Mis datos" y "Cerrar sesión" no cambian.

## 51.6 Badges de estado como componente

El bloque `.obras-estado*` de `pages/obras.css` se promovió a un componente compartido `components/estados.css` por reutilización real entre listado, ficha y dashboard del inspector:

* clases neutrales: `.estado-badge` con variantes `.estado-previo`, `.estado-ejecucion`, `.estado-neutralizada`, `.estado-conservacion`, `.estado-finalizada`;
* usan las variables `--color-state-*` existentes; no se agregan colores nuevos;
* se cargan en el layout autenticado `layouts/auth.php` junto a `badges.css`;
* `obras/index` y `obras/ficha` se actualizaron a las nuevas clases; se eliminó el bloque de `pages/obras.css` (se conservan `.obras-estado-preview` y `.obras-estado-nota`, que son de la selección de estado en el alta).

## 51.7 Modelo

`InspectoresObrasModel` incorpora los métodos de consulta del dashboard e integridad de acceso:

* `listarVigentesConObra(int $usuarioId): array` — obras asignadas de forma vigente con nombres de catálogo resueltos y ordenadas por nombre;
* `esVigente(int $obraId, int $usuarioId): bool` — validación de pertenencia para la ruta destino.

## 51.8 Componentes nuevos

* `App\Controllers\Inspector\Obras` con `ver(int $id)`.
* `InspectoresObrasModel::listarVigentesConObra`, `InspectoresObrasModel::esVigente`.
* `inspector/dashboard` y `inspector/obra` (vistas), `inspector-dashboard.css`, `inspector-obra.css`, `components/estados.css`.
* `tests/database/InspectoresObrasModelTest.php`.

---

# 52. DECISIONES — FUNCIONAMIENTO OFFLINE Y SINCRONIZACIÓN (FASE A)

Esta sección registra la **especificación técnica** del funcionamiento offline y la sincronización de SIGOA (Fase A: análisis, decisiones y documentación).

Es exclusivamente una especificación: **ninguna parte de lo documentado aquí está implementada todavía**. Las fases siguientes implementarán estos puntos de forma incremental.

Decisiones cerradas en esta fase: HTTPS, UUID, CSRF, sesión, múltiples inspecciones por día, autorización histórica del inspector, estados que permiten inspeccionar, fotografías, dispositivos/navegadores y reintentos/sincronización.

## 52.1 Alcance y principios generales

* El inspector utiliza la aplicación desde el teléfono (PWA instalable). El servidor es la **fuente de verdad**; el dispositivo mantiene una **caché de trabajo persistente** con las operaciones capturadas sin conexión.
* El funcionamiento offline de V1 cubre: consultar el snapshot de las obras asignadas, crear inspecciones y capturar fotografías.
* Fuera del alcance de V1: edición de obras desde el dispositivo, sincronización bidireccional, trabajo offline para otros roles.
* Principios: **integridad de los datos → trazabilidad → seguridad → simplicidad → funcionalidad** (§43).
* No se pierde información local: los datos locales se eliminan únicamente después de una confirmación exitosa del servidor.
* La sincronización debe evitar duplicación, pérdida de inspecciones/fotografías e inconsistencias dispositivo-servidor (§21).

## 52.2 HTTPS (F.1)

* **HTTPS es obligatorio** para el funcionamiento PWA/offline en dispositivos móviles (Service Worker, IndexedDB persistente, `crypto.randomUUID`, cámara y geolocalización exigen contexto seguro o `localhost`).
* La configuración concreta del certificado, CA, nombre interno y Apache se resolverá posteriormente, **antes de activar el Service Worker en producción**.
* Esto no bloquea las fases de servidor que puedan desarrollarse previamente.
* El diseño no debe asumir que HTTP serviría para las funcionalidades PWA.

## 52.3 UUID, idempotencia y múltiples inspecciones por día (F.2, F.5)

* El **UUID v4 es generado por el cliente** (en contexto seguro: `crypto.randomUUID()`; con fallback basado en `crypto.getRandomValues`). El servidor lo valida como clave de idempotencia.
* Se agregarán en migraciones futuras:
  * `inspecciones.uuid CHAR(36) NOT NULL UNIQUE`;
  * `fotografias.uuid CHAR(36) NOT NULL UNIQUE`.
* Se conservan los IDs autoincrementales existentes y `obras.id` como clave de relación.
* Usos del UUID:
  * identificación única de la operación offline;
  * idempotencia de sincronización;
  * relación entre datos locales y remotos;
  * identificación de la inspección;
  * identificación/nombre de archivos cuando corresponda.
* No se agrega `hash_sha256` en V1.
* **Una obra puede tener múltiples inspecciones en la misma fecha** (no existe la regla de "una inspección por obra y día"). La restricción `UNIQUE (obra_id, fecha_inspeccion)` (`KEY obra_id_fecha_inspeccion`) se eliminará mediante una migración nueva; no se modifica la migración histórica que la creó. La fecha de la inspección agrupa documentalmente; cada inspección se identifica por su `uuid`.

## 52.4 Autorización histórica del inspector (F.6)

* La autorización de una inspección offline se determina por la vigencia del inspector para la obra **en la fecha de la inspección**: se valida `inspector + obra + fecha_inspeccion`, no la fecha de captura de una fotografía.
* Se agregará a `InspectoresObrasModel` un método de consulta (p. ej. `fueVigente(int $obraId, int $usuarioId, string $fecha): bool`) que verifique la existencia de una fila con:
  * `obra_id` = obra;
  * `usuario_id` = inspector;
  * `fecha_inicio <= fecha` **y** (`fecha_fin IS NULL` **o** `fecha_fin >= fecha`);
* Los períodos de asignación son **inclusivos** (la fecha de fin es el último día efectivo).
* Ejemplo (inspector A vigente hasta 22/09; el 23/09 pasa a B; A recupera conexión el 23/09): las inspecciones de A del 22/09 **sincronizan**; una inspección nueva de A con fecha 23/09 **no sincroniza**.
* Colaboración fuera del mecanismo: quien ya no es vigente entrega el material por fuera y el inspector vigente lo incorpora.
* Las operaciones rechazadas por autorización **no se eliminan silenciosamente**: quedan localmente como `ERROR` con un mensaje comprensible.

## 52.5 Estados de obra que permiten nuevas inspecciones (F.7)

| Estado | Nuevas inspecciones |
| --- | --- |
| EN EJECUCIÓN (id 2) | PERMITIDO |
| NEUTRALIZADA (id 3) | PERMITIDO |
| EN PLAZO DE CONSERVACIÓN (id 4) | PERMITIDO |
| PREVIO INICIO (id 1) | NO |
| FINALIZADA (id 5) | NO |

* La restricción aplica a la **creación** de inspecciones (precondición evaluada en el dispositivo con el estado del snapshot; también en el alta en línea).
* Una inspección creada legítimamente offline durante un estado permitido **mantiene su validez histórica** aunque la obra cambie de estado antes de sincronizar.
* En sincronización, el servidor **no rechaza por el estado actual** si la autorización histórica de §52.4 es válida; el estado no permitido es condicionante de la creación, no de la sincronización.

## 52.6 Sesión y autenticación (F.4)

* Se conserva la sesión actual (Config\Session `expiration = 7200` s, `regenerateDestroy = false`). **No se extiende la duración solo para resolver offline.**
* Los datos creados offline persisten en IndexedDB y **sobreviven la expiración de sesión**.
* Si al sincronizar la sesión expiró:
  1. el servidor responde **401 JSON**;
  2. SIGOA solicita nuevamente autenticación;
  3. el usuario se autentica;
  4. la cola de sincronización continúa.
* Nunca se eliminan ni se marcan como sincronizados datos locales motivado por un 401.
* **No se almacenan contraseñas, cookies ni credenciales en IndexedDB.** Solo se almacena un perfil mínimo (nombre, apellido, nombre de usuario) para la interfaz.
* Los endpoints de sincronización responden con `401` explícito (JSON) cuando la sesión expiró — patrón de `MisDatos::verifyPassword()` — y no con la redirección HTML de `AuthFilter`. Implementado en la Fase B: `AuthFilter`/`RoleFilter` responden `401`/`403` JSON ante peticiones AJAX/API (§52.22).

## 52.7 CSRF (F.3)

* El filtro CSRF está **activado globalmente desde la Fase B** (§52.22), antes de exponer las APIs de sincronización. Se apoya en la configuración de `Config\Security` (protección `cookie`, `tokenName = csrf_test_name`, `headerName = X-CSRF-TOKEN`). La activación es transversal y afecta todos los formularios del sistema (ver §32 y §45.8).
* El manejo debe ser transparente para el usuario.
* **No se almacenan tokens CSRF en la cola offline.**
* Con la rotación activa (`Security::$regenerate = true`, mantenido salvo incompatibilidad demostrada), al **comenzar cada sincronización se obtiene automáticamente un token vigente**: petición previa (GET) que renueva la cookie CSRF, lectura de la cookie `csrf_cookie_name` (legible por JS) y envío como header `X-CSRF-TOKEN` en cada `fetch()`.
* `tokenRandomize = false`: el token coincide con el hash de la cookie.
* Los endpoints de sincronización combinan: autenticación, rol/autorización, validación de vigencia histórica (§52.4) y CSRF.
* Las APIs devuelven `401` explícito ante sesión expirada (§52.6).

## 52.8 Arquitectura de almacenamiento físico (F.5)

Estructura prevista:

```text
SIGOA/
└── OBR-000001/
    └── 2026-09-22/
        ├── UUID-inspeccion-1/
        │   ├── IMAGENES/
        │   └── THUMBNAILS/
        │
        └── UUID-inspeccion-2/
            ├── IMAGENES/
            └── THUMBNAILS/
```

Conceptualmente:

```text
Obra → Fecha → Inspección → archivos
```

* El nombre de la carpeta de obra continúa siendo su código interno `OBR-XXXXXX` (de `obras.codigo`), validado con `ObraAlmacenamiento::normalizarCodigo()` (formato `^OBR-\d{6}$`, anti-traversal).
* `ObraAlmacenamiento` (hoy crea `OBR/IMAGENES` y `OBR/THUMBNAILS`, usado en `Inspector\Obras::ver()`) deberá **ajustarse incrementalmente a la estructura anidada sin romper el código ya implementado**: nuevos métodos por obra+fecha+uuid, compatibilidad con el comportamiento actual y actualización de llamadores.
* En la base de datos se guardan **referencias relativas** a la raíz (`SIGOA_STORAGE_PATH`, Config\SigoaStorage), nunca rutas absolutas — misma convención que `empresas.ruta_logo`.
* El código de la obra no se expone innecesariamente en la interfaz del inspector (§51.3, ya no se muestra).

## 52.9 Fotografías (F.8)

* Formatos aceptados: **JPG, JPEG, PNG, WEBP**. HEIC/HEIF fuera de V1.
* Límite máximo: **8 MB por fotografía** (también validado en el servidor como defensa).
* Procesamiento en el teléfono **antes** de almacenar/sincronizar:
  * máximo **2560 px en el lado mayor**;
  * JPEG con calidad aproximada **80–85 %**; PNG redimensionado conservando formato;
  * thumbnail independiente: máximo **400 px en el lado mayor**.
* La **fotografía original de cámara no se conserva** en SIGOA: se trabaja con la versión procesada/optimizada.
* Orientación: considerar EXIF `Orientation` (especialmente iOS) al procesar con canvas.
* Mientras esté pendiente de sincronización, IndexedDB conserva por fotografía: Blob de la fotografía, Blob del thumbnail, metadata, UUID, relación con la inspección y estado de sincronización.
* Los blobs locales **solo se eliminan después de una confirmación exitosa del servidor**.

## 52.10 Almacenamiento local — IndexedDB

Persistencia en el teléfono mediante IndexedDB (base de datos SIGOA, versionada). Almacenes previstos:

| Almacén | Clave | Contenido |
| --- | --- | --- |
| `obras` | `obra_id` | snapshot de cada obra (§52.11) + `snapshot_at` (fecha de descarga local) |
| `inspecciones` | `uuid` | uuid, obra_id, fecha_inspeccion, hora_inspeccion, observacion, `servidor_id` (tras confirmación), estado de sync |
| `fotografias` | `uuid` | uuid, inspeccion_uuid, extension, mime_type, tamano_bytes, ancho, alto, fecha_hora_captura, latitud, longitud, dispositivo, blob (Blob), thumbnail (Blob), ruta_relativa/ruta_thumbnail (asignadas por el servidor), estado de sync |
| `cola` | uuid | operación (`inspeccion`/`fotografia`), entidad relacionada, dependencia (uuid de inspección), estado (PENDIENTE/ERROR), intentos, ultimo_intento, error |
| `metadatos` | clave | perfil mínimo del usuario, última sincronización, config |

* Se solicita `navigator.storage.persist()` en el primer uso y se monitorea con `storage.estimate()` para avisar sobre cuota.
* Estados locales de sincronización alineados a los indicadores de RNF §44: `pendiente`, `sincronizando`, `sincronizado`, `error` (texto + iconografía + color, sin depender solo del color).
* Los indicadores de conectividad (conectado/desconectado) forman parte de la interfaz global según RNF §44.

## 52.11 Snapshot de obras offline

* Endpoint futuro `GET /inspector/offline/obras`: devuelve **solo las obras con asignación vigente** al usuario autenticado. Una **única consulta con joins** (extender `InspectoresObrasModel::listarVigentesConObra()` agregando `obras.codigo` y `obras.updated_at`, o método específico). **Sin N+1.**
* Campos mínimos del snapshot:

| Campo | Origen |
| --- | --- |
| `obra_id` | `obras.id` |
| `codigo` | `obras.codigo` (para almacenamiento físico, no se muestra) |
| `nombre` | `obras.nombre` |
| `expediente_municipal` | `obras.expediente_municipal` |
| `tipo_licitacion` | `tipo_licitacion_id` + `tipo_licitacion_nombre` |
| `numero_licitacion` | `obras.numero_licitacion` |
| `estado_obra` | `estado_obra_id` + `estado_nombre` |
| `updated_at` | `obras.updated_at` |
| `snapshot_at` | fecha de descarga, agregada por el dispositivo |

* No se incluyen en V1: barrio, empresa, representante técnico, montos, plazos, fecha de inicio, contables.
* Se guardan en el almacén `obras` (upsert por `obra_id`).
* Refresco: al abrir el dashboard con conexión; opcionalmente antes de cada sincronización.

## 52.12 Entidades y relaciones locales

```text
obras (obra_id)
   │ 1..*
   ▼
inspecciones (uuid, obra_id, fecha_inspeccion, ...)
   │ 1..*
   ▼
fotografias (uuid, inspeccion_uuid, blobs, metadata, ...)

cola (uuid, tipo, dependencia inspeccion_uuid, estado)
```

* Las relaciones locales se resuelven por UUID (inspección → fotografías). La relación a obras se mantiene con `obra_id` (id estable de servidor).
* El servidor deriva los IDs numéricos (`inspeccion_id`, etc.) al confirmar; el dispositivo los conserva como `servidor_id` para trazabilidad.

## 52.13 Cola de sincronización y reintentos (F.9, F.10)

* Estados de operación: **PENDIENTE** (errores temporales) y **ERROR** (errores permanentes). Espejo en el servidor: `operaciones_sincronizacion` con `PENDIENTE`/`PROCESADA`/`ERROR`.
* Errores temporales (pérdida de conexión, servidor inaccesible, timeout, red inestable) permanecen como **PENDIENTE** con reintentos automáticos y backoff progresivo (esquema orientativo: 5 s, 15 s, 30 s, 60 s, 5 min; a formalizar en la implementación).
* Agotados los reintentos automáticos, la operación queda **pendiente esperando una acción explícita** ("Sincronizar ahora"). **No se eliminan datos** por agotamiento de reintentos.
* Los errores permanentes pasan a **ERROR** e informan el motivo (p. ej. autorización histórica rechazada).
* **Dependencias**:

```text
Inspección
    ↓ confirmación del servidor
Fotografías de esa inspección
```

  Una fotografía no se sincroniza antes de que su inspección esté confirmada.
* La sincronización puede iniciarse:
  * al recuperar conexión;
  * al abrir SIGOA con conexión;
  * mediante acción manual "Sincronizar ahora";
  * después de resolver una sesión expirada.
* No se depende exclusivamente de ejecución automática en segundo plano (no usar Background Sync de Chrome como mecanismo único; la app ejecuta la sincronización cuando el usuario vuelve a usarla con conexión, compatible con Android/Chrome e iOS/Safari).

## 52.14 Flujo offline

```text
Con conexión:
  abrir dashboard → descargar snapshot (GET /inspector/offline/obras)
                   → persistir en IndexedDB (obras)

Sin conexión:
  abrir obra (snapshot local) → crear inspección (fecha/hora/observación)
                              → capturar fotos → procesar (2560 px) → thumbnail (400 px)
                              → guardar blobs + metadata en IndexedDB → encolar

Recupera conexión:
  detectar red → sincronizar (automático y/o "Sincronizar ahora"):
     1) inspecciones pendientes → confirmación del servidor
     2) fotografías de inspecciones confirmadas → confirmación del servidor
     3) liberar blobs confirmados
```

* El dispositivo guarda localmente la fecha/hora de captura del dispositivo; `fecha_inspeccion` (DATE) es el dato funcional de la inspección y se valida contra la autorización histórica (§52.4).

## 52.15 Sincronización — endpoints y manejo de respuestas

Endpoints futuros (grupo `inspector`, filtros `auth` + `role:INSPECTOR` + CSRF):

| Método | Ruta | Acción |
| --- | --- | --- |
| POST | `/inspector/sincronizar/inspecciones` | Alta confirmada de inspecciones por uuid (idempotente) |
| POST | `/inspector/sincronizar/fotografias` | Alta de fotografías (multipart): archivo + thumbnail + metadata |
| GET | `/inspector/offline/obras` | Snapshot de obras vigentes (§52.11) |
| GET | `/inspector/fotografias/ver/{uuid}` | Servir fotografía optimizada |
| GET | `/inspector/fotografias/mini/{uuid}` | Servir thumbnail |

* **Idempotencia:** si el `uuid` ya existe, el servidor devuelve el registro existente (200) sin duplicar.
* **401:** sesión expirada → JSON 401 → proceso de reautenticación (§52.6).
* **Errores temporales:** respuesta 5xx/timeouts → reintento (backoff) → PENDIENTE.
* **Errores permanentes:** validación fallida (autorización histórica, datos inválidos, formato de archivo) → ERROR con motivo.
* **Confirmación:** cada respuesta exitosa incluye los `servidor_id` (id de inspección/fotografía) para actualizar los registros locales y liberar blobs.

## 52.16 Revisión de `operaciones_sincronizacion`

La tabla existente es suficiente para la arquitectura definida en V1:

* `tipo_operacion`: alta/confirmación; `entidad`: `inspecciones` o `fotografias`.
* `registro_id` (VARCHAR 100): contendrá el **UUID** del registro (`registro_id = uuid`), ya compatible con `CHAR(36)`.
* `estado`: `PENDIENTE` / `PROCESADA` / `ERROR`; `intentos`, `ultimo_intento`, `error` para reintentos/trazabilidad.
* Relación: la cola local (IndexedDB) es el estado operativo del dispositivo; `operaciones_sincronizacion` es el **registro en el servidor** de las altas de sincronización (trazabilidad).
* Cambios futuros documentados (no necesarios para V1 pero recomendados luego): índices compuestos para monitoreo, p. ej. `(entidad, registro_id)` y `(estado, intentos)`.

## 52.17 Fotografías — encaje de uuid y rutas nuevas

La tabla `fotografias` no requiere eliminar columnas. El encaje con UUID y rutas:

* `uuid`: columna nueva `CHAR(36) NOT NULL UNIQUE`.
* `nombre_archivo`: `{uuid}.{extension}` (asignado por el servidor al sincronizar).
* `ruta_relativa`: `OBR-XXXXXX/YYYY-MM-DD/{uuid_inspeccion}/IMAGENES/{uuid}.{ext}` — relativa a la raíz de almacenamiento.
* `ruta_thumbnail`: igual con `THUMBNAILS`.
* `extension`/`mime_type`: validados con `finfo` (solo jpg/jpeg/png/webp).
* `tamano_bytes`, `ancho`, `alto`: metadata de la **versión procesada**.
* `fecha_hora_captura`: dispositivo; `fecha_hora_carga`: servidor.
* `latitud`, `longitud`, `dispositivo`: opcionales desde el dispositivo.
* `anulada`: se conserva (anulación lógica sin borrado físico).

## 52.18 Migraciones futuras (base de datos) — SIN EJECUTAR

Migración por migración, pendientes de crear en fases posteriores:

1. **`inspecciones.uuid`**: `ALTER TABLE inspecciones ADD uuid CHAR(36) NOT NULL` + índice/clave `UNIQUE`. Las tablas de interés se encuentran hoy **vacías**, por lo que no requiere backfill de datos.
2. **`fotografias.uuid`**: `ALTER TABLE fotografias ADD uuid CHAR(36) NOT NULL` + `UNIQUE`.
3. **Eliminar `UNIQUE (obra_id, fecha_inspeccion)`** de `inspecciones` (KEY `obra_id_fecha_inspeccion`) para permitir múltiples inspecciones por día. Se conserva el índice simple `obra_id` existente y las FKs.
4. **Opcional/recomendado más adelante**: índices en `operaciones_sincronizacion` `(entidad, registro_id)` y `(estado, intentos)`.

Reglas: crear migraciones nuevas numeradas posteriormente; **no modificar** migraciones históricas ni `RepairDatabaseStructure`; no usar `migrate:fresh`/`migrate:refresh`/rollback sin autorización (§5.1, §37).

## 52.19 Componentes: reutilizar / crear

**Reutilizar:**
* `AuthFilter`, `RoleFilter`, `BaseController::getRolPrincipal()` / `getDashboardPath()`.
* `InspectoresObrasModel` (vigentes, esVigente; agregar `fueVigente` §52.4), `ObraModel`, `EstadoObraModel`.
* `ObraAlmacenamiento` (`normalizarCodigo`, raíz desde `Config\SigoaStorage`) — adaptado a §52.8.
* Patrón `Empresas::subirLogo` / `verLogo` / `resolverRutaLogo` (validación `finfo`, límites, anti-traversal, servir con Content-Type) para fotografías.
* Patrón de respuesta JSON + 401 de `MisDatos::verifyPassword()` (incluye `X-Requested-With` en `mis-datos.js`).
* Layout `layouts/auth.php`, componentes CSS existentes (alerts, badges, botones, estados, modal, formularios, tablas, navbar, sidebar), `sidebar.js`, recursos locales (§42 RNF).

**Crear (fases siguientes):**
* Filtros: API/filtro que responda 401 JSON para peticiones AJAX (o extensión de `AuthFilter`).
* Modelos: `InspeccionModel`, `FotografiaModel`, `OperacionSincronizacionModel`.
* Controladores: `Inspector\Sincronizar`, `Inspector\Offline`, `Inspector\Fotos`.
* Servicio: lógica de sincronización en el servidor; refactor de `ObraAlmacenamiento` para la estructura por fecha/uuid.
* JS: `components/connectivity.js`, `components/indexeddb.js`, `components/sincronizacion.js`, `components/camera-resize.js` (procesamiento + thumbnail) y JS de páginas del inspector. `app.js` cuando exista responsabilidad global concreta (p. ej. indicador de conectividad).
* PWA: `manifest.json`, `sw.js` (app shell cache-first de recursos estáticos; **no** cachear páginas autenticadas ni respuestas JSON); `layouts/auth.php` con `theme-color`/manifest cuando corresponda.
* CSS: indicadores de conectividad/sincronización según RNF §44 y paleta; páginas de inspector.

## 52.20 Compatibilidad Android/iOS (F.9)

* Contemplar desde V1: **Android + Chrome** (entorno principal de desarrollo/pruebas) e **iPhone + Safari** (inspectores existentes usan ambos).
* Usar APIs web estándar y no depender deliberadamente de APIs exclusivas de Chrome.
* Atención especial y pruebas en ambos: IndexedDB, Service Worker, almacenamiento persistente, cámara (`input capture` respalde `getUserMedia`), procesamiento de imágenes (canvas, EXIF orientation), sincronización (al volver a usar la app, no solo Background Sync de iOS/Safari).
* Requerimientos: iOS Safari moderno (SW ≥ 16.4 instalable), contexto HTTPS (§52.2).

## 52.21 Riesgos y decisiones pendientes

Riesgos identificados:

* HTTPS no resuelto antes de activar SW/PWA en producción (bloqueante temporal para PWA, no para fases de servidor).
* Sesión de 2 h: re-login en el flujo de sincronización (§52.6); validar GC de sesión (FileHandler) en el servidor.
* CSRF: activación global afecta todos los formularios; token vigente por batch de sincronización (§52.7).
* Cuota/almacenamiento local: `persist()`, `estimate()` y avisos.
* Pérdida de blobs con confirmación no recibida: regla de no liberar sin 200/servidor_id.
* Dependencia de la hora del dispositivo para `fecha_inspeccion`: el servidor valida autorización histórica con esa fecha.
* Tamaños de archivo y límites PHP/`upload_max_filesize`/`post_max_size` al reseber fotografías.
* Navegadores móviles viejos (SW/IDB/canvas): definir matriz y fallbacks.

Decisiones pendientes (solo las realmente abiertas):

1. Configuración concreta de HTTPS (certificado, CA, nombre interno, Apache), a cerrar antes de activar SW en producción (F.1).
2. Contrato definitivo del request/response de sincronización (batch vs. por fotografía; cómo refleja la confirmación de blobs; formato multipart) — formalizar en la fase de API.
3. Confianza del reloj del dispositivo para `fecha_inspeccion` (acotación de fechas futuras/imposibles antes de sincronizar).
4. Límites de PHP para upload (revisar `upload_max_filesize`/`post_max_size`) con el límite de 8 MB por fotografía.
5. Formalización del esquema exacto de backoff y de la política de limpieza de `operaciones_sincronizacion` (§52.13/§52.16).
6. Momento y criteros exactos del refresco del snapshot (dashboard con conexión / antes de sincronizar) sin agregar columnas nuevas a `obras`.

## 52.22 Fase B implementada — CSRF y seguridad base de APIs

### CSRF (implementado)

* `Config\Filters`: el alias `csrf` está activo en `$globals['before']`.
* `Config\Security` se mantiene sin cambios: protección `cookie`, `tokenRandomize = false`, `tokenName = csrf_test_name`, `headerName = X-CSRF-TOKEN`, `cookieName = csrf_cookie_name`, `expires = 7200`, `regenerate = true`, `redirect` solo en producción (`ENVIRONMENT === 'production'`).
* `Config\Cookie::$httponly = false`: la cookie CSRF es legible por JavaScript (esquema double-submit). La cookie de sesión `ci_session` **sigue siendo HttpOnly** porque el framework PHP la fuerza en `Session` independientemente de `Config\Cookie`.
* Formularios HTML tipo POST (12 formularios): incluyen `<?= csrf_field() ?>` (helper global de CI4): login, mis-datos (email y contraseña), empresas (alta/edición, subir logo, eliminar logo), representantes (alta/edición, cambio de estado), obras (alta, ficha, cambio de inspector vigente, cambio de representante vigente).
* JavaScript (único `fetch()` del sistema, `mis-datos.js`): lee la cookie `csrf_cookie_name` en cada envío y la envía como header `X-CSRF-TOKEN` (además de `X-Requested-With: XMLHttpRequest`). Como `regenerate = true` rota el token en cada petición, leer la cookie en cada `fetch()` garantiza tokens vigentes incluso ante reintentos.
* Comportamiento ante rechazo CSRF (framework): en producción y petición no-AJAX → redirección hacia atrás con mensaje de error; en desarrollo/pruebas y en peticiones AJAX → `SecurityException` (respuesta de error del servidor). Las futuras APIs de sincronización deben enviar siempre un token vigente; un token inválido rechaza la petición.

### Seguridad base para APIs (implementado)

* `AuthFilter`:
  * contexto web (HTML) → redirección a `/login` (comportamiento previo intacto);
  * contexto API (AJAX `X-Requested-With` o `Accept: application/json`) y sin sesión válida o usuario inactivo → `401` `{"ok": false, "error": "AUTH_REQUIRED"}`.
* `RoleFilter`:
  * contexto web → redirección a `/dashboard` con advertencia (intacto);
  * contexto API y sin el rol requerido → `403` `{"ok": false, "error": "FORBIDDEN"}`.
* Cada filtro tiene su propia detección de contexto API (`esPeticionApi()`). Autenticación ≠ rol ≠ autorización sobre obra ≠ autorización histórica ≠ CSRF siguen siendo responsabilidades separadas; esta base es la que reutilizarán las APIs de sincronización de las Fases A/C (§52.15).

### Pruebas (verdes)

* `tests/unit/CsrfProteccionTest.php`: POST sin token rechazado; token inválido por campo y por cabecera rechazado; token válido por campo y por cabecera aceptado; regeneración del token tras petición exitosa; token antiguo rechazado tras regeneración; GET no bloqueado.
* `tests/unit/ApiSeguridadBaseTest.php`: `401` JSON sin sesión; redirección a `/login` en web; `401` JSON con usuario inactivo; `403` JSON sin rol; redirección a `/dashboard` en web; rol correcto atraviesa el filtro; `/dashboard` resuelve destino según rol.
* Suite completa en verde (57 tests / 124 assertions).

## 52.23 Fase C implementada — UUID de inspecciones y fotografías

### Identidad de inspecciones y fotografías

* `inspecciones.id` y `fotografias.id` **continúan** siendo las PK autoincrementales del servidor (no se reemplazan).
* `inspecciones.uuid` y `fotografias.uuid` son `CHAR(36) NOT NULL UNIQUE`, agregadas por migraciones nuevas (§52.23.2).
* El UUID es la **identidad estable** destinada a soportar el futuro flujo offline/sincronización: identifica la inspección (y sus fotografías) independientemente de la fecha y sin depender del `id` del servidor.
* **No se implementó ninguna sincronización** en esta fase: el UUID queda establecido como identidad, listo para ser usado por las APIs de sincronización futuras (§52.1–§52.21).

### Múltiples inspecciones por fecha

* Se eliminó la restricción `UNIQUE (obra_id, fecha_inspeccion)` (clave `obra_id_fecha_inspeccion`) mediante una **nueva migración** (§52.23.2) — la decisión ya estaba consolidada en §52.3.
* Una obra puede tener **múltiples inspecciones en la misma fecha**; cada inspección se diferencia por su `uid` `uuid` (`CHAR(36)` único) y por su `id`.
* La migración histórica que creó la restricción **no se modifica**: se agregó la migración que la elimina (§52.23.2, acorde a las reglas de §3).

### Migraciones (agregadas en Fase C)

Nuevas migraciones reales, numeradas posteriormente a las históricas; **no se modificó** ninguna migración histórica ni `RepairDatabaseStructure`:

* `2026-09-23-120000_AddUuidToInspecciones.php`: agrega `inspecciones.uuid CHAR(36) NOT NULL UNIQUE`.
* `2026-09-23-121000_AddUuidToFotografias.php`: agrega `fotografias.uuid CHAR(36) NOT NULL UNIQUE`.
* `2026-09-23-122000_DropUniqueObraFechaInspeccion.php`: elimina la restricción `UNIQUE (obra_id, fecha_inspeccion)` de `inspecciones`.

### Pruebas (verdes)

* `tests/database/InspeccionFotografiaUuidTest.php`: `uuid` `NOT NULL`/único en inspecciones y fotografías; distintos UUID en una misma obra/fecha permitidos; UUID duplicado rechazado; modelos `InspeccionModel`/`FotografiaModel` insertan y recuperan por `uuid`; FKs hacia obras/usuarios/inspecciones **continúan** aplicándose.
* Suite completa en verde: **86 tests / 182 assertions**.

### Consideración sobre la infraestructura de pruebas (esquema compartido)

* El grupo `tests` de la suite database ejecuta sobre **una única BD SQLite `:memory:` compartida** por todos los archivos del grupo.
* Al estar en memoria y crearse con `CREATE TABLE IF NOT EXISTS`, **el primer test que inicializa determinadas tablas fija el esquema compartido** para el resto de la corrida.
* Por eso, `tests/database/InspeccionFotografiaUuidTest.php` — primero en orden alfabético del grupo — declara las tablas compartidas con un **esquema superset canónico**, compatible con los demás tests:
  * `db_obras` conserva el esquema compatible con `InspectorObrasStorageTest` (incluye la columna `codigo`);
  * `db_usuarios` conserva el esquema compatible con `MisDatosCsrfTest` (incluye `password_hash`/`activo`).
* Esta es una **consideración de infraestructura de pruebas**: no implica ningún cambio al esquema de producción ni a las definiciones reales de las tablas.

### Sin implementar (queda para Fase D)

* IndexedDB, Service Worker/PWA, cámara, fotografías (captura/sincronización), endpoints de sincronización, cola, reintentos, snapshots offline, autorización histórica de sincronización, cambios HTTPS y refactor de almacenamiento OBRA/FECHA/UUID. Ver §52.1–§52.21.

---

# 53. FASE D.1 IMPLEMENTADA — FUNDACIÓN PWA / MODO OFFLINE / INDEXEDDB

## 53.1 Alcance de D.1

D.1 implementa la **base para que SIGOA funcione como aplicación instalable/offline** y la **capa local persistente** para las futuras inspecciones y fotografías:

* manifest PWA;
* Service Worker de app shell (recursos estáticos);
* registro del Service Worker compatible con la arquitectura actual;
* capa propia de acceso a IndexedDB (base `SIGOA`, versionada);
* UUID v4 en el cliente como identidad local;
* estado online/offline con indicador discreto en la topbar.

**NO** se implementó sincronización, endpoints de API, cola de operaciones contra el servidor, cámara, fotografías, snapshots de obras, ni cambios HTTPS/certificados (queda para D.2+; §53.9).

## 53.2 Manifest PWA

Archivo: `public/manifest.json` (servido por Apache como recurso real; no pasa por el front controller).

| Campo | Valor |
| --- | --- |
| `name` | SIGOA — Sistema para Inspección de Obras de Arquitectura |
| `short_name` | SIGOA |
| `start_url` | `/login` |
| `scope` | `/` |
| `display` | `standalone` |
| `orientation` | `portrait` |
| `lang` | `es` |
| `background_color` | `#F5F3F0` (blanco cálido) |
| `theme_color` | `#24344C` (azul institucional) |
| `icons` | `[]` — **pendiente**: no existen todavía iconos institucionales reales; no se fabricaron imágenes arbitrarias |

El layout autenticado (`app/Views/layouts/auth.php`) referencia el manifest, declara `theme-color` y las metas Apple/iOS mínimas para el modo standalone.

**Pendiente (fuera de D.1):** diseñar los iconos institucionales (192 px y 512 px, con sus variantes) antes de considerar la PWA instalable en producción. Chrome exige iconos para habilitar la instalación; el manifest es válido pero no instalable sin ellos.

## 53.3 Service Worker y estrategia de cache

Archivo: `public/sw.js`. Implementación deliberadamente pequeña y mantenible.

* **Precache de app shell** al instalar: CSS global y de componentes, tipografía Inter, Bootstrap Icons, `manifest.json`, `robots.txt`, `favicon.ico` y los JS del frontend offline.
* **Cache-first para recursos estáticos** (`/assets/…`, manifest, robots, favicon), con fallback a red; las respuestas nuevas se incorporan al cache solo si son `ok`, del mismo origen (`type === 'basic'`) y **no llevan cabecera `Set-Cookie`**.
* **No se intercepta ni se cachea** ninguna página HTML, `/login`, `/dashboard`, APIs ni futuras rutas de sincronización: esas respuestas son privadas/dinámicas y pueden contener sesión, CSRF o datos personales.
* **No hay Background Sync**, ni cola, ni lógica de sincronización (Fase D.x).
* **Versionado**: constante `CACHE_VERSION = 'sigoa-shell-v1'`. Al modificar cualquier asset del app shell hay que incrementar la versión, o los clientes quedarán con assets viejos. En `activate` se borran los caches `sigoa-shell-*` de versiones anteriores.

Alcance del SW: servido desde la raíz (`/sw.js`), su control cubre toda la aplicación.

### Limitación documentada de D.1

Sin red, **los assets del app shell se recuperan del cache**, pero **las páginas HTML todavía requieren servidor**: no hay snapshot de obras ni vistas cacheadas (eso pertenece a fases siguientes, §52.11). "Abrir la interfaz sin red" en D.1 significa que la interfaz básica sigue cargada mientras el navegador conserva la página abierta y que el app shell queda precacheado para la próxima visita.

## 53.4 Registro del Service Worker

Se realiza en `public/assets/js/app.js` (responsabilidad global concreta, RNF §41b), cargado desde el layout autenticado.

* Se registra **solo** si `navigator.serviceWorker` existe y el contexto es **HTTPS o localhost** (`localhost` / `127.0.0.1` / `[::1]`).
* Fuera de contexto seguro, no se registra y **no rompe la aplicación**: se informa por consola. La prueba en desarrollo mediante IP HTTP (`app.baseURL = 'http://10.11.20.161/'`) no registra SW, lo cual es esperado y no representa el entorno productivo (§53.8).
* Errores de registro capturados y registrados en consola, sin afectar la navegación.

## 53.5 Capa local — IndexedDB

Capa propia **vanilla JavaScript** (sin librerías externas): `public/assets/js/components/indexeddb.js`, que expone `SIGOA.almacenamiento`.

Base: **`SIGOA`**, versión **1**.

| Store | PK | Campos locales | Índices |
| --- | --- | --- | --- |
| `inspecciones` | `uuid` | `uuid`, `obra_id`, `inspector_id`, `fecha_inspeccion`, `hora_inspeccion`, `observacion`, `estado_local`, `created_at_local`, `updated_at_local` | — |
| `fotografias` | `uuid` | `uuid`, `inspeccion_uuid`, `nombre_archivo`, `extension`, `mime_type`, `tamano_bytes`, `ancho`, `alto`, `fecha_hora_captura`, `latitud`, `longitud`, `dispositivo`, `estado_local` (+ Blob optimizado y thumbnail **previstos**, se usarán en D.2) | `por_inspeccion` (`inspeccion_uuid`) |
| `operaciones` | `id` (auto) | `id`, `tipo_operacion`, `entidad`, `entidad_uuid`, `estado`, `intentos`, `ultimo_intento`, `error`, `created_at` | `por_entidad` (`entidad_uuid`), `por_estado` (`estado`) |

API expuesta (`SIGOA.almacenamiento`): `soportado()`, `iniciar()`, `abrir()`, `obtener()` / `obtenerTodos()`, `guardar()` (`put`), `agregar()` (`add`, falla si la clave existe), `eliminar()`, `limpiar()`, `contar()` y `autotest()`.

* `iniciar()` abre/crea la base y los stores en cada página autenticada y solicita `navigator.storage.persist()` cuando el navegador lo soporta (mejor esfuerzo). **No escribe datos.**
* El store `operaciones` es la **cola local futura**: en D.1 solo existe su estructura; **no realiza ninguna request al servidor**.
* **No se almacenan** contraseñas, cookies de sesión (`ci_session`), tokens CSRF ni credenciales en IndexedDB (decisiones Fase B, §52.6/§52.7).
* **Nada se borra automáticamente**: los datos locales solo se eliminan mediante operaciones explícitas (`eliminar`, `limpiar`, `autotest`).
* `SIGOA.almacenamiento.ALMACENES` expone los nombres de stores y `STORES` su definición, para que las fases siguientes no dupliquen la estructura.

## 53.6 UUID como identidad local

Archivo: `public/assets/js/components/uuid.js` → `SIGOA.uuid`.

* `v4()` usa **`crypto.randomUUID()`** cuando está disponible (Chrome ≥ 92, Safari ≥ 15.4, contexto seguro/HTTPS) y devuelve la forma canónica en minúsculas, igual que `App\Libraries\Uuid::v4()` del servidor.
* **Fallback explícito**: RF-4122 v4 sobre `crypto.getRandomValues` para navegadores que aún no exponen `randomUUID`. Si no hay fuente criptográfica segura, lanza un error en lugar de degradar la identidad.
* `esValido()` replica el patrón RFC 4122 de la validación de servidor (§52.3).
* No se usan SHA-256, timestamps ni ids autoincrementales como identidad offline: el `uuid` es la identidad estable de sincronización.

## 53.7 Estado online/offline

Archivo: `public/assets/js/components/connectivity.js` → `SIGOA.conectividad`, con indicador en la topbar autenticada (`components/connectivity.css`).

* Estado inicial: `navigator.onLine`. Cambios: eventos `online`/`offline`.
* El indicador combina **texto + iconografía + color** (RNF §44): `bi-wifi` "En línea" / `bi-wifi-off` "Sin conexión", con colores de la paleta (verde éxito / ocre advertencia). No depende solo del color.
* `alCambiar(cb)` permite a las fases siguientes consumir el estado (devuelve un "unsubscribe").
* **Advertencia documentada**: `navigator.onLine` solo informa la red del dispositivo. **No confirma que el servidor SIGOA esté disponible** ni que exista sesión válida. La sincronización futura deberá validar sesión/rol/autorización y CSRF en cada intento (§52.6/§52.7).

## 53.8 HTTPS — requisito de producción

* Service Worker, `crypto.randomUUID`, cámara y geolocalización exigen **contexto seguro (HTTPS)** o `localhost` (§52.2 / F.1). 
* `localhost` cubre las excepciones de desarrollo del navegador; **no se relajó** ninguna decisión de seguridad de producción.
* La prueba real desde el teléfono mediante **IP HTTP** (p. ej. `http://10.11.20.161/`) **no representa el entorno productivo definitivo** y no activará SW/PWA.
* **No se configuró el certificado HTTPS de Apache** en D.1: queda pendiente formalizar certificado/CA/nombre interno/**antes de activar SW en producción** (§52.21).

## 53.9 Deliberadamente fuera de D.1 (fases siguientes)

D.2/D.3/D.4 (por implementar, no adelantadas):

* sincronización (`/sync`), endpoints de inspecciones/fotografías, cola real con reintentos (5/15/30/60/300 s) contra el servidor;
* autorización histórica del inspector en sincronización (§52.4);
* snapshot de obras offline, `operaciones_sincronizacion` del servidor y su limpieza;
* cámara, captura GPS, procesamiento/compresión de fotografías (2560 px / 80–85 % / thumbnail 400 px, §52.9), thumbnails reales y subida de imágenes;
* refactor de `ObraAlmacenamiento` a estructura OBR/FECHA/UUID (§52.8);
* resolución de conflictos, Background Sync y HTTPS/certificados Apache.

## 53.10 Compatibilidad Android Chrome / iPhone Safari

* Objetivos explícitos: **Android + Chrome** y **iPhone + Safari** (§52.20).
* D.1 usa únicamente APIs web estándar soportadas por ambos: IndexedDB, Service Worker, `crypto.randomUUID`, eventos `online`/`offline`, `navigator.storage.persist`.
* No depende de Background Sync (Chrome) ni de APIs propietarias. Los fallbacks están encapsulados detrás de la capa propia (p. ej. UUID).
* Requisitos para el entorno instalable: iOS Safari moderno (SW instalable ≥ 16.4) y HTTPS (§52.20, §53.8).

## 53.11 Pruebas y checklist manual

### Suite PHP (verde)

`tests/unit/InfraestructuraOfflineTest.php` valida estructuralmente la capa entregada:

* manifest válido y con los campos PWA esperados (sin iconos fabricados);
* Service Worker con ciclo de vida (install/activate/fetch), versionado, sin Background Sync, sin cache de páginas/login y con guardia `Set-Cookie`;
* todos los recursos del app shell existen en disco;
* componentes JS offline presentes y layout autenticado los integra.

Suite completa en verde: **94 tests / 254 assertions** (incremento +8 tests / +72 assertions sobre el cierre de Fase C).

No se introdujo infraestructura de tests JavaScript (el proyecto no la posee y D.1 no la justifica). La capa queda testeable de forma manual mediante el autotest de la capa y el namespace global `SIGOA`.

### Checklist manual reproducible (navegador)

Con Chrome/Edge en escritorio (localhost es contexto seguro para SW):

1. Iniciar sesión en SIGOA → abrir el dashboard del inspector.
2. Abrir DevTools → **Application → IndexedDB**: debe existir la base `SIGOA` con los stores `inspecciones`, `fotografias` y `operaciones`.
3. En la consola ejecutar: `SIGOA.almacenamiento.autotest()` → debe responder `{ok: true, ...}` (guarda, recupera y elimina un registro de prueba en `inspecciones`; no deja datos).
4. Cerrar y volver a abrir la aplicación → **Application → IndexedDB**: la base `SIGOA` sigue existiendo.
5. En `Application → Service Workers`: `sw.js` está activo (registrado) y `Storage` muestra el origen cacheado (`sigoa-shell-v2`).
6. Con DevTools abierto, activar **Offline** y recargar la página: la interfaz autenticada conserva el app shell (CSS/fuentes/iconos desde cache); el indicador de conectividad pasa a "Sin conexión". Las páginas HTML seguirán requiriendo red (limitación documentada §53.3).
7. Desactivar Offline: el indicador vuelve a "En línea" (eventos `online`/`offline`).
8. Verificar que `manifest.json` responde con `Content-Type: application/json` y `sw.js` con `text/javascript`.

> Nota: al probar desde el teléfono por IP HTTP, los puntos 5/6/8 no aplican (requieren HTTPS/localhost, §53.8).

---

# 54. FASE D.2 IMPLEMENTADA — NUEVA INSPECCIÓN 100% LOCAL (INSPECTOR MÓVIL)

## 54.1 Alcance

D.2 entrega el **primer flujo operativo real** del inspector sobre su dispositivo: iniciar una
nueva inspección, guardarla **solo en el dispositivo** (IndexedDB), capturar fotografías,
**optimizarlas en el cliente**, generar thumbnail y dejar cada registro local **pendiente de
sincronización**.

El servidor **no persiste inspecciones ni fotografías** en esta fase: solo autoriza la operación
para las obras que el inspector tiene asignadas de forma vigente y cuyo estado lo permite (F.7).
La sincronización con el servidor sigue pendiente (§4.2, §52.13/§52.15).

## 54.2 Precondiciones en servidor (autorización únicamente)

Ruta nueva (grupo `inspector`, filtros `auth` + `role:INSPECTOR`):

```
GET /inspector/inspecciones/nueva/{obraId}
```

Controlador: `app/Controllers/Inspector/Inspecciones.php::nueva()`.

Flujo de autorización (no crea datos de servidor):

1. La obra debe existir (`ObraModel::findDetalle`); si no, redirige al dashboard.
2. `InspectoresObrasModel::esVigente()`: el inspector debe tener **asignación vigente** a la obra
   (F.6, §52.4). Si no, redirige a la vista de la obra con aviso.
3. `EstadoObraModel::permiteInspeccionar()` (constante `PERMITEN_INSPECCIONAR`): solo
   **EN EJECUCIÓN**, **NEUTRALIZADA** y **EN PLAZO DE CONSERVACIÓN** permiten nuevas
   inspecciones (F.7, §52.5). **PREVIO INICIO** y **FINALIZADA** no lo permiten (redirige con aviso).

La validación de la fecha local (§52.4, autorización histórica) corresponde a la sincronización
futura, no a la creación local.

## 54.3 Vista de obra — acción y sección de inspecciones locales

`app/Views/inspector/obra.php` cambia el bloque "Vista operativa en preparación" por la primera
herramienta real:

* **Botón "Nueva inspección"** (`.io-btn-nueva`), visible solo si el controlador pasa
  `puede_inspeccionar = true` (mismas precondiciones de §54.2), que enlaza a
  `/inspector/inspecciones/nueva/{obraId}`.
* **Sección "Inspecciones guardadas en este dispositivo"** (`#inspeccionesLocales`, `data-obra-id`):
  lista las inspecciones locales de esa obra (por el índice `por_obra`, §54.7) con fecha/hora,
  observación y **badge de estado local**; cada ítem permite expandir "Ver fotografías" (thumbnails
  del store `fotografias` por índice `por_inspeccion`).
* Estilos de página ampliados en `public/assets/css/pages/inspector-obra.css` (`.io-locales*`,
  `.io-badge-*`, `.io-btn-nueva`, `.io-aviso-bloqueado`).

## 54.4 Flujo de nueva inspección local

Vista: `app/Views/inspector/inspeccion_nueva.php` (`titulo` "Nueva inspección").
JS de página: `public/assets/js/pages/inspeccion-nueva.js`.

1. La vista expone `#datosLocal` con `data-obra-id` y `data-inspector-id` (del servidor).
2. Fecha y hora se **inicializan con el reloj del dispositivo** y son **editables** por el usuario.
3. Al "Guardar inspección" se valida el formulario (fecha/hora obligatorias, `.field-error`) y se
   persiste en el dispositivo:
   * genera `uuid` v4 (SIGOA.uuid, §53.6);
   * registra `obra_id`, `inspector_id`, `fecha_inspeccion`, `hora_inspeccion`, `observacion`;
   * `estado_local = PENDIENTE_SYNC`, `created_at_local` / `updated_at_local` (ISO);
   * guarda con `put` (re-guardar actualiza el mismo `uuid`, no duplica).
4. Al guardar se habilita la **sección de fotografías** y se muestra la alerta de éxito
   "Inspección guardada en este dispositivo."
5. No se realiza ninguna request al servidor en todo el flujo (sin `fetch`).

## 54.5 Estados locales usados

La capa define `BORRADOR` y `PENDIENTE_SYNC` (§53/F.5). **D.2 usa exclusivamente
`PENDIENTE_SYNC`**: cada inspección y cada fotografía nace lista para la sincronización futura.
No se agregaron estados nuevos; la sincronización transicionará a los estados de cola de §52.13.

## 54.6 Fotografías — optimización en cliente (decisión JPEG)

Componente: `public/assets/js/components/camera-resize.js` → `SIGOA.imagenes.optimizar(blob)`.
Captura: `<input type="file" accept="image/*" capture="environment">` (cámara nativa del
dispositivo; móvil-first).

Pipeline por fotografía (una a la vez, con indicador "Optimizando fotografía…"):

1. decodifica preservando la orientación EXIF (`createImageBitmap` con
   `imageOrientation: 'from-image'`; fallback a `Image` + canvas);
2. reescala a un **máximo de 2560 px** en el lado mayor (manteniendo proporción);
3. codifica a **JPEG, calidad 0.82**;
4. genera **thumbnail de 400 px** máx (calidad 0.8);
5. persiste en `fotografias` (store local): solo el **blob optimizado + thumbnail**, dimensiones,
   `tamano_bytes`, `mime_type = 'image/jpeg'`, `extension = 'jpg'`, `fecha_hora_captura`,
   `estado_local = PENDIENTE_SYNC` y vínculo `inspeccion_uuid`. El archivo original de la cámara
   **no se almacena**.
6. Renderiza el thumbnail y permite "Quitar" (borrado explícito del registro local).

> **Divergencia documentada vs. §52.9/§53.5**: la redacción de F.8/D.1 preveía conservar el
> formato original. **Decisión D.2: la salida optimizada es SIEMPRE JPEG** (aunque la fuente sea
> PNG), para minimizar tamaño, homogeneizar el preview y simplificar el modelo de datos de la
> futura sincronización. Los campos `blob` y `thumbnail` de `fotografias`, previstos en D.1, se
> **llenan efectivamente** a partir de D.2.

## 54.7 IndexedDB — migración a v2

`public/assets/js/components/indexeddb.js` (base `SIGOA`) migra de **v1 → v2** (sin pérdida de
datos: la migración solo agrega índices a stores ya existentes, usando la transacción del evento
`upgradeneeded`).

* `inspecciones` gana el índice **`por_obra`** (`keyPath: 'obra_id'`).
* Nueva API pública: **`buscarPorIndice(nombreStore, nombreIndice, valor)`** (`index().getAll`),
  usada por la vista de obra (§54.3).
* `fotografias` conserva su índice `por_inspeccion` (v1).
* Interfaces expuestas: `SIGOA.almacenamiento.ALMACENES`, `.STORES`, `.buscarPorIndice`, además de
  las operaciones del §53.5.

## 54.8 Service Worker y assets nuevos

`public/sw.js`:

* precache agrega: `components/camera-resize.js`, `pages/inspeccion-nueva.js`,
  `pages/obra-inspecciones.js`, `pages/inspeccion-nueva.css`, `pages/inspector-obra.css`;
* **CACHE_VERSION → `sigoa-shell-v2`** (invalida la shell anterior al desplegar).

Estilos nuevos/ampliados:
* nuevo `public/assets/css/pages/inspeccion-nueva.css` (`.nin-*`, `.field-error`);
* ampliado `public/assets/css/pages/inspector-obra.css` (§54.3).

## 54.9 Deliberadamente fuera de D.2

* sincronización al servidor y cola `operaciones` (§52.13/§52.15);
* inspecciones y fotografías completas en servidor (`inspecciones`/`fotografias`, §4.2);
* módulo de fotografías con metadatos EXIF/geo completos;
* edición/borrado de inspecciones locales existentes (D.2 permite re-guardar la inspección actual
  y quitar fotografías de la sesión activa);
* snapshot de obras offline (§52.11).

## 54.10 Pruebas

Suite completa en verde: **113 tests / 334 assertions** (incremento **+19 tests / +80 assertions**
sobre el cierre de Fase D.1).

Nuevos tests:

* `tests/database/InspeccionNuevaAutorizacionTest.php` — autorización de la ruta: sin sesión →
  /login; inspector vigente + estado permitido → 200 con formulario; estado PREVIO INICIO /
  FINALIZADA → redirige a la obra; inspector no vigente → redirige; obra inexistente → dashboard.
* `tests/unit/PermisoInspeccionarEstadoTest.php` — regla pura F.7
  (`EstadoObraModel::permiteInspeccionar`, normalización de mayúsculas/espacios).
* `tests/unit/InspeccionNuevaEstructuraTest.php` — controlador/ruta/vistas/JS del flujo local
  (sin `fetch`, usa solo la capa local).
* `tests/unit/InfraestructuraOfflineTest.php` — actualizado a `sigoa-shell-v2`, nuevos componentes
  JS y límites del procesador de imágenes (2560/400/JPEG).

Notas de scope:

* El flujo de IndexedDB (migración v2, índice `por_obra`) se valida con el checklist manual
  (navegador) y con `SIGOA.almacenamiento.autotest()`; no se introdujo infraestructura de tests JS.
* La fecha/reloj del dispositivo y la identidad UUID se validan en la sincronización futura
  (§52.4, §52.3).

---
