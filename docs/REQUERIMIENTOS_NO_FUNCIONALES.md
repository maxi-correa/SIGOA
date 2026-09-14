# SIGOA

# Requerimientos No Funcionales

**Documento:** `docs/REQUERIMIENTOS_NO_FUNCIONALES.md`
**Sistema:** SIGOA — Sistema de Gestión e Inspección de Obras de Arquitectura
**Estado:** Definitivo
**Versión:** 1.3

---

# 1. Propósito

Este documento establece los criterios visuales, de interfaz, presentación, organización de recursos y experiencia de usuario que deberán respetarse durante el desarrollo de SIGOA.

Su objetivo es establecer una **identidad visual y técnica coherente para todo el sistema**, evitando que cada pantalla sea diseñada de manera independiente.

Los criterios definidos aquí deberán aplicarse a las nuevas funcionalidades y, cuando corresponda, utilizarse para adaptar las pantallas existentes.

El documento constituye una referencia para desarrolladores y agentes de desarrollo que intervengan en el proyecto.

---

# 2. Principios generales de diseño

SIGOA deberá transmitir las siguientes características:

* Institucional.
* Profesional.
* Sobrio.
* Claro.
* Ordenado.
* Funcional.
* Moderno, sin resultar excesivamente tecnológico.
* Fácil de utilizar en oficina y en dispositivos móviles.
* Adecuado tanto para usuarios administrativos como para inspectores que trabajan en terreno.

La interfaz deberá priorizar siempre:

1. La información.
2. La legibilidad.
3. La comprensión de la situación de una obra.
4. La claridad de las acciones.
5. La velocidad de utilización.

Se evitará:

* exceso de colores;
* efectos visuales innecesarios;
* animaciones excesivas;
* interfaces recargadas;
* botones ambiguos;
* texto excesivamente pequeño;
* utilización indiscriminada de colores fuertes;
* elementos decorativos que dificulten la lectura;
* dependencia innecesaria de recursos externos.

El diseño deberá favorecer una sensación general de **orden, estabilidad y confiabilidad**.

---

# 3. Identidad visual

La identidad visual de SIGOA toma como referencia el entorno institucional de la Dirección General de Obras de Arquitectura.

Los elementos visuales predominantes utilizados como referencia son:

* azul oscuro;
* blanco;
* blanco cálido;
* grises;
* madera en tonos cálidos.

La interfaz trasladará estos elementos a una paleta adaptada a medios digitales.

El **azul institucional** será el principal elemento identificatorio de SIGOA.

El **marrón madera** podrá utilizarse como complemento del azul institucional cuando aporte valor visual y contribuya a la identidad del sistema.

No deberá convertirse en el color dominante de la interfaz.

La identidad deberá mantenerse consistente en:

* login;
* navegación;
* dashboard;
* gestión de obras;
* inspecciones;
* fotografías;
* documentos;
* formularios;
* tablas;
* mensajes;
* dispositivos móviles.

---

# 4. Paleta de colores

La paleta oficial de SIGOA se divide en:

1. colores institucionales;
2. colores neutros;
3. colores funcionales;
4. colores específicos para los estados de las obras.

Los valores HEX establecidos en este documento deberán respetarse en toda la aplicación.

No deberán introducirse nuevos colores arbitrariamente.

Cuando sea necesario utilizar una tonalidad adicional, deberá justificarse y evaluarse su incorporación a la paleta oficial.

---

# 5. Color institucional principal

## 5.1 Azul institucional

**HEX:** `#24344C`

**Nombre:** Azul institucional SIGOA.

**Uso principal:**

* barra de navegación;
* encabezados;
* títulos destacados;
* botones principales;
* elementos de identidad;
* elementos seleccionados;
* enlaces importantes;
* indicadores principales;
* elementos de navegación;
* componentes institucionales.

Este color constituye la principal referencia cromática del sistema.

Deberá utilizarse de forma consistente en todas las secciones.

No deberá introducirse otro azul como reemplazo del azul institucional.

---

# 6. Colores neutros

## 6.1 Fondo general

**HEX:** `#F5F3F0`

**Nombre:** Blanco cálido.

**Uso:**

* fondo general;
* áreas de trabajo;
* separación visual entre componentes;
* fondos exteriores de tarjetas;
* páginas y secciones.

Se prefiere este tono antes que un blanco completamente puro para generar una apariencia más cálida y reducir el contraste excesivo.

---

## 6.2 Superficie

**HEX:** `#FFFFFF`

**Nombre:** Blanco.

**Uso:**

* tarjetas;
* formularios;
* paneles;
* ventanas modales;
* tablas;
* bloques de información;
* elementos que deban destacar sobre el fondo general.

El blanco deberá utilizarse principalmente como superficie de contenido.

---

## 6.3 Texto principal

**HEX:** `#2C3036`

**Nombre:** Gris grafito.

**Uso:**

* texto principal;
* títulos secundarios;
* etiquetas;
* contenido de tablas;
* información relevante;
* contenido de formularios.

Se evitará utilizar negro puro (`#000000`) como color general del texto.

---

## 6.4 Texto secundario

**HEX:** `#6B7078`

**Nombre:** Gris medio.

**Uso:**

* descripciones;
* fechas;
* información complementaria;
* textos auxiliares;
* indicaciones;
* metadatos.

---

## 6.5 Acento cálido

**HEX:** `#8E7058`

**Nombre:** Marrón madera.

Este color complementa la identidad azul de SIGOA.

