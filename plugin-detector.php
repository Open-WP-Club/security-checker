<?php

/**
 * WordPress Plugin/Theme Detector
 * Uses client-side JavaScript to fetch pages and detect WordPress plugins/themes
 * to avoid server IP blocking
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simple logging function
function debug_log($message)
{
  error_log('[WP_DETECTOR] ' . $message);
}

class WordPressDetector
{

  private $common_plugins = [
    // Popular plugins with their identifiers
    'yoast-seo' => ['wp-content/plugins/wordpress-seo/', 'Yoast SEO'],
    'elementor' => ['wp-content/plugins/elementor/', 'Elementor'],
    'contact-form-7' => ['wp-content/plugins/contact-form-7/', 'Contact Form 7'],
    'woocommerce' => ['wp-content/plugins/woocommerce/', 'WooCommerce'],
    'jetpack' => ['wp-content/plugins/jetpack/', 'Jetpack'],
    'akismet' => ['wp-content/plugins/akismet/', 'Akismet'],
    'wordfence' => ['wp-content/plugins/wordfence/', 'Wordfence Security'],
    'w3-total-cache' => ['wp-content/plugins/w3-total-cache/', 'W3 Total Cache'],
    'wp-super-cache' => ['wp-content/plugins/wp-super-cache/', 'WP Super Cache'],
    'advanced-custom-fields' => ['wp-content/plugins/advanced-custom-fields/', 'Advanced Custom Fields'],
    'wp-rocket' => ['wp-content/plugins/wp-rocket/', 'WP Rocket'],
    'updraftplus' => ['wp-content/plugins/updraftplus/', 'UpdraftPlus'],
    'sucuri-scanner' => ['wp-content/plugins/sucuri-scanner/', 'Sucuri Security'],
    'mailchimp-for-wp' => ['wp-content/plugins/mailchimp-for-wp/', 'Mailchimp for WordPress'],
    'google-analytics' => ['wp-content/plugins/google-analytics-for-wordpress/', 'Google Analytics'],
    'wp-optimize' => ['wp-content/plugins/wp-optimize/', 'WP-Optimize'],
    'smush' => ['wp-content/plugins/wp-smushit/', 'Smush'],
    'wp-bakery' => ['wp-content/plugins/js_composer/', 'WPBakery Page Builder'],
    'slider-revolution' => ['wp-content/plugins/revslider/', 'Slider Revolution'],
    'wp-forms' => ['wp-content/plugins/wpforms-lite/', 'WPForms']
  ];

  private $common_themes = [
    'twenty-twenty-four' => ['wp-content/themes/twentytwentyfour/', 'Twenty Twenty-Four'],
    'twenty-twenty-three' => ['wp-content/themes/twentytwentythree/', 'Twenty Twenty-Three'],
    'twenty-twenty-two' => ['wp-content/themes/twentytwentytwo/', 'Twenty Twenty-Two'],
    'twenty-twenty-one' => ['wp-content/themes/twentytwentyone/', 'Twenty Twenty-One'],
    'astra' => ['wp-content/themes/astra/', 'Astra'],
    'hello-elementor' => ['wp-content/themes/hello-elementor/', 'Hello Elementor'],
    'generatepress' => ['wp-content/themes/generatepress/', 'GeneratePress'],
    'storefront' => ['wp-content/themes/storefront/', 'Storefront'],
    'oceanwp' => ['wp-content/themes/oceanwp/', 'OceanWP'],
    'neve' => ['wp-content/themes/neve/', 'Neve'],
    'kadence' => ['wp-content/themes/kadence/', 'Kadence'],
    'avada' => ['wp-content/themes/Avada/', 'Avada'],
    'divi' => ['wp-content/themes/Divi/', 'Divi'],
    'enfold' => ['wp-content/themes/enfold/', 'Enfold'],
    'x-theme' => ['wp-content/themes/x/', 'X Theme']
  ];

  public function analyzeContent($url, $html_content)
  {
    debug_log("Analyzing content for URL: " . $url);

    $results = [
      'url' => $url,
      'is_wordpress' => false,
      'plugins' => [],
      'themes' => [],
      'wordpress_version' => null,
      'additional_info' => []
    ];

    // Check if it's WordPress
    if ($this->isWordPress($html_content)) {
      $results['is_wordpress'] = true;
      debug_log("WordPress detected");

      // Get WordPress version
      $results['wordpress_version'] = $this->getWordPressVersion($html_content);

      // Detect plugins
      $results['plugins'] = $this->detectPlugins($html_content);

      // Detect themes
      $results['themes'] = $this->detectThemes($html_content);

      // Get additional WordPress info
      $results['additional_info'] = $this->getAdditionalInfo($html_content);
    } else {
      debug_log("Not a WordPress site");
    }

    return $results;
  }

  private function isWordPress($html)
  {
    $wordpress_indicators = [
      '/wp-content/',
      '/wp-includes/',
      'wp-json',
      'generator.*wordpress',
      'wp_enqueue_script',
      'wpematico',
      '/xmlrpc.php'
    ];

    foreach ($wordpress_indicators as $indicator) {
      if (preg_match('/' . preg_quote($indicator, '/') . '/i', $html)) {
        return true;
      }
    }

    return false;
  }

  private function getWordPressVersion($html)
  {
    // Look for WordPress version in generator meta tag
    if (preg_match('/<meta name="generator" content="WordPress ([^"]+)"/i', $html, $matches)) {
      return $matches[1];
    }

    return null;
  }

  private function detectPlugins($html)
  {
    $detected_plugins = [];

    foreach ($this->common_plugins as $slug => $plugin_data) {
      $path = $plugin_data[0];
      $name = $plugin_data[1];

      if (stripos($html, $path) !== false) {
        $detected_plugins[] = [
          'slug' => $slug,
          'name' => $name,
          'path' => $path
        ];
        debug_log("Plugin detected: " . $name);
      }
    }

    // Look for additional plugin paths
    if (preg_match_all('/wp-content\/plugins\/([^\/\'"]+)/i', $html, $matches)) {
      foreach ($matches[1] as $plugin_slug) {
        // Skip if already detected
        $already_detected = false;
        foreach ($detected_plugins as $detected) {
          if ($detected['slug'] === $plugin_slug) {
            $already_detected = true;
            break;
          }
        }

        if (!$already_detected) {
          $detected_plugins[] = [
            'slug' => $plugin_slug,
            'name' => ucwords(str_replace(['-', '_'], ' ', $plugin_slug)),
            'path' => "wp-content/plugins/{$plugin_slug}/"
          ];
          debug_log("Additional plugin detected: " . $plugin_slug);
        }
      }
    }

    return $detected_plugins;
  }

  private function detectThemes($html)
  {
    $detected_themes = [];

    foreach ($this->common_themes as $slug => $theme_data) {
      $path = $theme_data[0];
      $name = $theme_data[1];

      if (stripos($html, $path) !== false) {
        $detected_themes[] = [
          'slug' => $slug,
          'name' => $name,
          'path' => $path
        ];
        debug_log("Theme detected: " . $name);
      }
    }

    // Look for additional theme paths
    if (preg_match_all('/wp-content\/themes\/([^\/\'"]+)/i', $html, $matches)) {
      foreach ($matches[1] as $theme_slug) {
        // Skip if already detected
        $already_detected = false;
        foreach ($detected_themes as $detected) {
          if ($detected['slug'] === $theme_slug) {
            $already_detected = true;
            break;
          }
        }

        if (!$already_detected) {
          $detected_themes[] = [
            'slug' => $theme_slug,
            'name' => ucwords(str_replace(['-', '_'], ' ', $theme_slug)),
            'path' => "wp-content/themes/{$theme_slug}/"
          ];
          debug_log("Additional theme detected: " . $theme_slug);
        }
      }
    }

    return $detected_themes;
  }

  private function getAdditionalInfo($html)
  {
    $info = [];

    // Check for common page builders
    $page_builders = [
      'elementor' => 'Elementor',
      'vc_row' => 'WPBakery Page Builder',
      'et_pb_' => 'Divi Builder',
      'fusion-' => 'Avada Fusion Builder',
      'oxygen-' => 'Oxygen Builder'
    ];

    foreach ($page_builders as $identifier => $name) {
      if (stripos($html, $identifier) !== false) {
        $info[] = "Page Builder: {$name}";
      }
    }

    // Check for CDNs
    $cdns = [
      'cloudflare' => 'Cloudflare',
      'maxcdn' => 'MaxCDN',
      'amazonaws' => 'Amazon CloudFront',
      'googleusercontent' => 'Google CDN'
    ];

    foreach ($cdns as $identifier => $name) {
      if (stripos($html, $identifier) !== false) {
        $info[] = "CDN: {$name}";
      }
    }

    return $info;
  }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  header('Content-Type: application/json');

  if ($_POST['action'] === 'analyze') {
    $url = filter_var($_POST['url'] ?? '', FILTER_VALIDATE_URL);
    $html_content = $_POST['html_content'] ?? '';

    if (!$url) {
      echo json_encode(['error' => 'Invalid URL provided']);
      exit;
    }

    if (empty($html_content)) {
      echo json_encode(['error' => 'No HTML content provided']);
      exit;
    }

    debug_log("Processing analysis request for: " . $url);

    $detector = new WordPressDetector();
    $results = $detector->analyzeContent($url, $html_content);

    echo json_encode($results);
    exit;
  }
}

// HTML Interface
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WordPress Plugin/Theme Detector</title>
  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      max-width: 1200px;
      margin: 0 auto;
      padding: 20px;
      background-color: #f5f5f5;
    }

    .container {
      background: white;
      border-radius: 8px;
      padding: 30px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    h1 {
      color: #333;
      margin-bottom: 10px;
    }

    .subtitle {
      color: #666;
      margin-bottom: 30px;
    }

    .input-group {
      margin-bottom: 20px;
    }

    label {
      display: block;
      margin-bottom: 5px;
      font-weight: 600;
      color: #333;
    }

    input[type="url"] {
      width: 100%;
      padding: 12px;
      border: 2px solid #ddd;
      border-radius: 4px;
      font-size: 16px;
      box-sizing: border-box;
    }

    input[type="url"]:focus {
      outline: none;
      border-color: #007cba;
    }

    .btn {
      background: #007cba;
      color: white;
      padding: 12px 24px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 16px;
      font-weight: 600;
    }

    .btn:hover {
      background: #005a87;
    }

    .btn:disabled {
      background: #ccc;
      cursor: not-allowed;
    }

    .loading {
      display: none;
      text-align: center;
      padding: 20px;
      color: #666;
    }

    .results {
      margin-top: 30px;
      display: none;
    }

    .result-section {
      margin-bottom: 25px;
      padding: 20px;
      background: #f9f9f9;
      border-radius: 6px;
      border-left: 4px solid #007cba;
    }

    .result-section h3 {
      margin-top: 0;
      color: #333;
    }

    .plugin-item,
    .theme-item {
      background: white;
      padding: 12px;
      margin: 8px 0;
      border-radius: 4px;
      border: 1px solid #ddd;
    }

    .plugin-name,
    .theme-name {
      font-weight: 600;
      color: #333;
    }

    .plugin-path,
    .theme-path {
      font-size: 12px;
      color: #666;
      font-family: monospace;
    }

    .error {
      color: #d63638;
      background: #ffeaea;
      padding: 12px;
      border-radius: 4px;
      border: 1px solid #d63638;
      margin: 10px 0;
    }

    .success {
      color: #008a00;
      background: #eafaea;
      padding: 12px;
      border-radius: 4px;
      border: 1px solid #008a00;
      margin: 10px 0;
    }

    .info-item {
      background: #fff3cd;
      padding: 8px 12px;
      margin: 5px 0;
      border-radius: 4px;
      border: 1px solid #ffeaa7;
      color: #856404;
    }

    @media (max-width: 768px) {
      body {
        padding: 10px;
      }

      .container {
        padding: 20px;
      }
    }
  </style>
</head>

<body>
  <div class="container">
    <h1>WordPress Plugin/Theme Detector</h1>
    <p class="subtitle">Analyze any WordPress website to discover what plugins and themes it's using. This tool uses your browser to fetch the content, keeping your server IP safe.</p>

    <div class="input-group">
      <label for="website-url">Website URL:</label>
      <input type="url" id="website-url" placeholder="https://example.com" required>
    </div>

    <button class="btn" onclick="analyzeWebsite()">Analyze Website</button>

    <div class="loading" id="loading">
      <p>🔍 Analyzing website... This may take a few seconds.</p>
    </div>

    <div class="results" id="results"></div>
  </div>

  <script>
    async function analyzeWebsite() {
      const urlInput = document.getElementById('website-url');
      const loadingDiv = document.getElementById('loading');
      const resultsDiv = document.getElementById('results');
      const analyzeBtn = document.querySelector('.btn');

      const url = urlInput.value.trim();

      if (!url) {
        alert('Please enter a valid URL');
        return;
      }

      // Validate URL format
      try {
        new URL(url);
      } catch (urlError) {
        alert('Please enter a valid URL (include http:// or https://)');
        return;
      }

      // Show loading state
      analyzeBtn.disabled = true;
      loadingDiv.style.display = 'block';
      resultsDiv.style.display = 'none';

      try {
        let htmlContent = '';

        try {
          // First try direct fetch
          console.log('Fetching content from:', url);

          const response = await fetch(url, {
            method: 'GET',
            mode: 'cors',
            cache: 'no-cache'
          });

          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }

          htmlContent = await response.text();
          console.log('Direct fetch successful, analyzing...');

        } catch (corsError) {
          console.log('Direct fetch failed (CORS), trying proxy...', corsError.message);

          try {
            // Try with CORS proxy
            const proxyUrl = `https://api.allorigins.win/get?url=${encodeURIComponent(url)}`;
            console.log('Using proxy:', proxyUrl);

            const proxyResponse = await fetch(proxyUrl);
            const proxyData = await proxyResponse.json();

            if (proxyData.status && proxyData.status.http_code !== 200) {
              throw new Error(`Proxy returned HTTP ${proxyData.status.http_code}`);
            }

            htmlContent = proxyData.contents;
            console.log('Proxy fetch successful, analyzing...');

          } catch (proxyError) {
            console.log('Proxy also failed, trying alternative proxy...', proxyError.message);

            try {
              // Try alternative CORS proxy
              const altProxyUrl = `https://corsproxy.io/?${encodeURIComponent(url)}`;
              console.log('Using alternative proxy:', altProxyUrl);

              const altResponse = await fetch(altProxyUrl);
              if (!altResponse.ok) {
                throw new Error(`Alternative proxy HTTP error! status: ${altResponse.status}`);
              }

              htmlContent = await altResponse.text();
              console.log('Alternative proxy successful, analyzing...');

            } catch (altError) {
              console.error('All fetch methods failed:', altError);
              throw new Error(`Unable to fetch content. CORS blocked and proxies failed: ${altError.message}`);
            }
          }
        }

        if (!htmlContent || htmlContent.trim() === '') {
          throw new Error('No content received from the website');
        }

        // Send to PHP backend for analysis
        const analysisResponse = await fetch(window.location.href, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `action=analyze&url=${encodeURIComponent(url)}&html_content=${encodeURIComponent(htmlContent)}`
        });

        const analysisData = await analysisResponse.json();

        if (analysisData.error) {
          throw new Error(analysisData.error);
        }

        displayResults(analysisData);

      } catch (error) {
        console.error('Analysis error:', error);
        resultsDiv.innerHTML = `<div class="error">Error analyzing website: ${error.message}</div>`;
        resultsDiv.style.display = 'block';
      } finally {
        // Hide loading state
        analyzeBtn.disabled = false;
        loadingDiv.style.display = 'none';
      }
    }

    function displayResults(data) {
      const resultsDiv = document.getElementById('results');

      if (!data.is_wordpress) {
        resultsDiv.innerHTML = `
                    <div class="result-section">
                        <h3>❌ Not a WordPress Site</h3>
                        <p>The analyzed website does not appear to be running WordPress.</p>
                    </div>
                `;
        resultsDiv.style.display = 'block';
        return;
      }

      let html = `
                <div class="result-section">
                    <h3>✅ WordPress Site Detected</h3>
                    <p><strong>URL:</strong> ${data.url}</p>
                    ${data.wordpress_version ? `<p><strong>WordPress Version:</strong> ${data.wordpress_version}</p>` : ''}
                </div>
            `;

      // Display plugins
      if (data.plugins.length > 0) {
        html += `
                    <div class="result-section">
                        <h3>🔌 Detected Plugins (${data.plugins.length})</h3>
                `;

        data.plugins.forEach(plugin => {
          html += `
                        <div class="plugin-item">
                            <div class="plugin-name">${plugin.name}</div>
                            <div class="plugin-path">${plugin.path}</div>
                        </div>
                    `;
        });

        html += `</div>`;
      } else {
        html += `
                    <div class="result-section">
                        <h3>🔌 Plugins</h3>
                        <p>No common plugins detected in the HTML source.</p>
                    </div>
                `;
      }

      // Display themes
      if (data.themes.length > 0) {
        html += `
                    <div class="result-section">
                        <h3>🎨 Detected Themes (${data.themes.length})</h3>
                `;

        data.themes.forEach(theme => {
          html += `
                        <div class="theme-item">
                            <div class="theme-name">${theme.name}</div>
                            <div class="theme-path">${theme.path}</div>
                        </div>
                    `;
        });

        html += `</div>`;
      } else {
        html += `
                    <div class="result-section">
                        <h3>🎨 Themes</h3>
                        <p>No common themes detected in the HTML source.</p>
                    </div>
                `;
      }

      // Display additional info
      if (data.additional_info.length > 0) {
        html += `
                    <div class="result-section">
                        <h3>ℹ️ Additional Information</h3>
                `;

        data.additional_info.forEach(info => {
          html += `<div class="info-item">${info}</div>`;
        });

        html += `</div>`;
      }

      resultsDiv.innerHTML = html;
      resultsDiv.style.display = 'block';
    }

    // Allow Enter key to trigger analysis
    document.getElementById('website-url').addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        analyzeWebsite();
      }
    });
  </script>
</body>

</html>