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
* **Fase D.2** — nueva inspección 100 % local del inspector móvil: autorización en servidor (estado F.7 + asignación vigente), guardado en IndexedDB (v2), captura/optimización/thumbnail de fotografías en el cliente y vista de inspecciones locales — ver §54;
* **Fase D.3** — sincronización servidor de inspecciones capturadas offline: endpoint `POST /inspector/sincronizar/inspecciones` idempotente por uuid, autorización histórica del inspector (§52.4), validación del payload, trazabilidad en `operaciones_sincronizacion`, botón "Sincronizar" en la vista de obra y estados `SINCRONIZADA`/`ERROR` en las inspecciones locales — ver §55.

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
* La raíz física es configurable en `.env` con `SIGOA_STORAGE_COMPARTIDO` (o `SIGOA_STORAGE_PATH` en desarrollo) e implementada en `Config\SigoaStorage`. En producción es un recurso UNC (§59.3.1).
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

> **Actualizado en la Fase B (§52.22):** la protección CSRF está **activada globalmente**
> (`Config\Filters::$globals['before']` incluye el alias `csrf`, hoy `App\Filters\CsrfApi`) y
> `Config\Security` usa protección por cookie con `regenerate = true`. Los formularios HTML
> incluyen `csrf_field()` y el JavaScript envía la cabecera `X-CSRF-TOKEN`. El texto original de
> esta sección describía el estado previo a la Fase B y se conserva corregido más abajo.

Protección CSRF **antes de la Fase B**: estaba desactivada globalmente en el proyecto
(`Config\Filters::$globals['before']` tenía CSRF comentado). Todos los formularios existentes
(login, etc.) operaban sin token CSRF. El módulo Mis Datos mantenía esa coherencia.

La protección CSRF estaba documentada como pendiente en SIGOA.md §32. Habilitarla fue una
decisión global implementada de forma transversal; afecta a todos los formularios del sistema. La
activación global y el manejo con la sincronización offline están especificados en §52.7.

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
* Un rechazo por sesión o por token de seguridad (401 o 403 `CSRF_INVALID`/`FORBIDDEN`) **no consume intentos**: la operación vuelve a `PENDIENTE` con el contador devuelto a su valor anterior. Así la cola no se agota ni queda congelada mientras el inspector no vuelve a autenticarse o a recargar la página.
* **No se almacenan contraseñas, cookies ni credenciales en IndexedDB.** Solo se almacena un perfil mínimo (nombre, apellido, nombre de usuario) para la interfaz.
* Los endpoints de sincronización responden con `401` explícito (JSON) cuando la sesión expiró — patrón de `MisDatos::verifyPassword()` — y no con la redirección HTML de `AuthFilter`. Implementado en la Fase B: `AuthFilter`/`RoleFilter` responden `401`/`403` JSON ante peticiones AJAX/API (§52.22).

## 52.7 CSRF (F.3)

* El filtro CSRF está **activado globalmente desde la Fase B** (§52.22), antes de exponer las APIs de sincronización. Se apoya en la configuración de `Config\Security` (protección `cookie`, `tokenName = csrf_test_name`, `headerName = X-CSRF-TOKEN`). La activación es transversal y afecta todos los formularios del sistema (ver §32 y §45.8).
* El manejo debe ser transparente para el usuario.
* **No se almacenan tokens CSRF en la cola offline.**
* Con la rotación activa (`Security::$regenerate = true`, mantenido salvo incompatibilidad demostrada), cada `fetch()` **resuelve el token en el momento de enviarlo**, sin almacenamiento persistente:
  1. `public/assets/js/components/csrf.js` (`SIGOA.csrf`) es el **único origen** del token que el cliente envía. Resuelve, en este orden, **cookie `csrf_cookie_name` → cabecera `X-CSRF-TOKEN` de la última respuesta API → `<meta name="X-CSRF-TOKEN">`**, y lo conserva solo en memoria;
  2. la cookie es la fuente primaria porque es la que el servidor acaba de comparar (`csrfProtection = cookie`) y la que su propia respuesta renueva. Es además el único canal compartido entre pestañas;
  3. el `<meta>` que emite `app/Views/layouts/auth.php` con `<?= csrf_meta() ?>` queda como **último recurso**: renueva la cookie cuando esta venció (`Config\Security::$expires = 7200`), pero es una foto del token del momento de cargar la página y el navegador no lo actualiza, por lo que **no puede ser la fuente primaria** (causa del defecto documentado en §61);
  4. cada respuesta API publica el token vigente en la cabecera `X-CSRF-TOKEN` (`App\Filters\CsrfApi::after()`), de modo que la siguiente petición salga con el token renovado sin recargar (§61).
* Un token vencido o ausente en una petición de la API no produce un error 500: `App\Filters\CsrfApi` (alias global `csrf`, sustituye a `CodeIgniter\Filters\CSRF`) responde **403 JSON `{"ok": false, "error": "CSRF_INVALID"}`**. Las peticiones HTML conservan el comportamiento del filtro original (redirección con mensaje en producción).
* El cliente trata `CSRF_INVALID` como un rechazo de sesión, no como un fallo de la operación: **no consume intentos**, vuelve la operación a `PENDIENTE` y espera su siguiente disparador (reintento manual, reautenticación o recarga). Un rechazo **no renueva el token**: la pausa es real, no un reintento en bucle (§61). Los formularios HTML siguen lanzando `SecurityException` como hasta ahora (§52.22).
* `tokenRandomize = false`: el token coincide con el hash de la cookie.
* Los endpoints de sincronización combinan: autenticación, rol/autorización, validación de vigencia histórica (§52.4) y CSRF.
* Las APIs devuelven `401` explícito ante sesión expirada (§52.6).

## 52.8 Arquitectura de almacenamiento físico (F.5)

Estructura prevista:

```text
SIGOA/
└── OBR-000001/
    └── 2026-09-22/
        ├── 10-30-00/
        │   ├── IMAGENES/
        │   └── THUMBNAILS/
        │
        └── 10-30-00-2/
            ├── IMAGENES/
            └── THUMBNAILS/
```

Conceptualmente:

```text
Obra → Fecha → Hora de inspección → archivos
```

* El nombre de la carpeta de obra continúa siendo su código interno `OBR-XXXXXX` (de `obras.codigo`), validado con `ObraAlmacenamiento::normalizarCodigo()` (formato `^OBR-\d{6}$`, anti-traversal).
* Desde la Fase E.2 el nombre de la carpeta de la inspección se compone **exclusivamente a partir de `fecha_inspeccion` y `hora_inspeccion`**, no a partir del UUID: `HH-mm-ss` (`hora_inspeccion` nula → `SIN-HORA`). El UUID sigue siendo la identidad técnica de la inspección en la base, pero deja de formar parte del esquema físico.
* `InspeccionModel::nombreCarpetaInspeccion()` resuelve la composición; el detalle del nombre queda centralizado en `ObraAlmacenamiento::nombreCarpetaInspeccion()` y `normalizarHoraCarpeta()`. `00:00:00` es una hora válida y produce `00-00-00`, nunca `SIN-HORA`.
* `ObraAlmacenamiento` expone los métodos por obra+fecha+carpeta (`asegurarEstructuraInspeccion()`, `rutaRelativaInspeccion()`, `rutaRelativaImagen()`, `rutaRelativaThumbnail()`), usados por `FotografiaArchivo`. `asegurarEstructuraObra()`, llamado por `Inspector\Obras::ver()`, crea únicamente la carpeta `OBR-XXXXXX`.
* **Colisiones.** Dos o más inspecciones de la misma obra, fecha y hora comparten el nombre base, así que el segundo recibe `-2`, el tercero `-3`, etc. El ordinal lo decide la base de datos, no el sistema de archivos: `InspeccionModel::sufijoCarpeta()` cuenta las inspecciones con **menor `id`** dentro de la misma terna `obra_id` + `fecha_inspeccion` + `hora_inspeccion`. Es determinista e independiente del orden de sincronización.
* El nombre de carpeta recibido por el servicio se valida contra `^(\d{2}-\d{2}-\d{2}|SIN-HORA)(-\d+)?$` antes de construir rutas, de modo que ningún dato pueda escapar de la raíz.
* Una `hora_inspeccion` **ilegible** (por ejemplo `24:00:00`) se rechaza con `STORAGE_ERROR` en lugar de guardarse como `SIN-HORA`: `SIN-HORA` significa "la inspección no tiene hora", y degradar un dato corrupto a ese nombre lo ocultaría detrás de una carpeta aparentemente válida.
* Desde la Fase E.1.2 el esquema base `OBR-XXXXXX/{IMAGENES,THUMBNAILS}` quedó **retirado**: solo dejaba carpetas vacías en el almacenamiento y ninguna ruta de la base lo referencia. `IMAGENES` y `THUMBNAILS` existen únicamente dentro de la carpeta de una inspección.
* En la base de datos se guardan **referencias relativas** a la raíz (`Config\SigoaStorage`), nunca rutas absolutas — misma convención que `empresas.ruta_logo`.
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

Endpoints (grupo `inspector`, filtros `auth` + `role:INSPECTOR` + CSRF):

| Método | Ruta | Acción | Estado |
| --- | --- | --- | --- |
| POST | `/inspector/sincronizar/inspecciones` | Alta confirmada de inspecciones por uuid (idempotente) | **implementado — ver §55** |
| POST | `/inspector/sincronizar/fotografias` | Alta de fotografías (multipart): archivo + thumbnail + metadata | pendiente |
| GET | `/inspector/offline/obras` | Snapshot de obras vigentes (§52.11) | pendiente |
| GET | `/inspector/fotografias/ver/{uuid}` | Servir fotografía optimizada | pendiente |
| GET | `/inspector/fotografias/mini/{uuid}` | Servir thumbnail | pendiente |

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
* `ruta_relativa`: `OBR-XXXXXX/YYYY-MM-DD/{HH-mm-ss|SIN-HORA}[-{n}]/IMAGENES/{nombre}` — relativa a la raíz de almacenamiento.
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

* `Config\Filters`: el alias `csrf` está activo en `$globals['before']` y en `$globals['after']`. La fase `after` es la que publica el token vigente en las respuestas API (§61).
* `Config\Security` se mantiene sin cambios: protección `cookie`, `tokenRandomize = false`, `tokenName = csrf_test_name`, `headerName = X-CSRF-TOKEN`, `cookieName = csrf_cookie_name`, `expires = 7200`, `regenerate = true`, `redirect` solo en producción (`ENVIRONMENT === 'production'`).
* `Config\Cookie::$httponly = false`: la cookie CSRF es legible por JavaScript (esquema double-submit). La cookie de sesión `ci_session` **sigue siendo HttpOnly** porque el framework PHP la fuerza en `Session` independientemente de `Config\Cookie`.
* Formularios HTML tipo POST (12 formularios): incluyen `<?= csrf_field() ?>` (helper global de CI4): login, mis-datos (email y contraseña), empresas (alta/edición, subir logo, eliminar logo), representantes (alta/edición, cambio de estado), obras (alta, ficha, cambio de inspector vigente, cambio de representante vigente).
* JavaScript: el token lo resuelve `public/assets/js/components/csrf.js` (`SIGOA.csrf`) en cada envío —cookie `csrf_cookie_name`, luego la cabecera `X-CSRF-TOKEN` de la última respuesta, y por último el `<meta name="X-CSRF-TOKEN">` del layout— y lo envía como header `X-CSRF-TOKEN` (además de `X-Requested-With: XMLHttpRequest`). Como `regenerate = true` rota el token en cada petición, resolverlo en cada `fetch()` garantiza tokens vigentes incluso ante reintentos (§61).
* Comportamiento ante rechazo CSRF: en producción y petición no-AJAX → redirección hacia atrás con mensaje de error; en peticiones AJAX/API → 403 JSON `CSRF_INVALID` (§52.7, §61). Un rechazo no renueva el token: el vigente sigue siendo válido para reintentar.
* **Ajuste Fase D.5:** el alias global `csrf` apunta a `App\Filters\CsrfApi`, que extiende el filtro del framework y solo cambia el resultado de las peticiones AJAX/API: en lugar de dejar propagating `SecurityException` (que el manejador de excepciones convertía en **500**, indistinguible de una caída del servidor y tratada por el cliente como fallo transitorio) responde **403 JSON `CSRF_INVALID`**. Las peticiones HTML mantienen exactamente el comportamiento anterior. El token vigente lo aporta el layout autenticado con `csrf_meta()` (§52.7).

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

*Nota de E.3 (§62):* ese bloque ya no es "las inspecciones de la obra", sino **la cola de
sincronización**: muestra lo pendiente de enviar y se vacía a medida que se sincroniza. En E.3 se
renombró a "Sincronización" y se añadió una acción independiente "Inspecciones" (`.io-consulta`)
para el futuro histórico del servidor. Los identificadores y clases del bloque (`#inspeccionesLocales`,
`.io-locales*`) se conservan a propósito: son el contrato DOM del JS de la cola.

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

## 55. Fase D.3 implementada — sincronización servidor de inspecciones

## 55.1 Alcance de D.3

Se implementa la **alta confirmada** de inspecciones capturadas offline en el servidor:

