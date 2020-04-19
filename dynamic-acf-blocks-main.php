<?php

if (!function_exists('add_filter')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

define('DYNAMIC_ACF_BLOCKS_VERSION', '0.1');


if (!defined('DYNAMIC_ACF_BLOCKS_PATH')) {
    define('DYNAMIC_ACF_BLOCKS_PATH', plugin_dir_path(DYNAMIC_ACF_BLOCKS_FILE));
}

if (!defined('DYNAMIC_ACF_BLOCKS_BASENAME')) {
    define('DYNAMIC_ACF_BLOCKS_BASENAME', plugin_basename(DYNAMIC_ACF_BLOCKS_FILE));
}

if (!class_exists('DYNAMIC_ACF_BLOCKS')) {

    class DYNAMIC_ACF_BLOCKS
    {

        private $key_helper;
        private $dynamic_blocks;
        private static $logs = [];
        private static $base_key = 'dynamic_acf_blocks';
        private static $base_slug = 'dynamic-acf-blocks';
        private static $external_icon = '<i class="dashicons dashicons-external" style="font-size: inherit; line-height: inherit; text-decoration: none; height: auto;"></i>';
        private static $pre_template = '<pre style="background: #f9f9f9; padding: 15px;">%s</pre>';

        function __construct()
        {
            add_action('plugins_loaded', [$this, 'init'], 999);
        }

        function init()
        {
            $this->key_helper = new dynamic_acf_blocks_key_helper(self::$base_key);
            $this->dynamic_blocks = $this->get_blocks();

            add_action('acf/init', [$this, 'add_options_page']);
            add_action('acf/init', [$this, 'add_field_groups']);
            add_action('acf/init', [$this, 'register_acf_block_types']);

            add_filter('block_categories', [$this, 'add_block_categories'], 10, 2);
            add_filter('acf/load_field/key=' . $this->key_helper->field('entries', 'category'), [$this, 'populate_block_categories']);

            add_action('acf/save_post', [$this, 'set_options'], 20, 1);
            add_action('acf/save_post', [$this, 'save_files'], 30, 1);
            add_action('acf/save_post', [$this, 'delete_files'], 40, 1);
        }

        function log($key, $val)
        {
            self::$logs[$key] = $val;
        }

        function d($var)
        {
            echo '<pre>';
            var_export($var);
            echo '<pre>';
        }

        function dd($var)
        {
            if(is_user_logged_in()) {
                $this->d($var);
                die;
            }
        }

        function add_block_categories($categories, $post)
        {
            if (function_exists('get_field')) {
                $groups = get_field($this->key_helper->name('groups'), 'option');
                if (!empty($groups)) {
                    $categories = array_merge(
                        $categories,
                        $groups
                    );
                }
            }

            return $categories;
        }

        function partially_empty($array)
        {
            if (empty($array)) {
                return true;
            }

            $result = false;

            if (is_array($array)) {
                foreach ($array as $item) {
                    if (
                        empty($item)
                        || (is_array($item) && $this->partially_empty($item))
                    ) {
                        $result = true;
                        break;
                    }
                }
            }

            return $result;
        }

        function populate_block_categories($field)
        {
            // reset choices
            $field['choices'] = array();

            if (!function_exists('get_block_categories')) {
                include ABSPATH . 'wp-admin/includes/post.php';
            }

            if (function_exists('get_block_categories')) {
                $recent_posts = wp_get_recent_posts(array('numberposts' => '1', 'post_type' => ['post', 'page']));
                if (empty($recent_posts)) {
                    return $field;
                }

                $categories = get_block_categories($recent_posts[0]['ID']);
                if (!empty($categories)) {
                    foreach ($categories as $category) {
                        $field['choices'][$category['slug']] = esc_html($category['title']);
                    }
                }
            }

            // return the field
            return $field;
        }

        function get_blocks()
        {
            $blocks = null;

            $blocks = get_option($this->key_helper->name('entries'));

            return apply_filters('dynamic_acf_blocks_entries', $blocks);
        }

        function add_options_page()
        {
            if (function_exists('acf_add_options_page')) {
                acf_add_options_page(array(
                    'page_title' => __('ACF Blocks', 'dynamic-acf-blocks'),
                    'menu_title' => __('ACF Blocks', 'dynamic-acf-blocks'),
                    'menu_slug' => self::$base_slug,
                    'capability' => 'manage_options',
                    'redirect' => false,
                    'icon_url' => 'dashicons-schedule'
                ));
            }

            if (function_exists('acf_add_options_sub_page')) {
                acf_add_options_sub_page(array(
                    'page_title' => __('Additional Block Groups', 'dynamic-acf-blocks'),
                    'menu_title' => __('Block Groups', 'dynamic-acf-blocks'),
                    'parent_slug' => self::$base_slug,
                    'menu_slug' => self::$base_slug . '-groups',
                ));

                if (!empty($this->dynamic_blocks) && is_array($this->dynamic_blocks)) {
                    foreach ($this->dynamic_blocks as $block) {
                        acf_add_options_sub_page(array(
                            'page_title' => esc_html($block['title']),
                            'menu_title' => esc_html($block['title']),
                            'parent_slug' => self::$base_slug,
                            'menu_slug' => self::$base_slug . '-entry-' . $block['entry_name'],
                        ));

                        if (function_exists('acf_add_local_field_group')):
                            acf_add_local_field_group(array(
                                'key' => $this->key_helper->group('entry', $block['entry_name']),
                                'title' => sprintf(__('%s Settings', 'dynamic-acf-blocks'), esc_html($block['title'])),
                                'fields' => array(
                                    array(
                                        'key' => $this->key_helper->field('entry', $block['entry_name'], 'files'),
                                        'label' => __('Files', 'acf-dynamic-codes'),
                                        'name' => '',
                                        'type' => 'tab',
                                        'instructions' => '',
                                        'required' => 0,
                                        'conditional_logic' => 0,
                                        'wrapper' => array(
                                            'width' => '',
                                            'class' => '',
                                            'id' => '',
                                        ),
                                        'placement' => 'top',
                                        'endpoint' => 0,
                                    ),
                                    array(
                                        'key' => $this->key_helper->field('entry', $block['entry_name'], 'php'),
                                        'label' => 'PHP',
                                        'name' => $this->key_helper->name('entry', $block['entry_name'], 'php'),
                                        'type' => 'acf_code_field',
                                        'instructions' => '',
                                        'required' => 0,
                                        'conditional_logic' => 0,
                                        'wrapper' => array(
                                            'width' => '',
                                            'class' => '',
                                            'id' => '',
                                        ),
                                        'default_value' => '',
                                        'placeholder' => '',
                                        'mode' => 'php',
                                        'theme' => 'monokai',
                                    ),
                                    array(
                                        'key' => $this->key_helper->field('entry', $block['entry_name'], 'html'),
                                        'label' => 'HTML (Twig)',
                                        'name' => $this->key_helper->name('entry', $block['entry_name'], 'html'),
                                        'type' => 'acf_code_field',
                                        'instructions' => '',
                                        'required' => 0,
                                        'conditional_logic' => 0,
                                        'wrapper' => array(
                                            'width' => '',
                                            'class' => '',
                                            'id' => '',
                                        ),
                                        'default_value' => '',
                                        'placeholder' => '',
                                        'mode' => 'htmlmixed',
                                        'theme' => 'monokai',
                                    ),
                                    array(
                                        'key' => $this->key_helper->field('entry', $block['entry_name'], 'css'),
                                        'label' => 'CSS',
                                        'name' => $this->key_helper->name('entry', $block['entry_name'], 'css'),
                                        'type' => 'acf_code_field',
                                        'instructions' => '',
                                        'required' => 0,
                                        'conditional_logic' => 0,
                                        'wrapper' => array(
                                            'width' => '',
                                            'class' => '',
                                            'id' => '',
                                        ),
                                        'default_value' => '',
                                        'placeholder' => '',
                                        'mode' => 'css',
                                        'theme' => 'monokai',
                                    ),
                                    array(
                                        'key' => $this->key_helper->field('entry', $block['entry_name'], 'js'),
                                        'label' => 'Javascript',
                                        'name' => $this->key_helper->name('entry', $block['entry_name'], 'js'),
                                        'type' => 'acf_code_field',
                                        'instructions' => '',
                                        'required' => 0,
                                        'conditional_logic' => 0,
                                        'wrapper' => array(
                                            'width' => '',
                                            'class' => '',
                                            'id' => '',
                                        ),
                                        'default_value' => '',
                                        'placeholder' => '',
                                        'mode' => 'javascript',
                                        'theme' => 'monokai',
                                    ),
                                    array(
                                        'key' => $this->key_helper->field('entry', $block['entry_name'], 'code_samples'),
                                        'label' => __('Code Samples', 'acf-dynamic-codes'),
                                        'name' => '',
                                        'type' => 'tab',
                                        'instructions' => '',
                                        'required' => 0,
                                        'conditional_logic' => 0,
                                        'wrapper' => array(
                                            'width' => '',
                                            'class' => '',
                                            'id' => '',
                                        ),
                                        'placement' => 'top',
                                        'endpoint' => 0,
                                    ),
                                    array(
                                        'key' => $this->key_helper->field('entry', $block['entry_name'], 'code_samples', 'php'),
                                        'label' => 'PHP',
                                        'name' => '',
                                        'type' => 'message',
                                        'instructions' => '',
                                        'required' => 0,
                                        'conditional_logic' => 0,
                                        'wrapper' => array(
                                            'width' => '50',
                                            'class' => '',
                                            'id' => '',
                                        ),
                                        'message' => sprintf(self::$pre_template, htmlentities(file_get_contents(DYNAMIC_ACF_BLOCKS_PATH . 'code-samples/php.php'))),
                                        'new_lines' => '',
                                        'esc_html' => 0,
                                    ),
                                    array(
                                        'key' => $this->key_helper->field('entry', $block['entry_name'], 'code_samples', 'html'),
                                        'label' => 'HTML',
                                        'name' => '',
                                        'type' => 'message',
                                        'instructions' => '',
                                        'required' => 0,
                                        'conditional_logic' => 0,
                                        'wrapper' => array(
                                            'width' => '50',
                                            'class' => '',
                                            'id' => '',
                                        ),
                                        'message' => sprintf(self::$pre_template, htmlentities(file_get_contents(DYNAMIC_ACF_BLOCKS_PATH . 'code-samples/html.twig'))),
                                        'new_lines' => '',
                                        'esc_html' => 0,
                                    ),
                                    array(
                                        'key' => $this->key_helper->field('entry', $block['entry_name'], 'code_samples', 'css'),
                                        'label' => 'CSS',
                                        'name' => '',
                                        'type' => 'message',
                                        'instructions' => '',
                                        'required' => 0,
                                        'conditional_logic' => 0,
                                        'wrapper' => array(
                                            'width' => '50',
                                            'class' => '',
                                            'id' => '',
                                        ),
                                        'message' => sprintf(self::$pre_template, htmlentities(file_get_contents(DYNAMIC_ACF_BLOCKS_PATH . 'code-samples/css.css'))),
                                        'new_lines' => '',
                                        'esc_html' => 0,
                                    ),
                                    array(
                                        'key' => $this->key_helper->field('entry', $block['entry_name'], 'code_samples', 'js'),
                                        'label' => 'JS',
                                        'name' => '',
                                        'type' => 'message',
                                        'instructions' => '',
                                        'required' => 0,
                                        'conditional_logic' => 0,
                                        'wrapper' => array(
                                            'width' => '50',
                                            'class' => '',
                                            'id' => '',
                                        ),
                                        'message' => sprintf(self::$pre_template, htmlentities(file_get_contents(DYNAMIC_ACF_BLOCKS_PATH . 'code-samples/js.js'))),
                                        'new_lines' => '',
                                        'esc_html' => 0,
                                    ),

                                ),
                                'location' => array(
                                    array(
                                        array(
                                            'param' => 'options_page',
                                            'operator' => '==',
                                            'value' => self::$base_slug . '-entry-' . $block['entry_name'],
                                        ),
                                    ),
                                ),
                                'menu_order' => 0,
                                'position' => 'normal',
                                'style' => 'default',
                                'label_placement' => 'top',
                                'instruction_placement' => 'label',
                                'hide_on_screen' => '',
                                'active' => true,
                                'description' => '',
                            ));

                        endif;
                    }
                }
            }
        }

        function add_field_groups()
        {
            if (function_exists('acf_add_local_field_group')):

                $groups_args = array(
                    'key' => $this->key_helper->group('groups'),
                    'title' => __('Block Groups', 'dynamic-acf-blocks'),
                    'fields' => array(
                        array(
                            'key' => $this->key_helper->field('groups'),
                            'label' => __('Block Groups', 'dynamic-acf-blocks'),
                            'name' => $this->key_helper->name('groups'),
                            'type' => 'repeater',
                            'instructions' => sprintf(
                                __('More info: %s', 'dynamic-acf-blocks'),
                                sprintf(
                                    '<a href="https://developer.wordpress.org/block-editor/developers/filters/block-filters/#managing-block-categories" target="_blank">%s%s</a>',
                                    __('Managing block categories', 'dynamic-acf-blocks'),
                                    self::$external_icon
                                )
                            ),
                            'required' => 0,
                            'conditional_logic' => 0,
                            'wrapper' => array(
                                'width' => '',
                                'class' => '',
                                'id' => '',
                            ),
                            'collapsed' => '',
                            'min' => 0,
                            'max' => 0,
                            'layout' => 'table',
                            'button_label' => '',
                            'sub_fields' => array(
                                array(
                                    'key' => $this->key_helper->field('groups', 'slug'),
                                    'label' => 'slug',
                                    'name' => 'slug',
                                    'type' => 'text',
                                    'instructions' => '',
                                    'required' => 1,
                                    'conditional_logic' => 0,
                                    'wrapper' => array(
                                        'width' => '',
                                        'class' => '',
                                        'id' => '',
                                    ),
                                    'default_value' => '',
                                    'placeholder' => '',
                                    'prepend' => '',
                                    'append' => '',
                                    'maxlength' => '',
                                ),
                                array(
                                    'key' => $this->key_helper->field('groups', 'title'),
                                    'label' => 'title',
                                    'name' => 'title',
                                    'type' => 'text',
                                    'instructions' => '',
                                    'required' => 1,
                                    'conditional_logic' => 0,
                                    'wrapper' => array(
                                        'width' => '',
                                        'class' => '',
                                        'id' => '',
                                    ),
                                    'default_value' => '',
                                    'placeholder' => '',
                                    'prepend' => '',
                                    'append' => '',
                                    'maxlength' => '',
                                ),
                                array(
                                    'key' => $this->key_helper->field('groups', 'dashicon'),
                                    'label' => 'icon',
                                    'name' => 'dashicon',
                                    'type' => 'text',
                                    'instructions' => sprintf(
                                        '<a href="https://developer.wordpress.org/resource/dashicons/" target="_blank">%s%s</a>',
                                        __('Find icon', 'dynamic-acf-blocks'),
                                        self::$external_icon
                                    ),
                                    'required' => 1,
                                    'conditional_logic' => 0,
                                    'wrapper' => array(
                                        'width' => '',
                                        'class' => '',
                                        'id' => '',
                                    ),
                                    'default_value' => 'schedule',
                                    'placeholder' => 'schedule',
                                    'prepend' => '',
                                    'append' => '',
                                    'maxlength' => '',
                                ),
                            ),
                        ),
                    ),
                    'location' => array(
                        array(
                            array(
                                'param' => 'options_page',
                                'operator' => '==',
                                'value' => self::$base_slug . '-groups',
                            ),
                        ),
                    ),
                    'menu_order' => 0,
                    'position' => 'normal',
                    'style' => 'default',
                    'label_placement' => 'top',
                    'instruction_placement' => 'label',
                    'hide_on_screen' => '',
                    'active' => true,
                    'description' => '',
                );
                acf_add_local_field_group($groups_args);
                $this->log('groups :: args', $groups_args);

                $entries_args = array(
                    'key' => $this->key_helper->group('entries'),
                    'title' => __('Dynamic ACF Blocks', 'dynamic-acf-blocks'),
                    'fields' => array(
                        array(
                            'key' => $this->key_helper->field('entries'),
                            'label' => __('Block Definitions', 'dynamic-acf-blocks'),
                            'name' => $this->key_helper->name('entries'),
                            'type' => 'repeater',
                            'instructions' => sprintf(
                                __('More info: %s %s', 'dynamic-acf-blocks'),
                                sprintf(
                                    '<a href="https://www.advancedcustomfields.com/resources/blocks/" target="_blank">%s%s</a>',
                                    'ACF Blocks',
                                    self::$external_icon
                                ),
                                sprintf(
                                    '<a href="https://www.advancedcustomfields.com/resources/acf_register_block_type/" target="_blank">%s%s</a>',
                                    'acf_register_block_type()',
                                    self::$external_icon
                                )
                            ),
                            'required' => 0,
                            'conditional_logic' => 0,
                            'wrapper' => array(
                                'width' => '',
                                'class' => '',
                                'id' => '',
                            ),
                            'collapsed' => '',
                            'min' => 0,
                            'max' => 0,
                            'layout' => 'table',
                            'button_label' => '',
                            'sub_fields' => array(
                                array(
                                    'key' => $this->key_helper->field('entries', 'entry_name'),
                                    'label' => 'name',
                                    'name' => 'entry_name',
                                    'type' => 'text',
                                    'instructions' => '',
                                    'required' => 1,
                                    'conditional_logic' => 0,
                                    'wrapper' => array(
                                        'width' => '',
                                        'class' => '',
                                        'id' => '',
                                    ),
                                    'default_value' => '',
                                    'placeholder' => '',
                                    'prepend' => '',
                                    'append' => '',
                                    'maxlength' => '',
                                ),
                                array(
                                    'key' => $this->key_helper->field('entries', 'title'),
                                    'label' => 'title',
                                    'name' => 'title',
                                    'type' => 'text',
                                    'instructions' => '',
                                    'required' => 1,
                                    'conditional_logic' => 0,
                                    'wrapper' => array(
                                        'width' => '',
                                        'class' => '',
                                        'id' => '',
                                    ),
                                    'default_value' => '',
                                    'placeholder' => '',
                                    'prepend' => '',
                                    'append' => '',
                                    'maxlength' => '',
                                ),
                                array(
                                    'key' => $this->key_helper->field('entries', 'description'),
                                    'label' => 'description',
                                    'name' => 'description',
                                    'type' => 'text',
                                    'instructions' => '',
                                    'required' => 0,
                                    'conditional_logic' => 0,
                                    'wrapper' => array(
                                        'width' => '',
                                        'class' => '',
                                        'id' => '',
                                    ),
                                    'default_value' => '',
                                    'placeholder' => '',
                                    'prepend' => '',
                                    'append' => '',
                                    'maxlength' => '',
                                ),
                                array(
                                    'key' => $this->key_helper->field('entries', 'category'),
                                    'label' => 'category',
                                    'name' => 'category',
                                    'type' => 'select',
                                    'instructions' => '',
                                    'required' => 1,
                                    'conditional_logic' => 0,
                                    'wrapper' => array(
                                        'width' => '',
                                        'class' => '',
                                        'id' => '',
                                    ),
                                    'choices' => array(),
                                    'default_value' => array(),
                                    'allow_null' => 0,
                                    'multiple' => 0,
                                    'ui' => 0,
                                    'return_format' => 'value',
                                    'ajax' => 0,
                                    'placeholder' => '',
                                ),
                                array(
                                    'key' => $this->key_helper->field('entries', 'dashicon'),
                                    'label' => 'icon',
                                    'name' => 'dashicon',
                                    'type' => 'text',
                                    'instructions' => sprintf(
                                        '<a href="https://developer.wordpress.org/resource/dashicons/" target="_blank">%s%s</a>',
                                        __('Find icon', 'dynamic-acf-blocks'),
                                        self::$external_icon
                                    ),
                                    'required' => 1,
                                    'conditional_logic' => 0,
                                    'wrapper' => array(
                                        'width' => '',
                                        'class' => '',
                                        'id' => '',
                                    ),
                                    'default_value' => 'schedule',
                                    'placeholder' => 'schedule',
                                    'prepend' => '',
                                    'append' => '',
                                    'maxlength' => '',
                                ),
                                array(
                                    'key' => $this->key_helper->field('entries', 'keywords'),
                                    'label' => 'keywords',
                                    'name' => 'keywords',
                                    'type' => 'text',
                                    'instructions' => 'Comma separated',
                                    'required' => 0,
                                    'conditional_logic' => 0,
                                    'wrapper' => array(
                                        'width' => '',
                                        'class' => '',
                                        'id' => '',
                                    ),
                                    'default_value' => '',
                                    'placeholder' => '',
                                    'prepend' => '',
                                    'append' => '',
                                    'maxlength' => '',
                                ),
                            ),
                        ),
                    ),
                    'location' => array(
                        array(
                            array(
                                'param' => 'options_page',
                                'operator' => '==',
                                'value' => self::$base_slug,
                            ),
                        ),
                    ),
                    'menu_order' => 0,
                    'position' => 'normal',
                    'style' => 'default',
                    'label_placement' => 'top',
                    'instruction_placement' => 'label',
                    'hide_on_screen' => '',
                    'active' => true,
                    'description' => '',
                );
                acf_add_local_field_group($entries_args);
                $this->log('entries :: args', $entries_args);

            endif;
        }

        public function register_acf_block_types()
        {
            if (!empty($this->dynamic_blocks) && is_array($this->dynamic_blocks) && function_exists('acf_register_block_type')) {

                foreach ($this->dynamic_blocks as $dynamic_block) {
                    $args = array(
                        'name' => $dynamic_block['entry_name'],
                        'title' => esc_html($dynamic_block['title']),
                        'description' => esc_html($dynamic_block['description']),
                        // 'render_template' => DYNAMIC_ACF_BLOCKS_PATH . 'global-block-template.php',
                        'render_callback' => function ($block) use ($dynamic_block) {
                            if (class_exists('Timber')) {
                                $context = Timber::context();

                                $context['block'] = $block;

                                if (function_exists('get_fields')) {
                                    $data = get_fields();
                                    if (!empty($data) && is_array($data)) {
                                        foreach ($data as $key => $val) {
                                            $context[$key] = $val;
                                        }
                                    }
                                }

                                $file = $this->get_file_path($dynamic_block['entry_name'] . '.php');
                                if (is_file($file)) {
                                    include $file;
                                }

                                $file = $this->get_file_path($dynamic_block['entry_name'] . '.html');
                                if (is_file($file)) {
                                    $html = file_get_contents($file);
                                    if (!empty($html)) {
                                        Timber::render_string($html, $context);
                                    }
                                }
                            }
                        },
                        'category' => $dynamic_block['category'],
                        'icon' => $dynamic_block['dashicon'],
                        'keywords' => array_filter(array_map('trim', explode(',', $dynamic_block['keywords']))),
                    );

                    $file = $this->get_file_path($dynamic_block['entry_name'] . '.css');
                    if (is_file($file)) {
                        $args['enqueue_style'] = $this->upload_file_path_to_url($file);
                    }

                    $file = $this->get_file_path($dynamic_block['entry_name'] . '.js');
                    if (is_file($file)) {
                        $args['enqueue_script'] = $this->upload_file_path_to_url($file);
                    }

                    acf_register_block_type($args);
                }
            }

        }

        function upload_file_path_to_url($path)
        {
            $upload_dir = wp_upload_dir();
            return str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $path);
        }

        function save_files($post_id)
        {
            $this->dynamic_blocks = $this->get_blocks();

            if (empty($_POST['acf'])) {
                return;
            }

            if (function_exists('get_field')) {
                $screen = get_current_screen();
                if (!empty($this->dynamic_blocks) && is_array($this->dynamic_blocks)) {
                    foreach ($this->dynamic_blocks as $block) {
                        if ($screen->id === 'acf-blocks_page_' . self::$base_slug . '-entry-' . $block['entry_name']) {
                            foreach (['php', 'html', 'css', 'js'] as $extension) {
                                $key = $this->key_helper->name('entry', $block['entry_name'], $extension);
                                $option = get_field($key, 'option');
                                if (!empty($option)) {
                                    $this->create_file($block['entry_name'] . '.' . $extension, $option);
                                } else {
                                    $this->delete_file($block['entry_name'] . '.' . $extension);
                                }
                            }
                        }
                    }
                }
            }
        }

        function delete_files($post_id)
        {
            if (empty($_POST['acf'])) {
                return;
            }

            $screen = get_current_screen();

            if ($screen->id === 'toplevel_page_' . self::$base_slug) {
                $blocks = $this->dynamic_blocks;

                if (empty($blocks) || !is_array($blocks)) {
                    $blocks = [];
                }

                $past_blocks = get_option('dynamic_acf_blocks_past');
                if (!empty($past_blocks)) {
                    $diff_block_names = array_diff(array_column($past_blocks, 'entry_name'), array_column($blocks, 'entry_name'));
                    if (!empty($diff_block_names)) {
                        foreach ($diff_block_names as $diff_block_name) {
                            foreach (['php', 'html', 'css', 'js'] as $extension) {
                                $this->delete_file($diff_block_name . '.' . $extension);
                            }
                        }
                    }
                }

                update_option('dynamic_acf_blocks_past', $blocks);
            }
        }

        function set_options($post_id)
        {
            if (empty($_POST['acf'])) {
                return;
            }

            $screen = get_current_screen();

            if ($screen->id === 'toplevel_page_' . self::$base_slug) {
                update_option($this->key_helper->name('entries'), get_field($this->key_helper->name('entries'), 'option'));
            }
        }

        function create_file($file_name, $content)
        {
            global $wp_filesystem;

            if (is_null($wp_filesystem)) {
                WP_Filesystem();
            }

            $file = $this->get_file_path($file_name);
            $wp_filesystem->put_contents($file, $content);
        }

        function delete_file($file_name)
        {
            global $wp_filesystem;

            if (is_null($wp_filesystem)) {
                WP_Filesystem();
            }

            $file = $this->get_file_path($file_name);
            $wp_filesystem->delete($file);
        }

        function get_file_path($file_name)
        {
            $destination = wp_upload_dir();
            $destination_path = $destination['basedir'] . '/' . self::$base_key;
            return $destination_path . '/' . $file_name;
        }

    }

    new DYNAMIC_ACF_BLOCKS;

}