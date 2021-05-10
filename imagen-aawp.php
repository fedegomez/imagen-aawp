<?php
/*
Plugin Name: Imagen destacada con AAWP
Plugin URI: 
Description: Establece como destacada la primera imagen que muestre el shortcode del plugin AAWP.
Version: 1.0.1
Author: Fede Gómez
Author URI: https://www.fedegomez.es
License: 
License URI: 
*/

include('simplehtmldom/simple_html_dom.php');

add_filter('post_thumbnail_html', 'custom_thumbnail_tag_filter', 10, 2);
function custom_thumbnail_tag_filter($html, $post_id)
{
    $content_post = get_post($post_id);
    $html = get_aawp_featured($content_post, $html, true);
    return $html;
}

if (defined('PT_CV_PREFIX_')) {
    add_filter(PT_CV_PREFIX_ . 'field_thumbnail_image', 'change_featured_for_content_views', 10, 4);
}

function change_featured_for_content_views($html, $post, $dimensions, $fargs)
{
    $featured = get_aawp_featured($post, $html, false);
    if ($featured != $html) {
        $size = get_aawp_image_size($featured);
        $html = preg_replace('/width="(.*)"/U', "width=\"$size[0]\"", $html);
        $html = preg_replace('/height="(.*)"/U', "height=\"$size[1]\"", $html);
        $html = preg_replace('/src="(.*)"/U', "src=\"$featured\"", $html);
        $html = preg_replace('/alt="(.*)"/U', "alt=\"$post->post_title\"", $html);
        $html = preg_replace('/(srcset=".*")/U', '', $html);
        $html = preg_replace('/(sizes=".*")/U', '', $html);
    }

    return $html;
}

function change_image_mejorcluster($image, $attachment_id, $size, $icon)
{
    if (is_mejorcluster_active()) {
        global $post;
        $mejorcluster_image = get_post_meta($post->ID, 'mejorcluster-image', true);
        if ($mejorcluster_image) {
            $image[0] = $mejorcluster_image;
        } else {
            $image[0] = get_aawp_featured($post, $image[0], false);
        }
        if ($image[0]) {
            $size = get_aawp_image_size($image[0]);
            $size = [$size[0], $size[1]];
        }
    }
    return $image;
}
add_filter('wp_get_attachment_image_src', 'change_image_mejorcluster', 10, 4);

