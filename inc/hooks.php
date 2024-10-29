<?php
class Aki_Toolset_Hooks {

    private $hook_suffix;

    private $theme_author = 'akithemes';

    public static function instance() {

        static $instance = null;

        if ( null === $instance ) {
            $instance = new self();
        }

        return $instance;
    }

    public function __construct() {}

    public function import_menu() {
        if( !class_exists('Advanced_Import')){
            $this->hook_suffix[] = add_theme_page( esc_html__( 'Demo Import ','aki-toolset' ), esc_html__( 'Demo Import','aki-toolset'  ), 'manage_options', 'advanced-import', array( $this, 'demo_import_screen' ) );
        }
    }

    public function enqueue_styles( $hook_suffix ) {
        if ( !is_array($this->hook_suffix) || !in_array( $hook_suffix, $this->hook_suffix )){
            return;
        }
        wp_enqueue_style( AKI_TOOLSET_PLUGIN_NAME, AKI_TOOLSET_URL . 'assets/aki-toolset.css',array( 'wp-admin', 'dashicons' ), AKI_TOOLSET_VERSION, 'all' );
    }

    public function enqueue_scripts( $hook_suffix ) {
        if ( !is_array($this->hook_suffix) || !in_array( $hook_suffix, $this->hook_suffix )){
            return;
        }

        wp_enqueue_script( AKI_TOOLSET_PLUGIN_NAME, AKI_TOOLSET_URL . 'assets/aki-toolset.js', array( 'jquery'), AKI_TOOLSET_VERSION, true );
        wp_localize_script( AKI_TOOLSET_PLUGIN_NAME, 'aki_toolset', array(
            'btn_text' => esc_html__( 'Processing...', 'aki-toolset' ),
            'nonce'    => wp_create_nonce( 'aki_toolset_nonce' )
        ) );
    }

    public function demo_import_screen() {
        ?>
        <div id="ads-notice">
            <div class="ads-container">
                <img class="ads-screenshot" src="<?php echo esc_url(aki_toolset_get_theme_screenshot() )?>" />
                <div class="ads-notice">
                    <h2>
                        <?php
                        printf(
                            esc_html__( 'Welcome! Thank you for choosing %1$s! To get started with ready-made starter site templates. Install the Advanced Import plugin and install Demo Starter Site within a single click', 'aki-toolset' ), '<strong>'. wp_get_theme()->get('Name'). '</strong>');
                        ?>
                    </h2>

                    <p class="plugin-install-notice"><?php esc_html_e( 'Clicking the button below will install and activate the Advanced Import plugin.', 'aki-toolset' ); ?></p>

                    <a class="ads-gsm-btn button button-primary button-hero" href="#" data-name="" data-slug="" aria-label="<?php esc_html_e( 'Get started with the Theme', 'aki-toolset' ); ?>">
                        <?php esc_html_e( 'Get Started', 'aki-toolset' );?>
                    </a>
                </div>
            </div>
        </div>
        <?php

    }

