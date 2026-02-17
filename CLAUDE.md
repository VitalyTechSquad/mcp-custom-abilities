# CLAUDE.md

Este fichero proporciona contexto a Claude Code (claude.ai/code) para trabajar con el código de este repositorio.

## Descripcion del Proyecto

**MCP Custom Abilities** — Plugin de WordPress (GPL v2) que expone 19 abilities MCP para gestionar posts, taxonomías, medios e información del sitio desde Claude o cualquier cliente MCP.

**Repositorio:** https://github.com/VitalyTechSquad/mcp-custom-abilities
**Version:** 2.1.0 | **WordPress:** 6.9+ | **PHP:** 7.4+

Para requisitos completos, instalación, configuración del cliente MCP y lista detallada de abilities, consultar [README.md](README.md).

## Setup para Desarrollo

```bash
# Clonar e instalar en WordPress
git clone https://github.com/VitalyTechSquad/mcp-custom-abilities.git
cp -r mcp-custom-abilities/ /ruta/a/wp-content/plugins/

# Activar con WP-CLI
wp plugin activate mcp-custom-abilities
wp plugin activate mcp-adapter
```

## Arquitectura

Plugin de un solo fichero: `mcp-custom-abilities/mcp-custom-abilities.php` (~1380 líneas)

**Hooks utilizados (en orden):**
1. `wp_abilities_api_categories_init` (prioridad 5) — registra la categoría `content-management`
2. `wp_abilities_api_init` (prioridad 5) — registra las 19 abilities

Cada ability es una llamada a `wp_register_ability()` con: `input_schema`, `output_schema`, `execute_callback`, `permission_callback` y `meta` (debe incluir `'mcp' => ['public' => true, 'type' => 'tool']`).

## Gotchas

- **Post type hardcodeado a `'post'`** — no soporta CPTs. Está fijado en las llamadas a `wp_insert_post()`
- **Categorías solo por ID** — las tags aceptan nombres (se auto-crean), pero las categorías requieren IDs de términos existentes
- **Whitelist de MIME en medios**: Solo `image/jpeg`, `image/png`, `image/gif`, `image/webp` — otros tipos se rechazan silenciosamente
- **Sin operaciones batch** — Claude debe orquestar operaciones multi-item llamando a las abilities en secuencia

## Añadir una Nueva Ability

Añadir un nuevo bloque `wp_register_ability()` dentro del callback `wp_abilities_api_init` (~línea 39). Seguir el patrón existente:

1. Usar convención de nombres `'mcp-custom/nombre-ability'`
2. Definir `input_schema` y `output_schema` como objetos JSON Schema
3. Sanitizar todo input en `execute_callback` (usar `sanitize_text_field`, `wp_kses_post`, `intval`, etc.)
4. Establecer `permission_callback` con el check `current_user_can()` apropiado
5. Incluir siempre `'meta' => ['show_in_rest' => true, 'mcp' => ['public' => true, 'type' => 'tool']]`
6. Usar text domain `'mcp-custom-abilities'` para todas las cadenas traducibles

## Depuración

```php
// En wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

- Revisar `wp-content/debug.log` para errores PHP durante la ejecución de abilities
- Verificar permisos: añadir `error_log(print_r(wp_get_current_user()->caps, true));` en un callback
- Si una ability no aparece en el cliente MCP: comprobar que MCP Adapter está activo, verificar `meta.mcp.public = true`, y asegurar que la categoría está registrada
