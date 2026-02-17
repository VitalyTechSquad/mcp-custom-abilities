# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**MCP Custom Abilities for WordPress** is a WordPress plugin that provides 15+ AI-powered abilities for managing WordPress content through Claude, Claude Code, or any MCP-compatible client. The plugin integrates with WordPress Abilities API and MCP Adapter to expose tools for post management, taxonomies, media handling, and site information.

## Repository Structure

- **mcp-customs-abilities/mcp-custom-abilities.php** - Main plugin file (~1,185 lines) containing:
  - Plugin header with WordPress metadata
  - Ability category registration (`wp_abilities_api_categories_init`)
  - All 15 ability registrations via `wp_abilities_api_init` hook
  - Each ability is registered with input/output schemas and execute callbacks

## Architecture & Key Patterns

### Ability Registration Pattern

Each "ability" (AI tool) follows this structure:

```php
wp_register_ability('mcp-custom/ability-name', [
    'label' => __('Display Name', 'mcp-custom-abilities'),
    'description' => __('Human-readable description', 'mcp-custom-abilities'),
    'category' => 'content-management',
    'input_schema' => [...],      // JSON Schema defining parameters
    'output_schema' => [...],      // JSON Schema defining return structure
    'execute_callback' => function($input) { ... },
    'permission_callback' => function() { ... },
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool']
    ]
]);
```

### Available Abilities (Grouped by Type)

**Posts (6 abilities):**
- `mcp-custom/create-post` - Create new post with title, content, status, categories, tags
- `mcp-custom/update-post` - Update specific post fields
- `mcp-custom/get-post` - Retrieve full post details with metadata
- `mcp-custom/list-posts` - List posts with filters (status, category, search, author, sort)
- `mcp-custom/delete-post` - Move to trash or permanently delete
- `mcp-custom/publish-post` - Change post status to published

**Taxonomies (3 abilities):**
- `mcp-custom/list-categories` - Get all categories with post counts
- `mcp-custom/create-category` - Create new category (with parent support)
- `mcp-custom/list-tags` - Get all tags with filtering and sorting

**Media (4 abilities):**
- `mcp-custom/upload-image-from-url` - Download image from URL and upload to library
- `mcp-custom/set-featured-image` - Assign library image as post featured image
- `mcp-custom/remove-featured-image` - Remove featured image from post
- `mcp-custom/list-media` - List library images with filters

**Site Info (1 ability):**
- `mcp-custom/get-site-info` - Get basic site info (name, URL, version, timezone)

### Data Validation & Security

All abilities follow WordPress security practices:

1. **Sanitization**: Input is sanitized with appropriate functions:
   - `sanitize_text_field()` for text inputs
   - `wp_kses_post()` for HTML content
   - `sanitize_textarea_field()` for multi-line text
   - `sanitize_title()` for slugs
   - `esc_url_raw()` for URLs

2. **Permissions**: Each ability checks `current_user_can()` for required capability:
   - `edit_posts` - Create/edit posts
   - `publish_posts` - Publish posts
   - `delete_posts` - Delete posts
   - `upload_files` - Upload media
   - `manage_categories` - Create categories
   - `read` - Read-only operations

3. **Error Handling**: Functions return WordPress `WP_Error` objects which are caught and reported

### Post Type Handling

All abilities work exclusively with `post_type = 'post'`. Custom post types are not supported. When creating/updating posts, the post type is hardcoded to `'post'`.

### Media Upload Details

The `upload-image-from-url` ability:
- Uses `download_url()` to fetch remote images
- Validates MIME types (jpeg, png, gif, webp only)
- Uses `media_handle_sideload()` to process upload
- Supports assigning image as featured image in same call via `set_post_thumbnail()`
- Stores alt text in `_wp_attachment_image_alt` post meta

### Taxonomy Handling

- **Categories**: Registered to `post` post type only, accessed via `wp_get_post_categories()`
- **Tags**: Registered to `post` post type only, accessed via `wp_get_post_tags()`
- Both support creating during post creation (tags created automatically, categories by ID only)

## Common Development Tasks

### Adding a New Ability

1. Create a new `wp_register_ability()` block in the `wp_abilities_api_init` hook
2. Define clear input/output JSON schemas for the AI to understand parameters
3. Implement `execute_callback` with proper sanitization
4. Add `permission_callback` checking appropriate user capability
5. Include `'meta' => ['show_in_rest' => true, 'mcp' => ['public' => true, 'type' => 'tool']]`

### Modifying Input/Output Schemas

Schemas use JSON Schema format and are critical for:
- Telling Claude what parameters the ability accepts
- Controlling how MCP clients display the ability
- Validating input before execution

Required fields use `'required' => ['field1', 'field2']` at the root of properties.

### Testing an Ability

Since this is a WordPress plugin with no unit tests, test by:
1. Installing the plugin in a WordPress instance
2. Activating the plugin and MCP Adapter
3. Using MCP client (Claude, Claude Code) to call the ability
4. Check WordPress debug.log for PHP errors

### Translations

Text strings use `__('string', 'mcp-custom-abilities')` for i18n. The text domain is `mcp-custom-abilities`.

## Requirements & Dependencies

- **WordPress**: 6.9+
- **PHP**: 7.4+
- **Required Plugins**: MCP Adapter, WordPress Abilities API (built-in to 6.9+)
- **No external PHP dependencies** - uses only WordPress APIs

## Files to Modify When Adding Features

All code lives in a single file:
- `mcp-customs-abilities/mcp-custom-abilities.php` - Add new abilities here within the `wp_abilities_api_init` hook

## Known Limitations & Design Decisions

1. **Single file structure** - All abilities in one file. As the plugin grows, consider breaking into separate files (e.g., `includes/abilities/posts.php`)
2. **Post type hardcoded** - Only supports `post` post type, not CPTs
3. **No batch operations** - Each ability handles single item operations; Claude orchestrates batches
4. **Featured image assignment** - Restricted to existing library images or images uploaded in same call
5. **Category management** - Can only assign existing categories by ID; no updating existing categories
6. **No async operations** - All media operations are synchronous

## Debugging Tips

1. Enable WordPress debug logging: `define('WP_DEBUG_LOG', true)` in wp-config.php
2. Check debug.log for PHP errors during ability execution
3. Test abilities directly via WordPress REST API with proper auth
4. Use the MCP client's debug logs to see full request/response cycles
5. Verify user permissions: `wp_get_current_user()->caps` in execute callback

## Common Issues & Solutions

**Ability doesn't appear in MCP client:**
- Check MCP Adapter is activated
- Verify ability has `'mcp' => ['public' => true]` in meta
- Check category is registered in `wp_abilities_api_categories_init` hook
- Review WordPress error logs for registration failures

**Media upload fails:**
- Ensure uploads directory is writable (`wp-content/uploads`)
- Check remote URL is accessible and returns valid image
- Verify user has `upload_files` capability
- MIME type may not be in allowed list (jpeg, png, gif, webp only)

**Permission denied errors:**
- Verify MCP user has required capability (`current_user_can()` check failing)
- Check WordPress role/capabilities for the authenticated user
- Some capabilities require a published post to check (e.g., `publish_posts`)