Está inspirado en los tonos cálidos de la madera presentes en el entorno institucional.

**Uso permitido:**

* pequeños detalles;
* líneas de separación;
* indicadores secundarios;
* elementos decorativos discretos;
* pequeños acentos institucionales;
* detalles visuales asociados a determinados componentes.

El marrón podrá combinarse con el azul institucional cuando contribuya a generar una identidad más cálida.

No deberá utilizarse como color dominante de grandes superficies ni reemplazar al azul institucional.

---

# 7. Colores funcionales

Los colores funcionales representan el significado de una acción o situación dentro de la interfaz.

No deberán confundirse necesariamente con el significado de los estados de las obras.

## 7.1 Éxito / confirmación

**HEX:** `#3F7D5A`

**Nombre:** Verde confirmación.

**Uso:**

* Guardar;
* Confirmar;
* Aceptar;
* Finalizar una operación;
* Registrar;
* operación realizada correctamente;
* mensajes de éxito.

El verde deberá ser sobrio y evitar tonalidades excesivamente brillantes o saturadas.

---

## 7.2 Advertencia

**HEX:** `#B8862F`

**Nombre:** Ocre.

**Uso:**

* advertencias;
* situaciones que requieren atención;
* vencimientos próximos;
* información preventiva;
* acciones que requieren revisión.

Deberá evitarse el amarillo brillante o excesivamente saturado.

---

## 7.3 Error / peligro

**HEX:** `#B84A4A`

**Nombre:** Rojo SIGOA.

**Uso:**

* errores;
* operaciones destructivas;
* eliminación;
* información crítica;
* fallos de operaciones.

El rojo deberá utilizarse con moderación.

---

## 7.4 Información

**HEX:** `#3B6E9E`

**Nombre:** Azul informativo.

**Uso:**

* mensajes informativos;
* ayuda contextual;
* información complementaria;
* avisos no críticos;
* indicaciones relacionadas con sincronización.

Deberá diferenciarse claramente del azul institucional `#24344C`.

---

# 8. Colores específicos de los estados de obra

Los estados de obra poseen colores **específicos y definidos**.

Cada estado deberá utilizar siempre el color establecido en este apartado cuando se represente visualmente mediante:

* etiquetas;
* badges;
* indicadores;
* tarjetas;
* tablas;
* listados;
* filtros;
* resúmenes;
* gráficos;
* otros componentes de interfaz.

El color deberá acompañar al nombre del estado y **no deberá ser la única forma de identificarlo**.

Los estados definidos son:

* PREVIO INICIO;
* EN EJECUCIÓN;
* NEUTRALIZADA;
* EN PLAZO DE CONSERVACIÓN;
* FINALIZADA.

---

## 8.1 PREVIO INICIO

**HEX:** `#F5F3F0`

**Color:** Blanco cálido.

Representa una obra registrada que todavía no ha comenzado.

El color deberá transmitir:

* neutralidad;
* espera;
* situación previa;
* ausencia de ejecución.

No deberá utilizarse un color fuerte para este estado.

Cuando sea necesario diferenciarlo visualmente del fondo general, podrá utilizarse un borde o contraste adicional, pero deberá conservarse el color definido `#F5F3F0`.

---

## 8.2 EN EJECUCIÓN

**HEX:** `#3F7D5A`

**Color:** Verde sobrio.

Representa una obra que actualmente se encuentra en ejecución.

El color podrá coincidir con el utilizado para acciones de confirmación.

Esta coincidencia es intencional.

El significado dependerá del contexto:

* botón verde → acción positiva;
* estado verde → obra en ejecución.

No deberá crearse otro verde únicamente para evitar esta coincidencia.

---

## 8.3 NEUTRALIZADA

**HEX:** `#B8862F`

**Color:** Ocre.

Representa una obra cuya ejecución se encuentra temporalmente neutralizada.

Deberá transmitir:

* atención;
* situación especial;
* necesidad de seguimiento.

No deberá interpretarse automáticamente como un error.

El tono deberá conservarse sobrio y evitar amarillos brillantes o chillones.

---

## 8.4 EN PLAZO DE CONSERVACIÓN

**HEX:** `#06B6D4`

**Color:** Cian / celeste.

Representa una obra finalizada en su ejecución que todavía se encuentra dentro del período de conservación correspondiente.

Este color deberá utilizarse específicamente para este estado.

Deberá diferenciarse claramente del azul institucional `#24344C`.

El color `#06B6D4` no deberá utilizarse como sustituto del azul institucional en elementos de navegación o identidad.

---

## 8.5 FINALIZADA

**HEX:** `#B84A4A`

**Color:** Rojo sobrio.

Representa una obra cuyo ciclo se encuentra culminado.

El uso del rojo en este estado **no representa error, peligro ni una situación negativa**.

Su finalidad es visual y conceptual:

* cierre;
* culminación;
* decisión;
* situación definitiva.

Deberá utilizarse un rojo sobrio, evitando tonalidades excesivamente intensas.

---

# 9. Tabla oficial de estados

La siguiente tabla constituye la referencia oficial para los colores de los estados de obra.

| Estado                   | Color          | HEX       |
| ------------------------ | -------------- | --------- |
| PREVIO INICIO            | Blanco cálido  | `#F5F3F0` |
| EN EJECUCIÓN             | Verde sobrio   | `#3F7D5A` |
| NEUTRALIZADA             | Ocre           | `#B8862F` |
| EN PLAZO DE CONSERVACIÓN | Cian / celeste | `#06B6D4` |
| FINALIZADA               | Rojo sobrio    | `#B84A4A` |