    public function install_advanced_import() {

        check_ajax_referer( 'aki_toolset_nonce', 'security' );

        $slug   = 'advanced-import';
        $plugin = 'advanced-import/advanced-import.php';

        $status = array(
            'install' => 'plugin',
            'slug'    => sanitize_key( wp_unslash( $slug ) ),
        );
        $status['redirect'] = admin_url( '/themes.php?page=advanced-import&browse=all&at-gsm-hide-notice=welcome' );

        if ( is_plugin_active_for_network( $plugin ) || is_plugin_active( $plugin ) ) {
            // Plugin is activated
            wp_send_json_success($status);
        }


        if ( ! current_user_can( 'install_plugins' ) ) {
            $status['errorMessage'] = __( 'Sorry, you are not allowed to install plugins on this site.', 'aki-toolset' );
            wp_send_json_error( $status );
        }

        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        include_once ABSPATH . 'wp-admin/includes/plugin-install.php';

        // Looks like a plugin is installed, but not active.
        if ( file_exists( WP_PLUGIN_DIR . '/' . $slug ) ) {
            $plugin_data          = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
            $status['plugin']     = $plugin;
            $status['pluginName'] = $plugin_data['Name'];

            if ( current_user_can( 'activate_plugin', $plugin ) && is_plugin_inactive( $plugin ) ) {
                $result = activate_plugin( $plugin );

                if ( is_wp_error( $result ) ) {
                    $status['errorCode']    = $result->get_error_code();
                    $status['errorMessage'] = $result->get_error_message();
                    wp_send_json_error( $status );
                }

                wp_send_json_success( $status );
            }
        }

        $api = plugins_api(
            'plugin_information',
            array(
                'slug'   => sanitize_key( wp_unslash( $slug ) ),
                'fields' => array(
                    'sections' => false,
                ),
            )
        );

        if ( is_wp_error( $api ) ) {
            $status['errorMessage'] = $api->get_error_message();
            wp_send_json_error( $status );
        }

        $status['pluginName'] = $api->name;

        $skin     = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader( $skin );
        $result   = $upgrader->install( $api->download_link );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $status['debug'] = $skin->get_upgrade_messages();
        }

        if ( is_wp_error( $result ) ) {
            $status['errorCode']    = $result->get_error_code();
            $status['errorMessage'] = $result->get_error_message();
            wp_send_json_error( $status );
        } elseif ( is_wp_error( $skin->result ) ) {
            $status['errorCode']    = $skin->result->get_error_code();
            $status['errorMessage'] = $skin->result->get_error_message();
            wp_send_json_error( $status );
        } elseif ( $skin->get_errors()->get_error_code() ) {
            $status['errorMessage'] = $skin->get_error_messages();
            wp_send_json_error( $status );
        } elseif ( is_null( $result ) ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            WP_Filesystem();
            global $wp_filesystem;

            $status['errorCode']    = 'unable_to_connect_to_filesystem';
            $status['errorMessage'] = __( 'Unable to connect to the filesystem. Please confirm your credentials.', 'aki-toolset' );

            // Pass through the error from WP_Filesystem if one was raised.
            if ( $wp_filesystem instanceof WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->get_error_code() ) {
                $status['errorMessage'] = esc_html( $wp_filesystem->errors->get_error_message() );
            }

            wp_send_json_error( $status );
        }

        $install_status = install_plugin_install_status( $api );

        if ( current_user_can( 'activate_plugin', $install_status['file'] ) && is_plugin_inactive( $install_status['file'] ) ) {
            $result = activate_plugin( $install_status['file'] );

            if ( is_wp_error( $result ) ) {
                $status['errorCode']    = $result->get_error_code();
                $status['errorMessage'] = $result->get_error_message();
                wp_send_json_error( $status );
            }
        }

