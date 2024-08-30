<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set headers to allow CORS (if needed) and specify JSON response
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

function sendUpdate($data)
{
    echo json_encode($data) . "\n";
    ob_flush();
    flush();
}

function checkWordPressSite($url)
{
    $results = [
        'is_wordpress' => false,
        'version' => null,
        'theme' => null,
        'plugins' => [],
        'issues' => [],
        'ssl_enabled' => false,
        'directory_indexing' => false,
        'wp_cron_found' => false,
        'user_enumeration' => false,
        'xml_rpc_enabled' => false,
        'hosting_provider' => null
    ];

    // Normalize URL
    $url = rtrim($url, '/');

    // Check SSL
    $results['ssl_enabled'] = (parse_url($url, PHP_URL_SCHEME) === 'https');
    sendUpdate(['ssl_enabled' => $results['ssl_enabled']]);

    // Initialize cURL session
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'WordPress Site Checker Bot');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    // Execute the request
    $html = curl_exec($ch);

    if (curl_errno($ch)) {
        $results['issues'][] = "Error accessing the site: " . curl_error($ch);
        sendUpdate(['issues' => $results['issues']]);
        curl_close($ch);
        return;
    }

    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($status_code != 200) {
        $results['issues'][] = "Site returned status code: $status_code";
        sendUpdate(['issues' => $results['issues']]);
    }

    // Get hosting provider information
    $ip = gethostbyname(parse_url($url, PHP_URL_HOST));
    $hosting_info = getHostingProvider($ip);
    $results['hosting_provider'] = $hosting_info;
    sendUpdate(['hosting_provider' => $results['hosting_provider']]);

    curl_close($ch);

    // Check if it's a WordPress site
    if (strpos($html, '/wp-content/') !== false) {
        $results['is_wordpress'] = true;
        sendUpdate(['is_wordpress' => true]);

        // Try to detect WordPress version
        preg_match('/<meta name="generator" content="WordPress ([0-9.]+)"/', $html, $version_matches);
        if (!empty($version_matches[1])) {
            $results['version'] = $version_matches[1];
            $results['issues'][] = "WordPress version is visible: " . $version_matches[1];
        }
        sendUpdate(['version' => $results['version']]);

        // Try to detect theme
        preg_match('/wp-content\/themes\/([^\/]+)/', $html, $theme_matches);
        if (!empty($theme_matches[1])) {
            $results['theme'] = $theme_matches[1];
        }
        sendUpdate(['theme' => $results['theme']]);

        // Try to detect plugins
        preg_match_all('/wp-content\/plugins\/([^\/]+)/', $html, $plugin_matches);
        if (!empty($plugin_matches[1])) {
            $plugins = array_values(array_unique($plugin_matches[1]));
            foreach ($plugins as $plugin) {
                $plugin_file = "$url/wp-content/plugins/$plugin/$plugin.php";
                $plugin_data = @file_get_contents($plugin_file);
                if ($plugin_data !== false) {
                    preg_match('/Version:\s*(.+)$/m', $plugin_data, $version_match);
                    $version = !empty($version_match[1]) ? trim($version_match[1]) : 'Unknown';
                    $results['plugins'][] = "$plugin|$version";
                } else {
                    $results['plugins'][] = "$plugin|Unknown";
                }
            }
        }
        sendUpdate(['plugins' => $results['plugins']]);

        // Check directory indexing
        $upload_dir = @file_get_contents("$url/wp-content/uploads/");
        $plugin_dir = @file_get_contents("$url/wp-content/plugins/");
        if (strpos($upload_dir, 'Index of') !== false || strpos($plugin_dir, 'Index of') !== false) {
            $results['directory_indexing'] = true;
            $results['issues'][] = "Directory indexing is enabled";
        }
        sendUpdate(['directory_indexing' => $results['directory_indexing']]);

        // Check wp-cron.php
        $wp_cron = @file_get_contents("$url/wp-cron.php");
        if ($wp_cron !== false) {
            $results['wp_cron_found'] = true;
            $results['issues'][] = "wp-cron.php is accessible";
        }
        sendUpdate(['wp_cron_found' => $results['wp_cron_found']]);

        // Check user enumeration
        $user_check = @file_get_contents("$url/?author=1");
        if ($user_check !== false && strpos($user_check, 'author/') !== false) {
            $results['user_enumeration'] = true;
            $results['issues'][] = "User enumeration is possible";
        }
        sendUpdate(['user_enumeration' => $results['user_enumeration']]);

        // Check XML-RPC
        $xmlrpc = @file_get_contents("$url/xmlrpc.php");
        if ($xmlrpc !== false && strpos($xmlrpc, 'XML-RPC server accepts POST requests only.') !== false) {
            $results['xml_rpc_enabled'] = true;
            $results['issues'][] = "XML-RPC is enabled";
        }
        sendUpdate(['xml_rpc_enabled' => $results['xml_rpc_enabled']]);
    } else {
        $results['issues'][] = "This does not appear to be a WordPress site.";
        sendUpdate(['is_wordpress' => false]);
    }

    sendUpdate(['issues' => $results['issues']]);
}

function getHostingProvider($ip)
{
    $whois = shell_exec("whois $ip");
    $hosting_providers = [
        'Amazon' => 'https://aws.amazon.com',
        'DigitalOcean' => 'https://www.digitalocean.com',
        'Google' => 'https://cloud.google.com',
        'Linode' => 'https://www.linode.com',
        'OVH' => 'https://www.ovh.com',
        'Rackspace' => 'https://www.rackspace.com',
        'Hetzner' => 'https://www.hetzner.com',
        'GoDaddy' => 'https://www.godaddy.com',
        'Bluehost' => 'https://www.bluehost.com',
        'HostGator' => 'https://www.hostgator.com',
        'SiteGround' => 'https://www.siteground.com',
        'InMotion' => 'https://www.inmotionhosting.com',
        'DreamHost' => 'https://www.dreamhost.com',
        'A2 Hosting' => 'https://www.a2hosting.com',
        'WP Engine' => 'https://wpengine.com',
        'Kinsta' => 'https://kinsta.com',
        'Cloudways' => 'https://www.cloudways.com',
        'Flywheel' => 'https://getflywheel.com',
        'Pantheon' => 'https://pantheon.io',
        'Netlify' => 'https://www.netlify.com',
        'Vercel' => 'https://vercel.com',
    ];

    foreach ($hosting_providers as $provider => $url) {
        if (stripos($whois, $provider) !== false) {
            return ['name' => $provider, 'url' => $url];
        }
    }

    return ['name' => 'Unknown', 'url' => null];
}

// Handle the incoming request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if the URL is provided
    if (isset($_POST['url']) && !empty($_POST['url'])) {
        $url = $_POST['url'];

        // Basic URL validation
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            checkWordPressSite($url);
        } else {
            echo json_encode(['error' => 'Invalid URL provided']);
        }
    } else {
        echo json_encode(['error' => 'No URL provided']);
    }
} else {
    // If not a POST request, return an error
    echo json_encode(['error' => 'Invalid request method. Use POST to check a site.']);
}