Estos valores son los colores oficiales de los estados.

No deberán sustituirse por otros tonos sin modificar previamente este documento.

---

# 10. Proporción de utilización de colores

La interfaz deberá mantener una predominancia de colores neutros.

La distribución conceptual será:

```text
FONDOS Y SUPERFICIES
██████████████████████████████████████████

AZUL INSTITUCIONAL
█████████

GRISES Y TEXTOS
█████

ACENTOS
██

COLORES FUNCIONALES Y ESTADOS
██
```

El objetivo es que:

> **El contenido sea protagonista y el color sirva para organizarlo.**

Los colores funcionales y los colores de estados deberán aparecer principalmente donde tengan un significado concreto.

---

# 11. Tipografía

La tipografía principal de SIGOA será:

**Inter**

Inter se utilizará por:

* legibilidad;
* aspecto moderno;
* buena visualización en pantallas;
* adecuada lectura de números y tablas;
* consistencia entre escritorio y dispositivos móviles.

Se priorizará una incorporación que no dependa de una conexión permanente a Internet.

Cuando sea conveniente, la fuente deberá incorporarse localmente dentro de los recursos del proyecto.

Como respaldo se utilizará:

```text
Inter
↓
Arial
↓
sans-serif
```

La tipografía deberá utilizarse de manera consistente en todo el sistema.

---

# 12. Jerarquía tipográfica

La interfaz deberá establecer una jerarquía visual clara.

## Título principal

* tamaño destacado;
* peso fuerte;
* color institucional;
* utilizado para identificar la pantalla.

## Título de sección

* tamaño intermedio;
* peso destacado;
* preferentemente azul institucional.

## Texto normal

* tamaño cómodo de lectura;
* color grafito.

## Texto secundario

* menor contraste;
* color gris medio.

## Información auxiliar

Podrá utilizar tamaños menores, pero nunca deberá resultar difícil de leer en dispositivos móviles.

---

# 13. Navegación autenticada (topbar + sidebar)

La aplicación autenticada utiliza una estructura de navegación de dos zonas.

La identidad institucional (azul `#24344C`) se conserva en la **barra superior (topbar)**.

La **navegación principal** se encuentra en un **sidebar lateral izquierdo**.

Conceptualmente:

```text
┌─────────────────────────────────────────────────────────────────┐
│ SIGOA  ☰                       Sesión: Mariana (mariana)        │
├───────────────┬─────────────────────────────────────────────────┤
│ Inicio        │                                                 │
│ Gestión       │              CONTENIDO PRINCIPAL                │
│ de usuarios   │                                                 │
│               │                                                 │
│ ───────────   │                                                 │
│               │                                                 │
│ Mis datos     │                                                 │
│ Cerrar sesión │                                                 │
└───────────────┴─────────────────────────────────────────────────┘
```

Características:

* fondo azul institucional en el topbar;
* texto claro;
* identificación visible del usuario en el topbar;
* sidebar de navegación con una **zona funcional** (por encima del separador) y una **zona personal/sesión** (por debajo: Mis datos, Cerrar sesión);
* los ítems del sidebar se muestran según el rol del usuario (los roles protegidos se validan siempre en backend);
* navegación sencilla;
* comportamiento consistente en todas las secciones.

La navegación móvil utiliza el mismo sidebar en modalidad **off-canvas/drawer**: oculto por defecto y desplegado mediante el botón de menú (`☰`), con una forma clara de cierre (botón propio, clic en el fondo oscurecido o tecla `Escape`). No existen dos sistemas de navegación independientes: cambia la presentación según el viewport, no la lógica.

En pantallas de escritorio el sidebar permanece fijo/lateral sin desplazarse junto con el contenido.

---

# 14. Botones

Los botones deberán utilizar colores según la importancia y naturaleza de la acción.

La interfaz deberá diferenciar claramente entre:

* acción principal;
* acción positiva;
* acción secundaria;
* acción destructiva;
* acción de advertencia.

---

# 15. Botón principal

## Azul institucional

**Color:** `#24344C`

Uso:

* Continuar;
* Buscar;
* Abrir;
* Editar;
* acciones principales no destructivas;
* avanzar dentro de un proceso.

Ejemplo:

```text
┌──────────────────────┐
│      CONTINUAR       │
└──────────────────────┘
```

---

# 16. Botón de confirmación / guardado

## Verde

**Color:** `#3F7D5A`

Uso:

* Guardar;
* Confirmar;
* Aceptar;
* Finalizar;
* Registrar.

Ejemplo:

```text
┌──────────────────────┐
│      ✓ GUARDAR       │
└──────────────────────┘
```

El verde deberá contrastar correctamente con el azul institucional sin resultar excesivamente brillante.

---

# 17. Botón secundario / volver

No deberá utilizar un color fuerte.

Características:

* fondo blanco `#FFFFFF`;
* borde gris;
* texto grafito `#2C3036`;
* icono opcional.

Ejemplo:

```text
┌──────────────────────┐
│      ← VOLVER        │
└──────────────────────┘
```

Uso:

* Volver;
* Regresar;
* Atrás;
* cancelar una navegación sin modificar información.

---

# 18. Botón cancelar

El botón cancelar será secundario.

Podrá utilizar el mismo patrón visual que el botón volver:

* fondo blanco `#FFFFFF`;
* borde gris;
* texto grafito `#2C3036`.

Ejemplo:

```text
┌──────────────────────┐
│      CANCELAR        │
└──────────────────────┘
```

No deberá competir visualmente con:

* Guardar;
* Confirmar;
* Eliminar.

---

# 19. Botón eliminar

## Rojo

**Color:** `#B84A4A`

Uso exclusivo para operaciones destructivas.

Ejemplo:

```text
┌──────────────────────┐
│      ELIMINAR        │
└──────────────────────┘
```

Las operaciones que impliquen pérdida de información deberán solicitar confirmación cuando corresponda.

---

# 20. Botón de advertencia

## Ocre

**Color:** `#B8862F`

Uso:

* Revisar;
* acciones que requieren atención;
* operaciones especiales;
* situaciones preventivas.

No deberá utilizarse para acciones rutinarias.

---

# 21. Tarjetas de información

Las tarjetas utilizarán principalmente:

* fondo de superficie `#FFFFFF`;
* sobre fondo general `#F5F3F0`.

Ejemplo:

```text
┌────────────────────────┐
│ OBRAS ACTIVAS          │
│                        │
│          12            │
│                        │
│ Obras actualmente      │
│ en ejecución           │
└────────────────────────┘
```

Se evitará convertir cada tarjeta en un bloque de color.

El color podrá utilizarse en:

* título;
* indicador;
* icono;
* borde;
* línea decorativa;
* estado.

---

# 22. Formularios

Los formularios deberán priorizar:

* claridad;
* lectura;
* agrupación lógica;
* identificación inequívoca de los campos;
* facilidad de uso táctil;
* validación comprensible.

Los campos deberán mantener una estructura consistente.

Ejemplo:

```text
┌─────────────────────────────────────────────────┐
│ DATOS DE LA OBRA                                │
│                                                 │
│ Nombre de la obra                               │
│ ┌─────────────────────────────────────────────┐ │
│ │ PAVIMENTO CALLES LAFERRERE                  │ │
│ └─────────────────────────────────────────────┘ │
│                                                 │
│ Expediente municipal                            │
│ ┌──────────────────────┐                        │
│ │ 4356-M-2026          │                        │
│ └──────────────────────┘                        │
│                                                 │
│ Estado                                          │
│ ┌─────────────────────────────────────────────┐ │
│ │ EN EJECUCIÓN                         ▼      │ │
│ └─────────────────────────────────────────────┘ │
│                                                 │
│                    ┌────────────┐ ┌───────────┐ │
│                    │ ← VOLVER   │ │ ✓ GUARDAR │ │
│                    └────────────┘ └───────────┘ │
└─────────────────────────────────────────────────┘
```

La acción principal deberá diferenciarse claramente de la acción secundaria.

---

# 23. Validación de formularios

Los errores de validación deberán:

* indicar claramente qué campo presenta el problema;
* explicar, cuando sea posible, cómo corregirlo;
* utilizar color rojo `#B84A4A` como complemento;
* utilizar texto o iconografía;
* mantener una presentación consistente.

No deberá dependerse exclusivamente del color rojo.

Ejemplo:

```text
❌ El expediente municipal es obligatorio.
```

en lugar de solamente cambiar el borde del campo a rojo.

---

# 24. Tablas

Las tablas deberán priorizar:

* legibilidad;
* alineación;
* separación clara de registros;
* identificación de columnas;
* desplazamiento horizontal cuando sea necesario;
* correcta visualización en dispositivos pequeños.

Ejemplo:

```text
┌────────────┬──────────────────────────┬──────────────┬──────────────┐
│ CÓDIGO     │ OBRA                     │ ESTADO       │ PROGRESO     │
├────────────┼──────────────────────────┼──────────────┼──────────────┤
│ OBR-0001   │ PAVIMENTO LAFERRERE      │ EN EJECUCIÓN │     65.300 %  │
│ OBR-0002   │ PLAZA BARRIO CENTRO      │ PREVIO INICIO│      0.000 %  │
│ OBR-0003   │ VEREDAS BARRIO SISMOG.   │ FINALIZADA   │    100.000 %  │
└────────────┴──────────────────────────┴──────────────┴──────────────┘
```

El encabezado podrá utilizar el azul institucional `#24344C`.

Las filas deberán utilizar fondos neutros.

No se deberán utilizar colores fuertes en cada fila.

Los estados podrán utilizar etiquetas o indicadores cromáticos utilizando **exactamente los colores definidos en el apartado 9**.

---

# 25. Mensajes del sistema

Los mensajes deberán diferenciarse claramente según su finalidad.

## 25.1 Éxito

Color:

`#3F7D5A`

Ejemplo:

```text
┌────────────────────────────────────────────┐
│ ✓  La obra fue registrada correctamente.   │
└────────────────────────────────────────────┘
```

---

## 25.2 Advertencia

Color:

`#B8862F`

Ejemplo:

```text
┌────────────────────────────────────────────┐
│ ⚠  La obra se encuentra próxima a vencer. │
└────────────────────────────────────────────┘
```

---

## 25.3 Error

Color:

`#B84A4A`

Ejemplo:

```text
┌────────────────────────────────────────────┐
│ ✕  No fue posible guardar la información.  │
└────────────────────────────────────────────┘
```

---

## 25.4 Información

Color:

`#3B6E9E`

Ejemplo:

```text
┌────────────────────────────────────────────┐
│ ℹ  La información será sincronizada luego. │
└────────────────────────────────────────────┘
```

---

## 25.5 Criterio de uso