* endpoint `POST /inspector/sincronizar/inspecciones` (grupo `inspector`, filtros
  `auth` + `role:INSPECTOR` + CSRF global);
* idempotencia por `uuid` de inspección (F.2, F.5);
* autorización **histórica** del inspector en la fecha de la inspección (§52.4);
* validación del payload (uuid RFC 4122, obra existente, fecha/hora, observación);
* trazabilidad de cada alta en `operaciones_sincronizacion` (§52.16);
* botón manual "Sincronizar" en la vista de obra y estados locales actualizados
  (`SINCRONIZADA` / `ERROR`), según §52.15.

El estado actual de la obra **no** se reevalúa al sincronizar: aplica únicamente cuando se
captura una inspección al momento (F.7 / §52.5). La sincronización revalida la asignación
histórica vigente en la fecha de la inspección.

## 55.2 Contrato del endpoint

`POST /inspector/sincronizar/inspecciones`

Body (JSON, CSRF por cabecera `X-CSRF-TOKEN` y detección AJAX/`Accept: application/json`):

```json
{
  "inspecciones": [
    {
      "uuid": "…",
      "obra_id": 123,
      "fecha_inspeccion": "2026-03-15",
      "hora_inspeccion": "10:30:00",
      "observacion": "…"
    }
  ]
}
```

* `uuid` (obligatorio): UUID v4/v5 RFC 4122 de la inspección local.
* `obra_id` (obligatorio): id numérico de la obra.
* `fecha_inspeccion` (obligatorio): fecha real de la inspección en `YYYY-MM-DD`.
* `hora_inspeccion` (opcional): `HH:MM:SS` (o `null`).
* `observacion` (opcional): texto de hasta 5000 caracteres (o `null`).
* `inspector_id` **no se envía ni se acepta**: la identidad del inspector proviene
  exclusivamente de la sesión.

## 55.3 Respuestas

**200 OK** — lote procesado ítem por ítem (un rechazo no aborta el resto):

```json
{
  "ok": true,
  "results": [ … ],
  "resumen": {
    "procesadas": 1,
    "sincronizadas": 1,
    "ya_sincronizadas": 0,
    "rechazadas": 0,
    "errores": 0
  }
}
```

**422** — contrato global inválido (`inspecciones` no es un array):

```json
{ "ok": false, "error": "VALIDATION_ERROR", "details": { } }
```

**401 / 403** — sesión inválida / rol insuficiente (JSON de `AuthFilter`/`RoleFilter`).

**403 `CSRF_INVALID`** — token CSRF ausente o vencido en una petición API (JSON de
`App\Filters\CsrfApi`, §52.7). El cliente lo trata como rechazo de sesión: no consume
intentos, no toca entidades y pide recargar la página.

### Resultados por ítem

| `estado` | `error` | Significado |
| --- | --- | --- |
| `SYNCED` | — | Inspección confirmada; incluye `id` (servidor) |
| `ALREADY_SYNCED` | — | El `uuid` ya existía; incluye `id` (no duplica) |
| `REJECTED` | `INVALID_UUID` | `uuid` ausente o no es RFC 4122 |
| `REJECTED` | `OBRA_NOT_FOUND` | La obra no existe |
| `REJECTED` | `HISTORICAL_AUTHORIZATION_FAILED` | El inspector no estuvo asignado a la obra en la fecha indicada |
| `REJECTED` | `VALIDATION_ERROR` | Fecha/hora inválida u observación excesiva (con `mensaje`) |
| `ERROR` | `SERVER_ERROR` | Fallo inesperado (se registra y se loguea) |

Los ítems `REJECTED`/`ERROR` se devuelven en el `results` del 200: el lote se procesa
totalmente y el cliente decide el estado local de cada inspección.

## 55.4 Reglas de negocio implementadas

* **Identidad de sesión:** `inspector_id` se toma de `session('user_id')`; cualquier
  `inspector_id` enviado en el payload se ignora.
* **Idempotencia:** `InspeccionModel::findByUuid()`; si el `uuid` ya existe, se responde
  `ALREADY_SYNCED` con el id existente y no se inserta ni se registra operación.
* **Autorización histórica:** `InspectoresObrasModel::fueVigente($obraId, $usuarioId, $fecha)`
  — asignación vigente en la fecha de la inspección con límites **inclusivos**
  (`fecha_inicio <= fecha` y `fecha_fin IS NULL OR fecha_fin >= fecha`).
* **Sin reevaluación del estado actual** de la obra al sincronizar (§52.5).
* **Fecha del dispositivo:** la fecha de inspección se valida a nivel de formato (verdadero
  `YYYY-MM-DD`), no contra el reloj del servidor; la incoherencia de horario/reloj queda
  cubierta por los checks manuales (§54.10).

## 55.5 Trazabilidad

Cada `SYNCED` registra en `operaciones_sincronizacion`:

* `tipo_operacion`: paquete de sincronización; `entidad`: `inspecciones`;
* `registro_id`: el **uuid** de la inspección (§52.16);
* `estado`: `PROCESADA`; los rechazos permanentes y excepciones registran `ERROR` (con `error`).

`ALREADY_SYNCED` no genera una nueva operación. El registro es de mejor esfuerzo: si la
escritura de trazabilidad falla, se loguea sin abortar la confirmación.

## 55.6 Componente de sincronización en el cliente

* `public/assets/js/components/sincronizacion.js` — `SIGOA.sincronizacion.sincronizarInspecciones(obraId?)`:
  obtiene las inspecciones `PENDIENTE_SYNC` de la obra (o todas), envía el lote con
  `X-CSRF-TOKEN`/`X-Requested-With`, actualiza cada registro por su `uuid`
  (respetando `estado_local`/`servidor_id`/`error_local`) y devuelve el resumen.
* Estados locales gestionados: `PENDIENTE_SYNC` → `SINCRONIZADA` (con `servidor_id`) o
  `ERROR` (con `error_local`; los datos se conservan); un error de red
  (`{ error: "RED" }`) no modifica los datos locales; un 401 devuelve `authRequerida: true`
  sin tocar IndexedDB.
* `app/Views/inspector/obra.php` — botón "Sincronizar" (`bi-arrow-repeat`) y área de alerta
  en la barra de inspecciones locales; el componente se carga antes que
  `obra-inspecciones.js`.
* `public/assets/js/pages/obra-inspecciones.js` — badges `SINCRONIZADA`/`ERROR` y aviso de
  red; la página **no** dispara `fetch()` (delegado al componente).

> Actualizado en D.4 (§56): el botón de esta vista ejecuta ahora el ciclo completo
> (`sincronizarTodo({ obraId })`) y el componente se carga globalmente desde el layout.

## 55.7 Service Worker y app shell

`public/sw.js` pasa a `sigoa-shell-v3` y precachea
`/assets/js/components/sincronizacion.js`. No se cachean páginas autenticadas ni respuestas
con cookie de sesión (sin cambios respecto de §53.3).

## 55.8 Cliente — decisión de sincronización manual

La sincronización en D.3 es **manual** (botón en la vista de obra), ejecutable en cualquier
momento: al abrir la obra o tras recuperar conectividad. Quedan para D.4 la cola local
`operaciones`, el disparo automático (eventos `online`/visibilidad), reintentos con backoff,
Background Sync y la sincronización de fotografías.

> D.4 (§56) implementó la cola local, el disparo automático y la sincronización de
> fotografías. El botón de la vista de obra se conserva como disparador manual explícito.

## 55.9 Deliberadamente fuera de D.3

* fotografías (almacenamiento multipart, validación `finfo`, thumbnails) — §52.15/§52.17;
* snapshot de obras offline (§52.11);
* cola local y reintentos automáticos (§52.13);
* sincronización automática al recuperar conectividad (evento `online`) y Background Sync;
* edición/borrado de inspecciones desde el servidor;
* migraciones de estructura de base de datos (no se requirieron cambios: `inspecciones.uuid`
  ya existe por Fase C).

## 55.10 Pruebas

Suite completa en verde: **152 tests / 461 assertions** (incremento **+39 tests / +127
assertions** sobre el cierre de Fase D.2).

Nuevos/actualizados:

* `tests/database/SincronizarInspeccionesTest.php` — endpoint: 401/403/CSRF; alta con
  asignación vigente; `inspector_id` del payload ignorado; idempotencia (mismo `uuid` →
  `ALREADY_SYNCED` con el mismo id); autorización histórica (inicio/fin, reasignación, sin
  relación, estado actual de la obra no condiciona); obra inexistente, `uuid` inválido;
  fecha/hora/observación inválidas; lote vacío y mixto; trazabilidad `PROCESADA`.
* `tests/database/InspectoresObrasModelTest.php` — límites inclusivos de `fueVigente`
  (igual al inicio, igual al fin, abierta, anterior/posterior, otro inspector, reasignación).
* `tests/unit/SincronizacionEstructuraTest.php` — controlador, ruta POST, modelos, botón y
  carga de componentes en la vista, `sincronizacion.js` como único origen de `fetch()`,
  precache v3 en `sw.js`.
* `tests/unit/InfraestructuraOfflineTest.php` — actualizado a `sigoa-shell-v3` y al
  componente `sincronizacion.js`.

Notas de scope:

* El flujo de red completo (IndexedDB → JSON → alta → actualización local) se valida con el
  checklist manual en navegador; los tests cubren el endpoint y la estructura del cliente.
* La suite se corre con conexión `tests` (SQLite en memoria compartida): el esquema de
  `operaciones_sincronizacion` para tests se declara con `CREATE TABLE IF NOT EXISTS`,
  coherente con la migración de producción.

---

## 56. Fase D.4 implementada — cola de sincronización y fotografías

## 56.1 Alcance de D.4

Se implementa la **sincronización completa** de la captura offline: inspecciones y fotografías,
con cola local persistente, reintentos con backoff y almacenamiento físico definitivo en el
servidor.

* endpoint `POST /inspector/sincronizar/fotografias` (grupo `inspector`, filtros `auth` +
  `role:INSPECTOR` + CSRF global), un archivo por petición;
* cola local `operaciones` con estados, dependencia padre/hijo y reintentos (§52.13);
* orden obligatorio inspecciones → fotografías, con liberación de blobs tras la confirmación;
* escritura física en disco de imagen y thumbnail, con rutas relativas en la base de datos
  (§52.8);
* idempotencia por `uuid` de fotografía y **reparación** de archivos perdidos;
* reintento manual desde la vista de obra.

No se requirieron migraciones: `fotografias.uuid`, `ruta_thumbnail`, `tamano_bytes`,
`ancho`/`alto` y `fecha_hora_captura` ya existen (Fase C y `CreateFotografias`).

## 56.2 Contrato del endpoint de fotografías

`POST /inspector/sincronizar/fotografias`

`multipart/form-data` (CSRF por cabecera `X-CSRF-TOKEN` y detección AJAX/`Accept:
application/json`):

| Campo | Obligatorio | Contenido |
| --- | --- | --- |
| `uuid` | sí | UUID v4/v5 RFC 4122 de la fotografía local |
| `inspeccion_uuid` | sí | UUID de la inspección **ya sincronizada** en el servidor |
| `archivo` | sí | JPEG, PNG o WebP, máximo `8388608` bytes |
| `fecha_hora_captura` | no | `YYYY-MM-DD HH:MM:SS` o ISO 8601 |
| `latitud` / `longitud` | no | decimal, `-90..90` / `-180..180` |
| `dispositivo` | no | etiqueta de equipo, hasta 255 caracteres (`VARCHAR(255)`, se recorta con `mb_substr`) |

* El tamaño es el de `$_FILES['archivo']['size']`; el MIME se valida con `finfo` **y** se
  decodifica la imagen para obtener dimensiones reales.
* El nombre de campo lo define el servidor (`archivo`); el nombre de archivo que envía el
  cliente es una etiqueta y no influye en la ruta ni en el nombre físico.

## 56.3 Respuestas

**200 OK** — alta o reenvío idempotente:

```json
{
  "ok": true,
  "results": [{
    "uuid": "…",
    "estado": "SYNCED",
    "id": 99,
    "inspeccion_id": 42,
    "nombre_archivo": "INS-00042-20260315-103000-a1b2c3.jpg",
    "ruta_relativa": "OBR-000001/2026-03-15/10-30-00/IMAGENES/INS-00042-20260315-103000-a1b2c3.jpg",
    "ruta_thumbnail": "OBR-000001/2026-03-15/10-30-00/THUMBNAILS/THB-00042-20260315-103000-d4e5f6.jpg",
    "reparado": false
  }],
  "resumen": {
    "procesadas": 1,
    "sincronizadas": 1,
    "ya_sincronizadas": 0,
    "rechazadas": 0,
    "errores": 0
  }
}
```

* `estado: "ALREADY_SYNCED"` con `reparado: true` cuando la fila existía y los archivos se
  reescribieron desde el contenido reenviado.
* **401** sesión expirada / **403** inspección de otro inspector: no se toca ninguna
  fotografía.
* **422** con `error` estable: `VALIDATION_ERROR`, `UUID_EN_CONFLICTO`,
  `HISTORICAL_AUTHORIZATION_FAILED`, `ARCHIVO_INVALIDO`, `ARCHIVO_CORRUPTO`,
  `ARCHIVO_DEMASIADO_GRANDE`, `MIME_NO_PERMITIDO`, `OBRA_NOT_FOUND`.

