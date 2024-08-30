<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'WordPressChecker.php';

// Handle the incoming request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Check if the URL is provided
  if (isset($_POST['url']) && !empty($_POST['url'])) {
    $url = $_POST['url'];

    // Basic URL validation
    if (filter_var($url, FILTER_VALIDATE_URL)) {
      $checker = new WordPressChecker($url);
      $checker->checkSite();
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
