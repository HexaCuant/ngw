<!-- 
EJEMPLO DE USO DEL SISTEMA DE CONEXIONES
=========================================

Este archivo muestra cómo funciona el sistema de conexiones implementado
-->

# Ejemplo de Uso - Sistema de Conexiones

## Paso 1: Configurar el Carácter

Primero, desde la página de Caracteres:

1. Abrir o crear un carácter (ej: "Color de Ojos")
2. Crear genes para el carácter:
   - Gen: "melanina" (Cromosoma: 15, Posición: q11-q13)
   - Gen: "oca2" (Cromosoma: 15, Posición: q11.2-q12)

## Paso 2: Definir Sustratos

Los sustratos representan los diferentes estados del sistema.

**Ejemplo para "Color de Ojos":**
- S0 = Sin pigmento
- S1 = Pigmento bajo (azul)
- S2 = Pigmento medio (verde/avellana)
- S3 = Pigmento alto (marrón)

En la interfaz:
```
Número de sustratos: [4]  [Actualizar]
```

## Paso 3: Crear Conexiones

Las conexiones definen cómo los genes transforman los estados.

**Interfaz de creación:**

```
Estado inicial (S):
( ) S0  ( ) S1  ( ) S2  ( ) S3

Gen (Transición):
( ) melanina  ( ) oca2

Estado final (S):
( ) S0  ( ) S1  ( ) S2  ( ) S3

[Guardar Conexión]
```

**Ejemplo de conexiones creadas:**

| Estado A | Gen        | Estado B | Significado                           |
|----------|------------|----------|---------------------------------------|
| S0       | melanina   | S1       | Melanina básica produce pigmento bajo |
| S1       | melanina   | S2       | Más melanina aumenta pigmento         |
| S2       | melanina   | S3       | Melanina alta da marrón               |
| S1       | oca2       | S2       | OCA2 modifica de azul a verde         |

## Paso 4: Visualización

La tabla de conexiones se mostraría así:

```
┌──────────┬──────────┬──────────┬──────────┐
│ Estado A │    Gen   │ Estado B │ Acciones │
├──────────┼──────────┼──────────┼──────────┤
│   S0     │ melanina │   S1     │ [Borrar] │
│   S1     │ melanina │   S2     │ [Borrar] │
│   S2     │ melanina │   S3     │ [Borrar] │
│   S1     │   oca2   │   S2     │ [Borrar] │
└──────────┴──────────┴──────────┴──────────┘
```

## Interpretación Biológica

Este sistema modela cómo diferentes genes pueden:
- Transformar un fenotipo en otro (S0 -> S1)
- Actuar secuencialmente (S0 -> S1 -> S2 -> S3)
- Proporcionar rutas alternativas (S1 -> S2 con diferentes genes)

## Casos de Uso

### 1. Pigmentación (como en el ejemplo)
- Estados: niveles de pigmento
- Genes: enzimas que producen/modifican pigmento

### 2. Tamaño/Altura
- Estados: rangos de altura (bajo, medio, alto)
- Genes: factores de crecimiento

### 3. Metabolismo
- Estados: niveles de producción de una sustancia
- Genes: enzimas en una ruta metabólica

### 4. Resistencia/Susceptibilidad
- Estados: niveles de resistencia a enfermedad
- Genes: genes de inmunidad

## Validaciones Implementadas

✓ No se pueden crear conexiones sin sustratos definidos
✓ No se pueden crear conexiones sin genes
✓ Solo propietarios/profesores/admins pueden modificar
✓ Confirmación antes de borrar
✓ Los genes en transiciones deben pertenecer al carácter

## Estructura de Datos

En la base de datos:

```sql
-- Carácter
characters.id = 1
characters.name = "Color de Ojos"
characters.substrates = 4

-- Genes del carácter
genes.id = 10, name = "melanina"
genes.id = 11, name = "oca2"

-- Conexiones
connections (character_id=1, state_a=0, transition=10, state_b=1)
connections (character_id=1, state_a=1, transition=10, state_b=2)
connections (character_id=1, state_a=2, transition=10, state_b=3)
connections (character_id=1, state_a=1, transition=11, state_b=2)
```

## Comparación con Sistema Original (gw)

El sistema implementado en `ngw` mantiene la misma lógica que el original:

**gw (PostgreSQL):**
```php
$sql = "insert into conexiones (estadoa, transicion, estadob, car_id) 
        values ($SA, $gen, $SB, $caractivo)";
```

**ngw (SQLite):**
```php
$sql = "INSERT INTO connections (state_a, transition, state_b, character_id) 
        VALUES (:state_a, :transition, :state_b, :character_id)";
```

Las únicas diferencias son:
- Nombres de columnas en inglés (state_a vs estadoa)
- Uso de prepared statements (más seguro)
- SQLite en lugar de PostgreSQL
- Interfaz más moderna con permisos mejorados