## 56.4 Reglas de negocio implementadas

* **Identidad de sesión:** la pertenencia se valida contra
  `inspeccion.inspector_id === session('user_id')`, y **antes** de consultar la idempotencia,
  para no revelar la existencia de fotografías de otra inspección.
* **Autorización histórica:** se reutiliza `fueVigente($obraId, $usuarioId, fecha_inspeccion)`
  sin cambios (§52.4, §55.4).
* **Idempotencia:** `FotografiaModel::findByUuid()`; si el `uuid` pertenece a otra inspección
  se responde `UUID_EN_CONFLICTO` y no se reescribe nada.
* **Reparación:** si la fila existe pero falta el archivo o el thumbnail, se reescriben
  únicamente los archivos de esa misma fila a partir del reenvío, en lugar de responder
  `ALREADY_SYNCED` y ocultar la inconsistencia.
* **Orden de escritura:** validar → carpetas → imagen → thumbnail → base de datos. Si la
  base de datos falla, se borran **solo** los archivos creados por esa misma operación.
* **Trazabilidad:** cada alta o reenvío reparado registra `PROCESADA` en
  `operaciones_sincronizacion` con `entidad: 'fotografias'` y `registro_id` = uuid; los
  rechazos permanentes y fallos de autorización registran `ERROR` con su mensaje.

## 56.5 Almacenamiento físico

`app/Services/FotografiaArchivo.php` + `app/Config/SigoaStorage.php` (raíz declarada en `.env`):

```
OBR-XXXXXX/YYYY-MM-DD/{HH-mm-ss|SIN-HORA}[-{n}]/IMAGENES/INS-XXXXX-{Ymd-His}-{random6}.{ext}
OBR-XXXXXX/YYYY-MM-DD/{HH-mm-ss|SIN-HORA}[-{n}]/THUMBNAILS/THB-XXXXX-{Ymd-His}-{random6}.jpg
```

* El thumbnail se genera en servidor con GD, JPEG y lado mayor acotado a `400 px`.
* `random6` es hexadecimal en minúsculas; el nombre físico se decide **en el servidor**.
* `{HH-mm-ss|SIN-HORA}` es la carpeta de la inspección (§52.8) y `{-n}` solo aparece ante
  colisiones de obra + fecha + hora. La base de datos guarda solo rutas relativas; la resolución a
  ruta absoluta se valida contra la raíz de almacenamiento (`ObraAlmacenamiento::raiz()`).
* La raíz efectiva se declara con `SIGOA_STORAGE_COMPARTIDO`, que admite una ruta o un booleano
  (§59.3.2). En producción es el recurso UNC `//DESKTOP-RJ9VDRF/Compartido KM5/SIGOA` (§59.3.1). Si
  no está definida, las operaciones de archivos no pueden ejecutarse.

## 56.6 Cola local y backoff

`public/assets/js/components/sincronizacion.js` (cargado globalmente en
`app/Views/layouts/auth.php` antes de `app.js`):

* **Estados de entidad:** `PENDIENTE_SYNC` → `SINCRONIZADA` (con `servidor_id`) o `ERROR`
  (con `error_local`); los datos de la entidad nunca se borran.
* **Estados de operación:** `PENDIENTE`, `SINCRONIZANDO` (transitorio), `SINCRONIZADA`,
  `ERROR`.
* **Backoff persistente** en `intentos` + `ultimo_intento`:
  `[0, 5 s, 15 s, 30 s, 60 s, 5 min]`, con un máximo de **6 intentos**; al agotarse la
  operación pasa a `ERROR` y conserva la entidad con sus blobs.
* **Dependencia:** una fotografía solo se habilita si su inspección padre está `SINCRONIZADA`
  y tiene `servidor_id`; si no, queda **bloqueada** sin consumir intentos ni pasar a `ERROR`.
* **Liberación de blobs:** `blob` y `thumbnail` se ponen a `null` en la **misma** escritura
  que marca la fotografía `SINCRONIZADA`, y solo después de la confirmación del servidor.
* **401/403 (incluido `CSRF_INVALID`):** las operaciones vuelven a `PENDIENTE` sin tocar
  ninguna entidad y **sin consumir el intento**: el contador se devuelve a su valor anterior
  y `ultimo_intento` se limpia cuando queda en cero. Un rechazo por sesión no puede agotar la
  cola ni dejarla congelada; el siguiente disparo (reautenticación, recarga, `online`,
  `visibilitychange`) reintenta sin costo. El temporizador diferido solo se programa para
  operaciones que realmente tienen que esperar su backoff, de modo que una operación pausada no
  cancela la planificación de las demás.
* **Reintento manual:** `reintentar(tipo, uuid)` reinicia `intentos`/`ultimo_intento`/`error`
  y devuelve la entidad a `PENDIENTE_SYNC`, sin reconstruirla ni borrarla.
* **Disparadores:** apertura de la aplicación autenticada, evento `online`, retorno al primer
  plano (`visibilitychange`) y `setTimeout` diferido al expirar el backoff más próximo. El
  temporizador es una comodidad: la decisión siempre se recalcula desde los datos persistidos.
* **Sin Background Sync:** no existe en iOS/Safari y no es necesario para el comportamiento
  requerido (§52.20).

### API pública

`SIGOA.sincronizacion`: `iniciar()`, `sincronizarTodo({obraId?, revivirAgotadas?})`,
`sincronizarInspecciones(obraId?)`, `sincronizarFotografias(obraId?)`, `encolar(tipo, uuid,
dependenciaUuid?)`, `obtenerOperaciones()`, `buscarOperacion(operaciones, tipo, uuid)`,
`resumir(operaciones)`, `reintentar(tipo, uuid)`, `requiereReintentoManual(operacion)`,
`diagnostico()`, `MAX_INTENTOS`, `TIPO_INSPECCION`, `TIPO_FOTOGRAFIA`.

`sincronizarTodo()` acepta `revivirAgotadas` desde D.6.2 (§59.3).

## 56.7 Captura y vista de obra

* `public/assets/js/pages/inspeccion-nueva.js` — al guardar, encola la inspección y cada
  fotografía (`SIGOA.sincronizacion.encolar(...)`); la página **no** dispara `fetch()`.
* `public/assets/js/pages/obra-inspecciones.js` — el botón "Sincronizar" ejecuta el ciclo
  completo de la obra (`sincronizarTodo({ obraId, motivo: 'manual', revivirAgotadas: true })`):
  primero inspecciones y después fotografías. Muestra estado por ítem, miniatura local, error y
  botón de reintento, que reencola e intenta el envío de inmediato. Desde D.6.2 la visibilidad
  del botón de reintento la decide `requiereReintentoManual(operacion)` (§59.3).
* `public/assets/css/pages/inspector-obra.css` — `.io-locales-estados`,
  `.io-locales-reintentar` y `.io-nueva` (§59.1).
* El componente de sincronización es el **único** origen de `fetch()`: ni la vista de obra ni
  la de nueva inspección realizan peticiones directas.

## 56.8 Service Worker y app shell

`public/sw.js` precachea `/assets/js/components/sincronizacion.js`. La Fase D.5 modificó ese
componente y `obra-inspecciones.js`, por lo que el app shell pasó a `sigoa-shell-v4` (§57). No
se cachean páginas autenticadas ni respuestas con cookie de sesión (§53.3).

`app.js` encadena la inicialización: conectividad → base local → cola, para no abrir la base
dos veces al arrancar.

## 56.9 Deliberadamente fuera de D.4

* edición/borrado de inspecciones o fotografías desde el servidor (la cola solo resuelve
  altas; el servidor no expone operaciones de actualización);
* limpieza automática de datos locales: nunca se borran registros ni blobs por decisión del
  cliente;
* Background Sync / Service Worker con cola de subida;
* snapshot de obras offline (§52.11);
* migraciones de base de datos.

## 56.10 Pruebas

Suite completa en verde: **185 tests / 607 assertions** (incremento **+33 tests / +146
assertions** sobre el cierre de D.3).

Nuevos/actualizados:

* `tests/database/SincronizarFotografiasTest.php` — endpoint: 401 sin sesión, 403 por
  inspección de otro inspector, CSRF; alta completa con escritura de imagen y thumbnail;
  thumbnail acotado a 400 px; MIME no permitido, archivo corrupto, tamaño máximo y
  ausencia de residuos en disco; metadatos y normalización de coordenadas/fecha;
  idempotencia (`ALREADY_SYNCED`), `UUID_EN_CONFLICTO` y uuid de otra inspección; reparación
  de archivo físico perdido; reenvío sin faltantes que no reescribe; autorización histórica
  fallida y su registro en `operaciones_sincronizacion`.
* `tests/js/sincronizacion.test.js` + `tests/unit/SincronizacionLogicaTest.php` — **ejecutan**
  el componente en Node con un banco de pruebas simulado: tabla de backoff, elegibilidad y
  espera restante, encolado idempotente, recuperación de la cola, orden inspecciones →
  fotografías, fotografías bloqueadas, liberación de blobs, conservación de blobs ante fallo,
  401 sin tocar entidades, rechazo permanente, agotamiento de intentos, reintento manual,
  ausencia de conexión, filtro por obra, códigos no reintentables y rechazo de un resultado
  ajeno. La prueba se salta si Node no está disponible en el entorno.
* `tests/unit/SincronizacionEstructuraTest.php` — endpoint y ruta de fotografías, servicio de
  archivos, carga global del componente, botón de la vista de obra ejecutando el ciclo
  completo y `sincronizacion.js` como único origen de `fetch()`.

Notas de scope:

* La persistencia real de blobs en el navegador y la descarga de la miniatura se validan con
  el checklist manual; el banco de pruebas cubre la lógica de cola con blobs simulados.
* La suite se corre con conexión `tests` (SQLite en memoria compartida): el esquema de
  `fotografias` y `operaciones_sincronizacion` para tests se declara con
  `CREATE TABLE IF NOT EXISTS`, coherente con las migraciones de producción.

---

## 57. Fase D.5 implementada — auditoría y endurecimiento del flujo offline

Auditoría técnica del flujo completo **inspección → fotografía → cola → sincronización →
almacenamiento físico**, sin agregar funcionalidades. Resultado: dos defectos **CRÍTICOS**,
tres **MENORES** y una discrepancia de documentación, todos corregidos o documentados. Sin
migraciones: el esquema de `app/Database/Migrations` no se modificó.

## 57.1 Defectos críticos corregidos

### 57.1.1 Token CSRF vencido producía 500 y congelaba la cola

* **Problema:** el layout autenticado no emitía ningún token CSRF y ninguna vista de inspector
  usaba `csrf_field()`. Con `Config\Security::$expires = 7200` y `csrfProtection = cookie`, la
  cookie de token vencía aunque la sesión siguiera viva (la cookie de sesión se renueva en cada
  petición, la de CSRF no). Al vencerse, el filtro CSRF global —que se ejecuta **antes** del
  filtro `auth` de ruta— lanzaba `SecurityException`, que el manejador de excepciones convertía
  en **HTTP 500** por no ser `HTTPExceptionInterface`. El cliente trata 500 como fallo
  transitorio: consumía los 6 intentos y dejaba la operación en `ERROR`.
* **Agravante:** `marcarOperacionPausada()` devolvía la operación a `PENDIENTE` **conservando**
  el intento ya registrado. Con la sesión vencida, cada disparo (pestaña en primer plano,
  `online`, temporizador) repetía el ciclo: tras 6 rechazos la operación quedaba en `PENDIENTE`
  con `intentos = 6`, es decir **congelada**: `puedeReintentar()` es falso, no se programa
  temporizador, el botón de reintento solo aparece en `ERROR` y reautenticarse no la revive.
* **Solución:** el layout autenticado emite `<?= csrf_meta() ?>`, que renueva la cookie cuando
  venció y expone el token en `<meta name="X-CSRF-TOKEN">`; `App\Filters\CsrfApi` responde
  **403 JSON `CSRF_INVALID`** en peticiones AJAX/API en lugar de un 500, y el cliente trata
  401/403 como rechazo de sesión **sin consumir intentos** (§52.6, §52.7, §56.6). El temporizador
  diferido pasó a programarse solo para operaciones con espera real, para que una operación pausada
  no cancele la planificación de las demás.
* **Corrección posterior (Fase D.6.3, §61):** este arreglo resolví el caso del token *vencido*
  —cuando no hay ningún token válido—, pero paneles el orden de las fuentes y dejó el `<meta>` como
  fuente primaria. Con `regenerate = true` eso solo funcionaba para la **primera** petición de cada
  carga de página: a partir de la segunda, el `<meta>` quedaba obsoleto y la cola recibía 403. El
  token vigente se resuelve ahora en `public/assets/js/components/csrf.js`, con la cookie primero.

### 57.1.2 Fuga de Object URLs en la lista de inspecciones locales

* **Problema:** `obra-inspecciones.js` creaba una Object URL por miniatura en cada
  `renderInspecciones()` y nunca la revocaba. La función se vuelve a ejecutar tras cada ciclo de
  sincronización y cada reintento, con lo que las miniaturas se acumulaban en memoria durante
  toda la sesión de la página.
