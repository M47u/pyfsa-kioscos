---
name: pyfsa-multi-tenancy
description: >
  Reglas y procedimientos específicos para trabajar con el sistema
  multi-tenant de PyFsa Kioscos. Usar obligatoriamente al modificar
  tenancy, middleware, modelos, consultas, rutas tenant, conexiones
  de base de datos, migraciones tenant, autenticación o cualquier
  funcionalidad que pueda acceder a datos de un Comercio.
---

# PyFsa Multi-tenancy

## Propósito

Este proyecto utiliza una arquitectura multi-tenant basada en
database-per-tenant mediante stancl/tenancy.

Cada Comercio posee su propia base de datos tenant.

La separación entre datos centrales y datos tenant es una regla
fundamental de seguridad y arquitectura.

Nunca modificar esta arquitectura sin una decisión explícita del
desarrollador.

## Arquitectura

Existen dos contextos de datos:

### Base central

La base central contiene información global de la aplicación.

Incluye, entre otros:

- usuarios administrativos
- comercios
- información necesaria para resolver tenancy
- suscripciones
- información global
- auditoría cuando corresponda

Los modelos que pertenecen exclusivamente a la base central deben
mantener explícitamente su conexión central cuando la arquitectura
existente así lo requiera.

### Base tenant

Cada Comercio posee una base de datos independiente.

Los datos operativos del Comercio viven en su base tenant.

Ejemplos:

- productos
- clientes
- ventas
- pagos
- stock
- movimientos
- configuraciones específicas del comercio

Nunca asumir que dos comercios comparten sus datos operativos.

## Regla fundamental

Toda operación que acceda a datos de un Comercio debe ejecutarse
dentro del contexto tenant correcto.

Nunca confiar únicamente en un identificador proporcionado por el
cliente para determinar el tenant.

El tenant debe provenir del mecanismo de tenancy establecido por la
aplicación.

## Aislamiento

Nunca permitir:

- consultas de un tenant utilizando la conexión de otro tenant
- acceso cruzado entre comercios
- modelos tenant ejecutados accidentalmente contra la base central
- modelos centrales ejecutados accidentalmente contra una base tenant
- rutas tenant que puedan ejecutarse antes de inicializar tenancy

## Middleware

Antes de modificar middleware relacionado con tenancy:

1. Identificar cómo se resuelve actualmente el Comercio.
2. Identificar qué middleware inicializa tenancy.
3. Verificar el orden de ejecución.
4. Verificar autenticación y autorización.
5. Verificar route model binding.
6. Verificar que los modelos involucrados utilicen la conexión correcta.

No cambiar el orden del middleware sin comprobar sus consecuencias.

## Route Model Binding

El route model binding debe analizarse cuidadosamente dentro del
contexto tenant.

Antes de modificar una ruta que utiliza binding:

1. Determinar si el modelo es central o tenant.
2. Determinar cuándo se inicializa tenancy.
3. Determinar qué conexión utiliza el modelo.
4. Verificar que un usuario no pueda obtener un modelo perteneciente
   a otro Comercio.

## Modelos

Antes de modificar un modelo:

1. Determinar si pertenece a CENTRAL o TENANT.
2. Determinar qué conexión debe utilizar.
3. Revisar relaciones Eloquent.
4. Revisar casts.
5. Revisar scopes.
6. Revisar factories y tests relacionados.

No agregar una conexión explícita ni eliminarla sin comprender
por qué existe.

## Consultas

Toda consulta que trabaje con información de un Comercio debe
respetar el contexto tenant.

Evitar patrones que permitan consultar directamente una base tenant
sin haber inicializado correctamente el contexto.

No crear mecanismos paralelos de selección de tenant.

## Migraciones

Distinguir siempre entre:

- migraciones centrales
- migraciones tenant

Antes de crear o modificar una migración:

1. Determinar a qué contexto pertenece.
2. Revisar cómo se ejecuta actualmente.
3. Verificar impacto sobre instalaciones existentes.
4. Verificar impacto sobre tests.
5. No mezclar estructuras centrales y tenant.

## Base de datos

No introducir `CREATE DATABASE` desde la lógica normal de la
aplicación salvo que exista una razón explícita y documentada.

No cambiar database-per-tenant por shared-database.

No introducir SQLite como sustituto de MySQL en tests de tenancy
sin una decisión explícita.

## Seguridad

Considerar cualquier límite de tenant como un límite de seguridad.

Ante cualquier modificación relacionada con:

- autenticación
- autorización
- usuarios
- rutas
- controladores
- modelos
- consultas
- APIs
- importaciones
- sincronización offline

verificar que no exista posibilidad de acceso cruzado entre
Comercios.

## Antes de modificar código

Antes de implementar una funcionalidad relacionada con tenancy:

1. Buscar implementaciones existentes similares.
2. Leer el middleware involucrado.
3. Leer los modelos involucrados.
4. Revisar las rutas.
5. Revisar las migraciones.
6. Revisar los tests.
7. Determinar explícitamente CENTRAL vs TENANT.

No inventar una nueva estrategia si ya existe una implementación
establecida.

## Tests obligatorios

Toda modificación que pueda afectar el aislamiento tenant debe
considerar tests para:

- acceso válido dentro del tenant
- rechazo de acceso incorrecto
- aislamiento entre dos Comercios
- conexión correcta
- middleware correcto
- route model binding
- autorización

Los tests deben respetar la infraestructura real del proyecto.

No reemplazar silenciosamente MySQL por SQLite.

## Regla final

Cuando exista duda sobre si una operación pertenece al contexto
central o tenant, detener la implementación y analizar primero la
arquitectura existente.

La seguridad del aislamiento entre Comercios tiene prioridad sobre
la simplicidad de implementación.