function is_mejorcluster_active()
{
    /*if (is_plugin_active('mejorcluster/mejorcluster.php')) {
        return true;
    }*/

    if (in_array('mejorcluster/mejorcluster.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        return true;
    }

    return false;
}

function get_aawp_featured($post, $html, $imgtag = false)
{
    $respetar_destacada = get_post_meta($post->ID, 'aawp_featured_image_respetar-la-imagen-destacada-del-post-pagina', true);
    if ($respetar_destacada) {
        return $html;
    }
    if (has_shortcode($post->post_content, 'amazon') || has_shortcode($post->post_content, 'aawp')) {
        $aawp_featured = get_post_meta($post->ID, 'aawp_featured_image_url-de-la-imagen', true);
        if (!$aawp_featured) {
            $aawp_featured = extraer_imagen_aawp($post);
        }

        if (is_int($html)) {
            $image_size = get_aawp_image_size($aawp_featured);
            return $image_size;
        }

        if ($imgtag) {
            $html = "<img src='" . $aawp_featured . "' alt='" . $post->post_title . "' title='" . $post->post_title . "'>";
        } else {
            return $aawp_featured;
        }
    }

    return $html;
}

add_filter('rank_math/sitemap/xml_img_src', function ($src, $post) {
    if (get_the_post_thumbnail_url($post) === $src) {
        $src = get_aawp_featured($post, $src, false);
    }
    return $src;
}, 10, 2);

add_filter('wpseo_schema_imageobject', function ($data) {
    if (strpos(strtolower($data['@id']), '#primaryimage')) {
        global $post;
        $data['url'] = get_aawp_featured($post, $data['url'], false);
        if ($data['url']) {
            $image_size = get_aawp_image_size($data['url']);
            $data['width'] = $image_size[0];
            $data['height'] = $image_size[1];
        }
    }
    return $data;
});

add_filter('rank_math/json_ld', function ($data, $jsonld) {
    global $post;
    if (isset($data['primaryImage'])) {
        $data['primaryImage']['url'] = get_aawp_featured($post, $data['primaryImage']['url'], false);
        if ($data['primaryImage']['url']) {
            $image_size = get_aawp_image_size($data['primaryImage']['url']);
            $data['primaryImage']['width'] = $image_size[0];
            $data['primaryImage']['height'] = $image_size[1];
        }
    }
    return $data;
}, 99, 2);

add_filter('wpseo_xml_sitemap_img_src', 'change_opengraph_image_by_aawp', 10, 2);
add_filter('wpseo_opengraph_image', 'change_opengraph_image_by_aawp', 10, 1);
add_filter('rank_math/opengraph/facebook/image', 'change_opengraph_image_by_aawp', 10, 1);
add_filter('rank_math/opengraph/twitter/image', 'change_opengraph_image_by_aawp', 10, 1);
function change_opengraph_image_by_aawp($img, $post = false)
{
    if (!$post) {
        global $post;
    }
    if (get_the_post_thumbnail_url($post) === $img) {
        $img = get_aawp_featured($post, $img, false);
    }
    return $img;
};

add_filter("rank_math/opengraph/facebook/og_image_width", function ($content) {
    global $post;
    $content = get_aawp_featured($post, $content, false);
    if (is_array($content)) {
        return $content[0];
    }
    return $content;
});

add_filter("rank_math/opengraph/facebook/og_image_height", function ($content) {
    global $post;
    $content = get_aawp_featured($post, $content, false);
    if (is_array($content)) {
        return $content[1];
    }
    return $content;
});

function get_aawp_image_size($file)
{
    if ($file) {
        $image_size = getimagesize($file);
        return $image_size;
    }
    return [];
}

add_action('save_post', 'force_featured_image', 10, 2);
add_action('save_page', 'force_featured_image', 10, 2);
function force_featured_image($post_id, $post)
{
    preg_match('/\[(amazon|aawp) .*\]/', $post->post_content, $matches);

    if ($matches) {
        if (!has_post_thumbnail($post_id)) {
            $attachment = wp_get_attachment_by_post_name('aawp-placeholder');
            if ($attachment) {
                $image = $attachment->ID; // Gives the id of the attachment
            } else {
                $url = plugin_dir_url(__FILE__) . 'aawp-placeholder.png';
                $title = "aawp-placeholder";
                $image = media_sideload_image($url, $post_id, $title, 'id');
                $post_update = array(
                    'ID'         => $image,
                    'post_title' => ''
                );

                wp_update_post($post_update);
            }

            set_post_thumbnail($post_id, $image);
        }

        $src = extraer_imagen_aawp($post);
        $_POST['aawp_featured_image_url-de-la-imagen'] = $src;
    }
}

function extraer_imagen_aawp($post)
{
    $src = '';
    $pantalla = '';
    if (is_admin()) {
        $screen = get_current_screen();
        $pantalla = $screen->post_type;
    }
    if ($pantalla != 'page-generator-pro') {
        $html = str_get_html(apply_filters('the_content', $post->post_content));
        $primera_imagen = $html->find('div.aawp img', 0);

        if ($primera_imagen) {
            //https://m.media-amazon.com/images/I/41AX8zTLkxL._SL160_.jpg
            $extension = substr($primera_imagen->src, -3);
            $archivo = substr($primera_imagen->src, 0, strlen($primera_imagen->src) - 11);
            $src = $archivo . $extension;

            update_post_meta($post->ID, 'aawp_featured_image_url-de-la-imagen', $src);
        }
    }


    return $src;
}

if (!(function_exists('wp_get_attachment_by_post_name'))) {
    function wp_get_attachment_by_post_name($post_name)
    {
        $args           = array(
            'posts_per_page' => 1,
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'name'           => trim($post_name),
        );

        $get_attachment = new WP_Query($args);

        if (!$get_attachment || !isset($get_attachment->posts, $get_attachment->posts[0])) {
            return false;
        }

        return $get_attachment->posts[0];
    }
}

class Aawp_Featured_Image
{
    private $config = '{"title":"Imagen destacada extra\u00edda de AAWP","prefix":"aawp_featured_image_","domain":"aawp-featured-image","class_name":"Aawp_Featured_Image","post-type":["post","page"],"context":"side","priority":"low","fields":[{"type":"url","label":"URL de la imagen","id":"aawp_featured_image_url-de-la-imagen"},{"type":"checkbox","label":"Respetar la imagen destacada del post\/p\u00e1gina","description":"Marca esta casilla si no quieres que el plugin reemplace la imagen destacada.","id":"aawp_featured_image_respetar-la-imagen-destacada-del-post-pagina"}]}';

    public function __construct()
    {
        $this->config = json_decode($this->config, true);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('admin_head', [$this, 'admin_head']);
        add_action('save_post', [$this, 'save_post']);
    }

    public function add_meta_boxes()
    {
        foreach ($this->config['post-type'] as $screen) {
            add_meta_box(
                sanitize_title($this->config['title']),
                $this->config['title'],
                [$this, 'add_meta_box_callback'],
                $screen,
                $this->config['context'],
                $this->config['priority']
            );
        }
    }

    public function admin_head()
    {
        global $typenow;
        if (in_array($typenow, $this->config['post-type'])) {
?><?php
        }
    }

    public function save_post($post_id)
    {
        foreach ($this->config['fields'] as $field) {
            switch ($field['type']) {
                case 'checkbox':
                    update_post_meta($post_id, $field['id'], isset($_POST[$field['id']]) ? $_POST[$field['id']] : '');
                    break;
                case 'url':
                    if (isset($_POST[$field['id']])) {
                        $sanitized = esc_url_raw($_POST[$field['id']]);
                        update_post_meta($post_id, $field['id'], $sanitized);
                    }
                    break;
                default:
                    if (isset($_POST[$field['id']])) {
                        $sanitized = sanitize_text_field($_POST[$field['id']]);
                        update_post_meta($post_id, $field['id'], $sanitized);
                    }
            }
        }
    }

    public function add_meta_box_callback()
    {
        $this->fields_table();
    }

    private function fields_table()
    {
    ?><table class="form-table" role="presentation">
    <tbody><?php
            foreach ($this->config['fields'] as $field) {
            ?><tr>
                <th scope="row" style="width:auto"><?php $this->label($field); ?></th>
                <td><?php $this->field($field); ?></td>
            </tr><?php
                }
                    ?></tbody>
</table><?php
    }

    private function label($field)
    {
        switch ($field['type']) {
            default:
                printf(
                    '<label class="" for="%s">%s</label>',
                    $field['id'],
                    $field['label']
                );
        }
    }

    private function field($field)
    {
        switch ($field['type']) {
            case 'checkbox':
                $this->checkbox($field);
                break;
            default:
                $this->input($field);
        }
    }

    private function checkbox($field)
    {
        printf(
            '<label class="rwp-checkbox-label"><input %s id="%s" name="%s" type="checkbox"> %s</label>',
            $this->checkedit($field),
            $field['id'],
            $field['id'],
            isset($field['description']) ? $field['description'] : ''
        );
    }

    private function input($field)
    {
        printf(
            '<input style="width:100%%" class="regular-text %s" id="%s" name="%s" %s type="%s" value="%s">',
            isset($field['class']) ? $field['class'] : '',
            $field['id'],
            $field['id'],
            isset($field['pattern']) ? "pattern='{$field['pattern']}'" : '',
            $field['type'],
            $this->value($field)
        );
    }

    private function value($field)
    {
        global $post;
        if (metadata_exists('post', $post->ID, $field['id'])) {
            $value = get_post_meta($post->ID, $field['id'], true);
        } else if (isset($field['default'])) {
            $value = $field['default'];
        } else {
            return '';
        }
        return str_replace('\u0027', "'", $value);
    }

    private function checkedit($field)
    {
        global $post;
        if (metadata_exists('post', $post->ID, $field['id'])) {
            $value = get_post_meta($post->ID, $field['id'], true);
            if ($value === 'on') {
                return 'checked';
            }
            return '';
        } else if (isset($field['checked'])) {
            return 'checked';
        }
        return '';
    }
}
new Aawp_Featured_Image;
