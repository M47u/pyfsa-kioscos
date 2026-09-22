---
name: pyfsa-architecture
description: >
  Conocimiento arquitectónico específico de PyFsa Kioscos. Usar al
  implementar nuevas funcionalidades, refactorizar módulos, modificar
  modelos, servicios, controladores, autenticación, tenancy, frontend,
  PWA/offline, auditoría, testing o estructura general del proyecto.
---

# Arquitectura PyFsa Kioscos

## Propósito

PyFsa Kioscos es una aplicación web para la gestión de comercios.

La aplicación está construida con:

- PHP
- Laravel 12
- MySQL
- Eloquent ORM
- Blade
- Tailwind CSS
- Vite
- JavaScript
- PHPUnit
- Laravel Pint
- stancl/tenancy

La arquitectura existente debe respetarse antes de introducir
nuevos patrones.

## Regla principal

Antes de implementar una solución nueva:

1. Buscar si ya existe una implementación equivalente.
2. Identificar el patrón utilizado actualmente.
3. Reutilizar servicios, policies, middleware y componentes existentes.
4. Evitar duplicar lógica.
5. Evitar introducir dependencias innecesarias.
6. Mantener compatibilidad con la arquitectura multi-tenant.

No reescribir arquitectura existente simplemente porque otra solución
parezca más moderna.

## Contextos

La aplicación tiene dos contextos principales:

### CENTRAL

Responsable de información global de la plataforma.

### TENANT

Responsable de la operación individual de cada Comercio.

Antes de modificar cualquier módulo determinar explícitamente
en qué contexto funciona.

## Capas

Respetar la separación existente entre:

- rutas
- middleware
- controllers
- requests
- policies
- models
- services
- jobs
- events/listeners
- views
- JavaScript
- infraestructura

No mover lógica entre capas sin una razón concreta.

## Controladores

Los controladores deben mantenerse enfocados.

Evitar colocar grandes cantidades de lógica de negocio directamente
en controllers.

Antes de crear lógica compleja:

1. Buscar un Service existente.
2. Buscar un Action existente.
3. Buscar una Policy existente.
4. Buscar un patrón equivalente.

Reutilizar antes de crear.

## Models

Los modelos Eloquent deben representar correctamente su contexto.

Antes de modificar un modelo:

- determinar CENTRAL/TENANT
- revisar conexión
- revisar relaciones
- revisar scopes
- revisar casts
- revisar policies
- revisar factories

No agregar relaciones o atributos sin analizar su impacto en tenancy.

## Services

Los Services existentes deben reutilizarse cuando encapsulen
lógica de negocio relacionada.

Evitar crear múltiples Services que hagan esencialmente lo mismo.

Antes de crear un Service:

1. Buscar implementaciones similares.
2. Revisar responsabilidades existentes.
3. Determinar si realmente existe una nueva responsabilidad.

## Autenticación y autorización

No confundir:

- autenticación
- autorización
- tenancy

Que un usuario esté autenticado no significa que pueda acceder
a cualquier Comercio.

Toda funcionalidad administrativa debe respetar los mecanismos
existentes de autorización.

No implementar permisos directamente en Blade como sustituto
de Policies o middleware.

## Auditoría

Cuando una operación sea sensible y la arquitectura existente
requiera auditoría:

- utilizar el mecanismo existente
- mantener usuario
- mantener contexto
- registrar la operación de manera consistente

No crear un sistema paralelo de auditoría.

## PWA y Offline

El proyecto contiene funcionalidad de frontend relacionada con:

- Service Worker
- IndexedDB
- operaciones offline
- sincronización

Antes de modificar estos componentes:

1. Entender el flujo online.
2. Entender qué datos se almacenan localmente.
3. Entender la sincronización.
4. Entender cómo se identifica el dispositivo.
5. Revisar idempotencia.
6. Revisar conflictos.
7. Revisar seguridad.

Nunca asumir que una operación offline equivale simplemente
a repetir una petición HTTP.

## JavaScript

El frontend utiliza JavaScript junto con Blade/Vite.

Antes de introducir un framework frontend adicional:

- verificar si realmente es necesario
- revisar componentes existentes
- respetar el stack actual

No introducir React/Vue/Angular simplemente para resolver
una funcionalidad pequeña.

## Tailwind

Mantener las convenciones visuales existentes.

Antes de crear componentes visuales:

1. Buscar componentes similares.
2. Reutilizar clases y patrones existentes.
3. Evitar duplicación.
4. Mantener responsive design.

## Testing

El proyecto utiliza PHPUnit/Laravel testing.

Toda funcionalidad significativa debe considerar tests.

Antes de escribir tests:

- revisar tests similares
- utilizar factories existentes
- respetar configuración de testing
- respetar tenancy
- respetar MySQL

No cambiar la infraestructura de testing solamente para simplificar
un test.

## Base de datos

Antes de modificar una tabla:

1. Revisar migración existente.
2. Revisar modelo.
3. Revisar relaciones.
4. Revisar factories.
5. Revisar seeders.
6. Revisar tests.
7. Determinar CENTRAL/TENANT.

Las migraciones deben ser compatibles con instalaciones existentes.

Evitar modificar destructivamente estructuras existentes sin
considerar migraciones de transición.

## Dependencias

Antes de agregar una dependencia:

1. Comprobar si Laravel ya proporciona la funcionalidad.
2. Comprobar si Composer ya contiene una dependencia equivalente.
3. Comprobar si el proyecto ya tiene una solución interna.
4. Evaluar mantenimiento y compatibilidad.

No agregar paquetes por conveniencia sin necesidad.

## Git

Antes de realizar modificaciones importantes:

- revisar git status
- revisar branch actual
- revisar cambios existentes

No sobrescribir cambios del desarrollador.

No ejecutar comandos destructivos de Git sin autorización explícita.

No hacer commits automáticamente salvo que el usuario lo solicite
o utilice explícitamente un comando de commit.

## Seguridad

Nunca:

- exponer secretos
- modificar `.env` innecesariamente
- introducir credenciales en código
- desactivar autenticación para simplificar tests
- desactivar tenancy para simplificar desarrollo
- saltarse Policies
- confiar ciegamente en datos enviados por el cliente

## Proceso recomendado

Para cualquier feature:

### Fase 1 — Comprender

- leer CLAUDE.md
- revisar arquitectura
- localizar código relacionado
- revisar tests

### Fase 2 — Planificar

Definir:

- archivos a modificar
- archivos a crear
- impacto en base de datos
- impacto en tenancy
- impacto en seguridad
- tests necesarios

### Fase 3 — Implementar

Modificar solamente lo necesario.

Mantener patrones existentes.

### Fase 4 — Verificar

Ejecutar:

- tests relevantes
- Laravel Pint cuando corresponda
- validaciones frontend cuando corresponda

### Fase 5 — Revisar

Comprobar:

- tenancy
- autorización
- regresiones
- duplicación
- seguridad
- compatibilidad

## Regla de oro

La arquitectura existente de PyFsa es una restricción del proyecto,
no una sugerencia.

Una solución técnicamente válida pero incompatible con las
decisiones arquitectónicas existentes no debe implementarse sin
consultar primero al desarrollador.