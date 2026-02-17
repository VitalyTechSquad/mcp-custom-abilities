# MCP Custom Abilities for WordPress

<p align="center">
  <img src="https://img.shields.io/badge/WordPress-6.9%2B-blue?logo=wordpress" alt="WordPress 6.9+">
  <img src="https://img.shields.io/badge/PHP-7.4%2B-purple?logo=php" alt="PHP 7.4+">
  <img src="https://img.shields.io/badge/MCP-Compatible-green" alt="MCP Compatible">
  <img src="https://img.shields.io/badge/License-GPL%20v2-orange" alt="License GPL v2">
</p>

Plugin de WordPress que proporciona **abilities personalizadas** para gestionar tu sitio desde **Claude**, **Claude Code** o cualquier cliente compatible con el **Model Context Protocol (MCP)**.

Permite crear, editar, eliminar posts, gestionar categorías, etiquetas e imágenes destacadas directamente desde una conversación con IA, sin necesidad de acceder al panel de WordPress.

---

## 📋 Tabla de Contenidos

- [Características](#-características)
- [Requisitos](#-requisitos)
- [Instalación](#-instalación)
- [Configuración](#-configuración)
- [Abilities Disponibles](#-abilities-disponibles)
- [Casos de Uso](#-casos-de-uso)
- [Ejemplos de Uso](#-ejemplos-de-uso)
- [Seguridad](#-seguridad)
- [FAQ](#-faq)
- [Contribuir](#-contribuir)
- [Changelog](#-changelog)
- [Licencia](#-licencia)

---

## ✨ Características

- 🚀 **Gestión completa de posts** - Crear, leer, actualizar, eliminar y publicar
- 🏷️ **Taxonomías** - Gestionar categorías y etiquetas
- 🖼️ **Medios** - Subir imágenes desde URL, gestionar imágenes destacadas
- 🔒 **Seguro** - Respeta los permisos de usuario de WordPress
- 🌐 **Multilenguaje** - Preparado para traducción
- ⚡ **Ligero** - Sin dependencias externas, código optimizado
- 🔌 **Plug & Play** - Funciona inmediatamente con MCP Adapter

---

## 📦 Requisitos

| Requisito | Versión |
|-----------|---------|
| WordPress | 6.9 o superior |
| PHP | 7.4 o superior |
| [MCP Adapter](https://github.com/WordPress/mcp-adapter) | 0.4.0 o superior |
| [AI Experiments](https://wordpress.org/plugins/ai/) (opcional) | 0.3.0 o superior |

### Plugins Requeridos

1. **MCP Adapter** - Puente entre WordPress y el protocolo MCP
   - Instalar desde: https://github.com/WordPress/mcp-adapter
   - O vía Composer: `composer require wordpress/mcp-adapter`

2. **WordPress Abilities API** - Incluida en WordPress 6.9+ o disponible como plugin separado

---

## 🔧 Instalación

### Opción 1: Descarga directa

1. Descarga el archivo ZIP del plugin desde [Releases](https://github.com/VitalyTechSquad/mcp-custom-abilities/releases)
2. Ve a **Plugins → Añadir nuevo → Subir plugin** en tu WordPress
3. Sube el archivo ZIP y activa el plugin

### Opción 2: Vía Git

```bash
cd wp-content/plugins/
git clone https://github.com/VitalyTechSquad/mcp-custom-abilities.git
```

### Opción 3: Vía Composer

```bash
composer require vitalytech-squad/mcp-custom-abilities
```

### Activación

1. Ve a **Plugins** en el panel de WordPress
2. Activa **MCP Custom Abilities**
3. Asegúrate de que **MCP Adapter** también está activo

---

## ⚙️ Configuración

### Configuración del Cliente MCP (Claude Desktop / Claude Code)

Añade la configuración del servidor MCP en tu cliente. Ejemplo para Claude Desktop:

```json
{
  "mcpServers": {
    "wordpress": {
      "command": "npx",
      "args": ["-y", "@anthropic/mcp-wordpress"],
      "env": {
        "WORDPRESS_URL": "https://tu-sitio.com",
        "WORDPRESS_USERNAME": "tu-usuario",
        "WORDPRESS_PASSWORD": "tu-application-password"
      }
    }
  }
}
```

### Crear Application Password en WordPress

1. Ve a **Usuarios → Tu Perfil**
2. Busca la sección **Application Passwords**
3. Crea una nueva contraseña y guárdala

---

## 🛠️ Abilities Disponibles

### Posts

| Ability | Descripción |
|---------|-------------|
| `mcp-custom/create-post` | Crea un nuevo post con título, contenido, categorías, etiquetas, extracto y slug |
| `mcp-custom/update-post` | Actualiza cualquier campo de un post existente |
| `mcp-custom/get-post` | Obtiene todos los detalles de un post (contenido, meta, taxonomías) |
| `mcp-custom/list-posts` | Lista posts con filtros por estado, categoría, autor, búsqueda |
| `mcp-custom/delete-post` | Mueve a papelera o elimina permanentemente |
| `mcp-custom/publish-post` | Publica un borrador rápidamente |

### Categorías y Etiquetas

| Ability | Descripción |
|---------|-------------|
| `mcp-custom/list-categories` | Lista todas las categorías con conteo de posts |
| `mcp-custom/create-category` | Crea una nueva categoría (con soporte para jerarquía) |
| `mcp-custom/list-tags` | Lista todas las etiquetas |

### Medios e Imágenes

| Ability | Descripción |
|---------|-------------|
| `mcp-custom/upload-image-from-url` | Descarga imagen desde URL y la sube a WordPress |
| `mcp-custom/set-featured-image` | Asigna una imagen existente como destacada |
| `mcp-custom/remove-featured-image` | Quita la imagen destacada de un post |
| `mcp-custom/list-media` | Lista imágenes de la biblioteca con filtros |

### Información del Sitio

| Ability | Descripción |
|---------|-------------|
| `mcp-custom/get-site-info` | Obtiene información básica del sitio |

---

## 💡 Casos de Uso

### 1. Publicación rápida desde Claude

> "Crea un borrador de post sobre las novedades de WordPress 6.9 con las etiquetas 'wordpress' y 'actualizaciones'"

Claude usará `mcp-custom/create-post` para crear el borrador directamente.

### 2. Gestión de contenido por lotes

> "Lista todos los posts en borrador del último mes"

Claude usará `mcp-custom/list-posts` con los filtros apropiados.

### 3. Workflow completo de publicación

> "Genera un artículo sobre IA en WordPress, busca una imagen relacionada, súbela como destacada y publícalo"

Claude encadenará:
1. `mcp-custom/create-post` (borrador)
2. `mcp-custom/upload-image-from-url` (con imagen generada o de URL)
3. `mcp-custom/publish-post`

### 4. Actualización masiva de metadatos

> "Añade la etiqueta 'featured' a todos los posts de la categoría 'Tutoriales'"

Claude combinará:
1. `mcp-custom/list-posts` (filtrar por categoría)
2. `mcp-custom/update-post` (para cada post)

### 5. Auditoría de contenido

> "Dame un resumen de todos los posts sin imagen destacada"

Claude usará `mcp-custom/list-posts` y `mcp-custom/get-post` para identificarlos.

---

## 📝 Ejemplos de Uso

### Crear un post completo

```
Usuario: Crea un post titulado "Guía de MCP para WordPress" con el contenido 
         que te proporciono, en la categoría "Tutoriales" (ID: 5), con las 
         etiquetas "mcp", "wordpress", "ia", y publícalo directamente.

Claude ejecutará:
- mcp-custom/create-post con:
  {
    "title": "Guía de MCP para WordPress",
    "content": "...",
    "categories": [5],
    "tags": ["mcp", "wordpress", "ia"],
    "status": "publish"
  }
```

### Subir imagen destacada desde URL

```
Usuario: Sube esta imagen https://ejemplo.com/imagen.jpg como destacada 
         del post 123, con alt text "Diagrama de arquitectura MCP"

Claude ejecutará:
- mcp-custom/upload-image-from-url con:
  {
    "url": "https://ejemplo.com/imagen.jpg",
    "post_id": 123,
    "alt_text": "Diagrama de arquitectura MCP",
    "title": "Arquitectura MCP"
  }
```

### Buscar y actualizar posts

```
Usuario: Busca posts que contengan "WordPress 6.8" y actualiza 
         el título para que diga "WordPress 6.9"

Claude ejecutará:
1. mcp-custom/list-posts con {"search": "WordPress 6.8"}
2. mcp-custom/update-post para cada resultado
```

---

## 🔒 Seguridad

### Permisos

Cada ability verifica los permisos del usuario autenticado:

| Acción | Capability Requerida |
|--------|---------------------|
| Crear/Editar posts | `edit_posts` |
| Publicar posts | `publish_posts` |
| Eliminar posts | `delete_posts` |
| Subir medios | `upload_files` |
| Gestionar categorías | `manage_categories` |
| Leer contenido | `read` |

### Validación de Datos

- Todos los inputs se sanitizan con funciones nativas de WordPress
- URLs se validan antes de descargar
- Los tipos MIME de imágenes se verifican
- IDs se convierten a enteros

### Recomendaciones

1. **Usa Application Passwords** en lugar de contraseñas reales
2. **Limita los permisos** del usuario MCP al mínimo necesario
3. **Usa HTTPS** siempre en producción
4. **Revisa los logs** de actividad periódicamente

---

## ❓ FAQ

### ¿Funciona con WordPress.com?

No directamente. WordPress.com no permite plugins personalizados en planes gratuitos. Necesitas WordPress.org (self-hosted) o un plan Business de WordPress.com.

### ¿Puedo añadir mis propias abilities?

¡Sí! Usa el hook `wp_abilities_api_init`:

```php
add_action('wp_abilities_api_init', function() {
    wp_register_ability('mi-plugin/mi-ability', [
        'label' => 'Mi Ability',
        'description' => 'Descripción...',
        'category' => 'content-management',
        'input_schema' => [...],
        'execute_callback' => function($input) {
            // Tu lógica aquí
            return ['success' => true];
        },
        'permission_callback' => fn() => current_user_can('edit_posts'),
        'meta' => [
            'show_in_rest' => true,
            'mcp' => ['public' => true, 'type' => 'tool']
        ]
    ]);
});
```

### ¿Por qué mis abilities no aparecen en MCP?

Verifica que:
1. El plugin MCP Adapter está activo
2. Tu ability tiene `'mcp' => ['public' => true]` en meta
3. La categoría está registrada en `wp_abilities_api_categories_init`
4. No hay errores PHP (revisa debug.log)

### ¿Qué tamaño de imagen recomiendas?

Para imágenes destacadas optimizadas para redes sociales: **1200x630 píxeles**.

---

## 🤝 Contribuir

¡Las contribuciones son bienvenidas!

1. Fork el repositorio
2. Crea una rama para tu feature (`git checkout -b feature/nueva-ability`)
3. Commit tus cambios (`git commit -am 'Añade nueva ability'`)
4. Push a la rama (`git push origin feature/nueva-ability`)
5. Abre un Pull Request

### Reportar Bugs

Usa [GitHub Issues](https://github.com/VitalyTechSquad/mcp-custom-abilities/issues) para reportar bugs o sugerir mejoras.

---

## 📜 Changelog

### 2.0.0 (2026-02-18)
- ✨ Versión inicial pública
- 📝 15 abilities para gestión completa de contenido
- 🖼️ Soporte para subida de imágenes desde URL
- 🏷️ Gestión de categorías y etiquetas
- 🔒 Validación completa de permisos

---

## 📄 Licencia

Este plugin está licenciado bajo [GPL v2 o posterior](https://www.gnu.org/licenses/gpl-2.0.html).

```
MCP Custom Abilities for WordPress
Copyright (C) 2026 VitalyTech

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
```

---

## 🙏 Créditos

- [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) - El puente que hace esto posible
- [WordPress Abilities API](https://github.com/WordPress/abilities-api) - La API de abilities de WordPress
- [Anthropic](https://anthropic.com) - Creadores de Claude y el protocolo MCP
- [VitalyTech](https://mododebug.vitalytech.es) - Desarrollo y mantenimiento

---

<p align="center">
  Hecho con ❤️ por <a href="https://mododebug.vitalytech.es">VitalyTech</a>
</p>
