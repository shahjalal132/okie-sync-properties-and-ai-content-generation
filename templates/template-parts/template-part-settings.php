<h4 class="common-title">CSV Upload</h4>

<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="import_csv">
    <label for="csv_file" class="custom-file-upload">
        Upload CSV
    </label>
    <input type="file" name="csv_file" id="csv_file" style="display: none;">
    <span id="file-name" class="file-name-display">No file selected</span>
    <button type="submit" class="save-btn mt-10" style="display: block;">Submit</button>
</form>

<h4 class="common-title mt-20">Settings</h4>

<div class="settings-wrapper overflow-hidden">
    <button type="button" class="save-btn mt-20 button-flex" id="fetch_properties">
        <span>Get Properties</span>
        <span class="fetch-properties-spinner-loader-wrapper"></span>
    </button>
</div>
