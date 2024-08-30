<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set headers to allow CORS (if needed) and specify JSON response
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

/**
 * Function to check a WordPress site
 * 
 * @param string $url The URL of the site to check
 * @return array An array containing the check results
 */
function checkWordPressSite($url)
{
    $results = [
        'is_wordpress' => false,
        'version' => null,
        'theme' => null,
        'plugins' => [],
        'issues' => []
    ];

    // Normalize URL
    $url = rtrim($url, '/');

    // Initialize cURL session
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'WordPress Site Checker Bot');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Set a timeout of 30 seconds

    // Execute the request
    $html = curl_exec($ch);

    if (curl_errno($ch)) {
        $results['issues'][] = "Error accessing the site: " . curl_error($ch);
        curl_close($ch);
        return $results;
    }

    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($status_code != 200) {
        $results['issues'][] = "Site returned status code: $status_code";
    }

    curl_close($ch);

    // Check if it's a WordPress site
    if (strpos($html, '/wp-content/') !== false) {
        $results['is_wordpress'] = true;

        // Try to detect WordPress version
        preg_match('/<meta name="generator" content="WordPress ([0-9.]+)"/', $html, $version_matches);
        if (!empty($version_matches[1])) {
            $results['version'] = $version_matches[1];
        } else {
            $results['issues'][] = "Unable to detect WordPress version";
        }

        // Try to detect theme
        preg_match('/wp-content\/themes\/([^\/]+)/', $html, $theme_matches);
        if (!empty($theme_matches[1])) {
            $results['theme'] = $theme_matches[1];
        } else {
            $results['issues'][] = "Unable to detect WordPress theme";
        }

        // Try to detect plugins
        preg_match_all('/wp-content\/plugins\/([^\/]+)/', $html, $plugin_matches);
        if (!empty($plugin_matches[1])) {
            $results['plugins'] = array_values(array_unique($plugin_matches[1]));
        }

        // Check for potential security issues
        $config_url = $url . '/wp-config.php';
        $config_response = @file_get_contents($config_url);
        if ($config_response !== false) {
            $results['issues'][] = "wp-config.php might be publicly accessible. This is a severe security risk.";
        }

        $debug_url = $url . '/wp-content/debug.log';
        $debug_response = @file_get_contents($debug_url);
        if ($debug_response !== false) {
            $results['issues'][] = "Debug log might be publicly accessible. This may expose sensitive information.";
        }

        // Check if the site is using HTTPS
        if (strpos($url, 'https://') === false) {
            $results['issues'][] = "Site is not using HTTPS. Consider adding SSL for improved security.";
        }
    } else {
        $results['issues'][] = "This does not appear to be a WordPress site.";
    }

    return $results;
}

// Handle the incoming request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if the URL is provided
    if (isset($_POST['url']) && !empty($_POST['url'])) {
        $url = $_POST['url'];

        // Basic URL validation
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $results = checkWordPressSite($url);
            echo json_encode($results);
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
