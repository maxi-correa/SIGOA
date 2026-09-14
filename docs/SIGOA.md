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

---

## 4.2 PENDIENTE

La existencia de una tabla o estructura de base de datos no implica que la funcionalidad correspondiente esté desarrollada.

Quedan pendientes, entre otras:

* interfaz de gestión de obras;
* gestión completa de usuarios (CRUD: creación, edición, eliminación, cambio de roles, activación/desactivación);
* interfaces;
* gestión de inspectores;
* registro de inspecciones;
* carga y visualización de fotografías;
* funcionamiento offline;
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

La estrategia técnica definitiva de almacenamiento local y sincronización todavía debe implementarse y validarse.

No asumir una tecnología concreta si todavía no fue definida.

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

La lógica completa de sincronización todavía no está implementada.

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

* CSRF;
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
* eliminar información histórica solamente para simplificar una implementación.

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

La protección CSRF está documentada como pendiente en SIGOA.md §32. Habilitarla es una decisión global que debe implementarse de forma transversal y afecta todos los formularios del sistema.

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