* **Solución:** se registran las URLs creadas y se revocan antes de cada re-renderizado.

## 57.2 Hallazgos menores

* `inspeccion-nueva.js` conserva la Object URL de cada miniatura durante la vida de la página
  (la lista solo crece, no se re-renderiza). Revisar solo las fotografías ya liberadas; queda
  acotado a la sesión de la página.
* Editar una inspección ya `SINCRONIZADA` desde el dispositivo actualiza el registro local pero
  **no** reencola nada: el servidor no expone operaciones de actualización (§56.9). La pérdida
  es silenciosa y queda documentada como limitación conocida, no corregida: resolverla exigiría
  un endpoint de actualización, es decir funcionalidad nueva.
* `FotografiaArchivo` escribe con `fopen`/`stream_copy_to_stream` en lugar de
  `is_uploaded_file`/`move_uploaded_file`. La validación de tamaño, MIME real y decodificación ya
  cubre el riesgo real; no se modifica en D.5.

## 57.3 Discrepancias de documentación corregidas

* §52.7 describía una "petición previa (GET) que renueva la cookie CSRF" que nunca existió. La
  solución real (token emitido por el layout) está ahora documentada.
* §56.2 declaraba `dispositivo` con 120 caracteres; la columna es `VARCHAR(255)` y el servidor
  recorta con `mb_substr(…, 0, 255)`.
* §52.22 describía el rechazo CSRF en AJAX como `SecurityException`; ahora documenta el 403
  `CSRF_INVALID` de `CsrfApi`.
* §56.6 describía que ante 401/403 se respetaba "el backoff ya consumido"; el comportamiento
  correcto (no consumir intentos) queda documentado.

## 57.4 Pruebas

Suite completa en verde: **188 tests / 621 assertions** (incremento **+3 tests / +14 assertions**
sobre el cierre de D.4) y **89 aserciones JS** (antes 77).

* `tests/database/SincronizarInspeccionesTest.php`,
  `tests/database/SincronizarFotografiasTest.php` — el rechazo CSRF en petición API pasa a
  verificarse como **403 JSON `CSRF_INVALID`** (antes `expectException`), y se agrega el caso de
  **token vencido** (`X-CSRF-TOKEN: token-vencido`), que es el escenario real de producción.
* `tests/js/sincronizacion.test.js` — 401 **no consume intentos**; la cola **no se congela** tras
  siete ciclos consecutivos sin sesión; 403 `CSRF_INVALID` pausa sin tocar entidades; el token de
  la cabecera `X-CSRF-TOKEN` se toma del `<meta>` y no de la cookie.
* `tests/unit/InfraestructuraOfflineTest.php` — el layout autenticado emite `csrf_meta()` y el
  componente lo lee; versión del app shell actualizada a `sigoa-shell-v4`.
* `tests/unit/SincronizacionEstructuraTest.php` — versión del app shell actualizada.

## 57.5 Fuera de D.5

* Sin cambios de esquema ni migraciones.
* Sin refactorización de `FotografiaArchivo`, del almacenamiento físico ni de los endpoints.
* Sin semaphore/lock de sincronización entre pestañas: la doble pestaña es idempotente en el
  servidor por `uuid` y se autcorrige en el reintento; queda como mejora futura.
* Sin soporte de edición de inspecciones sincronizadas (ver 57.2).

---

## 58. Fase D.6.1 implementada — sincronización real y defectos de interfaz

D.6 detectó que la cola del dispositivo fallaba siempre contra el servidor y que la interfaz
informaba el resultado de forma incorrecta. D.6.1 aplica las migraciones pendientes, verifica la
sincronización contra el motor real y corrige los tres defectos de interfaz observados. No se
agrega funcionalidad: no hay migraciones nuevas, ni endpoints, ni estados nuevos.

## 58.1 Causa raíz: las migraciones de D.3/D.4 nunca se habían aplicado

* **Síntoma observado:** diez peticiones `POST /inspector/sincronizar/inspecciones` con HTTP 500
  y `Unknown column 'uuid' in 'where clause'`. El error se producia en
  `InspeccionModel::findByUuid()`, es decir **antes de cualquier `insert`**: ninguna inspección
  llegó a escribirse y el endpoint de fotografías nunca fue invocado, porque el componente ordena
  el ciclo inspecciones → fotografías y aborta en la primera fase.
* **Motivo:** `migrate:status` mostraba exactamente tres migraciones sin aplicar —
  `AddUuidToInspecciones`, `AddUuidToFotografias` y `DropUniqueObraFechaInspeccion`. Los tests
  no lo detectaban porque construyen su propio esquema SQLite (§58.3).
* **Resolución:** `php spark migrate` (batch 23). No se modificó ninguna migración existente ni
  se ejecutó `fresh`, `refresh` o `rollback`.
* **Esquema resultante:** `inspecciones.uuid` y `fotografias.uuid` como `CHAR(36) NOT NULL` con
  índice único (`uq_inspecciones_uuid`, `uq_fotografias_uuid`); el índice único
  `obra_id_fecha_inspeccion` eliminado, de modo que una obra admite varias inspecciones el mismo
  día. Se conservaron los índices simples de `obra_id` e `inspector_id` y las claves foráneas.
* **Confirmación en producción:** la inspección `e4cba23b-…`, que llevaba horas fallando, se
  sincronizó 31 segundos después de aplicar la migración (16:29:23) y quedó registrada en
  `operaciones_sincronizacion` como `PROCESADA`. El log no registra ningún error posterior a las
  16:28:23.

## 58.2 El falso verde de la suite

`tests/database` construye su propio esquema SQLite en memoria, incluidas las columnas `uuid` y
el índice único que D.3 creó por migración. La suite podía estar completa en verde mientras la
base real no tenía nada de eso: la divergencia SQLite/real era invisible para los tests.

* `tests/database/EsquemaBaseAplicacionTest.php` (nuevo): **solo lectura** sobre la base
  configurada para la aplicación. Verifica que no quede ninguna migración de
  `app/Database/Migrations` pendiente, que `uuid` exista, no admita nulos, sea `CHAR(36)` y esté en
  un índice único en ambas tablas, y que el índice `obra_id_fecha_inspeccion` no exista. Con el
  esquema en el estado de D.6, el test falla.
* `tests/database/SincronizarMysqlRealTest.php` (nuevo): crea una base **descartable**
  (`sigoa_d61_descartable`) en el mismo servidor MySQL de la aplicación, ejecuta las
  **migraciones reales** sobre ella y ejercita los endpoints D.3/D.4 de punta a punta: alta de
  inspección con `uuid` persistido y trazabilidad en `operaciones_sincronizacion`, reenvío
  idempotente, dos inspecciones distintas de la misma obra y fecha, alta de fotografía con
  escritura física de imagen y thumbnail en `OBR-XXXXXX/AAAA-MM-DD/HH-mm-ss/{IMAGENES,THUMBNAILS}` y
  reenvío que no duplica ni reescribe. La base real de la aplicación no se toca y la base
  descartable se elimina al terminar.
* Ambos tests llevan el grupo `mysql-real` y se omiten si el entorno no ofrece MySQL/MariaDB con
  permisos para crear bases. Para una vuelta rápida: `phpunit --exclude-group mysql-real`.

## 58.3 El atributo `hidden` no ocultaba nada

Varios elementos marcados con `hidden` en `inspector/inspeccion_nueva.php` se veían siempre, y en
la página de la obra la rejilla de fotografías colapsada (`display: grid`) permanecía visible.
La causa es la cascada: la regla del navegador `[hidden] { display: none }` es de origen *user
agent*, y cualquier `display` de autor la gana aunque tenga la misma especificidad. `.alert`
(`display: flex`), `.field-error` (`display: block`), `.nin-card`, `.nin-procesando`, `.nin-pie` y
`.io-locales-fotos` la anulaban.

* **Solución:** una única regla global en `app.css`, en la sección de reset,
  `[hidden] { display: none !important; }`. Es la única forma de que el atributo vuelva a ser
  funcional frente a reglas de autor, y evita tener que repetir `[hidden]` en cada componente.
* Verificado con un banco de medición en Chrome sin servidor: a 412 px los seis elementos
  ocultos del caso de D.6 pasaron de `display: flex|block|grid` con altura real a `display: none`
  y altura 0.

## 58.4 La interfaz informaba un éxito inexistente

`nivelAlerta()` solo resignaba el aviso a verde cuando no había rechazos ni errores, de modo que
el caso real de D.6 —ciclo sin ningún elemento procesado y con cinco operaciones todavía en
cola— se mostraba como "Sincronización finalizada". Es el peor caso posible: un aviso verde
invita a dejar de intentar.

* **Solución:** `obra-inspecciones.js` distingue los cuatro desenlaces usando el recuento de la
  cola (`resumen.pendientes.total`) y los elementos procesados en el ciclo
  (`sincronizadas` + `yaSincronizadas`):

  | Desenlace | Condición | Aviso |
  | --------- | --------- | ----- |
  | Sin trabajo | `vacio` | `alert-info` |
  | Completo | procesados > 0 y cola vacía | `alert-success` — "Sincronización finalizada" |
  | Parcial | procesados > 0 y cola con pendientes | `alert-warning` — "Sincronización parcial" |
  | Sin avances | procesados = 0 y cola con pendientes | `alert-warning` — "Sincronización sin avances" |

  Rechazos y errores por ítem siguen siendo `alert-warning`; los fallos de sesión, CSRF, permiso
  o red siguen siendo `alert-danger`.

## 58.5 Desbordamiento horizontal de las etiquetas de estado

Con las etiquetas en una sola línea (`white-space: nowrap`), una etiqueta ancha como "Reintento
manual requerido" escapaba de la tarjeta: a 412 px su borde derecho llegaba a 410 px mientras la
tarjeta terminaba en 379 px. Con un viewport de 265 px la página pasaba de 265 a 341 px de ancho
(scroll horizontal) y la causa era exactamente la etiqueta.

* **Solución (CSS, sin tocar la paleta ni la estructura):** `min-width: 0` en la lista, la
  tarjeta de inspección, la rejilla de fotografías y cada foto; `overflow-wrap: anywhere` en el
  pie de foto; `max-width: 100%` y `text-align: left` en los botones de fotografía y reintento; y
  en el bloque `@media (max-width: 580px)` las etiquetas de estado pasan a `white-space: normal`.
* Verificado a 265 px: `scrollWidth` igual al ancho del viewport, sin desbordamiento.

## 58.6 Pruebas

* Suite PHP completa en verde: **202 tests / 725 assertions** (antes de D.6.1: 188 / 621).
  El incremento cubre la prueba real contra MySQL, el guardia de esquema de la base de la
  aplicación y las aserciones estructurales nuevas.
* Pruebas JS: **108 aserciones** (89 de `sincronizacion.test.js` + 19 del nuevo
  `tests/js/obra-inspecciones.test.js`, que ejecuta la página en un entorno simulado y verifica
  los cuatro desenlaces del aviso, incluida la regresión de "Sincronización sin avances").
* Banco de medición de interfaz (fuera de la suite, en `C:\temp\sigoa-d61`): mismas hojas de
  estilo y mismo marcado en Chrome sin servidor, comparando el estado anterior y el actual a
  412 px, 360 px y 280 px de ancho.
* Versión del app shell actualizada a `sigoa-shell-v5`: `app.css`, `inspector-obra.css` y
  `obra-inspecciones.js` están en el precaché del service worker y sin el incremento de
  `CACHE_VERSION` los dispositivos seguirían usando los recursos anteriores.

## 58.7 Fuera de D.6.1

* Sin migraciones nuevas ni modificación de migraciones existentes.
* Sin endpoints, estados de cola, permisos ni mecanismos nuevos.
* Sin reorganización de la pantalla "Nueva inspección" ni unificación de sus avisos con el de la
  obra: se corrigió el atributo `hidden` y el aviso engañoso de la página de obra, que son los
  defectos observados.
* Sin Background Sync ni semáforo/lock entre pestañas (ya fuera de alcance en D.5).
* La inspección `5bda4d36-…`, también fallida en D.6, no volvió a enviarse desde el dispositivo
  después de la migración: su estado local no es observable desde el servidor y queda pendiente de
  comprobación en el dispositivo.

## 59. Fase D.6.2 — pantalla de obra, cola que se quedaba congelada y raíz de almacenamiento

Fase de diagnóstico y corrección sobre tres defectos observados en la prueba manual con un
dispositivo real: el orden de la pantalla de obra, una inspección que nunca llegaba al servidor y
los archivos físicos acabando en `C:\Compartida\SIGOA` en lugar del recurso compartido.

### 59.1 Orden de la pantalla de obra

El orden pedido era volver a "Mis obras", datos de la obra, acción de nueva inspección con su
explicación, y por último las inspecciones locales. La acción era una tarjeta más dentro de la
sección de inspecciones locales, de modo que quedaba enterrada entre fotos y estados.

