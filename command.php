<?php
/**
 * Manage Revisions
 */
class WPRocket_CLI extends WP_CLI_Command
{

    /**
     * Set WP_CACHE constant in wp-config.php to true
     *
     * ## EXAMPLES
     *
     *     wp rocket activate
     *
     * @subcommand activate
     */
    public function activate()
    {

        if (defined('WP_CACHE') && !WP_CACHE)
        {

            if (is_writable(rocket_find_wpconfig_path()))
            {
                set_rocket_wp_cache_define(true);
                WP_CLI::success('WP Rocket is enable, WP_CACHE is set to true.');
            }
            else
            {
                WP_CLI::error('It seems we don\'t have writing permissions on wp-config.php file.');
            }

        }
        else
        {
            WP_CLI::error('WP Rocket is already enable.');
        }

    }

    /**
     * Set WP_CACHE constant in wp-config.php to false
     *
     * ## EXAMPLES
     *
     *     wp rocket deactivate
     *
     * @subcommand deactivate
     */
    public function deactivate()
    {

        if (defined('WP_CACHE') && WP_CACHE)
        {

            if (is_writable(rocket_find_wpconfig_path()))
            {
                set_rocket_wp_cache_define(false);
                WP_CLI::success('WP Rocket is disable, WP_CACHE is set to false.');
            }
            else
            {
                WP_CLI::error('It seems we don\'t have writing permissions on wp-config.php file.');
            }

        }
        else
        {
            WP_CLI::error('WP Rocket is already disable.');
        }

    }

    /**
     * Purge cache files
     *
     * ## OPTIONS
     *
     * [--post_id=<post_id>]
     * : List posts to purge cache files.
     *
     * [--permalink=<permalink>]
     * : List permalinks to purge cache files. Trumps --post_id.
     *
     * [--lang=<lang>]
     * : List langs to purge cache files. Trumps --post_id & --permalink.
     *
     * [--blog_id=<blog_id>]
     * : List blogs to purge cache files. Trumps --post_id & --permalink & lang.
     *
     * [--confirm]
     * : Automatic 'yes' to any confirmation
     *
     * ## EXAMPLES
     *
     *     wp rocket clean
     *     wp rocket clean --post_id=2
     *     wp rocket clean --post_id=2,4,6,8
     *     wp rocket clean --permalink=http://example.com
     *     wp rocket clean --permalink=http://example.com, http://example.com/category/(.*)
     *	   wp rocket clean --lang=fr
     *     wp rocket clean --lang=fr,de,en,it
     *	   wp rocket clean --blog_id=2
     *     wp rocket clean --blog_id=2,4,6,8
     *
     * @subcommand clean
     */
    public function clean($args = array() , $assoc_args = array())
    {

        if (!empty($assoc_args['blog_id']))
        {

            if (!defined('MULTISITE') || !MULTISITE)
            {
                WP_CLI::error('This installation doesn\'t multisite support.');
            }

            $blog_ids = explode(',', $assoc_args['blog_id']);
            $blog_ids = array_map('trim', $blog_ids);
            $total = 0;

            $notify = \WP_CLI\Utils\make_progress_bar('Delete cache files', count($blog_ids));

            foreach ($blog_ids as $blog_id)
            {

                if ($bloginfo = get_blog_details((int)$blog_id, false))
                {

                    switch_to_blog($blog_id);

                    rocket_clean_domain();
                    WP_CLI::line('Cache cleared for "' . esc_url('http://' . $bloginfo->domain . $bloginfo->path) . '".');

                    restore_current_blog();

                    $total++;

                }
                else
                {
                    WP_CLI::line('This blog ID "' . $blog_id . '" doesn\'t exist.');
                }

                $notify->tick();

            }

            $notify->finish();
            WP_CLI::success('Cache cleared for ' . $total . ' blog(s).');

        }
        else if (!empty($assoc_args['lang']))
        {

            if (!rocket_has_i18n())
            {
                WP_CLI::error('No WPML or qTranslate in this website.');
            }

            $langs = explode(',', $assoc_args['lang']);
            $langs = array_map('trim', $langs);
            $total = count($langs);

            $notify = \WP_CLI\Utils\make_progress_bar('Delete cache files', $total);

            foreach ($langs as $lang)
            {

                rocket_clean_domain_for_selected_lang($lang);
                $notify->tick();

            }

            $notify->finish();
            WP_CLI::success('Cache files cleared for ' . $total . ' lang(s).');

        }
        else if (!empty($assoc_args['permalink']))
        {

            $permalinks = explode(',', $assoc_args['permalink']);
            $permalinks = array_map('trim', $permalinks);
            $total = count($permalinks);

            $notify = \WP_CLI\Utils\make_progress_bar('Delete cache files', $total);

            foreach ($permalinks as $permalink)
            {

                rocket_clean_files($permalink);
                WP_CLI::line('Cache cleared for "' . $permalink . '".');

                $notify->tick();

            }

            $notify->finish();
            WP_CLI::success('Cache files cleared for ' . $total . ' permalink(s).');

        }
        else if (!empty($assoc_args['post_id']))
        {

            $total = 0;
            $post_ids = explode(',', $assoc_args['post_id']);
            $post_ids = array_map('trim', $post_ids);

            $notify = \WP_CLI\Utils\make_progress_bar('Delete cache files', count($post_ids));

            foreach ($post_ids as $post_id)
            {

                global $wpdb;
                $post_exists = $wpdb->get_row("SELECT ID FROM $wpdb->posts WHERE id = '" . (int)$post_id . "'");

                if ($post_exists)
                {

                    if (get_post_type($post_id) == 'attachment')
                    {

                        WP_CLI::line('This post ID "' . $post_id . '" is an attachment.');

                    }
                    else
                    {

                        rocket_clean_post($post_id);
                        WP_CLI::line('Cache cleared for post ID "' . $post_id . '".');
                        $total++;

                    }

                }
                else
                {

                    WP_CLI::line('This post ID "' . $post_id . '" doesn\'t exist.');

                }

                $notify->tick();

            }

            if ($total)
            {

                $notify->finish();

                if ($total == 1)
                {
                    WP_CLI::success('1 post is cleared.');
                }
                else
                {
                    WP_CLI::success($total . ' posts are cleared.');
                }

            }
            else
            {
                WP_CLI::error('No cache files are cleared.');
            }

        }
        else
        {

            if (!empty($assoc_args['confirm']) && $assoc_args['confirm'])
            {
                WP_CLI::line('Deleting all cache files.');
            }
            else
            {
                WP_CLI::confirm('Delete all cache files ?');
            }

            rocket_clean_domain();
            WP_CLI::success('All cache files cleared.');

        }

    }

