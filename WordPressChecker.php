<?php

class WordPressChecker
{
    private $url;
    private $results;

    public function __construct($url)
    {
        $this->url = rtrim($url, '/');
        $this->results = [
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

    public function checkSite()
    {
        $this->runTest('checkSSL');
        $this->runTest('getHostingProvider');
        $this->runTest('checkRobotsTxt');
        $this->runTest('checkWordPressContent');

        $this->sendUpdate(['final_results' => $this->results]);
    }

    private function runTest($testMethod)
    {
        try {
            $this->$testMethod();
        } catch (Exception $e) {
            $this->results['issues'][] = "Error in {$testMethod}: " . $e->getMessage();
            $this->sendUpdate(['error' => "Error in {$testMethod}", 'message' => $e->getMessage()]);
        }
    }

    private function sendUpdate($data)
    {
        echo json_encode($data) . "\n";
        ob_flush();
        flush();
    }

    private function checkSSL()
    {
        $this->results['ssl_enabled'] = (parse_url($this->url, PHP_URL_SCHEME) === 'https');
        $this->sendUpdate(['ssl_enabled' => $this->results['ssl_enabled']]);
    }

    private function getHostingProvider()
    {
        $ip = gethostbyname(parse_url($this->url, PHP_URL_HOST));
        $this->results['hosting_provider'] = $this->detectHostingProvider($ip);
        $this->sendUpdate(['hosting_provider' => $this->results['hosting_provider']]);
    }

    private function detectHostingProvider($ip)
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

    private function checkRobotsTxt()
    {
        $robots_txt_url = $this->url . "/robots.txt";
        $context = stream_context_create(['http' => ['ignore_errors' => true]]);
        $robots_txt = @file_get_contents($robots_txt_url, false, $context);

        if ($robots_txt === false) {
            $this->results['robots_txt'] = "Not found or inaccessible";
            $this->results['issues'][] = "robots.txt is not found or inaccessible";
        } else {
            $http_response_header = $http_response_header ?? [];
            $status_line = $http_response_header[0] ?? '';
            if (strpos($status_line, '200') !== false) {
                $this->results['robots_txt'] = "Found";
            } else {
                $this->results['robots_txt'] = "Not found (Status: " . substr($status_line, 9) . ")";
                $this->results['issues'][] = "robots.txt returned an unexpected status: " . substr($status_line, 9);
            }
        }

        $this->sendUpdate(['robots_txt' => $this->results['robots_txt']]);
    }

    private function checkWordPressContent()
    {
        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'WordPress Site Checker Bot');
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $html = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception("Error accessing the site: " . curl_error($ch));
        }

        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($status_code != 200) {
            $this->results['issues'][] = "Site returned status code: $status_code";
            $this->sendUpdate(['issues' => $this->results['issues']]);
        }

        curl_close($ch);

        if (strpos($html, '/wp-content/') !== false) {
            $this->results['is_wordpress'] = true;
            $this->sendUpdate(['is_wordpress' => true]);

            $this->checkWordPressVersion($html);
            $this->checkTheme($html);
            $this->checkPlugins($html);
            $this->checkDirectoryIndexing();
            $this->checkWpCron();
            $this->checkUserEnumeration();
            $this->checkXmlRpc();
        } else {
            $this->results['issues'][] = "This does not appear to be a WordPress site.";
            $this->sendUpdate(['is_wordpress' => false]);
        }

        $this->sendUpdate(['issues' => $this->results['issues']]);
    }

    private function checkWordPressVersion($html)
    {
        preg_match('/<meta name="generator" content="WordPress ([0-9.]+)"/', $html, $version_matches);
        if (!empty($version_matches[1])) {
            $this->results['version'] = $version_matches[1];
            $this->results['issues'][] = "WordPress version is visible: " . $version_matches[1];
        }
        $this->sendUpdate(['version' => $this->results['version']]);
    }

    private function checkTheme($html)
    {
        preg_match('/wp-content\/themes\/([^\/]+)/', $html, $theme_matches);
        if (!empty($theme_matches[1])) {
            $this->results['theme'] = $theme_matches[1];
        }
        $this->sendUpdate(['theme' => $this->results['theme']]);
    }

