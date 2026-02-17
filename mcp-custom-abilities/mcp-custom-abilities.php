<?php
/**
 * Plugin Name: MCP Custom Abilities
 * Plugin URI: https://github.com/VitalyTechSquad/mcp-custom-abilities
 * Description: Abilities personalizadas para gestionar WordPress desde Claude/MCP. Permite crear, editar, eliminar posts, gestionar categorías, etiquetas e imágenes destacadas directamente desde Claude o cualquier cliente MCP.
 * Version: 2.1.0
 * Author: VitalyTech
 * Author URI: https://mododebug.vitalytech.es
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Text Domain: mcp-custom-abilities
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

define('MCP_CUSTOM_ABILITIES_VERSION', '2.0.0');
define('MCP_CUSTOM_ABILITIES_PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * Registrar categoría de abilities
 */
add_action('wp_abilities_api_categories_init', function() {
    if (function_exists('wp_register_ability_category')) {
        wp_register_ability_category('content-management', [
            'label'       => __('Gestión de Contenido', 'mcp-custom-abilities'),
            'description' => __('Abilities para crear y gestionar contenido en WordPress desde clientes MCP como Claude.', 'mcp-custom-abilities')
        ]);
    }
}, 5);

/**
 * Registrar todas las abilities
 */
add_action('wp_abilities_api_init', function() {
    
    // =========================================================================
    // POSTS - CREAR
    // =========================================================================
    wp_register_ability('mcp-custom/create-post', [
        'label'       => __('Crear Post', 'mcp-custom-abilities'),
        'description' => __('Crea un nuevo post en WordPress con título, contenido, estado, categorías, etiquetas y más.', 'mcp-custom-abilities'),
        'category'    => 'content-management',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type'        => 'string',
                    'description' => 'Título del post'
                ],
                'content' => [
                    'type'        => 'string',
                    'description' => 'Contenido del post (puede incluir HTML y bloques Gutenberg)'
                ],
                'excerpt' => [
                    'type'        => 'string',
                    'description' => 'Extracto/resumen del post para listados y SEO'
                ],
                'status' => [
                    'type'        => 'string',
                    'description' => 'Estado del post',
                    'enum'        => ['draft', 'publish', 'pending', 'private'],
                    'default'     => 'draft'
                ],
                'categories' => [
                    'type'        => 'array',
                    'items'       => ['type' => 'integer'],
                    'description' => 'Array de IDs de categorías'
                ],
                'tags' => [
                    'type'        => 'array',
                    'items'       => ['type' => 'string'],
                    'description' => 'Array de etiquetas (nombres, se crean si no existen)'
                ],
                'slug' => [
                    'type'        => 'string',
                    'description' => 'URL slug personalizado (se genera del título si no se proporciona)'
                ],
                'author' => [
                    'type'        => 'integer',
                    'description' => 'ID del autor (usa el usuario actual si no se especifica)'
                ]
            ],
            'required' => ['title', 'content']
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'success'   => ['type' => 'boolean'],
                'post_id'   => ['type' => 'integer'],
                'edit_url'  => ['type' => 'string'],
                'permalink' => ['type' => 'string']
            ]
        ],
        'execute_callback' => function($input) {
            $post_data = [
                'post_title'   => sanitize_text_field($input['title']),
                'post_content' => wp_kses_post($input['content']),
                'post_status'  => $input['status'] ?? 'draft',
                'post_type'    => 'post'
            ];
            
            if (!empty($input['excerpt'])) {
                $post_data['post_excerpt'] = sanitize_textarea_field($input['excerpt']);
            }
            if (!empty($input['slug'])) {
                $post_data['post_name'] = sanitize_title($input['slug']);
            }
            if (!empty($input['categories'])) {
                $post_data['post_category'] = array_map('intval', $input['categories']);
            }
            if (!empty($input['author'])) {
                $post_data['post_author'] = intval($input['author']);
            }
            
            $post_id = wp_insert_post($post_data, true);
            
            if (is_wp_error($post_id)) {
                return ['success' => false, 'error' => $post_id->get_error_message()];
            }
            
            // Añadir etiquetas
            if (!empty($input['tags'])) {
                wp_set_post_tags($post_id, array_map('sanitize_text_field', $input['tags']));
            }
            
            return [
                'success'   => true,
                'post_id'   => $post_id,
                'edit_url'  => admin_url('post.php?post=' . $post_id . '&action=edit'),
                'permalink' => get_permalink($post_id)
            ];
        },
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        },
        'meta' => [
            'show_in_rest' => true,
            'mcp' => ['public' => true, 'type' => 'tool']
        ]
    ]);
    
    // =========================================================================
    // POSTS - ACTUALIZAR
    // =========================================================================
    wp_register_ability('mcp-custom/update-post', [
        'label'       => __('Actualizar Post', 'mcp-custom-abilities'),
        'description' => __('Actualiza un post existente. Solo se modifican los campos proporcionados.', 'mcp-custom-abilities'),
        'category'    => 'content-management',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'post_id' => [
                    'type'        => 'integer',
                    'description' => 'ID del post a actualizar'
                ],
                'title' => [
                    'type'        => 'string',
                    'description' => 'Nuevo título'
                ],
                'content' => [
                    'type'        => 'string',
                    'description' => 'Nuevo contenido'
                ],
                'excerpt' => [
                    'type'        => 'string',
                    'description' => 'Nuevo extracto'
                ],
                'status' => [
                    'type'        => 'string',
                    'description' => 'Nuevo estado',
                    'enum'        => ['draft', 'publish', 'pending', 'private', 'trash']
                ],
                'categories' => [
                    'type'        => 'array',
                    'items'       => ['type' => 'integer'],
                    'description' => 'Nuevas categorías (reemplaza las existentes)'
                ],
                'tags' => [
                    'type'        => 'array',
                    'items'       => ['type' => 'string'],
                    'description' => 'Nuevas etiquetas (reemplaza las existentes)'
                ],
                'slug' => [
                    'type'        => 'string',
                    'description' => 'Nuevo slug URL'
                ]
            ],
            'required' => ['post_id']
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'success'   => ['type' => 'boolean'],
                'post_id'   => ['type' => 'integer'],
                'permalink' => ['type' => 'string']
            ]
        ],
        'execute_callback' => function($input) {
            $post = get_post($input['post_id']);
            if (!$post) {
                return ['success' => false, 'error' => __('Post no encontrado', 'mcp-custom-abilities')];
            }
            
            $post_data = ['ID' => intval($input['post_id'])];
            
            if (isset($input['title'])) {
                $post_data['post_title'] = sanitize_text_field($input['title']);
            }
            if (isset($input['content'])) {
                $post_data['post_content'] = wp_kses_post($input['content']);
            }
            if (isset($input['excerpt'])) {
                $post_data['post_excerpt'] = sanitize_textarea_field($input['excerpt']);
            }
            if (isset($input['status'])) {
                $post_data['post_status'] = $input['status'];
            }
            if (isset($input['categories'])) {
                $post_data['post_category'] = array_map('intval', $input['categories']);
            }
            if (isset($input['slug'])) {
                $post_data['post_name'] = sanitize_title($input['slug']);
            }
            
            $result = wp_update_post($post_data, true);
            
            if (is_wp_error($result)) {
                return ['success' => false, 'error' => $result->get_error_message()];
            }
            
            if (isset($input['tags'])) {
                wp_set_post_tags($input['post_id'], array_map('sanitize_text_field', $input['tags']));
            }
            
            return [
                'success'   => true,
                'post_id'   => $input['post_id'],
                'permalink' => get_permalink($input['post_id'])
            ];
        },
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        },
        'meta' => [
            'show_in_rest' => true,
            'mcp' => ['public' => true, 'type' => 'tool']
        ]
    ]);
    
    // =========================================================================
    // POSTS - LISTAR
    // =========================================================================
    wp_register_ability('mcp-custom/list-posts', [
        'label'       => __('Listar Posts', 'mcp-custom-abilities'),
        'description' => __('Lista posts con filtros por estado, categoría, búsqueda y ordenación.', 'mcp-custom-abilities'),
        'category'    => 'content-management',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'numberposts' => [
                    'type'        => 'integer',
                    'description' => 'Número de posts a devolver (máx. 100)',
                    'default'     => 10,
                    'minimum'     => 1,
                    'maximum'     => 100
                ],
                'post_status' => [
                    'type'        => 'string',
                    'description' => 'Filtrar por estado',
                    'enum'        => ['publish', 'draft', 'pending', 'private', 'trash', 'any'],
                    'default'     => 'any'
                ],
                'category' => [
                    'type'        => 'integer',
                    'description' => 'Filtrar por ID de categoría'
                ],
                'tag' => [
                    'type'        => 'string',
                    'description' => 'Filtrar por slug de etiqueta'
                ],
                'search' => [
                    'type'        => 'string',
                    'description' => 'Buscar en título y contenido'
                ],
                'author' => [
                    'type'        => 'integer',
                    'description' => 'Filtrar por ID de autor'
                ],
                'orderby' => [
                    'type'        => 'string',
                    'description' => 'Ordenar por campo',
                    'enum'        => ['date', 'title', 'modified', 'ID', 'rand'],
                    'default'     => 'date'
                ],
                'order' => [
                    'type'        => 'string',
                    'description' => 'Dirección del orden',
                    'enum'        => ['DESC', 'ASC'],
                    'default'     => 'DESC'
                ]
            ]
        ],
        'output_schema' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'ID'        => ['type' => 'integer'],
                    'title'     => ['type' => 'string'],
                    'status'    => ['type' => 'string'],
                    'date'      => ['type' => 'string'],
                    'modified'  => ['type' => 'string'],
                    'author'    => ['type' => 'string'],
                    'permalink' => ['type' => 'string'],
                    'edit_url'  => ['type' => 'string']
                ]
            ]
        ],
        'execute_callback' => function($input) {
            $args = [
                'numberposts' => min(intval($input['numberposts'] ?? 10), 100),
                'post_status' => $input['post_status'] ?? 'any',
                'orderby'     => $input['orderby'] ?? 'date',
                'order'       => $input['order'] ?? 'DESC'
            ];
            
            if (!empty($input['category'])) {
                $args['cat'] = intval($input['category']);
            }
            if (!empty($input['tag'])) {
                $args['tag'] = sanitize_title($input['tag']);
            }
            if (!empty($input['search'])) {
                $args['s'] = sanitize_text_field($input['search']);
            }
            if (!empty($input['author'])) {
                $args['author'] = intval($input['author']);
            }
            
            $posts = get_posts($args);
            
            return array_map(function($post) {
                $author = get_userdata($post->post_author);
                return [
                    'ID'        => $post->ID,
                    'title'     => $post->post_title,
                    'status'    => $post->post_status,
                    'date'      => $post->post_date,
                    'modified'  => $post->post_modified,
                    'author'    => $author ? $author->display_name : '',
                    'permalink' => get_permalink($post->ID),
                    'edit_url'  => admin_url('post.php?post=' . $post->ID . '&action=edit')
                ];
            }, $posts);
        },
        'permission_callback' => function() {
            return current_user_can('read');
        },
        'meta' => [
            'show_in_rest' => true,
            'mcp' => ['public' => true, 'type' => 'tool']
        ]
    ]);
    
    // =========================================================================
    // POSTS - OBTENER DETALLE
    // =========================================================================
    wp_register_ability('mcp-custom/get-post', [
        'label'       => __('Obtener Post', 'mcp-custom-abilities'),
        'description' => __('Obtiene todos los detalles de un post específico incluyendo contenido, categorías, etiquetas y metadatos.', 'mcp-custom-abilities'),
        'category'    => 'content-management',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'post_id' => [
                    'type'        => 'integer',
                    'description' => 'ID del post'
                ]
            ],
            'required' => ['post_id']
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'ID'             => ['type' => 'integer'],
                'title'          => ['type' => 'string'],
                'content'        => ['type' => 'string'],
                'excerpt'        => ['type' => 'string'],
                'status'         => ['type' => 'string'],
                'date'           => ['type' => 'string'],
                'modified'       => ['type' => 'string'],
                'slug'           => ['type' => 'string'],
                'author'         => ['type' => 'object'],
                'categories'     => ['type' => 'array'],
                'tags'           => ['type' => 'array'],
                'featured_image' => ['type' => 'object'],
                'permalink'      => ['type' => 'string'],
                'edit_url'       => ['type' => 'string']
            ]
        ],
        'execute_callback' => function($input) {
            $post = get_post($input['post_id']);
            
            if (!$post) {
                return ['success' => false, 'error' => __('Post no encontrado', 'mcp-custom-abilities')];
            }
            
            $author = get_userdata($post->post_author);
            $categories = wp_get_post_categories($post->ID, ['fields' => 'all']);
            $tags = wp_get_post_tags($post->ID, ['fields' => 'all']);
            $thumbnail_id = get_post_thumbnail_id($post->ID);
            
            $featured_image = null;
            if ($thumbnail_id) {
                $featured_image = [
                    'id'  => $thumbnail_id,
                    'url' => wp_get_attachment_url($thumbnail_id),
                    'alt' => get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true)
                ];
            }
            
            return [
                'ID'             => $post->ID,
                'title'          => $post->post_title,
                'content'        => $post->post_content,
                'excerpt'        => $post->post_excerpt,
                'status'         => $post->post_status,
                'date'           => $post->post_date,
                'modified'       => $post->post_modified,
                'slug'           => $post->post_name,
                'author'         => [
                    'id'   => $post->post_author,
                    'name' => $author ? $author->display_name : ''
                ],
                'categories'     => array_map(function($cat) {
                    return ['id' => $cat->term_id, 'name' => $cat->name, 'slug' => $cat->slug];
                }, $categories),
                'tags'           => array_map(function($tag) {
                    return ['id' => $tag->term_id, 'name' => $tag->name, 'slug' => $tag->slug];
                }, $tags),
                'featured_image' => $featured_image,
                'permalink'      => get_permalink($post->ID),
                'edit_url'       => admin_url('post.php?post=' . $post->ID . '&action=edit')
            ];
        },
        'permission_callback' => function() {
            return current_user_can('read');
        },
        'meta' => [
            'show_in_rest' => true,
            'mcp' => ['public' => true, 'type' => 'tool']
        ]
    ]);
    
    // =========================================================================
    // POSTS - ELIMINAR
    // =========================================================================
    wp_register_ability('mcp-custom/delete-post', [
        'label'       => __('Eliminar Post', 'mcp-custom-abilities'),
        'description' => __('Mueve un post a la papelera o lo elimina permanentemente.', 'mcp-custom-abilities'),
        'category'    => 'content-management',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'post_id' => [
                    'type'        => 'integer',
                    'description' => 'ID del post a eliminar'
                ],
                'force' => [
                    'type'        => 'boolean',
                    'description' => 'Si es true, elimina permanentemente. Si es false, mueve a papelera.',
                    'default'     => false
                ]
            ],
            'required' => ['post_id']
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'message' => ['type' => 'string']
            ]
        ],
        'execute_callback' => function($input) {
            $post = get_post($input['post_id']);
            
            if (!$post) {
                return ['success' => false, 'error' => __('Post no encontrado', 'mcp-custom-abilities')];
            }
            
            $force = $input['force'] ?? false;
            $result = wp_delete_post($input['post_id'], $force);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => $force 
                        ? __('Post eliminado permanentemente', 'mcp-custom-abilities')
                        : __('Post movido a la papelera', 'mcp-custom-abilities')
                ];
            }
            
            return ['success' => false, 'error' => __('No se pudo eliminar el post', 'mcp-custom-abilities')];
        },
        'permission_callback' => function() {
            return current_user_can('delete_posts');
        },
        'meta' => [
            'show_in_rest' => true,
            'mcp' => ['public' => true, 'type' => 'tool']
        ]
    ]);
    
    // =========================================================================
    // POSTS - PUBLICAR
    // =========================================================================
    wp_register_ability('mcp-custom/publish-post', [
        'label'       => __('Publicar Post', 'mcp-custom-abilities'),
        'description' => __('Cambia el estado de un post a publicado.', 'mcp-custom-abilities'),
        'category'    => 'content-management',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'post_id' => [
                    'type'        => 'integer',
                    'description' => 'ID del post a publicar'
                ]
            ],
            'required' => ['post_id']
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'success'   => ['type' => 'boolean'],
                'permalink' => ['type' => 'string']
            ]
        ],
        'execute_callback' => function($input) {
            $post = get_post($input['post_id']);
            
            if (!$post) {
                return ['success' => false, 'error' => __('Post no encontrado', 'mcp-custom-abilities')];
            }
            
            $result = wp_update_post([
                'ID'          => $input['post_id'],
                'post_status' => 'publish'
            ], true);
            
            if (is_wp_error($result)) {
                return ['success' => false, 'error' => $result->get_error_message()];
            }
            
            return [
                'success'   => true,
                'permalink' => get_permalink($input['post_id'])
            ];
        },
        'permission_callback' => function() {
            return current_user_can('publish_posts');
        },
        'meta' => [
            'show_in_rest' => true,
            'mcp' => ['public' => true, 'type' => 'tool']
        ]
    ]);
    
    // =========================================================================
    // CATEGORÍAS - LISTAR
    // =========================================================================
    wp_register_ability('mcp-custom/list-categories', [
        'label'       => __('Listar Categorías', 'mcp-custom-abilities'),
        'description' => __('Obtiene todas las categorías disponibles con su información.', 'mcp-custom-abilities'),
        'category'    => 'content-management',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'hide_empty' => [
                    'type'        => 'boolean',
                    'description' => 'Ocultar categorías sin posts',
                    'default'     => false
                ],
                'parent' => [
                    'type'        => 'integer',
                    'description' => 'Filtrar por ID de categoría padre (0 para categorías raíz)'
                ]
            ]
        ],
        'output_schema' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'id'          => ['type' => 'integer'],
                    'name'        => ['type' => 'string'],
                    'slug'        => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'parent'      => ['type' => 'integer'],
                    'count'       => ['type' => 'integer']
                ]
            ]
        ],
        'execute_callback' => function($input) {
            $args = [
                'hide_empty' => $input['hide_empty'] ?? false,
                'orderby'    => 'name',
                'order'      => 'ASC'
            ];
            
            if (isset($input['parent'])) {
                $args['parent'] = intval($input['parent']);
            }
            
            $categories = get_categories($args);
            
            return array_map(function($cat) {
                return [
                    'id'          => $cat->term_id,
                    'name'        => $cat->name,
                    'slug'        => $cat->slug,
                    'description' => $cat->description,
                    'parent'      => $cat->parent,
                    'count'       => $cat->count
                ];
            }, $categories);
        },
        'permission_callback' => function() {
            return current_user_can('read');
        },
        'meta' => [
            'show_in_rest' => true,
            'mcp' => ['public' => true, 'type' => 'tool']
        ]
    ]);
    
    // =========================================================================
    // CATEGORÍAS - CREAR
    // =========================================================================
    wp_register_ability('mcp-custom/create-category', [
        'label'       => __('Crear Categoría', 'mcp-custom-abilities'),
        'description' => __('Crea una nueva categoría en WordPress.', 'mcp-custom-abilities'),
        'category'    => 'content-management',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type'        => 'string',
                    'description' => 'Nombre de la categoría'
                ],
                'slug' => [
                    'type'        => 'string',
                    'description' => 'Slug URL (se genera del nombre si no se proporciona)'
                ],
                'description' => [
                    'type'        => 'string',
                    'description' => 'Descripción de la categoría'
                ],
                'parent' => [
                    'type'        => 'integer',
                    'description' => 'ID de la categoría padre (0 para categoría raíz)',
                    'default'     => 0
                ]
            ],
            'required' => ['name']
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'success'     => ['type' => 'boolean'],
                'category_id' => ['type' => 'integer'],
                'name'        => ['type' => 'string'],
                'slug'        => ['type' => 'string']
            ]
        ],
        'execute_callback' => function($input) {
            $args = [
                'description' => $input['description'] ?? '',
                'parent'      => $input['parent'] ?? 0
            ];
            
            if (!empty($input['slug'])) {
                $args['slug'] = sanitize_title($input['slug']);
            }
            
            $result = wp_insert_category([
                'cat_name'             => sanitize_text_field($input['name']),
                'category_description' => $args['description'],
                'category_parent'      => $args['parent'],
                'category_nicename'    => $args['slug'] ?? ''
            ]);
            
            if (is_wp_error($result)) {
                return ['success' => false, 'error' => $result->get_error_message()];
            }
            
            if ($result === 0) {
                return ['success' => false, 'error' => __('No se pudo crear la categoría', 'mcp-custom-abilities')];
            }
            
            $category = get_category($result);
            
            return [
                'success'     => true,
                'category_id' => $result,
                'name'        => $category->name,
                'slug'        => $category->slug
            ];
        },
        'permission_callback' => function() {
            return current_user_can('manage_categories');
        },
        'meta' => [
            'show_in_rest' => true,
            'mcp' => ['public' => true, 'type' => 'tool']
        ]
    ]);
    
    // =========================================================================
    // ETIQUETAS - LISTAR
    // =========================================================================
    wp_register_ability('mcp-custom/list-tags', [
        'label'       => __('Listar Etiquetas', 'mcp-custom-abilities'),
        'description' => __('Obtiene todas las etiquetas disponibles.', 'mcp-custom-abilities'),
        'category'    => 'content-management',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'hide_empty' => [
                    'type'        => 'boolean',
                    'description' => 'Ocultar etiquetas sin posts',
                    'default'     => false
                ],
                'number' => [
                    'type'        => 'integer',
                    'description' => 'Número máximo de etiquetas',
                    'default'     => 100
                ],
                'orderby' => [
                    'type'        => 'string',
                    'description' => 'Ordenar por',
                    'enum'        => ['name', 'count', 'id'],
                    'default'     => 'name'
                ],
                'order' => [
                    'type'        => 'string',
                    'description' => 'Dirección del orden',
                    'enum'        => ['ASC', 'DESC'],
                    'default'     => 'ASC'
                ]
            ]
        ],
        'output_schema' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'id'    => ['type' => 'integer'],
                    'name'  => ['type' => 'string'],
                    'slug'  => ['type' => 'string'],
                    'count' => ['type' => 'integer']
                ]
            ]
        ],
        'execute_callback' => function($input) {
            $tags = get_tags([
                'hide_empty' => $input['hide_empty'] ?? false,
                'number'     => min(intval($input['number'] ?? 100), 500),
                'orderby'    => $input['orderby'] ?? 'name',
                'order'      => $input['order'] ?? 'ASC'
            ]);
            
            if (is_wp_error($tags) || !$tags) {
                return [];
            }
            
            return array_map(function($tag) {
                return [
                    'id'    => $tag->term_id,
                    'name'  => $tag->name,
                    'slug'  => $tag->slug,
                    'count' => $tag->count
                ];
            }, $tags);
        },
        'permission_callback' => function() {
            return current_user_can('read');
        },
        'meta' => [
            'show_in_rest' => true,
            'mcp' => ['public' => true, 'type' => 'tool']
        ]
    ]);
    
    // =========================================================================
    // MEDIOS - SUBIR IMAGEN DESDE URL
    // =========================================================================
    wp_register_ability('mcp-custom/upload-image-from-url', [
        'label'       => __('Subir Imagen desde URL', 'mcp-custom-abilities'),
        'description' => __('Descarga una imagen desde una URL y la sube a la biblioteca de medios. Ideal para imágenes destacadas (recomendado: 1200x630px para redes sociales).', 'mcp-custom-abilities'),
        'category'    => 'content-management',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'url' => [
                    'type'        => 'string',
                    'description' => 'URL de la imagen a descargar'
                ],
                'filename' => [
                    'type'        => 'string',
                    'description' => 'Nombre del archivo (sin extensión). Si no se proporciona, se genera automáticamente.'
                ],
                'title' => [
                    'type'        => 'string',
                    'description' => 'Título de la imagen en la biblioteca'
                ],
                'alt_text' => [
                    'type'        => 'string',
                    'description' => 'Texto alternativo para accesibilidad y SEO'
                ],
                'caption' => [
                    'type'        => 'string',
                    'description' => 'Leyenda de la imagen'
                ],
                'post_id' => [
                    'type'        => 'integer',
                    'description' => 'Si se proporciona, asigna la imagen como destacada de este post'
                ]
            ],
            'required' => ['url']
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'success'          => ['type' => 'boolean'],
                'attachment_id'    => ['type' => 'integer'],
                'url'              => ['type' => 'string'],
                'assigned_to_post' => ['type' => 'boolean']
            ]
        ],
        'execute_callback' => function($input) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            
            $url = esc_url_raw($input['url']);
            
            // Validar URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return ['success' => false, 'error' => __('URL no válida', 'mcp-custom-abilities')];
            }
            
            // Descargar imagen temporal
            $temp_file = download_url($url);
            
            if (is_wp_error($temp_file)) {
                return ['success' => false, 'error' => __('Error descargando imagen: ', 'mcp-custom-abilities') . $temp_file->get_error_message()];
            }
            
            // Determinar extensión por MIME type
            $mime_type = mime_content_type($temp_file);
            $allowed_mimes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/gif'  => 'gif',
                'image/webp' => 'webp'
            ];
            
            if (!isset($allowed_mimes[$mime_type])) {
                @unlink($temp_file);
                return ['success' => false, 'error' => __('Tipo de imagen no soportado: ', 'mcp-custom-abilities') . $mime_type];
            }
            
            $extension = $allowed_mimes[$mime_type];
            
            // Generar nombre de archivo
            $filename = !empty($input['filename']) 
                ? sanitize_file_name($input['filename']) . '.' . $extension
                : 'imagen-' . time() . '-' . wp_rand(1000, 9999) . '.' . $extension;
            
            $file_array = [
                'name'     => $filename,
                'tmp_name' => $temp_file
            ];
            
            // Subir a la biblioteca de medios
            $attachment_id = media_handle_sideload($file_array, $input['post_id'] ?? 0);
            
            // Limpiar archivo temporal
            if (file_exists($temp_file)) {
                @unlink($temp_file);
            }
            
            if (is_wp_error($attachment_id)) {
                return ['success' => false, 'error' => __('Error subiendo imagen: ', 'mcp-custom-abilities') . $attachment_id->get_error_message()];
            }
            
            // Actualizar metadatos
            if (!empty($input['title'])) {
                wp_update_post([
                    'ID'         => $attachment_id,
                    'post_title' => sanitize_text_field($input['title'])
                ]);
            }
            
            if (!empty($input['alt_text'])) {
                update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($input['alt_text']));
            }
            
            if (!empty($input['caption'])) {
                wp_update_post([
                    'ID'           => $attachment_id,
                    'post_excerpt' => sanitize_textarea_field($input['caption'])
                ]);
            }
            
            // Asignar como imagen destacada si se proporcionó post_id
            $assigned = false;
            if (!empty($input['post_id'])) {
                $assigned = set_post_thumbnail(intval($input['post_id']), $attachment_id);
            }
            
            return [
                'success'          => true,
                'attachment_id'    => $attachment_id,
                'url'              => wp_get_attachment_url($attachment_id),
                'assigned_to_post' => (bool) $assigned
            ];
        },
        'permission_callback' => function() {
            return current_user_can('upload_files');
        },
        'meta' => [
            'show_in_rest' => true,
            'mcp' => ['public' => true, 'type' => 'tool']
        ]
    ]);
    
    // =========================================================================
    // MEDIOS - ASIGNAR IMAGEN DESTACADA
    // =========================================================================
    wp_register_ability('mcp-custom/set-featured-image', [
        'label'       => __('Asignar Imagen Destacada', 'mcp-custom-abilities'),
        'description' => __('Asigna una imagen existente de la biblioteca como imagen destacada de un post.', 'mcp-custom-abilities'),
        'category'    => 'content-management',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'post_id' => [
                    'type'        => 'integer',
                    'description' => 'ID del post'
                ],
                'attachment_id' => [
                    'type'        => 'integer',
                    'description' => 'ID de la imagen en la biblioteca de medios'
                ]
            ],
            'required' => ['post_id', 'attachment_id']
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'success'       => ['type' => 'boolean'],
                'thumbnail_url' => ['type' => 'string']
            ]
        ],
        'execute_callback' => function($input) {
            $post = get_post($input['post_id']);
            if (!$post) {
                return ['success' => false, 'error' => __('Post no encontrado', 'mcp-custom-abilities')];
            }
            
            $attachment = get_post($input['attachment_id']);
            if (!$attachment || $attachment->post_type !== 'attachment') {
                return ['success' => false, 'error' => __('Imagen no encontrada en la biblioteca', 'mcp-custom-abilities')];
            }
            
            $result = set_post_thumbnail(intval($input['post_id']), intval($input['attachment_id']));
            
            if ($result) {
                return [
                    'success'       => true,
                    'thumbnail_url' => get_the_post_thumbnail_url($input['post_id'], 'full')
                ];
            }
            
            return ['success' => false, 'error' => __('No se pudo asignar la imagen destacada', 'mcp-custom-abilities')];
        },
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        },
        'meta' => [
            'show_in_rest' => true,
            'mcp' => ['public' => true, 'type' => 'tool']
        ]
    ]);
    
    // =========================================================================
    // MEDIOS - LISTAR IMÁGENES
    // =========================================================================
    wp_register_ability('mcp-custom/list-media', [
        'label'       => __('Listar Imágenes', 'mcp-custom-abilities'),
        'description' => __('Lista las imágenes de la biblioteca de medios con filtros y búsqueda.', 'mcp-custom-abilities'),
        'category'    => 'content-management',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'number' => [
                    'type'        => 'integer',
                    'description' => 'Número de imágenes a devolver',
                    'default'     => 20,
                    'minimum'     => 1,
                    'maximum'     => 100
                ],
                'search' => [
                    'type'        => 'string',
                    'description' => 'Buscar por nombre o título'
                ],
                'mime_type' => [
                    'type'        => 'string',
                    'description' => 'Filtrar por tipo MIME',
                    'enum'        => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image'],
                    'default'     => 'image'
                ],
                'orderby' => [
                    'type'        => 'string',
                    'description' => 'Ordenar por',
                    'enum'        => ['date', 'title', 'ID'],
                    'default'     => 'date'
                ],
                'order' => [
                    'type'        => 'string',
                    'description' => 'Dirección del orden',
                    'enum'        => ['DESC', 'ASC'],
                    'default'     => 'DESC'
                ]
            ]
        ],
        'output_schema' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'id'        => ['type' => 'integer'],
                    'title'     => ['type' => 'string'],
                    'filename'  => ['type' => 'string'],
                    'url'       => ['type' => 'string'],
                    'thumbnail' => ['type' => 'string'],
                    'width'     => ['type' => 'integer'],
                    'height'    => ['type' => 'integer'],
                    'alt'       => ['type' => 'string'],
                    'date'      => ['type' => 'string']
                ]
            ]
        ],
        'execute_callback' => function($input) {
            $args = [
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'posts_per_page' => min(intval($input['number'] ?? 20), 100),
                'post_mime_type' => $input['mime_type'] ?? 'image',
                'orderby'        => $input['orderby'] ?? 'date',
                'order'          => $input['order'] ?? 'DESC'
            ];
            
            if (!empty($input['search'])) {
                $args['s'] = sanitize_text_field($input['search']);
            }
            
            $attachments = get_posts($args);
            
            return array_map(function($attachment) {
                $metadata = wp_get_attachment_metadata($attachment->ID);
                return [
                    'id'        => $attachment->ID,
                    'title'     => $attachment->post_title,
                    'filename'  => basename(get_attached_file($attachment->ID)),
                    'url'       => wp_get_attachment_url($attachment->ID),
                    'thumbnail' => wp_get_attachment_image_url($attachment->ID, 'thumbnail'),
                    'width'     => $metadata['width'] ?? 0,
                    'height'    => $metadata['height'] ?? 0,
                    'alt'       => get_post_meta($attachment->ID, '_wp_attachment_image_alt', true),
                    'date'      => $attachment->post_date
                ];
            }, $attachments);
        },
        'permission_callback' => function() {
            return current_user_can('upload_files');
        },
        'meta' => [
            'show_in_rest' => true,
            'mcp' => ['public' => true, 'type' => 'tool']
        ]
    ]);
    
    // =========================================================================
    // MEDIOS - ELIMINAR IMAGEN DESTACADA
    // =========================================================================
    wp_register_ability('mcp-custom/remove-featured-image', [
        'label'       => __('Eliminar Imagen Destacada', 'mcp-custom-abilities'),
        'description' => __('Quita la imagen destacada de un post (no elimina la imagen de la biblioteca).', 'mcp-custom-abilities'),
        'category'    => 'content-management',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'post_id' => [
                    'type'        => 'integer',
                    'description' => 'ID del post'
                ]
            ],
            'required' => ['post_id']
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean']
            ]
        ],
        'execute_callback' => function($input) {
            $post = get_post($input['post_id']);
            if (!$post) {
                return ['success' => false, 'error' => __('Post no encontrado', 'mcp-custom-abilities')];
            }
            
            delete_post_thumbnail(intval($input['post_id']));
            return ['success' => true];
        },
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        },
        'meta' => [
            'show_in_rest' => true,
            'mcp' => ['public' => true, 'type' => 'tool']
        ]
    ]);
    
    // =========================================================================
    // SITIO - INFORMACIÓN
    // =========================================================================
    wp_register_ability('mcp-custom/get-site-info', [
        'label'       => __('Información del Sitio', 'mcp-custom-abilities'),
        'description' => __('Obtiene información básica del sitio WordPress.', 'mcp-custom-abilities'),
        'category'    => 'content-management',
        'input_schema' => [
            'type' => 'object',
            'properties' => []
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'name'        => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'url'         => ['type' => 'string'],
                'admin_email' => ['type' => 'string'],
                'language'    => ['type' => 'string'],
                'version'     => ['type' => 'string'],
                'timezone'    => ['type' => 'string']
            ]
        ],
        'execute_callback' => function($input) {
            return [
                'name'        => get_bloginfo('name'),
                'description' => get_bloginfo('description'),
                'url'         => get_bloginfo('url'),
                'admin_email' => get_bloginfo('admin_email'),
                'language'    => get_bloginfo('language'),
                'version'     => get_bloginfo('version'),
                'timezone'    => wp_timezone_string()
            ];
        },
        'permission_callback' => function() {
            return current_user_can('read');
        },
        'meta' => [
            'show_in_rest' => true,
            'mcp' => ['public' => true, 'type' => 'tool']
        ]
    ]);

    // ── delete-category ─────────────────────────────────────────────────────
    wp_register_ability('mcp-custom/delete-category', [
        'label'       => __('Delete Category', 'mcp-custom-abilities'),
        'description' => __('Deletes an existing category by term ID.', 'mcp-custom-abilities'),
        'category'    => 'content-management',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'term_id' => [
                    'type'        => 'integer',
                    'description' => __('ID of the category to delete.', 'mcp-custom-abilities'),
                ],
                'force' => [
                    'type'        => 'boolean',
                    'description' => __('If true, also removes the category from orphaned posts.', 'mcp-custom-abilities'),
                    'default'     => false,
                ],
            ],
            'required' => ['term_id'],
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'message' => ['type' => 'string'],
            ],
        ],
        'execute_callback' => function($input) {
            $term_id = intval($input['term_id']);
            $category = get_category($term_id);
            if (!$category || is_wp_error($category)) {
                return ['success' => false, 'message' => __('Category not found.', 'mcp-custom-abilities')];
            }
            $result = wp_delete_category($term_id);
            if (is_wp_error($result)) {
                return ['success' => false, 'message' => $result->get_error_message()];
            }
            if ($result === false) {
                return ['success' => false, 'message' => __('Failed to delete category.', 'mcp-custom-abilities')];
            }
            return ['success' => true, 'message' => __('Category deleted successfully.', 'mcp-custom-abilities')];
        },
        'permission_callback' => function() {
            return current_user_can('manage_categories');
        },
        'meta' => [
            'show_in_rest' => true,
            'mcp' => ['public' => true, 'type' => 'tool']
        ]
    ]);

    // ── update-category ──────────────────────────────────────────────────────
    wp_register_ability('mcp-custom/update-category', [
        'label'       => __('Update Category', 'mcp-custom-abilities'),
        'description' => __('Updates name, slug, description or parent of an existing category.', 'mcp-custom-abilities'),
        'category'    => 'content-management',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'term_id'     => ['type' => 'integer', 'description' => __('ID of the category to update.', 'mcp-custom-abilities')],
                'name'        => ['type' => 'string',  'description' => __('New name for the category.', 'mcp-custom-abilities')],
                'slug'        => ['type' => 'string',  'description' => __('New slug for the category.', 'mcp-custom-abilities')],
                'description' => ['type' => 'string',  'description' => __('New description.', 'mcp-custom-abilities')],
                'parent'      => ['type' => 'integer', 'description' => __('Parent category term ID (0 for top-level).', 'mcp-custom-abilities')],
            ],
            'required' => ['term_id'],
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'term_id' => ['type' => 'integer'],
                'name'    => ['type' => 'string'],
                'slug'    => ['type' => 'string'],
            ],
        ],
        'execute_callback' => function($input) {
            $term_id = intval($input['term_id']);
            $category = get_category($term_id);
            if (!$category || is_wp_error($category)) {
                return ['success' => false, 'term_id' => $term_id, 'name' => '', 'slug' => ''];
            }
            $args = [];
            if (!empty($input['name']))        $args['name']        = sanitize_text_field($input['name']);
            if (!empty($input['slug']))        $args['slug']        = sanitize_title($input['slug']);
            if (isset($input['description']))  $args['description'] = sanitize_text_field($input['description']);
            if (isset($input['parent']))       $args['parent']      = intval($input['parent']);

            if (empty($args)) {
                return ['success' => false, 'term_id' => $term_id, 'name' => $category->name, 'slug' => $category->slug];
            }
            $result = wp_update_term($term_id, 'category', $args);
            if (is_wp_error($result)) {
                return ['success' => false, 'term_id' => $term_id, 'name' => $category->name, 'slug' => $category->slug];
            }
            $updated = get_category($result['term_id']);
            return [
                'success' => true,
                'term_id' => intval($result['term_id']),
                'name'    => $updated->name,
                'slug'    => $updated->slug,
            ];
        },
        'permission_callback' => function() {
            return current_user_can('manage_categories');
        },
        'meta' => [
            'show_in_rest' => true,
            'mcp' => ['public' => true, 'type' => 'tool']
        ]
    ]);

    // ── create-tag ───────────────────────────────────────────────────────────
    wp_register_ability('mcp-custom/create-tag', [
        'label'       => __('Create Tag', 'mcp-custom-abilities'),
        'description' => __('Creates a new post tag.', 'mcp-custom-abilities'),
        'category'    => 'content-management',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'name'        => ['type' => 'string', 'description' => __('Name of the new tag.', 'mcp-custom-abilities')],
                'slug'        => ['type' => 'string', 'description' => __('Slug for the new tag (optional, auto-generated if empty).', 'mcp-custom-abilities')],
                'description' => ['type' => 'string', 'description' => __('Description of the tag.', 'mcp-custom-abilities')],
            ],
            'required' => ['name'],
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'tag_id'  => ['type' => 'integer'],
                'name'    => ['type' => 'string'],
                'slug'    => ['type' => 'string'],
            ],
        ],
        'execute_callback' => function($input) {
            $name = sanitize_text_field($input['name']);
            $args = [];
            if (!empty($input['slug']))        $args['slug']        = sanitize_title($input['slug']);
            if (isset($input['description']))  $args['description'] = sanitize_text_field($input['description']);

            $result = wp_insert_term($name, 'post_tag', $args);
            if (is_wp_error($result)) {
                return ['success' => false, 'tag_id' => 0, 'name' => $name, 'slug' => ''];
            }
            $tag = get_term($result['term_id'], 'post_tag');
            return [
                'success' => true,
                'tag_id'  => intval($result['term_id']),
                'name'    => $tag->name,
                'slug'    => $tag->slug,
            ];
        },
        'permission_callback' => function() {
            return current_user_can('manage_categories');
        },
        'meta' => [
            'show_in_rest' => true,
            'mcp' => ['public' => true, 'type' => 'tool']
        ]
    ]);

    // ── delete-tag ───────────────────────────────────────────────────────────
    wp_register_ability('mcp-custom/delete-tag', [
        'label'       => __('Delete Tag', 'mcp-custom-abilities'),
        'description' => __('Deletes an existing post tag by term ID.', 'mcp-custom-abilities'),
        'category'    => 'content-management',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'term_id' => ['type' => 'integer', 'description' => __('ID of the tag to delete.', 'mcp-custom-abilities')],
            ],
            'required' => ['term_id'],
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'message' => ['type' => 'string'],
            ],
        ],
        'execute_callback' => function($input) {
            $term_id = intval($input['term_id']);
            $tag = get_term($term_id, 'post_tag');
            if (!$tag || is_wp_error($tag)) {
                return ['success' => false, 'message' => __('Tag not found.', 'mcp-custom-abilities')];
            }
            $result = wp_delete_term($term_id, 'post_tag');
            if (is_wp_error($result)) {
                return ['success' => false, 'message' => $result->get_error_message()];
            }
            if ($result === false) {
                return ['success' => false, 'message' => __('Failed to delete tag.', 'mcp-custom-abilities')];
            }
            return ['success' => true, 'message' => __('Tag deleted successfully.', 'mcp-custom-abilities')];
        },
        'permission_callback' => function() {
            return current_user_can('manage_categories');
        },
        'meta' => [
            'show_in_rest' => true,
            'mcp' => ['public' => true, 'type' => 'tool']
        ]
    ]);

    // ── get-current-user ─────────────────────────────────────────────────────
    wp_register_ability('mcp-custom/get-current-user', [
        'label'       => __('Get Current User', 'mcp-custom-abilities'),
        'description' => __('Returns information about the currently authenticated user.', 'mcp-custom-abilities'),
        'category'    => 'content-management',
        'input_schema' => [
            'type'       => 'object',
            'properties' => [],
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'id'                   => ['type' => 'integer'],
                'login'                => ['type' => 'string'],
                'email'                => ['type' => 'string'],
                'display_name'         => ['type' => 'string'],
                'roles'                => ['type' => 'array', 'items' => ['type' => 'string']],
                'capabilities_summary' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ],
        'execute_callback' => function($input) {
            $user = wp_get_current_user();
            if (!$user || $user->ID === 0) {
                return [
                    'id'                   => 0,
                    'login'                => '',
                    'email'                => '',
                    'display_name'         => '',
                    'roles'                => [],
                    'capabilities_summary' => [],
                ];
            }
            $caps = array_keys(array_filter($user->allcaps));
            return [
                'id'                   => intval($user->ID),
                'login'                => $user->user_login,
                'email'                => $user->user_email,
                'display_name'         => $user->display_name,
                'roles'                => $user->roles,
                'capabilities_summary' => $caps,
            ];
        },
        'permission_callback' => function() {
            return current_user_can('read');
        },
        'meta' => [
            'show_in_rest' => true,
            'mcp' => ['public' => true, 'type' => 'tool']
        ]
    ]);

}, 5);
