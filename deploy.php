<?php
/**
 * Freemius Plugin Deployment Script
 *
 * This script handles the deployment of WordPress plugins to Freemius.
 * It validates environment variables, handles file uploads, and manages
 * version deployments in different release modes (pending/beta/released).
 */

// Validate required environment variables
$required_env = ['DEV_ID', 'PUBLIC_KEY', 'SECRET_KEY', 'PLUGIN_SLUG', 'PLUGIN_ID'];
foreach ($required_env as $env) {
	if ( empty($_ENV[$env]) ) {
        echo "Error: Required environment variable $env is missing or empty\n";
        exit(1);
    }
}
$file_name = $_ENV['INPUT_FILE_NAME'];
if (!file_exists("$file_name")) {
	echo "Error: File '$file_name' not found\n";
	exit(1);
}

// Validate release mode
$release_mode = empty($_ENV['INPUT_RELEASE_MODE']) ? 'pending' : $_ENV['INPUT_RELEASE_MODE'];
if (!in_array($release_mode, ['pending', 'beta', 'released'])) {
    $release_mode = 'pending'; //fallback to default
	echo "Warning: Invalid release mode '$release_mode', falling back to 'pending'\n";
}

// set other input variables
$version = $_ENV['INPUT_VERSION'];
$sandbox = ($_ENV['INPUT_SANDBOX'] === 'true');
$release_limit = intval($_ENV['INPUT_LIMIT']);
$percentage_limit = intval($_ENV['INPUT_LIMIT_PERCENTAGE']);
$is_incremental = ($_ENV['INPUT_IS_INCREMENTAL'] === 'true');
$add_contributor = ($_ENV['INPUT_ADD_CONTRIBUTOR'] === 'true');
$overwrite = ($_ENV['INPUT_OVERWRITE'] === 'true');
$num_versions = intval($_ENV['INPUT_NUM_VERSIONS']);

$debugging = !empty($_ENV['ACTIONS_STEP_DEBUG']) && $_ENV['ACTIONS_STEP_DEBUG'] === 'true';

echo "\n- Deploying " . $_ENV['PLUGIN_SLUG'] . " to Freemius, with arguments: ";
echo "\n- file_name: " . $file_name . " version: " . $version . " sandbox: " . $sandbox . " release_mode: " . $release_mode;

// Include Freemius SDK files
require_once '/freemius-php-api/freemius/FreemiusBase.php';
require_once '/freemius-php-api/freemius/Freemius.php';

// Configure Freemius API constants
define('FS__API_SCOPE', 'developer');
define('FS__API_DEV_ID', $_ENV['DEV_ID']);
define('FS__API_PUBLIC_KEY', $_ENV['PUBLIC_KEY']);
define('FS__API_SECRET_KEY', $_ENV['SECRET_KEY']);

echo "\n- Deploy in progress on Freemius\n";

try {
    // Init SDK.
	$api = new Freemius_Api(FS__API_SCOPE, FS__API_DEV_ID, FS__API_PUBLIC_KEY, FS__API_SECRET_KEY, $sandbox);

    if (!is_object($api)) {
        echo "Error: Failed to initialize Freemius API client\n";
        exit(1);
    }

    // Fetch all existing version tags for the plugin
    // This is used to check if the current version already exists
	$tags_response = $api->Api('plugins/' . $_ENV['PLUGIN_ID'] . '/tags.json', 'GET');
	if ($debugging ) {
		echo "::debug:: Fetched existing version tags: " . print_r( $tags_response, true ) . "\n";
	}

	// Check if version already exists
    $version_exists = false;
    $existing_tag = null;
    foreach ($tags_response->tags as $tag) {
        if ($tag->version === $version) {
            $version_exists = true;
            $existing_tag = $tag;
            break;
        }
    }

    // Handle existing version
    if ($version_exists) {
        $deploy = $existing_tag;
        echo "Package version $version already deployed on Freemius";
    } else {
        // Upload the zip
        $deploy = $api->Api('plugins/' . $_ENV['PLUGIN_ID'] . '/tags.json', 'POST', array(
            'add_contributor' => false
        ), array(
            'file' => $file_name
        ));

	    if ($debugging ) {
		    echo "::debug:: response: " . print_r( $deploy, true ) . "\n";
	    }

	    if (!property_exists($deploy, 'id')) {
		    echo "Deploy failed. No id in response object.";
            if (!$debugging) { //we didn't already echo the response
                echo "Response: " . print_r($deploy, true) . "\n";
            }
		    exit(1);
	    }

	    echo "- Deploy done on Freemius\n";
    }
	$is_released = $api->Api('plugins/' . $_ENV['PLUGIN_ID'] . '/tags/' . $deploy->id . '.json', 'PUT', array(
		'release_mode' => $release_mode
	), array());

	echo "- Set as $release_mode on Freemius\n";

	echo "- Download Freemius free version\n";

    // Generate url to download the zip
	$zip_free = $api->GetSignedUrl('plugins/' . $_ENV['PLUGIN_ID'] . '/tags/' . $deploy->id . '.zip');
    $path = pathinfo($file_name);
    $zipname_free = $path['dirname'] . '/' . basename($file_name, '.zip');
    $zipname_free .= '__free.zip';

    file_put_contents($zipname_free, file_get_contents($zip_free));

    if (!file_exists($zipname_free) || filesize($zipname_free) == 0) {
        echo "Error: Failed to download free version or file is empty\n";
        exit(1);
    }

    echo "- Downloaded Freemius free version to " . $zipname_free . "\n";
    file_put_contents(getenv('GITHUB_OUTPUT'), "free_version=" . $zipname_free . "\n", FILE_APPEND);

    // Generate url to download the pro-zip
	$zip_pro = $api->GetSignedUrl('plugins/' . $_ENV['PLUGIN_ID'] . '/tags/' . $deploy->id . '.zip?is_premium=true');
    $path = pathinfo($file_name);
    $zipname_pro = $path['dirname'] . '/' . basename($file_name, '.zip');
    $zipname_pro .= '__pro.zip';

    file_put_contents($zipname_pro, file_get_contents($zip_pro));

    if (!file_exists($zipname_pro) || filesize($zipname_pro) == 0) {
        echo "Error: Failed to download pro version or file is empty\n";
        exit(1);
    }

    echo "- Downloaded Freemius pro version to " . $zipname_pro . "\n";
    file_put_contents(getenv('GITHUB_OUTPUT'), "pro_version=" . $zipname_pro . "\n", FILE_APPEND);
} catch (Exception $e) {
    // Handle any errors during deployment and provide detailed error information
    // Exit with non-zero status to indicate failure to GitHub Actions
    echo "- Error: " . $e->getMessage() . "\n";
    echo "- Error occurred at line " . $e->getLine() . " in " . $e->getFile() . "\n";
    exit(1); // Return non-zero exit code to indicate failure to GitHub Actions
}
