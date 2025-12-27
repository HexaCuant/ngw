# Sistema de Conexiones - Implementación

## Resumen

Se ha implementado completamente el sistema de conexiones para caracteres en el proyecto `ngw`, basado en la funcionalidad existente en el directorio `gw`.

## Componentes implementados

### 1. Base de Datos (`database/schema.sql`)

La tabla `connections` ya existía en el esquema con la siguiente estructura:

```sql
CREATE TABLE IF NOT EXISTS connections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    character_id INTEGER NOT NULL,
    state_a INTEGER NOT NULL,        -- Estado inicial
    transition INTEGER NOT NULL,      -- ID del gen que actúa como transición
    state_b INTEGER NOT NULL,        -- Estado final
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
);
```

### 2. Modelo de Datos (`src/Models/Character.php`)

Se verificó que los siguientes métodos ya estaban implementados:

- `getConnections(int $characterId)` - Obtiene todas las conexiones de un carácter
- `addConnection(int $characterId, int $stateA, int $transition, int $stateB)` - Crea una nueva conexión
- `removeConnection(int $connectionId)` - Elimina una conexión

### 3. Vista de Caracteres (`templates/pages/characters.php`)

Se añadieron las siguientes funcionalidades:

#### a) Botón "Ver Conexiones"
- Permite mostrar/ocultar la sección de conexiones
- Similar al botón "Ver Genes"

#### b) Sección de Conexiones
Incluye:
- **Tabla de conexiones existentes** con las columnas:
  - Estado A (S0, S1, S2, ...)
  - Gen (Transición) - muestra el nombre del gen
  - Estado B (S0, S1, S2, ...)
  - Acciones (botón Borrar para propietarios)

- **Formulario para actualizar número de sustratos**
  - Campo numérico para definir cuántos estados (sustratos) tiene el carácter
  - Botón "Actualizar" para guardar cambios

- **Formulario para añadir conexiones**
  - Selección de estado inicial (S0, S1, ..., Sn) mediante radio buttons
  - Selección del gen que actúa como transición mediante radio buttons
  - Selección de estado final (S0, S1, ..., Sn) mediante radio buttons
  - Botón "Guardar Conexión"
  
  **Validaciones:**
  - Solo se muestra si hay sustratos configurados (> 0)
  - Solo se muestra si hay genes definidos para el carácter
  - Muestra mensajes informativos si falta algún requisito

#### c) Control de Permisos
- Solo los propietarios, profesores y administradores pueden:
  - Crear conexiones
  - Eliminar conexiones
  - Modificar número de sustratos

### 4. Manejo de Acciones POST

Se implementaron las siguientes acciones en `characters.php`:

- `add_connection` - Crea una nueva conexión entre estados
- `remove_connection` - Elimina una conexión existente (con confirmación)
- `update_substrates` - Actualiza el número de sustratos del carácter

### 5. JavaScript

Se añadieron:
- Toggle para mostrar/ocultar la sección de conexiones
- Confirmación antes de eliminar conexiones

## Funcionamiento

### Flujo de trabajo para añadir conexiones:

1. **Abrir un carácter** desde la lista
2. **Crear genes** para el carácter (si no existen)
3. Click en **"Ver Conexiones"**
4. **Definir número de sustratos** (estados) del sistema
5. **Seleccionar:**
   - Estado inicial (S)
   - Gen que actúa como transición
   - Estado final (S)
6. Click en **"Guardar Conexión"**

### Ejemplo de conexión:

```
S0 -> gen_altura -> S1
```

Esto significa: "El gen de altura transforma el estado 0 en el estado 1"

## Comparación con el sistema original (gw)

La implementación en `ngw` mantiene la misma lógica que en `gw`:

| Aspecto | gw (original) | ngw (nuevo) |
|---------|---------------|-------------|
| Tabla BD | `conexiones` | `connections` |
| Campos | estadoa, transicion, estadob | state_a, transition, state_b |
| Sustratos | Campo en `caracteres` | Campo `substrates` en `characters` |
| Interfaz | Radio buttons para estados y genes | Igual |
| Permisos | Solo propietario | Propietario + admin + teacher |

## Archivos modificados

1. `/srv/http/ngw/templates/pages/characters.php` - Vista principal
   - Añadido manejo de acciones POST para conexiones
   - Añadida sección de conexiones con formularios
   - Añadido JavaScript para toggle de vista

2. `/srv/http/ngw/src/Models/Character.php` - Ya contenía los métodos necesarios
   - ✓ getConnections()
   - ✓ addConnection()
   - ✓ removeConnection()

## Pruebas

Se incluían scripts temporales para pruebas manuales (eliminados en limpieza reciente). Si necesitas pruebas automatizadas, muévelo a `tests/` o añade un script de PHPUnit en `tests/`.

## Notas técnicas

- Las conexiones se eliminan automáticamente si se elimina el carácter (CASCADE)
- El número de sustratos debe configurarse antes de poder crear conexiones
- Las transiciones solo pueden usar genes que ya estén asociados al carácter
- La interfaz valida que existan tanto sustratos como genes antes de mostrar el formulario

## Próximos pasos sugeridos

- [ ] Validar que state_a y state_b sean menores que el número de sustratos
- [ ] Añadir validación para evitar conexiones duplicadas
- [ ] Implementar visualización gráfica de las conexiones (diagrama de estados)
- [ ] Exportar/importar conexiones en formato JSON