Cada tipo de mensaje se reserva para su finalidad específica:

| Tipo | Uso |
| --- | --- |
| **Éxito** | Confirmación de una operación completada correctamente. Solo se usa el color verde `#3F7D5A`. |
| **Advertencia** | Advertir sobre una situación que requiere atención pero no impide continuar. |
| **Error** | Informar que una operación falló o que el ingreso de datos es inválido. |
| **Información** | Notificación neutra sin connotación de éxito, error o advertencia. |

El color azul Información `#3B6E9E` **no debe utilizarse** para confirmar operaciones exitosas; las confirmaciones siempre usan el verde Éxito.

Los mensajes se implementan con el componente común `components/alerts.css`, que aplica un borde izquierdo de 4px en el color funcional para diferenciar visualmente cada tipo. No debe copiarse ni modificarse el estilo individualmente en cada View.

# 26. Iconografía y biblioteca de iconos

SIGOA deberá utilizar una **biblioteca de iconos propia de la aplicación**, evitando depender de los iconos visuales proporcionados por el sistema operativo.

La biblioteca de iconos de referencia será:

**Bootstrap Icons**

La utilización de una biblioteca uniforme permitirá mantener el mismo lenguaje visual en:

* escritorio;
* notebook;
* tablet;
* teléfono;
* navegador;
* PWA.

Los iconos deberán utilizarse para complementar las acciones y no simplemente como decoración.

---

# 27. Prohibición de emojis como iconografía funcional

Los emojis no deberán utilizarse como iconografía funcional de SIGOA.

Por ejemplo, no deberá utilizarse:

```text
🗑
📷
⚠
✓
👤
```

como solución definitiva para los controles de la aplicación.

La apariencia de los emojis puede variar según:

* sistema operativo;
* navegador;
* dispositivo;
* configuración del usuario.

Esto afectaría la consistencia visual del sistema.

Cuando se necesite un icono deberá utilizarse la biblioteca definida para SIGOA.

---

# 28. Aplicaciones de la iconografía

La biblioteca de iconos deberá contemplar, entre otros:

* agregar;
* editar;
* eliminar;
* visualizar;
* descargar;
* subir;
* guardar;
* cancelar;
* volver;
* buscar;
* cerrar;
* menú;
* usuario;
* configuración;
* cámara;
* fotografía;
* documento;
* PDF;
* sincronización;
* conexión;
* desconexión;
* advertencia;
* información;
* éxito;
* error.

Los iconos podrán utilizarse junto con texto.

En acciones importantes se priorizará:

```text
[ ICONO ] TEXTO
```

sobre:

```text
[ ICONO ]
```

cuando el significado pueda resultar ambiguo.

---

# 29. Mostrar y ocultar contraseña

Los campos de contraseña deberán contemplar, cuando corresponda, una acción visual para:

* mostrar contraseña;
* ocultar contraseña.

Esta acción deberá utilizar iconografía de Bootstrap Icons y no un emoji ni un icono dependiente del sistema operativo.

Ejemplo conceptual:

```text
Contraseña

┌──────────────────────────────────────┐
│ •••••••••••••••••                 👁 │
└──────────────────────────────────────┘
```

El símbolo mostrado anteriormente es únicamente conceptual.

La implementación real deberá utilizar el icono correspondiente de Bootstrap Icons.

---

# 30. Tamaño y comportamiento de los iconos

Los iconos deberán:

* mantener tamaños consistentes;
* alinearse correctamente con el texto;
* conservar una separación adecuada;
* ser suficientemente grandes en controles táctiles;
* no utilizar tamaños arbitrarios en cada pantalla.

Un mismo tipo de acción deberá utilizar el mismo icono en todo SIGOA.

Por ejemplo:

**Editar** deberá utilizar siempre el mismo icono.

**Eliminar** deberá utilizar siempre el mismo icono.

**Descargar** deberá utilizar siempre el mismo icono.

---

# 31. Responsive Design

SIGOA deberá funcionar correctamente en:

* computadoras de escritorio;
* notebooks;
* tablets;
* teléfonos celulares.

El diseño deberá adaptarse especialmente a las funcionalidades utilizadas por inspectores en terreno.

Las pantallas de inspección deberán priorizar:

* botones táctiles;
* lectura rápida;
* fotografías;
* formularios simples;
* navegación clara;
* funcionamiento con conectividad limitada o inexistente.

---

# 32. Mobile-first

Las funcionalidades destinadas principalmente a inspectores podrán desarrollarse siguiendo un enfoque mobile-first.

Esto no significa que el sistema de escritorio sea secundario.

La interfaz deberá adaptarse progresivamente a:

```text
TELÉFONO
   ↓
TABLET
   ↓
NOTEBOOK
   ↓
ESCRITORIO
```

manteniendo los mismos componentes y criterios visuales.

---

# 33. Tablas en dispositivos pequeños

Las tablas deberán contemplar específicamente pantallas pequeñas.

Cuando no sea posible mostrar todas las columnas:

* podrá utilizarse desplazamiento horizontal;
* podrán priorizarse determinadas columnas;
* podrá utilizarse una presentación alternativa de registros;
* no deberá reducirse excesivamente el tamaño del texto para intentar mostrar todo.

La legibilidad tendrá prioridad sobre la cantidad de información visible simultáneamente.

---

# 34. Accesibilidad

La interfaz deberá procurar:

