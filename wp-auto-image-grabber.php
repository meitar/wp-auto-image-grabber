<?php
/*
Plugin Name: WP-Auto Image Grabber
Plugin URI: http://maymay.net/blog/projects/wp-auto-image-grabber/
Description: Fetches images from a remote source and displays them by deep-linking to the source.
Version: 0.3.1
Author: Meitar Moscovitz
Author URI: http://maymay.net/
*/

class WP_AutoImageGrabber {

    var $options; /**< Array of plugin options. */

    /**
     * Constructor
     */
    function WP_AutoImageGrabber () {
        $this->options = get_option('wp_aig_options', array(
                'img_class' => 'wp-auto-image-grabber alignright',
                'dst_page'  => '//a[1]',
                'img_pats'  => implode("\n", array( // default patterns as string
                    '//table[contains(@class, "image")]//img[1]', // blogs.laweekly.com
                    '//*[@id="content"]//figure//img[1]', // The Guardian
                    '//*[@id="content"]//img[1]',         // TechYum
                    '//*[@id="articlecontent"]//img[contains(@class, "thumb")][1]',  // SFGate.com
                    '//*[contains(@class, "articleBody")]//img[1]', // CafeMom.com
                    '//*[contains(@class, "mainimage")]//img[1]', // Newsweek
                    '//*[contains(@class, "content")]//img[1]', // Gawker
                    '//*[@class="entry"]//img[1]', // just a common pattern
                    '//*[@class="entryContent"]//img[1]', // ThinkProgress
                    '//*[@class="entry-content"]//*[@class="image"]/img[1]', // NYMag.com
                    '//*[@class="storyimage"]/img[1]', // Xtra.ca
                    '//*[contains(@class, "hentry")]//img[1]' // microformat pattern
                ))
            ));
    }

    /**
     * WordPress Activation Hook callback
     */
    function activate () {
        if (!get_option('wp_aig_options')) {
            add_option('wp_aig_options', $this->options);
        }
    }

    /**
     * Takes content of post and returns filtered content.
     */
    function filterContent ($content) {
        // TODO: Remove this AFTER caching is implemented.
        if (!is_single()) { return $content; } // don't hog resources if not single post

        // Find the correct destination to search for images.
        $uri = $this->findDestinationPage($content);
        if (!$uri) { return $content; } // return unmodified content

        // Retrieve the main image from the destination.
        $img = $this->findMainImage($uri);
        if (false === $img) { return $content; } // no match, do not filter
        $src = $img->getAttribute('src');
        $src = $this->maybeMakeFQURL($src, $uri);

        $alt = $img->getAttribute('alt');

        // Prepend the appropriate image code
        return "<p><img class=\"{$this->options['img_class']}\" src=\"$src\" alt=\"$alt\" /></p>" . $content;
    }

    /**
     * Determine destination.
     */
    function findDestinationPage ($content) {
        $xquery = $this->options['dst_page'];
        try {
            @$dom  = DOMDocument::loadHTML($content);
            $xpath = new DOMXpath($dom);
        } catch (Exception $e) { /* fail silently; do nothing */ }
        $links = $xpath->query($xquery);
        foreach ($links as $link) {
            // Return in loop as we only need whatever the first item is, anyway.
            return ($link->getAttribute('href')) ? $link->getAttribute('href') : false;
        }
    }

    /**
     * Scan remote HTML, return URI of best matching image.
     *
     * @return object DOM element of matched image, false if no match.
     */
    function findMainImage ($uri) {
        $html = @file_get_contents($uri);

        $xpaths = (is_string($this->options['img_pats'])) ?
            explode("\n", $this->options['img_pats']) : $this->options['img_pats'];
        try {
            @$dom  = DOMDocument::loadHTML($html);
            if ($dom) {
                $xpath = new DOMXpath($dom); // avoid passing FALSE
            } else {
                return false;
            }
        } catch (Exception $e) { /* fail silently; do nothing */ }
        foreach ($xpaths as $xquery) {
            $results = $xpath->query($xquery);
            if (0 < $results->length) { return $results->item(0); }
        }
        return false;
    }

    /**
     * Make a fully-qualified URL if necessary.
     *
     * @param string url The suspected partial URL to maybe make fully-qualified.
     * @param string referer The referring URL from which the partial was extracted.
     *
     * @return string The resolved, fully-qualified URL, or the original one.
     */
    function maybeMakeFQURL ($url, $referer) {
        if ('/' === substr($url, 0, 1)) {
            $x = parse_url($referer);
            return $x['scheme'] . '://' . $x['host'] . $url;
        } else {
            return $url;
        }
    }
}

$WP_AutoImageGrabber = new WP_AutoImageGrabber();
register_activation_hook(__FILE__, array($WP_AutoImageGrabber, 'activate'));
add_filter('the_content', array($WP_AutoImageGrabber, 'filterContent'));

function WP_AutoImageGrabber_menu () {
    add_options_page('Auto Image Grabber', 'Auto Image Grabber', 8, __FILE__, 'WP_AutoImageGrabber_options');
}
add_action('admin_menu', 'WP_AutoImageGrabber_menu');

/**
 * Provides plugin-wide options screen.
 */
function WP_AutoImageGrabber_options () {
    $options = get_option('wp_aig_options');
?>
<div class="wrap">
<form method="post" action="options.php">

    <div id="icon-options-general" class="icon32"></div>
    <h2>Auto Image Grabber options</h2>

    <?php wp_nonce_field('update-options'); ?>

    <table class="form-table" summary="Auto Image Grabber options">
        <tr valign="top">
            <th scope="row"><label for="wp_aig_options[img_class]">Image class value</label></th>
            <td>
                <input type="text" id="wp_aig_options[img_class]" name="wp_aig_options[img_class]" class="regular-text" value="<?php print htmlentities($options['img_class'], ENT_QUOTES, "UTF-8");?>" /><br />
                <span class="setting-description">The class name of the automatically-added <code>&lt;img&gt;</code> element. (Defaults to <code>wp-auto-image-grabber alignright</code>.)</span>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row"><label for="wp_aig_options[dst_page]">Pointer element for destination page</label></th>
            <td>
                <input type="text" id="wp_aig_options[dst_page]" name="wp_aig_options[dst_page]" class="regular-text" value="<?php print htmlentities($options['dst_page'], ENT_QUOTES, "UTF-8");?>" /><br />
                <span class="setting-description">The XPath query returning the element pointing to the remote page. Should be an <code>&lt;a&gt;</code> element. (Defaults to <code>//a[1]</code>.)</span>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row"><label for="wp_aig_options[img_pats]">Image patterns</label></th>
            <td>
                <textarea id="wp_aig_options[img_pats]" name="wp_aig_options[img_pats]" rows="15" cols="60"><?php print htmlentities($options['img_pats'], ENT_QUOTES, "UTF-8");?></textarea><br />
                <span class="setting-description">Set of ordered XPath queries used to locate a main image in remote sites, one per line. Put more specific queries on top, more general patterns at bottom.</span>
            </td>
        </tr>
    </table>

    <p style="text-align: center;">I &hearts; the Internet. Don't use images you don't link back to. &mdash; <a href="http://maymay.net/">Meitar</a></p>

    <input type="hidden" name="action" value="update" />
    <input type="hidden" name="page_options" value="wp_aig_options" />
    <p class="submit">
        <input type="submit" name="Submit" value="<?php _e('Save Changes') ?>" />
    </p>
</form>
</div><!-- END .wrap -->
<?
}
?>
