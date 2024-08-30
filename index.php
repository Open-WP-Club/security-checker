<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Function to send updates
function sendUpdate($data)
{
  echo json_encode($data) . "\n";
  ob_flush();
  flush();
}

// Function to check WordPress site
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
    'hosting_provider' => null,
    'robots_txt' => null
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

  // Check robots.txt
  $robots_txt = @file_get_contents("$url/robots.txt");
  if ($robots_txt !== false) {
    $results['robots_txt'] = "Found";
    sendUpdate(['robots_txt' => $results['robots_txt']]);
  } else {
    $results['robots_txt'] = "Not found";
    sendUpdate(['robots_txt' => $results['robots_txt']]);
  }

  // Check if it's a WordPress site
  if (strpos($html, '/wp-content/') !== false) {
    $results['is_wordpress'] = true;
    sendUpdate(['is_wordpress' => true]);

    // WordPress version check
    preg_match('/<meta name="generator" content="WordPress ([0-9.]+)"/', $html, $version_matches);
    if (!empty($version_matches[1])) {
      $results['version'] = $version_matches[1];
      $results['issues'][] = "WordPress version is visible: " . $version_matches[1];
    }
    sendUpdate(['version' => $results['version']]);

    // Theme check
    preg_match('/wp-content\/themes\/([^\/]+)/', $html, $theme_matches);
    if (!empty($theme_matches[1])) {
      $results['theme'] = $theme_matches[1];
    }
    sendUpdate(['theme' => $results['theme']]);

    // Plugin check
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

    // Directory indexing check
    $upload_dir = @file_get_contents("$url/wp-content/uploads/");
    $plugin_dir = @file_get_contents("$url/wp-content/plugins/");
    if (strpos($upload_dir, 'Index of') !== false || strpos($plugin_dir, 'Index of') !== false) {
      $results['directory_indexing'] = true;
      $results['issues'][] = "Directory indexing is enabled";
    }
    sendUpdate(['directory_indexing' => $results['directory_indexing']]);

    // wp-cron.php check
    $wp_cron = @file_get_contents("$url/wp-cron.php");
    if ($wp_cron !== false) {
      $results['wp_cron_found'] = true;
      $results['issues'][] = "wp-cron.php is accessible";
    }
    sendUpdate(['wp_cron_found' => $results['wp_cron_found']]);

    // User enumeration check
    $user_check = @file_get_contents("$url/?author=1");
    if ($user_check !== false && strpos($user_check, 'author/') !== false) {
      $results['user_enumeration'] = true;
      $results['issues'][] = "User enumeration is possible";
    }
    sendUpdate(['user_enumeration' => $results['user_enumeration']]);

    // XML-RPC check
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

// Function to get hosting provider
function getHostingProvider($ip)
{
  $hosting_providers = [
    'Amazon' => ['amazonaws.com', 'aws.amazon.com'],
    'DigitalOcean' => ['digitalocean.com'],
    'Google' => ['googleusercontent.com', 'google.com'],
    'Linode' => ['linode.com'],
    'OVH' => ['ovh.net', 'ovh.com'],
    'Rackspace' => ['rackspace.com'],
    'Hetzner' => ['hetzner.com', 'your-server.de'],
    'GoDaddy' => ['godaddy.com', 'secureserver.net'],
    'Bluehost' => ['bluehost.com'],
    'HostGator' => ['hostgator.com'],
    'SiteGround' => ['siteground.com'],
    'InMotion' => ['inmotionhosting.com'],
    'DreamHost' => ['dreamhost.com'],
    'A2 Hosting' => ['a2hosting.com'],
    'WP Engine' => ['wpengine.com'],
    'Kinsta' => ['kinsta.com'],
    'Cloudways' => ['cloudways.com'],
    'Flywheel' => ['getflywheel.com'],
    'Pantheon' => ['pantheonsite.io'],
    'Netlify' => ['netlify.com'],
    'Vercel' => ['vercel.app'],
  ];

  // Perform a reverse DNS lookup
  $host = gethostbyaddr($ip);

  foreach ($hosting_providers as $provider => $domains) {
    foreach ($domains as $domain) {
      if (stripos($host, $domain) !== false) {
        return [
          'name' => $provider,
          'url' => 'https://www.' . $domains[0]
        ];
      }
    }
  }

  // If no match found, try to get more information using ip-api.com
  $api_url = "http://ip-api.com/json/" . $ip;
  $response = @file_get_contents($api_url);
  if ($response !== false) {
    $data = json_decode($response, true);
    if ($data && $data['status'] === 'success') {
      return [
        'name' => $data['org'] ?? $data['isp'] ?? 'Unknown',
        'url' => null
      ];
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
  exit; // End execution after handling POST request
}

// Define an array of checks
$checks = [
  'isWordPress' => 'Is WordPress',
  'wpVersion' => 'WordPress Version',
  'wpTheme' => 'Theme',
  'wpPlugins' => 'Plugins',
  'sslEnabled' => 'SSL Enabled',
  'directoryIndexing' => 'Directory Indexing',
  'wpCronFound' => 'wp-cron.php Found',
  'userEnumeration' => 'User Enumeration',
  'xmlRpcEnabled' => 'XML-RPC Enabled',
  'hostingProvider' => 'Hosting Provider',
  'robotsTxt' => 'robots.txt',
  'wpIssues' => 'Issues'
];

// Define information for each check
$checkInfo = [
  'isWordPress' => 'Determines if the site is built with WordPress. This is important for applying WordPress-specific security checks.',
  'wpVersion' => 'Checks the WordPress version. Keeping WordPress updated is crucial for security. Visible versions may expose vulnerabilities.',
  'wpTheme' => 'Identifies the active theme. Themes should be kept updated to patch security vulnerabilities.',
  'wpPlugins' => 'Lists detected plugins. Like themes, plugins should be kept updated to maintain security.',
  'sslEnabled' => 'Checks if the site uses HTTPS. SSL encryption is crucial for securing data transmission between the server and users.',
  'directoryIndexing' => 'Checks if directory listing is enabled. If enabled, it can expose sensitive files and information to potential attackers.',
  'wpCronFound' => 'Checks if wp-cron.php is accessible. If found, it could potentially be used for Denial of Service attacks.',
  'userEnumeration' => 'Checks if user enumeration is possible. This can be used by attackers to gather usernames for brute force attacks.',
  'xmlRpcEnabled' => 'Checks if XML-RPC is enabled. While useful for some integrations, it can be exploited for brute force attacks if not properly secured.',
  'hostingProvider' => 'Identifies the hosting provider. Different providers offer varying levels of security and performance.',
  'robotsTxt' => 'Checks for the presence of a robots.txt file. This file can control search engine crawling and potentially reveal sensitive directories.',
  'wpIssues' => 'Summarizes potential security issues found during the scan.'
];

// Include the template
include 'template.php';