* contraste suficiente;
* tamaños de texto legibles;
* botones suficientemente grandes para dispositivos táctiles;
* estados de foco visibles;
* indicación clara del elemento seleccionado;
* navegación comprensible;
* etiquetas adecuadas en formularios;
* mensajes de error comprensibles.

SIGOA no deberá depender exclusivamente del color para transmitir información.

Por ejemplo:

```text
EN EJECUCIÓN
```

deberá acompañarse visualmente con el color `#3F7D5A`, pero el texto deberá permanecer visible.

El color deberá funcionar como complemento del texto y/o icono.

---

# 35. Espaciado y orden visual

SIGOA deberá mantener una separación consistente entre:

* títulos;
* formularios;
* campos;
* botones;
* tarjetas;
* tablas;
* secciones;
* mensajes.

Se evitará que diferentes pantallas utilicen márgenes y tamaños arbitrarios.

La interfaz deberá transmitir:

```text
ORDEN
  ↓
JERARQUÍA
  ↓
LECTURA FÁCIL
  ↓
ACCIÓN CLARA
```

---

# 36. Confirmaciones de operaciones

Las operaciones importantes deberán diferenciar claramente:

**Acción positiva**

```text
✓ GUARDAR
```

Color: `#3F7D5A`.

**Acción secundaria**

```text
CANCELAR
```

Color principal de superficie: `#FFFFFF`.

**Acción destructiva**

```text
ELIMINAR
```

Color: `#B84A4A`.

Ejemplo:

```text
¿Está seguro de eliminar esta inspección?

┌──────────────┐  ┌──────────────────┐
│   CANCELAR   │  │    ELIMINAR      │
└──────────────┘  └──────────────────┘
```

La acción destructiva no deberá confundirse visualmente con una acción positiva.

---

# 37. Organización de archivos CSS

Los estilos no deberán incorporarse directamente dentro de las Views, salvo casos excepcionales debidamente justificados.

Los recursos visuales deberán centralizarse dentro de `public/`.

Estructura conceptual:

```text
public/
└── assets/
    ├── css/
    │   ├── app.css
    │   │
    │   ├── components/
    │   │   ├── buttons.css
    │   │   ├── forms.css
    │   │   ├── tables.css
    │   │   ├── badges.css
    │   │   ├── alerts.css
    │   │   ├── navbar.css
    │   │   └── sidebar.css
    │   │
    │   └── pages/
    │       ├── login.css
    │       ├── dashboard.css
    │       ├── obras.css
    │       └── inspecciones.css
    │
    ├── js/
    │
    ├── fonts/
    │   └── inter/
    │
    ├── vendor/
    │   └── bootstrap-icons/
    │
    └── images/
```

Los componentes CSS en `components/` y los estilos de página en `pages/` se crearán cuando exista contenido real que justifique cada archivo.

No deberán crearse archivos vacíos ni anticipados.

La carpeta `fonts/` almacena localmente la tipografía Inter.

La carpeta `vendor/` almacena dependencias externas incorporadas al proyecto, como Bootstrap Icons.

---

# 38. CSS global

`app.css` deberá contener los elementos comunes del sistema:

* variables de colores (incluyendo estados hover, active, focus y fondos derivados);
* tipografía;
* tamaños base;
* estilos generales;
* elementos base;
* utilidades generales;
* reglas globales de interfaz.

Ejemplo conceptual:

```css
:root {
    --color-primary: #24344C;
    --color-success: #3F7D5A;
    --color-warning: #B8862F;
    --color-danger: #B84A4A;
    --color-info: #3B6E9E;

    --color-background: #F5F3F0;
    --color-surface: #FFFFFF;

    --color-text: #2C3036;
    --color-text-secondary: #6B7078;

    --color-accent: #8E7058;

    --color-state-previo: #F5F3F0;
    --color-state-ejecucion: #3F7D5A;
    --color-state-neutralizada: #B8862F;
    --color-state-conservacion: #06B6D4;
    --color-state-finalizada: #B84A4A;
}
```

La definición centralizada permitirá mantener la identidad visual desde un único lugar.

---

# 39. Componentes reutilizables

La carpeta `components/` contendrá estilos reutilizables.

Ejemplo:

```text
buttons.css
forms.css
tables.css
badges.css
alerts.css
navbar.css
sidebar.css
```

Cuando un componente se utilice en varias pantallas deberá existir un único patrón visual.

No deberá copiarse y modificarse individualmente en cada View.

---

# 40. Estilos específicos de página

La carpeta `pages/` contendrá solamente estilos particulares de determinadas páginas.

Ejemplo:

```text
login.css
dashboard.css
obras.css
inspecciones.css
```

Un estilo deberá permanecer en `pages/` únicamente cuando no corresponda a un componente reutilizable.

Si posteriormente un componente se utiliza en varias páginas, deberá evaluarse su traslado a `components/`.

---

# 41. Prohibición de estilos arbitrarios en Views

Las Views no deberán contener grandes bloques de CSS embebido.

Deberá evitarse:

```html
<style>
    ...
</style>
```

como mecanismo habitual de diseño.

Los estilos deberán mantenerse centralizados y reutilizables.

Esto facilitará:

* mantenimiento;
* correcciones;
* consistencia;
* evolución del diseño;
* trabajo con agentes de desarrollo.

---

# 41b. Organización de archivos JavaScript

Los scripts no deberán incorporarse directamente dentro de las Views, salvo inicializaciones mínimas que no justifiquen un archivo externo.

Los recursos JavaScript deberán centralizarse dentro de `public/assets/js/`.

Estructura conceptual:

