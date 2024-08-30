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
}

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
