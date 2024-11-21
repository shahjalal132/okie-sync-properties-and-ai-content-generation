<?php

$base_url = site_url() . '/wp-json/okie/v1';
$api_url  = get_option( 'okie_chatgpt_api_endpoint', 'https://api.openai.com/v1/chat/completions' );
$api_key  = get_option( 'okie_chatgpt_api_secret_key' );
$option1  = get_option( 'option1' );
$option2  = get_option( 'option2' );

?>

<!-- <h2 class="admin-menu-title">Admin Sub Menu Contents</h2> -->
<div class="page-heading-title">
    <h1>Okie Settings</h1>
</div>

<div class="tab-container">

    <div id="toast-container"></div>

    <div class="tabs-container common-shadow">

        <div class="tabs">
            <button class="tab active" data-tab="api">API</button>
            <button class="tab" data-tab="options">Options</button>
            <button class="tab" data-tab="settings">Settings</button>
            <button class="tab" data-tab="endpoints">Endpoints</button>
        </div>
    </div>

    <div class="tabs-contents-container">

        <div class="tab-content" id="api">
            <?php include_once PLUGIN_BASE_PATH . '/templates/template-parts/template-part-api.php'; ?>
        </div>

        <div class="tab-content" id="options" style="display: none;">
            <?php include_once PLUGIN_BASE_PATH . '/templates/template-parts/template-part-options.php'; ?>
        </div>

        <div class="tab-content" id="settings" style="display: none;">
            <?php include_once PLUGIN_BASE_PATH . '/templates/template-parts/template-part-settings.php'; ?>
        </div>

        <div class="tab-content" id="endpoints" style="display: none;">
            <?php include_once PLUGIN_BASE_PATH . '/templates/template-parts/template-part-endpoints.php'; ?>
        </div>

    </div>
</div>