```text
public/
└── assets/
    └── js/
        ├── app.js                  (globales, cuando existan)
        ├── components/             (reutilizables, bajo necesidad real)
        └── pages/
            └── login.js
```

La carpeta `pages/` contendrá scripts específicos de determinadas páginas.

La carpeta `components/` contendrá comportamientos reutilizables en varias pantallas. Los componentes JavaScript deberán crearse únicamente cuando exista reutilización real entre múltiples pantallas.

`app.js` contendrá comportamientos JavaScript necesarios en todas las páginas. Se creará cuando exista una responsabilidad global concreta.

No deberán crearse archivos vacíos ni anticipados.

---

# 41c. Layouts

Las páginas autenticadas deberán extender un layout común mediante el sistema de templates de CodeIgniter 4.

El layout contendrá exclusivamente elementos comunes:

* estructura HTML base (`<html>`, `<head>`, `<body>`);
* carga de recursos globales (tipografía, iconografía, estilos base);
* barra superior de navegación;
* contenedor principal para el contenido de cada página;
* carga de scripts globales.

El layout no deberá contener contenido específico de ninguna página.

Las páginas colocarán su contenido y sus recursos específicos mediante las secciones definidas por el layout.

El login es una pantalla pública y no deberá extender el layout autenticado.

---

# 41d. Carga selectiva de recursos

Cada página deberá cargar únicamente los CSS y JS que necesita.

Los recursos globales (app.css, tipografía, iconografía) se cargan una sola vez, ya sea en el layout o en la página.

Los componentes CSS y los estilos de página se cargan solamente en las páginas que los utilizan.

Lo mismo aplica para los scripts JavaScript.

Esto evita:

* duplicación de carga de recursos;
* sobrecarga innecesaria;
* tiempos de carga mayores.

---

# 42. Recursos externos y funcionamiento offline

SIGOA tendrá funcionalidades que deberán poder utilizarse en condiciones de conectividad limitada o inexistente.

Por este motivo, el funcionamiento básico de la interfaz no deberá depender innecesariamente de:

* fuentes externas;
* hojas de estilo externas;
* bibliotecas remotas;
* iconos descargados en tiempo real;
* servicios externos indispensables para representar la interfaz.

Los recursos incorporados localmente son parte de la arquitectura aprobada y deberán incluirse en las primeras fases de implementación:

* **Inter** se almacena en `public/assets/fonts/inter/` con sus definiciones `@font-face`.
* **Bootstrap Icons** se almacena en `public/assets/vendor/bootstrap-icons/` con su hoja de estilos y fuentes.

Cuando resulte conveniente, los recursos necesarios para la interfaz deberán incorporarse localmente al proyecto.

Se priorizará todo aquello que facilite:

* rapidez de carga;
* respuesta inmediata;
* funcionamiento offline;
* reducción de dependencias externas;
* estabilidad de la interfaz;
* utilización en terreno.

Esto resulta especialmente importante para:

* inspectores;
* captura de fotografías;
* formularios;
* consulta de información previamente sincronizada;
* funcionalidades PWA.

---

# 43. Fotografías e iconografía

Las funcionalidades relacionadas con fotografías deberán utilizar iconografía coherente con el resto del sistema.

Ejemplos conceptuales:

```text
[ Cámara ] Tomar fotografía
[ Imagen ] Ver fotografías
[ + ] Agregar fotografía
[ Descargar ] Descargar fotografía
```

La iconografía deberá integrarse visualmente con los botones y controles de SIGOA.

---

# 44. Indicadores de sincronización

Debido a la naturaleza de SIGOA, la sincronización constituye una funcionalidad importante.

Cuando corresponda, deberán existir indicadores claros para representar:

* conectado;
* desconectado;
* pendiente de sincronización;
* sincronizando;
* sincronizado;
* error de sincronización.

Estos indicadores deberán combinar:

* texto;
* iconografía;
* color.

No deberán depender exclusivamente del color.

La interfaz deberá priorizar la respuesta rápida y evitar operaciones innecesarias contra servicios externos cuando el dispositivo se encuentre sin conexión.

---

# 45. Consistencia

Una vez definido un componente visual, deberá reutilizarse.

Por ejemplo:

Si todos los botones principales utilizan:

```text
#24344C
```

no deberá crearse posteriormente otro azul arbitrario para otra pantalla.

Del mismo modo:

* los botones de confirmación deberán mantener el mismo criterio;
* los mensajes de error deberán mantener el mismo criterio;
* los formularios deberán conservar una estructura semejante;
* las tablas deberán compartir patrones visuales;
* los iconos deberán representar siempre las mismas acciones;
* los estados deberán conservar exactamente sus colores definidos.

---

# 46. Evolución de la interfaz

El diseño deberá permitir que SIGOA crezca sin necesidad de rediseñar completamente la interfaz.

La incorporación de nuevos módulos deberá reutilizar:

* colores;
* tipografía;
* botones;
* formularios;
* tablas;
* navegación;
* iconografía;
* mensajes;
* componentes.

La creación de una nueva pantalla deberá ser principalmente una:

> **combinación de componentes existentes**

complementada por estilos específicos cuando resulte necesario.

---

# 47. Regla para componentes nuevos

Cuando una nueva funcionalidad requiera un componente visual que todavía no exista:

1. deberá buscarse primero un componente existente que pueda reutilizarse;
2. si no existe, deberá crearse siguiendo los principios de este documento;
3. el nuevo componente deberá diseñarse pensando en su posible reutilización;
4. si modifica un patrón global, deberá documentarse la decisión;
5. no deberán introducirse componentes visuales contradictorios con los existentes.

