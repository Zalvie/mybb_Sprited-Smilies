<?php

    defined('IN_MYBB') or die('Nope');
    defined('PLUGINLIBRARY') or define('PLUGINLIBRARY', MYBB_ROOT . 'inc/plugins/pluginlibrary.php');

    define('SMILIE_FILE', 'images/smilies.png');

    function SpritedSmilies_info() {
        return array(
            'name'          => 'Sprited Smilies',
            'description'   => 'Generates the smilies into sprites for less image requests.',
            'author'        => 'Rakes / Cake / Zalvie',
            'website'       => 'https://github.com/Zalvie',
            'version'       => '0.1',
            'compatibility' => '16*'
        );
    }

    function SpritedSmilies_activate() {
        global $PL, $config;

        if ( !file_exists(PLUGINLIBRARY) ) {
            flash_message('PluginLibrary is missing, get it at <a href="http://mods.mybb.com/view/pluginlibrary">http://mods.mybb.com/view/pluginlibrary</a>.', 'error');
            admin_redirect('index.php?module=config-plugins');
        }

        $PL or require_once PLUGINLIBRARY;

        if ( $PL->version < 9 ) {
            flash_message('This plugin requires PluginLibrary 9 or newer', 'error');
            admin_redirect('index.php?module=config-plugins');
        }

        if ( !extension_loaded('gd') ) {
            flash_message('This plugin requires GD to be enabled.', 'error');
            admin_redirect('index.php?module=config-plugins');
        }

        $PL->edit_core('SpritedSmilies', 'inc/class_parser.php',
                       array(array('search'  => 'strip_tags($replace, "<img>");',
                                   'replace' => 'strip_tags($replace, "<i>");'),
                             array('search'  => array('$this->smilies_cache[$smilie[\'find\']]', ';'),
                                   'replace' => '$this->smilies_cache[$smilie[\'find\']] = "<i class=\"smilie-{$smilie[\'sid\']}\" title=\"{$smilie[\'name\']}\"></i>";')), TRUE);

        SpritedSmilies_generate();
    }

    function SpritedSmilies_deactivate() {
        global $PL, $config;
        $PL or require_once PLUGINLIBRARY;
        $PL->edit_core('SpritedSmilies', 'inc/class_parser.php', array(), TRUE);
        $PL->stylesheet_delete('SpritedSmilies');
    }

    function SpritedSmilies_generate() {
        global $cache, $PL;

        $PL or require_once PLUGINLIBRARY;

        $x = $height = $width = 0;

        $images = array();

        $smilies = $cache->read('smilies');

        foreach ( $smilies as $smilie ) {
            $imageSize = @getimagesize(MYBB_ROOT . $smilie['image']);

            if ( $imageSize === FALSE ) continue;

            list($itemWidth, $itemHeight, $itemType) = $imageSize;

            $images[$smilie['sid']] = array('height' => $itemHeight, 'width' => $itemWidth, 'x' => $x, 'image' => MYBB_ROOT . $smilie['image'], 'ext' => image_type_to_extension($itemType, FALSE));

            if ( $itemHeight > $height )
                $height = $itemHeight;

            $width += $itemWidth;

            $x += $itemWidth;
        }

        !empty($images) or die('Failed');

        $css = '[class^=smilie-],[class*=" smilie-"] {background-image:url(' . SMILIE_FILE . '?dateline=' . time() . ');background-position:0 0;background-repeat:no-repeat;display:inline-block;height: 0px; width: 0px;}';

        $dest = imagecreatetruecolor($width, $height);

        imagesavealpha($dest, TRUE);
        imagefill($dest, 0, 0, imagecolorallocatealpha($dest, 0, 0, 0, 127));

        foreach ( $images as $id => $smilie ) {
            $imgCreateFunc = 'imagecreatefrom' . $smilie['ext'];

            if ( !function_exists($imgCreateFunc) ) continue;

            $src = $imgCreateFunc($smilie['image']);
            imagealphablending($src, TRUE);
            imagesavealpha($src, TRUE);
            imagecopy($dest, $src, $smilie['x'], 0, 0, 0, $smilie['width'], $smilie['height']);
            imagedestroy($src);
            $css .= sprintf('.smilie-%d{background-position:-%dpx 0;width:%dpx;height:%dpx;}', $id, $smilie['x'], $smilie['width'], $smilie['height']);
        }

        imagepng($dest, MYBB_ROOT . SMILIE_FILE);

        $PL->stylesheet('SpritedSmilies', $css);

    }

    $plugins->add_hook('admin_config_smilies_delete_commit', 'SpritedSmilies_generate');
    $plugins->add_hook('admin_config_smilies_edit_commit', 'SpritedSmilies_generate');
    $plugins->add_hook('admin_config_smilies_add_commit', 'SpritedSmilies_generate');
    $plugins->add_hook('admin_config_smilies_mass_edit_commit', 'SpritedSmilies_generate');
    $plugins->add_hook('admin_config_smilies_add_multiple_commit', 'SpritedSmilies_generate');