        wp_send_json_success( $status );

    }

    public function add_demo_lists( $current_demo_list ) {

        if( aki_toolset_get_current_theme_author() != $this->theme_author ){
            return  $current_demo_list;
        }

        $theme_slug = aki_toolset_get_current_theme_slug();

        switch ($theme_slug):
            case "opus-blog":
                $templates = array(
                    array(
                        'title' => __( 'Main Demo', 'aki-toolset' ),/*Title*/
                        'is_premium' => false,/*Premium*/
                        'type' => 'normal',
                        'author' => __( 'Akithemes', 'aki-toolset' ),/*Author Name*/
                        'keywords' => array( 'main', 'demo' ),/*Search keyword*/
                        'categories' => array( 'blog' ),/*Categories*/
                        'template_url' => array(
                            'content' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/default/content.json',
                            'options' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/default/options.json',
                            'widgets' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/default/widgets.json'
                        ),
                        'screenshot_url' => 'https://raw.githubusercontent.com/akithemes/opus-blog-demo/master/screenshots/main.jpg',/*Screenshot of block*/
                        'demo_url' => 'http://demo.akithemes.com/opus-blog/',/*Demo Url*/
                        'plugins' => array(
                            array(
                                'name'      => 'Gutentor',
                                'slug'      => 'gutentor',
                            ),
                            array(
                                'name'      => 'Everest Forms',
                                'slug'      => 'everest-forms',
                            ),
                            array(
                                'name'      => 'WooCommerce',
                                'slug'      => 'woocommerce',
                            ),
                        )
                    ),
                    array(
                        'title' => __( 'Gutenberg Demo', 'aki-toolset' ),/*Title*/
                        'is_premium' => false,/*Premium*/
                        'type' => 'gutenberg',
                        'author' => __( 'Akithemes', 'aki-toolset' ),/*Author Name*/
                        'keywords' => array( 'main', 'demo' ),/*Search keyword*/
                        'categories' => array( 'gutenberg' ),/*Categories*/
                        'template_url' => array(
                            'content' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/gutenberg/content.json',
                            'options' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/gutenberg/options.json',
                            'widgets' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/gutenberg/widgets.json'
                        ),
                        'screenshot_url' => 'https://raw.githubusercontent.com/akithemes/opus-blog-demo/master/screenshots/gutenberg.jpg',/*Screenshot of block*/
                        'demo_url' => 'http://demo.akithemes.com/opus-blog-2/',/*Demo Url*/
                        'plugins' => array(
                            array(
                                'name'      => 'Gutentor',
                                'slug'      => 'gutentor',
                            ),
                            array(
                                'name'      => 'Everest Forms',
                                'slug'      => 'everest-forms',
                            ),
                        )
                    ),
                    array(
                        'title' => __( 'Masonry Demo', 'aki-toolset' ),/*Title*/
                        'is_premium' => false,/*Premium*/
                        'type' => 'masonry',
                        'author' => __( 'Akithemes', 'aki-toolset' ),/*Author Name*/
                        'keywords' => array( 'main', 'demo' ),/*Search keyword*/
                        'categories' => array( 'blog' ),/*Categories*/
                        'template_url' => array(
                            'content' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/masonry/content.json',
                            'options' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/masonry/options.json',
                            'widgets' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/masonry/widgets.json'
                        ),
                        'screenshot_url' => 'https://raw.githubusercontent.com/akithemes/opus-blog-demo/master/screenshots/masonry.png',/*Screenshot of block*/
                        'demo_url' => 'http://demo.akithemes.com/opus-blog-1/',/*Demo Url*/
                        'plugins' => array(
                            array(
                                'name'      => 'Gutentor',
                                'slug'      => 'gutentor',
                            ),
                            array(
                                'name'      => 'Everest Forms',
                                'slug'      => 'everest-forms',
                            ),
                        )
                    ),
                    array(
                        'title' => __( 'Related Post Demo', 'aki-toolset' ),/*Title*/
                        'is_premium' => false,/*Premium*/
                        'type' => 'normal',
                        'author' => __( 'Akithemes', 'aki-toolset' ),/*Author Name*/
                        'keywords' => array( 'main', 'demo' ),/*Search keyword*/
                        'categories' => array( 'blog' ),/*Categories*/
                        'template_url' => array(
                            'content' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/related/content.json',
                            'options' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/related/options.json',
                            'widgets' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/related/widgets.json'
                        ),
                        'screenshot_url' => 'https://raw.githubusercontent.com/akithemes/opus-blog-demo/master/screenshots/related.jpg',/*Screenshot of block*/
                        'demo_url' => 'http://demo.akithemes.com/opus-blog-3/',/*Demo Url*/
                        'plugins' => array(
                            array(
                                'name'      => 'Gutentor',
                                'slug'      => 'gutentor',
                            ),
                            array(
                                'name'      => 'Everest Forms',
                                'slug'      => 'everest-forms',
                            ),
                        )
                    ),
                    array(
                        'title' => __( 'RTL Demo', 'aki-toolset' ),/*Title*/
                        'is_premium' => false,/*Premium*/
                        'type' => 'rtl',
                        'author' => __( 'Akithemes', 'aki-toolset' ),/*Author Name*/
                        'keywords' => array( 'main', 'demo' ),/*Search keyword*/
                        'categories' => array( 'rtl' ),/*Categories*/
                        'template_url' => array(
                            'content' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/rtl/content.json',
                            'options' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/rtl/options.json',
                            'widgets' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/rtl/widgets.json'
                        ),
                        'screenshot_url' => 'https://raw.githubusercontent.com/akithemes/opus-blog-demo/master/screenshots/rtl.jpg',/*Screenshot of block*/
                        'demo_url' => 'http://demo.akithemes.com/opus-blog-rtl/',/*Demo Url*/
                        'plugins' => array(
                            array(
                                'name'      => 'Gutentor',
                                'slug'      => 'gutentor',
                            ),
                            array(
                                'name'      => 'Everest Forms',
                                'slug'      => 'everest-forms',
                            ),
                        )
                    ),
                );
                break;
            case "opus-blog-plus":
                $templates = array(
                    array(
                        'title' => __( 'Main Demo', 'aki-toolset' ),/*Title*/
                        'is_premium' => false,/*Premium*/
                        'type' => 'woocommerce',
                        'author' => __( 'Akithemes', 'aki-toolset' ),/*Author Name*/
                        'keywords' => array( 'main', 'demo' ),/*Search keyword*/
                        'categories' => array( 'blog' ),/*Categories*/
                        'template_url' => array(
                            'content' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/default/content.json',
                            'options' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/default/options.json',
                            'widgets' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/default/widgets.json'
                        ),
                        'screenshot_url' => 'https://raw.githubusercontent.com/akithemes/opus-blog-plus-demo/master/screenshots/pro-demo.jpg',/*Screenshot of block*/
                        'demo_url' => 'http://demo.akithemes.com/opus-blog-plus/',/*Demo Url*/
                        'plugins' => array(
                            array(
                                'name'      => 'Gutentor',
                                'slug'      => 'gutentor',
                            ),
                            array(
                                'name'      => 'Everest Forms',
                                'slug'      => 'everest-forms',
                            ),
                            array(
                                'name'      => 'WooCommerce',
                                'slug'      => 'woocommerce',
                            ),
                        )
                    ),
                    array(
                        'title' => __( 'Lifestyle Demo', 'aki-toolset' ),/*Title*/
                        'is_premium' => false,/*Premium*/
                        'type' => 'normal',
                        'author' => __( 'Akithemes', 'aki-toolset' ),/*Author Name*/
                        'keywords' => array( 'main', 'demo' ),/*Search keyword*/
                        'categories' => array( 'masonry' ),/*Categories*/
                        'template_url' => array(
                            'content' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/lifestyle/content.json',
                            'options' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/lifestyle/options.json',
                            'widgets' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/lifestyle/widgets.json'
                        ),
                        'screenshot_url' => 'https://raw.githubusercontent.com/akithemes/opus-blog-plus-demo/master/screenshots/lifestyle.jpg',/*Screenshot of block*/
                        'demo_url' => 'http://demo.akithemes.com/opus-blog-plus-lifestyle/',/*Demo Url*/
                        'plugins' => array(
                            array(
                                'name'      => 'Gutentor',
                                'slug'      => 'gutentor',
                            ),
                            array(
                                'name'      => 'Everest Forms',
                                'slug'      => 'everest-forms',
                            ),
                        )
                    ),
                    array(
                        'title' => __( 'Fashion Demo', 'aki-toolset' ),/*Title*/
                        'is_premium' => false,/*Premium*/
                        'type' => 'normal',
                        'author' => __( 'Akithemes', 'aki-toolset' ),/*Author Name*/
                        'keywords' => array( 'main', 'demo' ),/*Search keyword*/
                        'categories' => array( 'blog' ),/*Categories*/
                        'template_url' => array(
                            'content' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/fashion/content.json',
                            'options' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/fashion/options.json',
                            'widgets' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/fashion/widgets.json'
                        ),
                        'screenshot_url' => 'https://raw.githubusercontent.com/akithemes/opus-blog-plus-demo/master/screenshots/fashion.jpg',/*Screenshot of block*/
                        'demo_url' => 'http://demo.akithemes.com/opus-blog-plus-fashion/',/*Demo Url*/
                        'plugins' => array(
                            array(
                                'name'      => 'Gutentor',
                                'slug'      => 'gutentor',
                            ),
                            array(
                                'name'      => 'Everest Forms',
                                'slug'      => 'everest-forms',
                            ),
                        )
                    ),
                    array(
                        'title' => __( 'Travel Demo', 'aki-toolset' ),/*Title*/
                        'is_premium' => false,/*Premium*/
                        'type' => 'alternate',
                        'author' => __( 'Akithemes', 'aki-toolset' ),/*Author Name*/
                        'keywords' => array( 'main', 'demo' ),/*Search keyword*/
                        'categories' => array( 'alternate' ),/*Categories*/
                        'template_url' => array(
                            'content' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/travel/content.json',
                            'options' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/travel/options.json',
                            'widgets' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/travel/widgets.json'
                        ),
                        'screenshot_url' => 'https://raw.githubusercontent.com/akithemes/opus-blog-plus-demo/master/screenshots/travel.jpg',/*Screenshot of block*/
                        'demo_url' => 'http://demo.akithemes.com/opus-blog-plus-travel/',/*Demo Url*/
                        'plugins' => array(
                            array(
                                'name'      => 'Gutentor',
                                'slug'      => 'gutentor',
                            ),
                            array(
                                'name'      => 'Everest Forms',
                                'slug'      => 'everest-forms',
                            ),
                        )
                    ),
                    array(
                        'title' => __( 'Video Demo', 'aki-toolset' ),/*Title*/
                        'is_premium' => false,/*Premium*/
                        'type' => 'masonry',
                        'author' => __( 'Akithemes', 'aki-toolset' ),/*Author Name*/
                        'keywords' => array( 'main', 'demo' ),/*Search keyword*/
                        'categories' => array( 'masonry' ),/*Categories*/
                        'template_url' => array(
                            'content' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/video/content.json',
                            'options' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/video/options.json',
                            'widgets' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/video/widgets.json'
                        ),
                        'screenshot_url' => 'https://raw.githubusercontent.com/akithemes/opus-blog-plus-demo/master/screenshots/video.jpg',/*Screenshot of block*/
                        'demo_url' => 'http://demo.akithemes.com/opus-blog-plus-video/',/*Demo Url*/
                        'plugins' => array(
                            array(
                                'name'      => 'Gutentor',
                                'slug'      => 'gutentor',
                            ),
                            array(
                                'name'      => 'Everest Forms',
                                'slug'      => 'everest-forms',
                            ),
                        )
                    ),
                    array(
                        'title' => __( 'RTL Demo', 'aki-toolset' ),/*Title*/
                        'is_premium' => false,/*Premium*/
                        'type' => 'rtl',
                        'author' => __( 'Akithemes', 'aki-toolset' ),/*Author Name*/
                        'keywords' => array( 'main', 'demo' ),/*Search keyword*/
                        'categories' => array( 'rtl' ),/*Categories*/
                        'template_url' => array(
                            'content' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/rtl/content.json',
                            'options' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/rtl/options.json',
                            'widgets' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/rtl/widgets.json'
                        ),
                        'screenshot_url' => 'https://raw.githubusercontent.com/akithemes/opus-blog-plus-demo/master/screenshots/pro-rtl.jpg',/*Screenshot of block*/
                        'demo_url' => 'http://demo.akithemes.com/opus-plus-rtl/',/*Demo Url*/
                        'plugins' => array(
                            array(
                                'name'      => 'Gutentor',
                                'slug'      => 'gutentor',
                            ),
                            array(
                                'name'      => 'Everest Forms',
                                'slug'      => 'everest-forms',
                            ),
                        )
                    ),
                    array(
                        'title' => __( 'Technology Demo', 'aki-toolset' ),/*Title*/
                        'is_premium' => false,/*Premium*/
                        'type' => 'alternate',
                        'author' => __( 'Akithemes', 'aki-toolset' ),/*Author Name*/
                        'keywords' => array( 'main', 'demo' ),/*Search keyword*/
                        'categories' => array( 'alternate' ),/*Categories*/
                        'template_url' => array(
                            'content' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/technology/content.json',
                            'options' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/technology/options.json',
                            'widgets' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/technology/widgets.json'
                        ),
                        'screenshot_url' => 'https://raw.githubusercontent.com/akithemes/opus-blog-plus-demo/master/screenshots/technology.jpg',/*Screenshot of block*/
                        'demo_url' => 'http://akithemes.com/opus-blog-technology/',/*Demo Url*/
                        'plugins' => array(
                            array(
                                'name'      => 'Gutentor',
                                'slug'      => 'gutentor',
                            ),
                            array(
                                'name'      => 'Everest Forms',
                                'slug'      => 'everest-forms',
                            ),
                        )
                    ),
                    array(
                        'title' => __( 'Left Sidebar Demo', 'aki-toolset' ),/*Title*/
                        'is_premium' => false,/*Premium*/
                        'type' => 'alternate',
                        'author' => __( 'Akithemes', 'aki-toolset' ),/*Author Name*/
                        'keywords' => array( 'main', 'demo' ),/*Search keyword*/
                        'categories' => array( 'blog' ),/*Categories*/
                        'template_url' => array(
                            'content' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/related/content.json',
                            'options' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/related/options.json',
                            'widgets' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/related/widgets.json'
                        ),
                        'screenshot_url' => 'https://raw.githubusercontent.com/akithemes/opus-blog-plus-demo/master/screenshots/related.jpg',/*Screenshot of block*/
                        'demo_url' => 'http://akithemes.com/opus-blog-3/',/*Demo Url*/
                        'plugins' => array(
                            array(
                                'name'      => 'Gutentor',
                                'slug'      => 'gutentor',
                            ),
                            array(
                                'name'      => 'Everest Forms',
                                'slug'      => 'everest-forms',
                            ),
                        )
                    ),
                    array(
                        'title' => __( 'Beauty Demo', 'aki-toolset' ),/*Title*/
                        'is_premium' => false,/*Premium*/
                        'type' => 'alternate',
                        'author' => __( 'Akithemes', 'aki-toolset' ),/*Author Name*/
                        'keywords' => array( 'main', 'demo' ),/*Search keyword*/
                        'categories' => array( 'blog' ),/*Categories*/
                        'template_url' => array(
                            'content' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/beauty/content.json',
                            'options' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/beauty/options.json',
                            'widgets' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/beauty/widgets.json'
                        ),
                        'screenshot_url' => 'https://raw.githubusercontent.com/akithemes/opus-blog-plus-demo/master/screenshots/beauty.jpg',/*Screenshot of block*/
                        'demo_url' => 'http://akithemes.com/opus-blog-plus-beauty/',/*Demo Url*/
                        'plugins' => array(
                            array(
                                'name'      => 'Gutentor',
                                'slug'      => 'gutentor',
                            ),
                            array(
                                'name'      => 'Everest Forms',
                                'slug'      => 'everest-forms',
                            ),
                        )
                    ),
                    /*
                     * Opus Blog Free Demos For the Premium Themes as well
                     *
                     * Since 1.0.1
                     */
                    array(
                        'title' => __( 'Default Free Demo', 'aki-toolset' ),/*Title*/
                        'is_premium' => false,/*Premium*/
                        'type' => 'normal',
                        'author' => __( 'Akithemes', 'aki-toolset' ),/*Author Name*/
                        'keywords' => array( 'main', 'demo' ),/*Search keyword*/
                        'categories' => array( 'blog' ),/*Categories*/
                        'template_url' => array(
                            'content' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/default/content.json',
                            'options' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/default/options.json',
                            'widgets' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/default/widgets.json'
                        ),
                        'screenshot_url' => 'https://raw.githubusercontent.com/akithemes/opus-blog-demo/master/screenshots/main.jpg',/*Screenshot of block*/
                        'demo_url' => 'http://demo.akithemes.com/opus-blog/',/*Demo Url*/
                        'plugins' => array(
                            array(
                                'name'      => 'Gutentor',
                                'slug'      => 'gutentor',
                            ),
                            array(
                                'name'      => 'Everest Forms',
                                'slug'      => 'everest-forms',
                            ),
                        )
                    ),
                    array(
                        'title' => __( 'Gutenberg Demo', 'aki-toolset' ),/*Title*/
                        'is_premium' => false,/*Premium*/
                        'type' => 'normal',
                        'author' => __( 'Akithemes', 'aki-toolset' ),/*Author Name*/
                        'keywords' => array( 'main', 'demo' ),/*Search keyword*/
                        'categories' => array( 'gutenberg' ),/*Categories*/
                        'template_url' => array(
                            'content' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/gutenberg/content.json',
                            'options' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/gutenberg/options.json',
                            'widgets' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/gutenberg/widgets.json'
                        ),
                        'screenshot_url' => 'https://raw.githubusercontent.com/akithemes/opus-blog-demo/master/screenshots/gutenberg.jpg',/*Screenshot of block*/
                        'demo_url' => 'http://demo.akithemes.com/opus-blog-2/',/*Demo Url*/
                        'plugins' => array(
                            array(
                                'name'      => 'Gutentor',
                                'slug'      => 'gutentor',
                            ),
                            array(
                                'name'      => 'Everest Forms',
                                'slug'      => 'everest-forms',
                            ),
                        )
                    ),
                    array(
                        'title' => __( 'Masonry Demo', 'aki-toolset' ),/*Title*/
                        'is_premium' => false,/*Premium*/
                        'type' => 'normal',
                        'author' => __( 'Akithemes', 'aki-toolset' ),/*Author Name*/
                        'keywords' => array( 'main', 'demo' ),/*Search keyword*/
                        'categories' => array( 'masonry' ),/*Categories*/
                        'template_url' => array(
                            'content' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/masonry/content.json',
                            'options' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/masonry/options.json',
                            'widgets' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/masonry/widgets.json'
                        ),
                        'screenshot_url' => 'https://raw.githubusercontent.com/akithemes/opus-blog-demo/master/screenshots/masonry.png',/*Screenshot of block*/
                        'demo_url' => 'http://demo.akithemes.com/opus-blog-1/',/*Demo Url*/
                        'plugins' => array(
                            array(
                                'name'      => 'Gutentor',
                                'slug'      => 'gutentor',
                            ),
                            array(
                                'name'      => 'Everest Forms',
                                'slug'      => 'everest-forms',
                            ),
                        )
                    ),
                    array(
                        'title' => __( 'Related Post Demo', 'aki-toolset' ),/*Title*/
                        'is_premium' => false,/*Premium*/
                        'type' => 'normal',
                        'author' => __( 'Akithemes', 'aki-toolset' ),/*Author Name*/
                        'keywords' => array( 'main', 'demo' ),/*Search keyword*/
                        'categories' => array( 'related' ),/*Categories*/
                        'template_url' => array(
                            'content' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/related/content.json',
                            'options' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/related/options.json',
                            'widgets' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/related/widgets.json'
                        ),
                        'screenshot_url' => 'https://raw.githubusercontent.com/akithemes/opus-blog-demo/master/screenshots/related.jpg',/*Screenshot of block*/
                        'demo_url' => 'http://demo.akithemes.com/opus-blog-3/',/*Demo Url*/
                        'plugins' => array(
                            array(
                                'name'      => 'Gutentor',
                                'slug'      => 'gutentor',
                            ),
                            array(
                                'name'      => 'Everest Forms',
                                'slug'      => 'everest-forms',
                            ),
                        )
                    ),
                    array(
                        'title' => __( 'RTL Demo', 'aki-toolset' ),/*Title*/
                        'is_premium' => false,/*Premium*/
                        'type' => 'normal',
                        'author' => __( 'Akithemes', 'aki-toolset' ),/*Author Name*/
                        'keywords' => array( 'main', 'demo' ),/*Search keyword*/
                        'categories' => array( 'rtl' ),/*Categories*/
                        'template_url' => array(
                            'content' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/rtl/content.json',
                            'options' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/rtl/options.json',
                            'widgets' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/rtl/widgets.json'
                        ),
                        'screenshot_url' => 'https://raw.githubusercontent.com/akithemes/opus-blog-demo/master/screenshots/rtl.jpg',/*Screenshot of block*/
                        'demo_url' => 'http://demo.akithemes.com/opus-blog-rtl/',/*Demo Url*/
                        'plugins' => array(
                            array(
                                'name'      => 'Gutentor',
                                'slug'      => 'gutentor',
                            ),
                            array(
                                'name'      => 'Everest Forms',
                                'slug'      => 'everest-forms',
                            ),
                        )
                    ),

                );
                break;
            case "free-blog":
                $templates = array(
                    array(
                        'title' => __( 'Default', 'akithemes' ),/*Title*/
                        'is_premium' => false,/*Premium*/
                        'type' => 'normal',/*Optional eg gutentor, elementor or other page builders*/
                        'author' => __( 'akithemes','akithemes' ),/*Author Name*/
                        'keywords' => array( 'main', 'demo' ,'demo-1' ),/*Search keyword*/
                        'categories' => array( 'blog' ),/*Categories*/
                        'template_url' => array(
                            'content' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/default/content.json',
                            'options' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/default/options.json',
                            'widgets' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/default/widgets.json'
                        ),
                        'screenshot_url' => 'https://raw.githubusercontent.com/akithemes/free-blog/master/screenshots/main.jpg',/*Screenshot of block*/
                        'demo_url' => 'http://akithemes.com/free-blog/',/*Demo Url*/
                        'plugins' => array(
                            array(
                                'name'      => 'Gutentor',
                                'slug'      => 'gutentor',
                            ),
                            array(
                                'name'      => 'Everest Forms',
                                'slug'      => 'everest-forms',
                            ),
                        )
                    ),
                    array(
                        'title' => __( 'Two Col', 'akithemes' ),/*Title*/
                        'is_premium' => false,/*Premium*/
                        'type' => 'normal',/*Optional eg gutentor, elementor or other page builders*/
                        'author' => __( 'akithemes', 'akithemes' ),/*Author Name*/
                        'keywords' => array( 'main', 'demo' ,'demo-2' ),/*Search keyword*/
                        'categories' => array( 'blog' ),/*Categories*/
                        'template_url' => array(
                            'content' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/two-col/content.json',
                            'options' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/two-col/options.json',
                            'widgets' => AKI_TOOLSET_TEMPLATE_URL.$theme_slug.'/two-col/widgets.json'
                        ),
                        'screenshot_url' => 'https://raw.githubusercontent.com/akithemes/free-blog/master/screenshots/masonry.jpg',/*Screenshot of block*/
                        'demo_url' => 'http://akithemes.com/free-blog-1/',/*Demo Url*/
                        'plugins' => array(
                            array(
                                'name'      => 'Gutentor',
                                'slug'      => 'gutentor',
                            ),
                            array(
                                'name'      => 'Everest Forms',
                                'slug'      => 'everest-forms',
                            ),

                        )
                    ),
                );
                break;
            default:
                $templates = array();
        endswitch;

        return array_merge( $current_demo_list, $templates );

    }
    public function replace_term_ids( $replace_term_ids ){

        /*Terms IDS*/
        $term_ids = array(
            'opus-blog-select-category',
            'opus-blog-promo-select-category',
        );

        return array_merge( $replace_term_ids, $term_ids );
    }
}

/**
 * Begins execution of the hooks.
 *
 * @since    1.0.0
 */
function aki_toolset_hooks( ) {
    return Aki_Toolset_Hooks::instance();
}