    private function checkPlugins($html)
    {
        preg_match_all('/wp-content\/plugins\/([^\/]+)/', $html, $plugin_matches);
        if (!empty($plugin_matches[1])) {
            $plugins = array_values(array_unique($plugin_matches[1]));
            foreach ($plugins as $plugin) {
                try {
                    $plugin_file = $this->url . "/wp-content/plugins/$plugin/$plugin.php";
                    $context = stream_context_create(['http' => ['ignore_errors' => true]]);
                    $plugin_data = @file_get_contents($plugin_file, false, $context);

                    if ($plugin_data !== false) {
                        $http_response_header = $http_response_header ?? [];
                        $status_line = $http_response_header[0] ?? '';
                        if (strpos($status_line, '200') !== false) {
                            preg_match('/Version:\s*(.+)$/m', $plugin_data, $version_match);
                            $version = !empty($version_match[1]) ? trim($version_match[1]) : 'Unknown';
                            $this->results['plugins'][] = "$plugin|$version";
                        } else {
                            $this->results['plugins'][] = "$plugin|Unable to determine version";
                        }
                    } else {
                        $this->results['plugins'][] = "$plugin|Unable to access plugin file";
                    }
                } catch (Exception $e) {
                    $this->results['plugins'][] = "$plugin|Error checking plugin";
                    $this->results['issues'][] = "Error checking plugin $plugin: " . $e->getMessage();
                }
            }
        }
        $this->sendUpdate(['plugins' => $this->results['plugins']]);
    }

    private function checkDirectoryIndexing()
    {
        $this->results['directory_indexing'] = false;
        $dirs_to_check = [
            $this->url . "/wp-content/uploads/",
            $this->url . "/wp-content/plugins/"
        ];

        foreach ($dirs_to_check as $dir) {
            $context = stream_context_create(['http' => ['ignore_errors' => true]]);
            $response = @file_get_contents($dir, false, $context);

            if ($response !== false) {
                $status_line = $http_response_header[0];
                preg_match('{HTTP\/\S*\s(\d{3})}', $status_line, $match);
                $status = $match[1];

                if ($status == '200' && strpos($response, 'Index of') !== false) {
                    $this->results['directory_indexing'] = true;
                    $this->results['issues'][] = "Directory indexing is enabled for $dir";
                    break;
                }
            }
        }

        if (!$this->results['directory_indexing']) {
            $this->results['issues'][] = "Directory indexing appears to be disabled (good security practice)";
        }

        $this->sendUpdate(['directory_indexing' => $this->results['directory_indexing']]);
    }

    private function checkWpCron()
    {
        $wp_cron = @file_get_contents($this->url . "/wp-cron.php");
        if ($wp_cron !== false) {
            $this->results['wp_cron_found'] = true;
            $this->results['issues'][] = "wp-cron.php is accessible";
        }
        $this->sendUpdate(['wp_cron_found' => $this->results['wp_cron_found']]);
    }

    private function checkUserEnumeration()
    {
        $context = stream_context_create(['http' => ['ignore_errors' => true]]);
        $user_check = @file_get_contents($this->url . "/?author=1", false, $context);

        if ($user_check === false) {
            $this->results['user_enumeration'] = false;
            $this->results['issues'][] = "User enumeration check failed due to network error";
        } else {
            $http_response_header = $http_response_header ?? [];
            $status_line = $http_response_header[0] ?? '';
            preg_match('{HTTP\/\S*\s(\d{3})}', $status_line, $match);
            $status = $match[1] ?? '';

            if ($status == '200' && strpos($user_check, 'author/') !== false) {
                $this->results['user_enumeration'] = true;
                $this->results['issues'][] = "User enumeration is possible";
            } else {
                $this->results['user_enumeration'] = false;
                if ($status == '404') {
                    $this->results['issues'][] = "User enumeration appears to be disabled (good security practice)";
                } else {
                    $this->results['issues'][] = "User enumeration check returned unexpected status: $status";
                }
            }
        }

        $this->sendUpdate(['user_enumeration' => $this->results['user_enumeration']]);
    }

    private function checkXmlRpc()
    {
        $context = stream_context_create(['http' => ['ignore_errors' => true]]);
        $xmlrpc = @file_get_contents($this->url . "/xmlrpc.php", false, $context);

        if ($xmlrpc === false) {
            $this->results['xml_rpc_enabled'] = false;
            $this->results['issues'][] = "XML-RPC check failed due to network error";
        } else {
            $http_response_header = $http_response_header ?? [];
            $status_line = $http_response_header[0] ?? '';
            preg_match('{HTTP\/\S*\s(\d{3})}', $status_line, $match);
            $status = $match[1] ?? '';

            if ($status == '200' && strpos($xmlrpc, 'XML-RPC server accepts POST requests only.') !== false) {
                $this->results['xml_rpc_enabled'] = true;
                $this->results['issues'][] = "XML-RPC is enabled and accessible";
            } else {
                $this->results['xml_rpc_enabled'] = false;
                if ($status == '405' || $status == '403') {
                    $this->results['issues'][] = "XML-RPC access is restricted (good security practice)";
                } else {
                    $this->results['issues'][] = "XML-RPC check returned unexpected status: $status";
                }
            }
        }

        $this->sendUpdate(['xml_rpc_enabled' => $this->results['xml_rpc_enabled']]);
    }
}
