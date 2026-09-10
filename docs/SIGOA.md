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

---

## 4.2 PENDIENTE

La existencia de una tabla o estructura de base de datos no implica que la funcionalidad correspondiente esté desarrollada.

Quedan pendientes, entre otras:

* interfaz de gestión de obras;
* gestión de usuarios;
* autenticación y autorización completa;
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
* 4 roles.

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
* autenticación completa;
* autorización;
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
* API/mecanismo de comunicación definitivo.

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