* `app/Views/inspector/obra.php`: el botón "Nueva inspección" y su texto explicativo salen de
  `.io-locales` y pasan a su propia sección `.io-nueva`, colocada entre los datos de la obra y las
  inspecciones locales. Los avisos de estado se mantienen donde estaban, sobre los datos de la
  obra, porque describen el resultado del ciclo de sincronización de esa pantalla.
* `public/assets/css/pages/inspector-obra.css`: `.io-nueva` ocupa el ancho completo en móvil y se
  ajusta al contenido en escritorio, con el botón y la explicación alineados en línea a partir del
  punto de corte ya existente. Sin colores, iconos ni tipografías nuevos: reutiliza `.io-boton` y
  los tokens vigentes.

*Actualización de E.3 (§62):* el orden de esa pantalla pasó a ser volver, datos de la obra, alta
(.io-nueva, condicional), acción "Inspecciones" (.io-consulta, siempre visible) y, por último, el
bloque de sincronización.

### 59.2 La causa de la inspección que no llegaba: la cola se agotaba en silencio

El hallazgo central de la fase. La operación no estaba "pendiente de red": estaba **agotada**.

* `MAX_INTENTOS` es 6 (`[0, 5 s, 15 s, 30 s, 60 s, 5 min]`, §56.6). Al consumirse, la operación
  deja de ser elegible para la cola automática.
* `programarDiferido()` solo programa un temporizador para operaciones con espera pendiente. Una
  operación agotada tiene `esperaRestante() === 0` porque su `ultimo_intento` ya no avanza, así que
  **tampoco se programa**. Los disparadores (`online`, `visibilitychange`, apertura) la recolocaban
  en elegible y fallaban de nuevo, consumiendo el intento número 7, 8, 9… hasta que la.app
  quedaba en un estado inconsistente.
* El botón de reintento manual solo se mostraba con `estado === 'ERROR'`. Una operación que quedó
  en `SINCRONIZANDO` —porque la aplicación se cerró o la pestaña fue descartada mientras el sexto
  intento estaba en vuelo— se quedaba **sin ninguna salida manual**: la etiqueta decía
  "Sincronizando…" y no había botón.
* El botón "Sincronizar" tampoco ayudaba: ejecutaba el ciclo sin tocar los contadores, y una
  operación agotada quedaba fuera de `operacionesElegibles()`.

Por eso el síntoma era una inspección "pendiente" permanente que ningún botón podía desbloquear.

**Corrección** (`public/assets/js/components/sincronizacion.js`):

* `estaAgotada(operacion)` y `requiereReintentoManual(operacion)` concentran la decisión, en lugar
  de repetir la condición en la vista.
* `revivirOperacion()` / `revivirEntidad()` / `obraDeOperacion()` devuelven a `PENDIENTE` una
  operación agotada y su entidad, conservando los datos. `reintentar()` reutiliza esos helpers.
* `sincronizarTodo({ revivirAgotadas: true })` reactiva las agotadas **antes** de calcular la cola
  y devuelve `resumen.revividas`. Es opt-in: la cola automática conserva su comportamiento, porque
  reactivar sin que nadie lo pida equivaldría a ignorar el backoff.
* El botón "Sincronizar" de la obra lo pide siempre, y el aviso informa de cuántas se reactivaron.
* `diagnostico()` expone el estado de la cola local **en solo lectura** para poder clasificar el
  caso desde el dispositivo sin escribir nada.

**Idempotencia.** Reactivar no duplica: el servidor responde `ALREADY_SYNCED` y el ciclo lo trata
como éxito lógico (`app/Controllers/Inspector/Sincronizar.php` incluye el `id` y las rutas en ese
caso), de modo que la entidad queda `SINCRONIZADA` con su `servidor_id` y la fotografía dependiente
puede continuar. Cubierto por `pruebaIdempotenciaTrasRevivir`.

### 59.3 Contrato de la raíz física de almacenamiento

El diagnóstico de por qué los archivos acababan en `C:\Compartida\SIGOA` no fue el código de
carpetas, que es correcto (§56.5), sino la propia configuración: `.env` traía
`SIGOA_STORAGE_PATH = C:\Compartida\SIGOA`, un volumen **local del servidor**, no el recurso
compartido.

* **Por qué una letra de unidad no sirve en producción.** `Z:\…` es un alias que Windows crea en
  la sesión del usuario interactivo. El proceso que ejecuta Apache corre bajo otra cuenta —el
  servicio `httpd` o el usuario de "Iniciar sesión como"— y ese proceso no ve las unidades
  mapeadas por otro usuario: la ruta resuelve como inexistente. La ruta UNC
  (`\\servidor\recurso\SIGOA`) es la misma para todos los equipos y para cualquier proceso.
* `app/Config/SigoaStorage.php` incorpora la variable `SIGOA_STORAGE_COMPARTIDO` para **declarar la
  intención** del despliegue: en producción la raíz debe ser UNC. El valor no se deduce de la ruta,
  porque un volumen local también puede compartirse después por otros equipos y seguir siendo la
  letra equivocada. Añade `esRutaDeRed()`, `esUnidadDeDisco()`, `esAbsoluta()` y
  `problemasDeContrato()`.
* `env` documenta las dos grafías válidas y la regla de comillas. Se verificó contra el lector real
  de CodeIgniter: dentro de comillas **colapsa las barras inversas dobles**, así que
  `'\\\\SERVIDOR\\Compartido KM5\\SIGOA'` es la forma correcta con barras inversas y
  `'//SERVIDOR/Compartido KM5/SIGOA'` la correcta con barras normales. Sin comillas, un nombre de
  recurso con espacios hace fallar el arranque con `InvalidArgumentException`.
* `tests/unit/AlmacenamientoCompartidoTest.php` fija el contrato: `C:\…`, `Z:\…`, `Y:\…` y rutas
  relativas son inválidas en producción; la UNC y las dos grafías del `.env` son válidas. También
  comprueba que la plantilla versionada no fije una letra de unidad ni el nombre de un equipo.

#### 59.3.1 Configuración definitiva del recurso UNC

Con la infraestructura verificada, la raíz quedó declarada en el `.env` real:

```
SIGOA_STORAGE_COMPARTIDO = '//DESKTOP-RJ9VDRF/Compartido KM5/SIGOA'
```

* **Recurso y servidor.** Los archivos se publican en `DESKTOP-RJ9VDRF`, recurso SMB
  `\\DESKTOP-RJ9VDRF\Compartido KM5`. El servidor que corre Apache es `DESKTOP-LI5MEHE`; por eso
  la raíz es una ruta UNC y no una carpeta local.
* **Cuenta de servicio.** Apache (`wampapache64`) se ejecuta como `DESKTOP-LI5MEHE\SIGOA_APACHE`,
  que existe en ambos equipos y tiene permisos de lectura, escritura y borrado sobre el recurso.
  Los permisos SMB y NTFS quedaron verificados y no se modificaron.
* **Sin credenciales en SIGOA.** No hay usuario ni contraseña en `.env` ni en el código, y no deben
  añadirse: la identidad se resuelve a nivel de sistema con la cuenta de servicio. Guardarlas en
  `.env` expondría un secreto en un archivo que se copia entre equipos y no se versiona (§10). La
  plantilla `env` lo advierte, y `AlmacenamientoCompartidoTest` verifica que no aparezcan.
* **Por qué no `Z:` ni `Y:`.** Son alias que Windows crea en la sesión del usuario interactivo.
  Apache corre como `SIGOA_APACHE`, no como ese usuario, y no ve las unidades mapeadas por otro: la
  ruta resolvería como inexistente. La ruta UNC es la misma para todos los equipos y para cualquier
  proceso del servidor. Por eso `esUnidadDeDisco()` marca `C:\…`, `Z:\…` y `Y:\…` como
  inválidas cuando el despliegue está declarado como compartido.

#### 59.3.2 Cómo se declara la raíz

`SIGOA_STORAGE_COMPARTIDO` acepta las dos formas, y distinguir una de otra es un problema real: un
indicador (`1`, `true`, `si`) no lleva separadores, y una ruta absoluta o UNC siempre los lleva.

| Declaración | Raíz efectiva | Compartido |
|---|---|---|
| `SIGOA_STORAGE_COMPARTIDO = '//EQUIPO/RECURSO/SIGOA'` | esa ruta | sí, implícito |
| `SIGOA_STORAGE_COMPARTIDO = 0` + `SIGOA_STORAGE_PATH = C:\ruta` | `SIGOA_STORAGE_PATH` | no |
| `SIGOA_STORAGE_COMPARTIDO = 1` + `SIGOA_STORAGE_PATH = '//EQUIPO/RECURSO/SIGOA'` | `SIGOA_STORAGE_PATH` | sí |

* Una ruta en `SIGOA_STORAGE_COMPARTIDO` **anula** `SIGOA_STORAGE_PATH`. La precedencia es
  deliberada: si las dos variables existieran con valores distintos, gana la que declara el
  despliegue, para que una configuración antigua no sobreviva en silencio y siga recibiendo las
  escrituras.
* Declarar una ruta implica `compartido = true`. Es redundante a propósito: preguntar por separado
  "qué ruta" y "si es compartida" admite combinaciones imposibles, como una UNC con
  `compartido = 0`.
* Se conserva `SIGOA_STORAGE_PATH` para el desarrollo local y por compatibilidad. No se usa en
  producción.

#### 59.3.3 Estructura física y rutas relativas

Cambiar la raíz no obliga a migrar datos. La base guarda **solo referencias relativas**, y
`ObraAlmacenamiento` las combina con la raíz:

```
OBR-000001/2026-09-25/18-04-47/IMAGENES/INS-00001-20260925-180447-d3b819.jpg
OBR-000001/2026-09-25/18-04-47/THUMBNAILS/THB-00001-20260925-180447-76eeee.jpg
```

`rutaRelativaImagen()` y `rutaRelativaThumbnail()` no dependen de la raíz: siguen devolviendo esas
cadenas, y `absolutoDesdeRelativa()` es quien las une a la raíz configurada. Ninguna fila de la
base depende de dónde esté la raíz, ni del esquema de carpetas: la lectura se resuelve siempre a
partir de la ruta relativa almacenada, de modo que las fotografías ya escritas conservan su
ubicación aunque la carpeta de nuevas inspecciones siga otro esquema (§52.8).

**Pendiente de decidir: los archivos existentes no están en el recurso.** La fotografía real está
en `C:\Compartida\SIGOA`, y la carpeta `SIGOA` del recurso compartido se verificó **vacía**. Con la
nueva raíz, esa fotografía no se resuelve hasta que el árbol se copie al recurso. No se movió nada:
es una decisión de despliegue que requiere autorización explícita (§3).

### 59.4 Auditoría de las pruebas: ninguna toca la base ni los archivos reales

Requisito de D.6.2 y de §10 (seguridad): ninguna prueba puede escribir en la base `sigoa` ni en el
recurso compartido.

* `tests/database/SincronizarMysqlRealTest.php` es la única prueba que escribe en MySQL de verdad.
  Crea y destruye **únicamente** la base `sigoa_d61_descartable`, redirige el grupo `tests` a esa
  base y sustituye la raíz de almacenamiento por un directorio temporal propio. No usa `sigoa`.
* Bajo PHPUnit, `ENVIRONMENT === 'testing'` hace que `db_connect()` use el grupo `tests`, no
  `default`. El resto de `tests/database/` opera sobre SQLite en memoria.
* `tests/database/EsquemaBaseAplicacionTest.php` es de solo lectura sobre el esquema real, y las
  pruebas que crean la estructura física usan raíces temporales (`InspectorObrasStorageTest`,
  `ObraAlmacenamientoTest`, `SincronizarFotografiasTest`). `tests/database/InspeccionCarpetaFisicaTest.php`
  no toca el sistema de archivos: solo compone nombres de carpeta contra SQLite en memoria.
* No se ejecutó ninguna migración, ni `migrate:fresh`, ni `migrate:refresh`, ni rollback, ni
  `RepairDatabaseStructure`.

Comprobado sobre el entorno real, en solo lectura, después de ejecutar la suite:

* `sigoa_d61_descartable` **no existe** tras la prueba: la base descartable se creó y se eliminó
  sola. Las bases presentes son `sigoa`, `information_schema`, `mysql`, `performance_schema` y
  `sys`.
* La base `sigoa` conserva su contenido: 1 obra, 3 usuarios, 1 inspección, 1 fotografía y 2
  operaciones en `operaciones_sincronizacion`, ambas `PROCESADA`.
* La inspección `5bda4d36-…` **no tiene fila** en `operaciones_sincronizacion`. Confirma que nunca
  llegó al servidor: el fallo estaba en el dispositivo, no en el rechazo de un endpoint.
* La fotografía real está íntegra y coincide con la base: `OBR-000001/2026-09-25/
  e4cba23b-199a-…/IMAGENES/INS-00001-20260925-180447-d3b819.jpg` (392 322 bytes, 1920×2560) y su
  `THUMBNAILS/THB-00001-20260925-180447-76eeee.jpg` (19 790 bytes) están en disco con los tamaños
  exactos que declara la tabla, y `OBR-000001` contiene esos dos archivos y ningún otro. **No hay
  nada que migrar**: la estructura y las rutas relativas son correctas, lo único incorrecto es la
  raíz.

### 59.5 Pruebas