    /**
     * Run WP Rocket Bot for preload cache files
     *
     * ## EXAMPLES
     *
     *     wp rocket preload
     *
     * @subcommand preload
     */
    public function preload($args = array() , $assoc_args = array())
    {
        run_rocket_bot('cache-preload');

        WP_CLI::success('Finished WP Rocket preload cache files.');
    }

    /**
     * Regenerate file
     *
     * ## OPTIONS
     *
     * [--file=<file>]
     * : The file to regenerate. It could be:
     *	- htaccess
     *	- advanced-cache
     *	- config (It's the config file stored in the wp-rocket-config folder)
     *
     * ## EXAMPLES
     *
     *	   wp rocket regenerate --file=htaccess
     *
     * @subcommand regenerate
     */
    public function regenerate($args = array() , $assoc_args = array())
    {
        if (!empty($assoc_args['file']))
        {
            switch ($assoc_args['file'])
            {
                case 'advanced-cache':
                    rocket_generate_advanced_cache_file();
                    WP_CLI::success('The advanced-cache.php file has just been regenerated.');
                break;
                case 'config':
                    rocket_generate_config_file();
                    WP_CLI::success('The config file has just been regenerated.');
                break;
                case 'htaccess':
                    $GLOBALS['is_apache'] = true;
                    flush_rocket_htaccess();
                    WP_CLI::success('The .htaccess file has just been regenerated.');
                break;
                default:
                    WP_CLI::error('You don\'t specify a good value for the "file" argument. It should be: advanced-cache, config or htaccess.');
                break;
            }

        }
        else
        {
            WP_CLI::error('You don\'t specify the "file" argument.');
        }
    }

    /**
     * Load settings
		 * 
		 * ## OPTIONS
		 * 
		 * [--file=<file>]
		 * : The settings file to load
		 * 
		 * ## EXAMPLES
		 * 
		 * 		wp rocket load_settings --file=settings-23042.json
		 * 
		 * @subcommand load_settings
     */
    public function load_settings($args = array() , $assoc_args = array())
    {
        if (!empty($assoc_args['file']))
        {
            $settings = rocket_direct_filesystem()->get_contents($assoc_args['file']);
            if ('text/plain' === $file_data['type'])
            {
                $gz = 'gz' . strrev('etalfni');
                $settings = $gz($settings);
                $settings = maybe_unserialize($settings);
            }
            elseif ('application/json' === $file_data['type'])
            {
                $settings = json_decode($settings, true);

                if (null === $settings)
                {
                    WP_CLI::error('Settings import failed: unexpected file content.');
                }
            }
            if (is_array($settings))
            {
                $options_api = new WP_Rocket\Admin\Options('wp_rocket_');
                $current_options = $options_api->get('settings', []);

                $settings['consumer_key'] = $current_options['consumer_key'];
                $settings['consumer_email'] = $current_options['consumer_email'];
                $settings['secret_key'] = $current_options['secret_key'];
                $settings['secret_cache_key'] = $current_options['secret_cache_key'];
                $settings['minify_css_key'] = $current_options['minify_css_key'];
                $settings['minify_js_key'] = $current_options['minify_js_key'];
                $settings['version'] = $current_options['version'];

                $options_api->set('settings', $settings);

                WP_CLI::success('Settings imported and saved.');
            }
        }
        else
        {
            WP_CLI::error('You don\'t specify the "file" argument.');
        }

    }
}

WP_CLI::add_command('rocket', 'WPRocket_CLI');