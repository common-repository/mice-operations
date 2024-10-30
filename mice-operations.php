<?php
/**
 * MICE Operations
 *
 * Plugin Name: MICE Operations
 * Plugin URI: http://wordpress.org/plugins/mice-operations/
 * Description: Easily embed the MICE Operations widget on your WordPress site.
 * Author: MICE Operations
 * Version: 1.0.2
 * Author URI: https://www.miceoperations.com/
 * License: GPLv2 or later
 * Text Domain: miceoperations
 * Domain Path: /languages
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU
 * General Public License version 2, as published by the Free Software Foundation. You may NOT assume
 * that you can use any other version of the GPL.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
 * even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

if (!class_exists('MICEWidgetPlugin')) {
  class MICEWidgetPlugin {

    public $version = '1.0.2';
    public $text_domain = 'miceoperations';

    public $button_positions = [
      'left' => 'Left',
      'right' => 'Right',
    ];

    public function init() {
      add_action('wp_head', [$this, 'page_header'], 1);
      add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

      add_action('admin_menu', [$this, 'settings_page']);
      add_action('admin_init', [$this, 'settings']);
      add_action('admin_enqueue_scripts', [$this, 'enqueue_color_picker']);
      add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
      add_action('save_post', [$this, 'save_post']);

      add_filter('plugin_action_links_mice-operations/mice-operations.php', [$this, 'add_action_links']);
    }

    function page_header() {
      $widget_id = '';
      if (is_page() || is_single()) {
        $post_id = get_queried_object_id();
        $widget_id = get_post_meta($post_id, 'mice_widget_id', true);
      }
      if (!$widget_id) $widget_id = get_option('mice_widget_id');

      $arr = [];
      if (get_option('mice_style_color')) $arr['alignment'] = get_option('mice_button_alignment');
      if (get_option('mice_source')) $arr['source'] = get_option('mice_source');
      if ($widget_id) {
        $arr['widget_id'] = $widget_id;
        $arr['button_alignment'] = get_option('mice_button_alignment');
        $arr['button_horizontal_padding'] = get_option('mice_button_horizontal_padding');
        $arr['button_vertical_padding'] = get_option('mice_button_vertical_padding');
        $arr['style_color'] = get_option('mice_style_color');
      }
      $arr = array_filter($arr);
      if(!empty($arr)) {
        ?><script> window.miceWidgetSettings = <?= json_encode((object)$arr) ?>;</script><?php
      }
    }

    function enqueue_scripts() {
      wp_enqueue_script('mice_operations_widget', 'https://app.miceoperations.com/widget/widget.js', array(), $this->version, true);
    }

    function add_meta_boxes() {
      foreach (['page', 'post'] as $screen) {
        add_meta_box('miceoperations_metabox', 'MICE Operations', [$this, 'metabox_html'], $screen, 'side');
      }
    }

    function metabox_html($post) {
      wp_nonce_field('mice_meta_box', 'mice_nonce');
      $default_widget_id = get_option('mice_widget_id');
      $val = get_post_meta($post->ID, 'mice_widget_id', true);
      ?>
      <p class="post-attributes-label-wrapper menu-order-label-wrapper"><label class="post-attributes-label" for="mice_widget_id"><?php _e('Widget ID', 'miceoperations'); ?></label></p>
      <input type="text" name="mice_widget_id" style="width:100%;margin-bottom:10px" value="<?= $val ?>" id="mice_widget_id" />
      <?php if ($default_widget_id) : ?>
      <p class="post-attributes-help-text"><?php printf(__('Defaults to widget with ID %s'), '<a href="https://app.miceoperations.com/widget/'.$default_widget_id.'" target="_blank">'.$default_widget_id.'</a>'); ?></p>
      <?php
      endif;
    }

    function save_post($post_id) {
      $nonce_name   = isset($_POST['mice_nonce']) ? $_POST['mice_nonce'] : '';
      if (!wp_verify_nonce( $nonce_name, 'mice_meta_box')) return;
      if (!current_user_can('edit_post', $post_id) && !current_user_can('edit_page', $post_id)) return;
      if (wp_is_post_autosave($post_id)) return;
      if (wp_is_post_revision($post_id)) return;

      if (array_key_exists('mice_widget_id', $_POST)) {
        update_post_meta($post_id, 'mice_widget_id', sanitize_text_field($_POST['mice_widget_id']));
      }
    }

    function settings_page() {
      add_options_page('MICE Operations', 'MICE Operations', 'manage_options', 'miceoperations', [$this, 'edit_settings']);
    }

    function settings() {
      register_setting('miceoperations', 'mice_widget_id');
      register_setting('miceoperations', 'mice_source');
      register_setting('miceoperations', 'mice_button_alignment');
      register_setting('miceoperations', 'mice_button_horizontal_padding');
      register_setting('miceoperations', 'mice_button_vertical_padding');
      register_setting('miceoperations', 'mice_style_color');

      add_settings_section('mice_settings_section', 'Settings Section', [$this, 'settings_title'], 'miceoperations');
      add_settings_field('mice_widget_id', __('Widget ID', 'miceoperations'), [$this, 'widget_id'], 'miceoperations', 'miceoperations');
      add_settings_field('mice_style_color', __('Primary color', 'miceoperations'), [$this, 'style_color'], 'miceoperations', 'miceoperations');
      add_settings_field('mice_button_alignment', __('Button position', 'miceoperations'), [$this, 'floating_button_position'], 'miceoperations', 'miceoperations');

      add_settings_field('mice_button_horizontal_padding', __('Button horizontal padding', 'miceoperations'), [$this, 'mice_button_horizontal_padding'], 'miceoperations', 'miceoperations');
      add_settings_field('mice_button_vertical_padding', __('Button vertical padding', 'miceoperations'), [$this, 'mice_button_vertical_padding'], 'miceoperations', 'miceoperations');
    }

    public function settings_title() {
      return "Mice Operations";
    }

    public function widget_id() {
      $val = get_option('mice_widget_id');
      ?>
      <input type="text" name="mice_widget_id" value="<?= (isset($val) ? esc_attr($val) : '') ?>" />
      <p class="description" id="home-description"><?php printf(__('Enter the Widget ID you want to use (by default) for your floating button on every page. Leave blank to disable the floating button by default. <a href="%s" target="_blank">Learn more</a>', 'miceoperations'), 'https://help.miceoperations.com/articles/4412011'); ?></p>
      <?php
    }

    public function enable_floating_button() {
      $val = get_option('mice_enable_button');
      ?><label><input type="checkbox" name="mice_enable_button" value="1" <?= (!empty($val) ? 'checked' : '') ?> /> <?php _e('Enable floating button', 'miceoperations'); ?></label><p class="description" id="home-description"><?php _e('Do you want to enable the floating button on every page? You can still embed widgets manually if this option is disabled.', 'miceoperations'); ?></p><?php
    }

    public function floating_button_position() {
      $val = get_option('mice_button_alignment');
      $str = "<select name='mice_button_alignment'>";
      foreach ($this->button_positions as $k => $text) {
        $str .= '<option value="' . $k . '" ' . ($k == $val ? ' selected' : '') . '>' . $text . '</option>';
      }
      $str .= '</select>';
      echo $str;
    }

    public function style_color() {
      $val = get_option('mice_style_color', '#7E54E6');
      echo '<input type="text" value="' . esc_attr($val) . '" name="mice_style_color" class="mice-button-color" data-default-color="#eee" />';
    }

    public function mice_button_horizontal_padding() {
      $val = get_option('mice_button_horizontal_padding', 32);
      echo '<input type="number" step="1" min="0" value="' . esc_attr($val) . '" name="mice_button_horizontal_padding" class="small-text" /> px';
    }

    public function mice_button_vertical_padding() {
      $val = get_option('mice_button_vertical_padding', 32);
      echo '<input type="number" step="1" min="0" value="' . esc_attr($val) . '" name="mice_button_vertical_padding" class="small-text" /> px';
    }

    function enqueue_color_picker($hook_suffix) {
      // first check that $hook_suffix is appropriate for your admin page
      wp_enqueue_style('wp-color-picker');
      wp_enqueue_script('mice-admin', plugins_url('mice-admin.js', __FILE__), array('wp-color-picker'), false, true);
    }

    function add_action_links($links) {
      $settings_link = '<a href="options-general.php?page=miceoperations">'.__('Settings').'</a>';
      array_unshift($links, $settings_link);
      return $links;
    }

    function edit_settings() {
      global $wp_settings_sections, $wp_settings_fields;
      // check user capabilities
      if (!current_user_can('manage_options')) {
        return;
      }

      settings_errors('mice_messages');
      ?>
      <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
          <?= settings_fields('miceoperations'); ?>
          <table class="form-table">
            <?= do_settings_fields('miceoperations', 'miceoperations'); ?>
          </table>
          <?= submit_button(); ?>
        </form>
      </div>
      <?php
    }
  }

  $plugin = new MICEWidgetPlugin();
  $plugin->init();
}