* Pruebas JS: **159 aserciones** (140 de `sincronizacion.test.js` + 19 de
  `obra-inspecciones.test.js`). Las nuevas de D.6.2 cubren: la cola automática **no** reintenta una
  operación agotada, "Sincronizar" sí la revive y completa el ciclo con su fotografía dependiente,
  la reactivación no toca la cola de otra obra, la idempotencia con `ALREADY_SYNCED`, la
  visibilidad del botón de reintento y que `diagnostico()` no escribe nada.
* Pruebas PHP: `tests/unit/AlmacenamientoCompartidoTest.php` (**29 tests / 51 assertions**), que
  cubren la doble semántica de `SIGOA_STORAGE_COMPARTIDO` (ruta o booleano), la precedencia sobre
  `SIGOA_STORAGE_PATH`, el rechazo de `C:\…`/`Z:\…`/`Y:\…` y rutas relativas en producción, la
  validity de la ruta UNC real del despliegue, las dos grafías válidas del `.env` leídas contra el
  lector real, y que la plantilla no fije una unidad ni pida credenciales. Suite `tests/unit`:
  **112 tests / 399 assertions**. Ninguna prueba toca el recurso de red: `ObraAlmacenamiento` recibe
  su raíz por constructor o por inyección de configuración, y las que crean estructura usan
  directorios temporales.
 Suite `tests/database`, archivo por archivo:
  `EsquemaBaseAplicacionTest` (3/10), `ExampleDatabaseTest` (2/3), `InspeccionFotografiaUuidTest`
  (16/36), `InspeccionNuevaAutorizacionTest` (6/14), `InspectorObrasAutorizacionTest` (1/3),
  `InspectorObrasStorageTest` (4/13), `InspectoresObrasModelTest` (13/21), `MisDatosCsrfTest` (7/15),
  `RepresentantesTecnicosModelTest` (8/19), `SincronizarFotografiasTest` (26/90) y
  `SincronizarInspeccionesTest` (24/72), todas en verde.
* `SincronizarMysqlRealTest` contra el motor real: **8 tests / 83 assertions** en verde, en 4 min
  23 s (ejecuta las 29 migraciones reales sobre la base descartable). Se verificó después que la
  base descartable se eliminó (§59.4). Conviene excluirla con `--exclude-group mysql-real` en las
  vueltas rápidas: es la que domina el tiempo de la suite.
* Queda un error **preexistente** en `tests/unit`:
  `ApiSeguridadBaseTest::testInspectorConRolCorrectoAtraviesaElFiltroDeRol`, por
  `no such table: db_inspectores_obras` — falta esa tabla en la base SQLite en memoria del entorno
  de pruebas. Se comprobó con `git stash` que ya fallaba antes de esta fase y no está relacionado
  con ella. Es además **dependiente del orden**: la tabla existe una vez que otra prueba la creó en
  el mismo proceso, así que `tests/unit` + un archivo de `tests/database` pasa en verde y `tests/unit`
  solo falla. Es un defecto de aislamiento de pruebas, no de almacenamiento. No se corrigió por
  estar fuera del alcance de D.6.2.
* Dos pruebas de estructura dejaron de fijar `sigoa-shell-v5`: `InfraestructuraOfflineTest` ahora
  comprueba la **forma** de `CACHE_VERSION` y que `SHELL_CACHE` avance con ella, y
  `SincronizacionEstructuraTest` comprueba el comportamiento de `revivirAgotadas`. Fijar el número
  obligaba a editar las pruebas en cada despliegue, que es justo lo que contradice la regla
  documentada en `sw.js`.
* App shell a `sigoa-shell-v6`: `sincronizacion.js`, `obra-inspecciones.js` e
  `inspector-obra.css` están en el precaché y sin el incremento los dispositivos seguirían con los
  recursos anteriores.

### 59.6 Comprobación manual en el dispositivo

Procedimiento para cerrar los dos puntos que no se pueden verificar desde el servidor. Requiere
publicar `CACHE_VERSION = 'sigoa-shell-v6'` y recargar una vez con la aplicación abierta.

1. **Confirmar la raíz antes de nada.** En el servidor, con las credenciales del recurso: la raíz
   debe ser UNC y tener escritura para la cuenta de Apache. Si no se corrige, la sincronización
   escribirá donde siempre y el resultado será idéntico.
2. **Clasificar el caso antes de tocar nada.** En el navegador del teléfono, con la obra abierta:

   ```js
   SIGOA.sincronizacion.diagnostico()
   ```

   Es de solo lectura. Anotar, para cada operación `agotada`, `elegible`,
   `requiere_reintento_manual` y `estado`, además de `inspecciones[].estado_local` y
   `fotografias[].tiene_blob`. Es el dato que faltaba en D.6 y D.6.1.
3. **Reintentar** con el botón "Sincronizar" de la obra. Debe aparecer un aviso indicando cuántas
   operaciones se reactivaron; si el aviso no las menciona, el recurso sigue en `sigoa-shell-v5` y
   hay que forzar la recarga.
4. **Comprobar el resultado:** en "Nueva inspección" la ficha debe pasar a sincronizada, y en
   `operaciones_sincronizacion` del servidor debe aparecer una fila `PROCESADA` con el `uuid` de la
   inspección y, si llevaba fotos, otra por fotografía.
5. **Repetir "Sincronizar"** para verificar que no duplica: el aviso debe salir como "nada
   pendiente" y no crear filas nuevas.
6. **Orden de la pantalla** (§59.1) a 360 px y a escritorio: volver, datos, nueva inspección con
   su explicación, inspecciones locales.

### 59.7 Fuera de D.6.2

* Sin migraciones nuevas ni modificación de migraciones existentes.
* Sin endpoints, estados de cola, permisos ni mecanismos nuevos. Se amplían los ya existentes.
* Sin cambiar el backoff: `[0, 5 s, 15 s, 30 s, 60 s, 5 min]` y 6 intentos se conservan. El
  agotamiento es una protección contra el fallo permanente, no un defecto.
* Sin migrar ni reescribir los archivos ya existentes bajo `C:\Compartida\SIGOA`. Cambiar la raíz no
  traslada datos: las referencias de la base son relativas y seguirán resolviendo igual, pero solo
  cuando la raíz correcta esté configurada.
* Sin tocar la pantalla "Nueva inspección".
* La clasificación exacta del caso observado en el dispositivo (A–G) sigue pendiente: requiere
  ejecutar `SIGOA.sincronizacion.diagnostico()` en el navegador del teléfono. La corrección cubre
  los dos estados en los que la operación quedaba sin salida (`ERROR` agotada y `SINCRONIZANDO`
  agotada), pero el caso concreto debe confirmarse en el dispositivo.

---

## 60. La cola se drenaba de una en una — drenaje encadenado del ciclo

Defecto observado en el dispositivo después de D.6.2: con varias fotografías capturadas, **solo
se subía una por recarga**. Cada recarga de la página liberaba exactamente la siguiente. No era
un problema de red, de permisos ni del servidor —la fila de `operaciones_sincronizacion` llegaba
bien—, sino que el ciclo no VOLVÍA a mirar la cola después de consumirla.

### 60.1 Causa raíz

Dos defectos en el mismo sitio (`public/assets/js/components/sincronizacion.js`), ambos visibles
solo con fotografías que se capturan mientras las anteriores se están subiendo:

1. **El ciclo trabaja con una foto de la cola.** `sincronizarTodo()` leía las operaciones una vez por
   fase y procesaba esa lista. Lo que se capturara durante el ciclo no entraba en la lista ya
   tomada, así que quedaba `PENDIENTE` sin que nada lo recogiera.
2. **No había ningún continuador.** Al terminar, el ciclo solo llamaba a `programarDiferido()`, que
   por definición solo programa cuando queda una espera pendiente (§56.6). Una operación recién
   encolada tiene `esperaRestante() === 0`, de modo que **no se programaba nada**: la cola quedaba
   congelada hasta el siguiente disparador externo (`online`, `visibilitychange`, apertura, botón
   "Sincronizar"). En el dispositivo, el siguiente disparador era la recarga manual.

A esto se sumaba un tercero, relacionado con el lock: una petición que llegaba mientras había un ciclo en
curso se descartaba con `{ enCurso: true }` sin dejar rastro. Si la petición era un temporizador de
reintento ya consumido, ese reintento se perdía sin más.

Reproducido de forma determinista antes de corregir (nodo simulado, sin red ni servidor): tras
subir `f1`, las capturas `f2` y `f3` quedaban `PENDIENTE` y no existía ningún disparador
programado. Con el componente original, la prueba de regresión `pruebaDrenajeTrasCadaExito` falla
exactamente así: sube `foto-1` y deja dos en la cola.

### 60.2 Corrección

Se mantiene **un único consumidor** de la cola. El drenaje no es un segundo procesador: es el mismo
ciclo pidiendo más trabajo.

* `sincronizarTodo()` queda como entrada pública y aplica los guardas (soporte, conexión, lock).
  Ya no contiene el cuerpo del ciclo.
* `drenar(opciones)` toma el lock una sola vez, ejecuta `ejecutarCiclo()` y decide si abre otra
  vuelta. Lo que llega mientras dura el drenaje —el botón "Sincronizar", `online`,
  `visibilitychange`, la apertura, el temporizador del backoff o una foto capturada en ese
  instante— se acumula en `cicloPendiente` y se ejecuta **como vuelta siguiente**, nunca en
  paralelo. Ningún disparador se pierde y nunca hay dos consumidores de la misma cola.
* `combinarOpciones()` fusiona esa petición con la del ciclo en curso: `revivirAgotadas` se
  acumula, el alcance solo se acota si ambas peticiones son de la misma obra y la fase solo se
  restringe si ambas piden la misma. La combinación es deliberadamente más amplia que cada
  petición por separado, porque su único propósito es que la unión de lo pedido se ejecute.
* `fusionarResumenes()` acumula los contadores de todas las vueltas, de modo que quien lo pidió —la
  vista de obra, el botón "Sincronizar"— recibe el resultado del **drenaje completo**. Sin esto, una
  tanda de fotografías se informaría como "sin cambios" aunque se hubieran enviado todas.
* `puedeSeguirDrenando()` impide continuar cuando el ciclo se detuvo por una condición real: sin
  conexión, sesión caducada (401), permiso denegado (403), token de seguridad inválido o fallo de
  red. En esos casos insistir agotaría los reintentos, que es justo lo que D.6.2 vino a evitar.
* `ejecutarCiclo()` conserva el comportamiento anterior de una vuelta (orden inspecciones →
  fotografías, `asegurarCola()`, revive solo si se pide) y añade dos datos al resumen: `intentos`,
  el número de operaciones que consumieron un intento, y `pendientesElegibles`, cuántas podría
  tomar la cola automática ahora mismo.
* `MAX_VUELTAS_DRENADO = 100` acota las vueltas como red de seguridad. El número real de vueltas
  está acotado por la cola, porque **una vuelta solo continúa si la anterior avanzó**: si consume
  intentos y todavía queda cola elegible.

### 60.3 Invariantes

* **Un solo consumidor.** El lock `enCurso` se toma una vez al entrar y se suelta al terminar. Un
  disparo concurrente recibe `{ enCurso: true }` y su petición se encadena, no se ejecuta en
  paralelo.
* **El drenaje exige progreso.** Continuar exige `intentos > 0` **y** `pendientesElegibles > 0`.
  Una cola con solo fotografías bloqueadas por su padre, con operaciones esperando su backoff o en
  `ERROR` no se pone a girar: espera su propio disparador.
* **Los fallos no detienen el drenaje.** Un fallo transitorio consume un intento (como ya hacía) y
  deja la operación en espera de backoff, pero las fotografías siguientes se envían igual. El
  bloqueo por padre sigue siendo la única dependencia dura.
* **La cola vacía no programa temporizadores.** Sin esperas pendientes, `programarDiferido()`
  sigue sin programar nada: el drenaje no deja temporizadores huérfanos.
* Sin cambios en el backoff `[0, 5 s, 15 s, 30 s, 60 s, 5 min]`, en `MAX_INTENTOS = 6`, en los
  estados de la cola, en los endpoints, en el orden inspecciones → fotografías ni en la liberación
  de blobs (sigue siendo solo tras la confirmación del servidor).

### 60.4 Pruebas

Siete pruebas nuevas en `tests/js/sincronizacion.test.js`, todas sobre el comportamiento y no
sobre la estructura:

* `pruebaVariasFotografiasEnUnCiclo` — tres fotografías pendientes salen con **una sola** llamada a
  `sincronizarTodo()`, en orden, y un ciclo posterior con la cola vacía no repite peticiones.
* `pruebaDrenajeTrasCadaExito` — regresión del defecto real: cada fotografía se captura cuando el
  servidor confirma la anterior, es decir fuera de la foto de la cola que tomó el ciclo. Sin
  recargar, las tres acaban sincronizadas.
* `pruebaDrenajeNoAbreSegundoProcesador` — con la primera subida retenida llegan un disparador y
  otra captura: el disparador devuelve `{ enCurso: true }`, nunca hay dos peticiones simultáneas y
  lo capturado en vuelo se drena al terminar.
