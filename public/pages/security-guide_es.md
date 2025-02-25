# Guía de seguridad para SqlSanitizerTrait

Este documento proporciona directrices sobre el uso seguro del `SqlSanitizerTrait` para prevenir inyecciones SQL.

## Principios básicos

El trait `SqlSanitizerTrait` está diseñado para sanitizar identificadores SQL (nombres de tablas, columnas, etc.) y expresiones SQL de mediana complejidad. Aunque proporciona protección contra muchas formas de inyección SQL, es importante entender sus límites y casos de uso.

## Uso seguro

### ✅ Casos de uso recomendados

- **Nombres de columnas y tablas**:
  ```php
  $query->select('column_name');
  ```

- **Columnas calificadas**:
  ```php
  $query->select('table.column');
  ```

- **Expresiones con alias**:
  ```php
  $query->select('column AS alias');
  ```

- **Funciones de agregación estándar**:
  ```php
  $query->select('COUNT(*) AS total');
  ```

### ⚠️ Casos que requieren especial atención

- **Funciones SQL complejas**: Asegúrate de que los argumentos están correctamente sanitizados.
  ```php
  $query->select('COALESCE(column1, column2, 0) AS result');
  ```

- **Expresiones con operadores aritméticos**: Pueden funcionar pero verifica los resultados.
  ```php
  $query->select('price * quantity AS total');
  ```

## Prevención específica de inyecciones SQL

El sanitizador no puede garantizar seguridad absoluta si se ejecuta SQL dinámico con concatenación en lugar de parámetros preparados.

### Parámetros vs. Identificadores

Es importante entender la distinción:

- **Parámetros** (valores): Deben ser pasados a través de parámetros preparados, no sanitizados con este trait.
- **Identificadores** (nombres de columnas/tablas): Deben ser sanitizados con este trait.

## Buenas prácticas adicionales

1. **Emplea el principio de mínimo privilegio** para las conexiones a base de datos.
2. **Utiliza listas blancas** para nombres de columnas y tablas válidos.
3. **Mantén actualizados** los componentes de base de datos.
4. **Registra y monitoriza** consultas inusuales o errores de base de datos.
5. **Prueba regularmente** con herramientas de escaneo de seguridad.