---

# 48. Regla para el desarrollo con agentes

Los agentes de desarrollo deberán consultar este documento antes de crear o modificar interfaces.

No deberán introducir arbitrariamente:

* nuevos colores;
* nuevas tipografías;
* nuevos estilos de botones;
* nuevas estructuras de navegación;
* nuevas bibliotecas de iconos;
* emojis como iconografía funcional;
* estilos CSS embebidos en Views;
* dependencias externas innecesarias.

Cuando una decisión no esté contemplada en este documento, el agente deberá priorizar:

1. reutilización;
2. consistencia;
3. simplicidad;
4. accesibilidad;
5. funcionamiento offline;
6. rapidez de carga;
7. mantenimiento futuro.

---

# 49. Decisiones visuales globales

Las siguientes decisiones forman parte de la identidad visual oficial de SIGOA.

### Identidad

**Azul institucional**

`#24344C`

### Complemento

**Marrón madera**

`#8E7058`

### Fondo

**Blanco cálido**

`#F5F3F0`

### Superficie

**Blanco**

`#FFFFFF`

### Texto principal

**Gris grafito**

`#2C3036`

### Texto secundario

**Gris medio**

`#6B7078`

### Tipografía

**Inter**

### Iconografía

**Bootstrap Icons**

### Confirmación

**Verde**

`#3F7D5A`

### Advertencia

**Ocre**

`#B8862F`

### Error

**Rojo**

`#B84A4A`

### Información

**Azul informativo**

`#3B6E9E`

### Estado: PREVIO INICIO

**Blanco cálido**

`#F5F3F0`

### Estado: EN EJECUCIÓN

**Verde**

`#3F7D5A`

### Estado: NEUTRALIZADA

**Ocre**

`#B8862F`

### Estado: EN PLAZO DE CONSERVACIÓN

**Cian / celeste**

`#06B6D4`

### Estado: FINALIZADA

**Rojo**

`#B84A4A`

---

# 50. Principio visual general

La identidad visual de SIGOA puede resumirse de la siguiente manera:

```text
                    SIGOA

          ┌─────────────────────┐
          │ AZUL INSTITUCIONAL  │
          │      #24344C        │
          └─────────────────────┘
                    ↓
          IDENTIDAD / NAVEGACIÓN

                    +

          ┌─────────────────────┐
          │ BLANCO + GRISES     │
          │ #F5F3F0 / #FFFFFF   │
          └─────────────────────┘
                    ↓
               LEGIBILIDAD

                    +

          ┌─────────────────────┐
          │   MARRÓN MADERA     │
          │      #8E7058         │
          └─────────────────────┘
                    ↓
              COMPLEMENTO

                    +

          ┌─────────────────────┐
          │      VERDE          │
          │      #3F7D5A        │
          └─────────────────────┘
                    ↓
              CONFIRMACIÓN

                    +

          ┌─────────────────────┐
          │ OCRE / ROJO /       │
          │ AZUL / CIAN         │
          └─────────────────────┘
                    ↓
             ESTADOS FUNCIONALES
```

---

# 51. Principio fundamental

El diseño visual de SIGOA deberá respetar el siguiente principio:

> **El color debe ayudar a entender el sistema, no competir con la información.**

La interfaz deberá transmitir:

> **Institucionalidad + claridad + orden + funcionalidad.**

La estética deberá acompañar al trabajo de los usuarios y nunca dificultarlo.

---

# 52. Estado de las definiciones

Las definiciones visuales principales se consideran establecidas.

| Elemento                           | Estado          |
| ---------------------------------- | --------------- |
| Azul institucional                 | Definido        |
| Fondo general                      | Definido        |
| Superficie                         | Definido        |
| Texto principal                    | Definido        |
| Texto secundario                   | Definido        |
| Marrón madera                      | Definido        |
| Verde de confirmación              | Definido        |
| Ocre de advertencia                | Definido        |
| Rojo de error                      | Definido        |
| Azul informativo                   | Definido        |
| PREVIO INICIO                      | Definido        |
| EN EJECUCIÓN                       | Definido        |
| NEUTRALIZADA                       | Definido        |
| EN PLAZO DE CONSERVACIÓN           | Definido        |
| FINALIZADA                         | Definido        |
| Tipografía Inter                   | Definida        |
| Biblioteca de iconos               | Bootstrap Icons |
| Organización CSS                   | Definida        |
| Organización JavaScript            | Definida        |
| Layouts                            | Definidos       |
| Recursos locales                   | Definidos       |
| Responsive Design                  | Requerido       |
| Soporte de dispositivos móviles    | Requerido       |
| Funcionamiento offline             | Requerido       |
| Reducción de dependencias externas | Requerido       |

---

# 53. Regla final

Toda decisión visual nueva deberá intentar responder primero a esta pregunta:

> **¿Esta decisión hace que SIGOA sea más claro, consistente, rápido y fácil de utilizar?**

Si la respuesta es no, deberá evitarse aunque resulte visualmente atractiva.

SIGOA deberá priorizar siempre:

```text
CLARIDAD
   ↓
CONSISTENCIA
   ↓
USABILIDAD
   ↓
RENDIMIENTO
   ↓
IDENTIDAD
   ↓
ESTÉTICA
```

La estética es importante, pero estará subordinada a la funcionalidad, la velocidad y la facilidad de uso.