* `pruebaFalloNoBloqueaElDrenaje` — la segunda falla con 500: la tercera se envía igual, la fallida
  vuelve a la cola con un intento, no es elegible todavía y el reintento queda programado con la
  espera del backoff, no de inmediato.
* `pruebaColaVaciaNoProgramaTemporizador` — ni peticiones ni temporizadores.
* `pruebaHijoSaleAlConfirmarElPadre` — la fotografía bloqueada se envía en la vuelta que confirma
  al padre, respetando el orden obligatorio.
* `pruebaColaBloqueadaNoGira` — con el padre fallando, la fotografía no consume intentos ni se
  reintenta sola.

Las pruebas de drenaje se envuelven en un tiempo límite para que un eventual bucle infinito falle
en lugar de colgar la suite. Suite JS: **190 aserciones** en `sincronizacion.test.js` y **19
pruebas** en `obra-inspecciones.test.js`, en verde. Se comprobó restaurando temporalmente el
componente anterior (`git checkout`) que las pruebas de drenaje fallan contra él: suben
`foto-1` y dejan el resto pendiente, que es exactamente el defecto reportado.

No se pudo ejecutar PHPUnit en este entorno: no hay PHP disponible en WSL. Las pruebas PHP
afectadas son las estructurales (`SincronizacionEstructuraTest`,
`InfraestructuraOfflineTest`), que solo comprueban la presencia de cadenas y formas en los
archivos —se verificaron por `grep`— y `SincronizacionLogicaTest`, que se limita a ejecutar
`sincronizacion.test.js` con Node. Ninguna toca el backend, la base ni los archivos físicos.

*Corrección posterior (Fase D.6.3, §61):* PHP 8.3.28 sí estaba disponible y la suite completa se
ejecutó en verde. La limitación era del entorno, no del proyecto.

App shell a `sigoa-shell-v7` por el cambio en `sincronizacion.js`. Sin esto, el servicio seguiría
sirviendo el componente viejo y el dispositivo no observaría ningún cambio.

### 60.5 Fuera de alcance

* Sin cambios en el backend, los endpoints, el modelo de datos ni las rutas.
* Sin migraciones nuevas ni modificación de migraciones existentes.
* Sin tocar la raíz física de almacenamiento (§59.3) ni los archivos existentes.
* Sin cambiar la interfaz: no hay mensajes, botones ni estados visuales nuevos.
* Sin un procesador paralelo, sin Background Sync y sin cola en el Service Worker. El Service
  Worker sigue sirviendo el app shell; la cola sigue siendo del componente de sincronización.

---

## 61. Fase D.6.3 — el token CSRF se quedaba obsoleto y frenaba la cola tras la primera petición

§60 arregló el continuador del ciclo, de modo que una tanda de fotografías ya no quedaba
congelada esperando una recarga. Al ejecutarse en el dispositivo, el síntoma se había desplazado
una petición: **la primera fotografía subía y las siguientes seguían dando 403 `CSRF_INVALID`**,
también sin recargar. El defecto ya no estaba en la cola sino en el token.

### 61.1 Causa raíz

`Config\Security` combina dos decisiones que juntas son incompatibles con un `<meta>` estático:

* `csrfProtection = cookie` — la cookie `csrf_cookie_name` **es** el valor contra el que el
  servidor compara.
* `regenerate = true` — cada petición que supera la verificación **genera un token nuevo** y
  reescribe esa cookie.

`csrf_meta()` emite `<meta name="X-CSRF-TOKEN" content="A">` usando el nombre de cabecera
configurado, de modo que el selector era correcto. El problema es de ciclo de vida: el `<meta>` es
una **foto** del token en el momento de renderizar la página, y el navegador no lo actualiza.

Con la resolución que venía de D.5 —«el `<meta>` primero, la cookie de respaldo»— la secuencia
era:

| # | Petición | Token enviado | Cookie tras la respuesta | `<meta>` |
|---|----------|---------------|---------------------------|----------|
| 1 | foto 1 | A (meta) | B | A |
| 2 | foto 2 | A (meta) | B | A |
| 3 | foto 3 | A (meta) | B | A |

La petición 1 comparaba A con A y devolvía 200 **renovando la cookie a B**. Las peticiones 2 y 3
seguían enviando A porque el `<meta>` no había cambiado: el servidor esperaba B, devolvía 403 y la
operación quedaba en `PENDENTE` **sin consumir intentos**. Como el `<meta>` solo se actualiza al
recargar, la única salida era recargar —justo el rodeo que D.5 había dejado instalado: pedir al
usuario que recargara para recuperar un token.

Reproducido de forma determinista antes de corregir (nodo simulado, cookie y `<meta>` mutables
por separado): con el orden defectuoso, la regresión `pruebaVariasFotografiasConRotacionDeToken`
sube una fotografía y deja las otras dos pendientes, con 403 en cada intento posterior.

### 61.2 Corrección

La renovación deja de ser un efecto secundario invisible: **cada respuesta dice con qué token puede
hacerse la siguiente petición.**

* **`App\Filters\CsrfApi::after()`** publica el token vigente en la cabecera
  `X-CSRF-TOKEN` —el mismo nombre que el cliente ya envía, tomado de
  `Security::getHeaderName()`— en las respuestas de peticiones API/AJAX, las mismas que
  `before()` distingue. Se registra en `Config\Filters::$globals['after']`. Las respuestas HTML no
  la llevan: ya llevan el token en la cookie y en el `<meta>` del layout.
* **`public/assets/js/components/csrf.js`** expone `SIGOA.csrf` con dos operaciones: `token()` y
  `actualizar(respuesta)`. No hace peticiones, no guarda nada y no sabe nada de la cola: solo
  resuelve qué token usar ahora mismo.
* **Orden de resolución** en `token()`: **cookie `csrf_cookie_name` → cabecera `X-CSRF-TOKEN` de la
  última respuesta → `<meta name="X-CSRF-TOKEN">`**.
  * *Cookie primero* porque es el valor contra el que el servidor acaba de comparar y el que su
    propia respuesta renueva. Es además el único canal compartido entre pestañas, así que no se
    queda obsoleta si otra pestaña sincroniza antes.
  * *Cabecera después* porque es un canal explícito e independiente de la configuración de cookies
    (seguiría funcionando con `httponly = true` o si el navegador bloquea la escritura) y no
    duplica el token en el DOM ni en el cuerpo JSON.
  * *`<meta>` al final* porque cubre el primer render y el caso de token vencido
    (`Security::$expires = 7200`), donde todavía no hubo ninguna respuesta API que renovar.
* **`sincronizacion.js`** y **`mis-datos.js`** dejan de resolver el token por su cuenta y delegan en
  `SIGOA.csrf`. `actualizar()` se llama **antes de leer el cuerpo y el estado** de cada respuesta,
  porque cabecera y JSON son excluyentes en el mismo objeto `Response`.
* `csrf.js` se carga en el layout autenticado antes que `sincronizacion.js` y `app.js`, y se
  precachea en el app shell.

### 61.3 Invariantes

* **El token se resuelve en cada envío.** Nunca se fija al cargar la página ni se guarda.
* **Nada se persiste.** El valor resuelto vive solo en memoria del componente: no entra en
  IndexedDB, ni en la cola, ni en `localStorage`/`sessionStorage` (§52.6, §52.7). Se pierde al
  recargar, que es lo correcto para un valor rotado.
* **Un rechazo no renueva nada.** En el camino 403, `verify()` lanza antes de regenerar y, además,
  `before()` devuelve directamente la respuesta, por lo que los filtros `after` no llegan a
  ejecutarse. `actualizar()` descarta el valor memorizado y la siguiente resolución vuelve a mirar
  la cookie y el `<meta>`: la pausa de la cola sigue siendo real, sin reintento en bucle y sin
  consumir intentos (§56.6).
* **Sin decisiones de seguridad nuevas.** No se toca `Config\Security`: siguen
  `csrfProtection = cookie`, `regenerate = true`, `tokenRandomize = false` y `expires = 7200`. La
  cabecera solo informa a un cliente **del mismo origen** de un valor que ese mismo cliente ya
  puede leer en su cookie; no habilita por sí mismo ningún ataque ni relaja la protección.
* Sin cambios en los endpoints, los estados de la cola, el backoff `[0, 5 s, 15 s, 30 s, 60 s,
  5 min]`, `MAX_INTENTOS = 6`, el orden inspecciones → fotografías ni la liberación de blobs.
* La cookie CSRF sigue legible por JavaScript (`Config\Cookie::$httponly = false`), como exige el
  esquema double-submit; la de sesión continúa HttpOnly.

### 61.4 Pruebas

`tests/js/csrf.test.js` (nuevo, **16 aserciones**) sobre `SIGOA.csrf` en banco simulado:
resolución por cookie; por `<meta>` cuando no hay cookie legible; cadena vacía sin ninguna fuente;
uso de la cabecera de la respuesta; lectura de la cabecera sin distinguir mayúsculas; descarte del
valor memorizado cuando la respuesta no trae token; respuesta ausente o sin cabeceras sin romper
nada; y que no se escriba en ningún almacenamiento (con `localStorage` y `sessionStorage` que
lanzan si alguien los toca).

En `tests/js/sincronizacion.test.js` el banco pasó a simular la cookie de forma mutable y a dejar
el `<meta>` congelado, de modo que reproduce el ciclo real de rotación. Nuevas regresiones:
tres fotografías seguidas con token renovado en cada respuesta; el token renovado es el que sale en
la petición siguiente; renovación cuando no hay cookie legible y solo llega por cabecera; un token
irrenovable (ni cookie ni cabecera que renueve) que **no** entra en bucle ni consume intentos; y
recarga de página, tras la cual la operación se envía con el token vigente y sin renovar el resto de
la cola. Suite JS: **236 aserciones** en `sincronizacion.test.js`, **16** en `csrf.test.js` y **19
pruebas** en `obra-inspecciones.test.js`, en verde. Se comprobó que las nuevas regresiones fallan
contra el componente con el orden defectuoso.

En PHP, `tests/unit/CsrfProteccionTest.php` (12 pruebas): la respuesta API publica la cabecera con
el token **nuevo** (no con el que envió el cliente), el token publicado permite la petición
siguiente —que a su vez rota—, y un rechazo 403 deja el token vigente intacto. El caso «el HTML no
lleva la cabecera» se comprueba invocando el filtro sobre respuestas limpias, porque el banco de
pruebas comparte el objeto `Response` entre clases (`CIUnitTestCase::$app` es estático) y una
cabecera publicada por otra prueba aparecería como si la hubiera puesto esa.

Estructurales: `SincronizacionEstructuraTest` verifica el registro de `csrf` en
`$globals['after']`, la firma de `CsrfApi::after()`, que `csrf.js` no persiste nada (sobre el
código, sin comentarios, porque el componente documenta la prohibición nombrando esos almacenes),
que `sincronizacion.js` delega y no lee el DOM, el orden de carga en el layout y el precache del
Service Worker. `InfraestructuraOfflineTest` verifica que dentro de `token()` la cookie se resuelve
antes que el `<meta>`, y añade `csrf.js` a los componentes que deben existir.

`SincronizacionLogicaTest` ejecuta ahora los dos guiones de `tests/js/` (cola y token) con Node.

**PHPUnit sí pudo ejecutarse en este entorno**: PHP 8.3.28 (WAMP) sobre el que corre la suite
completa, **242 pruebas y 838 aserciones en verde**. Ninguna prueba toca la base ni los archivos
físicos (§59.4).

App shell a `sigoa-shell-v8` por los cambios en `sincronizacion.js`, `csrf.js` y `mis-datos.js`. Sin
esto el servicio seguiría sirviendo los componentes viejos y el dispositivo no observaría cambio.

*Discrepancia detectada al aplicar este cambio:* `public/sw.js` estaba en `sigoa-shell-v6`, no en
`sigoa-shell-v7` como afirma §60.4. El salto a v8 invalida cualquier caché anterior, así que la
discrepancia no tiene efecto funcional; queda registrada y no se ha tocado el texto de §60, que
describe lo que hizo D.6.2.

### 61.5 Fuera de alcance

* Sin cambios en `Config\Security` ni en ninguna decisión de seguridad preexistente.
* Sin migraciones nuevas ni modificación de migraciones existentes.
* Sin tocar la raíz física de almacenamiento (§59.3) ni los archivos existentes.
* Sin cambios en los endpoints, las rutas, el modelo de datos ni la autorización.
* Sin cambiar la interfaz: no hay mensajes, botones ni estados visuales nuevos.
* Sin Background Sync, sin cola en el Service Worker y sin segundo procesador: el Service Worker
  sigue sirviendo el app shell y la cola sigue siendo del componente de sincronización.
* Sin reintento automático ante un token inválido: la decisión de reintentar es del sincronizador,
  no del componente de token.

---

## 62. Fase E.3 implementada — la cola de sincronización deja de ser el historial de la obra

La pantalla de obra tenía un bloque titulado "Inspecciones guardadas en este dispositivo" que en
realidad era **la cola de sincronización**: elementos pendientes de enviar, que se vacían a medida
que se suben. Ese bloque cumplía dos papeles incompatibles —informar de lo que queda por enviar y
sustituir al historial de la obra—, y leerlo como historial es directamente incorrecto, porque
precisamente lo que ya se sincronizó es lo que desaparece de él.

E.3 separa ambos conceptos sin tocar la sincronización.

### 62.1 La vista de obra: dos bloques, dos responsabilidades

En `app/Views/inspector/obra.php` el orden queda así:

1. volver a "Mis obras";
2. datos de la obra;
3. alta: "Nueva inspección" con su explicación (`.io-nueva`), condicional a `puede_inspeccionar`;
4. **acción "Inspecciones"** (`.io-consulta`), siempre visible;
5. **bloque "Sincronización"** (`#inspeccionesLocales`).

La acción nueva es independiente del estado de la obra: se ofrece incluso cuando no se pueden generar
inspecciones, porque el histórico de una obra finalizada también se consulta. Enlaza a
`/inspector/inspecciones/ver/{obraId}`.

El bloque existente solo cambia en su capa visible: título "Sincronización" (`bi-cloud-arrow-up`),
explicación de que muestra lo pendiente de enviar y un resumen (`#sincronizacionResumen`) que
informa de inspecciones, fotografías y operaciones con error pendientes. **Se conservan los
identificadores y clases** (`#inspeccionesLocales`, `#inspeccionesLocalesLista`, `#btnSincronizar`,
`#sincronizacionEstado`, `.io-locales*`) porque son el contrato DOM del JS de la cola: renombrarlos
habría obligado a reescribir sincronización, que es justo lo que esta fase no toca.

### 62.2 La cola muestra solo lo pendiente

`public/assets/js/pages/obra-inspecciones.js` deja de listar elementos ya sincronizados:

* una inspección `SINCRONIZADA` se oculta, **salvo** que tenga fotografías pendientes: en ese caso se
  conserva y se indica cuántas le quedan, porque si no la operación pendiente quedaría sin ninguna
  representación visible en la cola;
* una fotografía `SINCRONIZADA` se oculta siempre;
* el resumen informa de inspecciones pendientes, fotografías pendientes y operaciones con error;
* sin nada pendiente, la lista muestra "No hay elementos pendientes de sincronización en este
  dispositivo." y la sección de fotografías indica que no hay pendientes.

No se toca la cola como estructura: persistencia, orden de operaciones, reintentos, errores, drenaje
encadenado (§60) y CSRF (§61) siguen siendo los de siempre.

### 62.3 La entrada a las inspecciones: consulta de solo lectura

`app/Controllers/Inspector/Inspecciones.php` incorpora `ver(int $obraId)` y la ruta
`inspecciones/ver/(:num)`, dentro del grupo del inspector y por tanto sujeta a `AuthFilter` y
`role:INSPECTOR` como el resto del área.

Precondiciones:

* obra existente (`ObraModel::findDetalle`);
* **asignación vigente** del inspector (`InspectoresObrasModel::esVigente`).

Lo que **no** se comprueba es `permite_inspeccionar` (§54.2), que sí limita el alta. Esa asimetría es
deliberada: el estado de la obra restringe crear inspecciones, no leerlas.

`InspeccionModel::contarParaObra()` era la única consulta nueva de E.3: contaba las inspecciones de la obra
en el servidor. La vista (`app/Views/inspector/inspecciones.php` +
`public/assets/css/pages/inspector-inspecciones.css`) muestra el contexto de la obra, el estado y el
resultado de la consulta:

* sin inspecciones: "No existen inspecciones aún" con la indicación de que se recomienda generar una
  nueva inspección para comenzar a registrar el seguimiento de la obra;
* con inspecciones: el total ("1 inspección registrada" / "N inspecciones registradas").

Es una entrada, no un historial: no hay navegación por fecha, ni por inspección, ni fotografías. La
página no carga JS de página, no toca IndexedDB y no altera la cola. El total es informativo: no
filtra, no ordena y no pagina, porque eso es trabajo de E.4.

*Actualización de E.4 (§63):* el histórico se implementó y `contarParaObra()` quedó **retirada**: el total
ahora se deriva de los propios datos agrupados que la vista necesita, de modo que no hay segunda
consulta ni dos verdades que puedan discrepar.

### 62.4 El alta sigue siendo independiente

`app/Views/inspector/inspeccion_nueva.php` no cambia: continúa siendo el alta local de una
inspección, sin enlaces al histórico ni listado de inspecciones anteriores de la obra.

### 62.5 Pruebas

JS (`tests/js/obra-inspecciones.test.js`): 32 pruebas, antes 19. Las nuevas cubren que se oculten las
inspecciones sincronizadas, que se conserve la inspección sincronizada con fotografías pendientes, que
se filtren las fotografías ya sincronizadas, el resumen de cola y la cola vacía. Se mantienen los
scripts JS tal cual, con `sincronizacion.test.js` en **236 aserciones** y `csrf.test.js` en **16**.

PHP:

* `tests/unit/InspectorInspeccionesConsultaTest.php` (15 pruebas, 59 aserciones): la ruta, la
  separación de métodos en el controlador, que `ver()` no comprueba el estado de la obra, que la acción
  de consulta está fuera del condicional del alta, que el bloque de sincronización conserva su JS y
  sus llamadas a reintento, la vista, sus estilos, el precache y que sin rol de inspector se redirige.
* `tests/database/InspectorInspeccionesConsultaRenderTest.php` (7 pruebas, 17 aserciones): render real
  con base de datos. Una obra sin inspecciones muestra el estado vacío acordado; con inspecciones no lo
  muestra e informa el total; en obra finalizada la consulta funciona; sin asignación vigente o con
  obra inexistente redirige; y la consulta no escribe inspecciones.

Suite completa: **290 pruebas y 972 aserciones en verde** (antes 268 y 895), excluyendo el grupo
`mysql-real`. App shell a `sigoa-shell-v9` con el CSS nuevo en el precache.

### 62.6 Fuera de alcance

* Sin navegación Obra → Fecha → Inspección → Fotografías: era E.4 y quedó implementada en §63 (la galería
  de fotografías sigue siendo E.5).
* Sin galería, miniaturas ni descarga de fotografías históricas.
* Sin caché ni precarga de inspecciones: el histórico no se guarda en el dispositivo en esta fase.
* Sin cambios de permisos: la consulta no amplía lo que el inspector puede hacer.
* Sin migraciones, sin cambios en la cola, los reintentos ni el CSRF, y sin dependencias nuevas.

### 62.7 Discrepancias

* `#inspeccionesLocales` conserva su nombre aunque el bloque ya se titule "Sincronización": renombrarlo
  exigiría tocar el JS de la cola, fuera de alcance. Es una deuda consciente, no un descuido.
* El histórico real de la obra vive en el servidor; el bloque de la pantalla de obra nunca lo mostró.


## 63. Fase E.4 implementada — el histórico de la obra deja de ser una entrada y se vuelve navegable

E.3 dio la puerta: una acción "Inspecciones" que llegaba a una página con un total. E.4 construye el
recorrido real, **Obra → Inspecciones → Fecha → Inspección**, sin tocar la sincronización, los
permisos ni el esquema.

### 63.1 El listado agrupado: ordena la base de datos

`InspeccionModel::listarPorObraAgrupado(int $obraId)` sustituye a `contarParaObra()`:

```sql
SELECT id, uuid, obra_id, inspector_id, fecha_inspeccion, hora_inspeccion, observacion
  FROM inspecciones
 WHERE obra_id = ?
 ORDER BY fecha_inspeccion DESC,
          (hora_inspeccion IS NULL) ASC,
          hora_inspeccion DESC,
          id DESC
```

* fechas de más reciente a más antigua;
* dentro de cada fecha, de más reciente a más antigua por hora;
* `hora_inspeccion IS NULL` al final del día: la ausencia de hora es el dato menos informativo, no el
  más antiguo, y así una inspección sin hora no se confunde con una de madrugada;
* `id DESC` desempata de forma estable cuando fecha y hora coinciden.

El agrupamiento por fecha se hace en PHP sobre ese orden, sin agrupar en SQL y sin recalcular nada. El
orden **no** se deduce del nombre de la carpeta física `HH-mm-ss[-N]` (§52.8): el nombre físico sigue
siendo responsabilidad de la sincronización y su ordinal lo decide
`InspeccionModel::sufijoCarpeta()`. El histórico lee datos, no nombres de archivo.

### 63.2 La hora: una regla, un solo lugar

`app/Libraries/HoraInspeccion.php` centraliza la presentación de la hora:

* `texto()` devuelve `HH:MM`, o "Sin hora" cuando el valor es `NULL` o no es reconocible;
* `esSinHora()` distingue ese caso explícitamente;
* `00:00:00` es una hora **válida** y se muestra como `00:00`.

Listado y detalle usan la misma librería, de modo que la misma inspección no puede mostrarse con dos
horas distintas en dos pantallas.

### 63.3 El detalle de una inspección

Nueva ruta `inspecciones/detalle/(:num)` en el grupo del inspector, con la misma autorización que el
resto del área (`AuthFilter` + `role:INSPECTOR`).

`InspeccionModel::findDetalle(int $id)` devuelve la inspección con el nombre de su inspector
(`LEFT JOIN usuarios`, porque `inspector_id` puede ser nulo en una inspección creada sin inspector).

Precondiciones del detalle:

1. la inspección existe;
2. se resuelve **la obra real de esa inspección**, no una obra de la URL: no hay forma de pedir el
   detalle de una inspección por su número dentro de otra obra;
3. el inspector tiene **asignación vigente** sobre esa obra (`InspectoresObrasModel::esVigente`).

No se consulta `permite_inspeccionar` (§54.2), igual que en el listado: el estado de la obra limita
crear inspecciones, no leerlas, y el histórico de una obra finalizada también se consulta.

La vista (`app/Views/inspector/inspeccion_detalle.php` +
`public/assets/css/pages/inspector-inspeccion-detalle.css`) muestra lo mínimo imprescindible y
registrado: fecha de la inspección, hora, inspector y observaciones, más el contexto de la obra y el
enlace de vuelta al listado. Sin hora muestra "Sin hora"; sin observaciones muestra "Sin
observaciones": un dato vacío se informa como tal y no como un fallo de carga.

### 63.4 Las fotografías: zona señalada y vacía

El detalle incluye una sección "Fotografías" que explica que las fotografías de esa inspección se
incorporarán en una etapa posterior. No hay consulta a `fotografias`, ni `<img>`, ni miniatura, ni
descarga, ni caché histórica, ni IndexedDB: la galería es E.5 y anticipar rutas o markup sería
mostrar algo que el servidor todavía no entrega.

### 63.5 Solo lectura

Ni el listado ni el detalle escriben. El total se deriva del propio listado, de modo que no hay una
segunda fuente de verdad que pueda discrepar, y no se recalcula `sufijoCarpeta()` ni ningún otro dato
derivado: leer el histórico no puede cambiar el almacenamiento.

### 63.6 Pruebas

* `tests/unit/InspectorInspeccionesHistoricoTest.php` (nueva, 20 pruebas): el orden se resuelve en el
  modelo y no con carpetas físicas, `hora_inspeccion IS NULL` al final del día, la regla de medianoche,
  el detalle busca por identidad técnica, la autorización por obra y asignación vigente, ninguna de
  las dos rutas escribe, las vistas no cargan JS ni tocan IndexedDB, y no se agregó ninguna migración.
* `tests/unit/InspectorInspeccionesConsultaTest.php` (15 pruebas): actualizada al modelo agrupado y a
  la vista con grupos.
* `tests/database/InspectorInspeccionesConsultaRenderTest.php` (18 pruebas, antes 7): render real
  sobre base de datos con orden de fechas, orden de horas dentro de una fecha, medianoche como hora
  válida, inspección sin hora al final del día, enlace al detalle, detalle con sus datos y sin ellos,
  obra finalizada, inspección de obra ajena rechazada, inspección inexistente y navegación completa sin
  escrituras.

Suite completa: **321 pruebas y 1129 aserciones en verde** (antes 290 y 972), excluyendo el grupo
`mysql-real`. JS sin cambios en E.4: `obra-inspecciones.test.js` en 32 pruebas,
`sincronizacion.test.js` en 236 aserciones y `csrf.test.js` en 16.

App shell a `sigoa-shell-v10`, con los dos CSS de inspecciones en el precache.

### 63.7 Fuera de alcance

* Sin galería, miniaturas, descarga ni visualización de fotografías históricas: es E.5.
* Sin caché ni precarga del histórico en el dispositivo, y sin volver consultable el histórico sin
  conexión.
* Sin cambios de permisos: E.4 no amplía lo que el inspector puede hacer.
* Sin migraciones, sin cambios en la cola, los reintentos ni el CSRF, y sin dependencias nuevas.
* Sin filtros, búsqueda ni paginación del histórico.

### 63.8 Discrepancias

* Las dos rutas usan `id` y no `uuid`. La URL es navegación humana; el `uuid` sigue siendo la identidad
  técnica de la inspección (§52.3) y no aparece en las rutas web.
* El total que mostraba E.3 como consulta independiente dejó de existir como método: ahora es una
  propiedad de los datos agrupados. Es una deuda a favor, no una pérdida de información.
