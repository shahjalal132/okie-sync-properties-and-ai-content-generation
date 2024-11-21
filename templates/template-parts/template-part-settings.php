<h4 class="common-title">CSV Upload</h4>

<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="import_csv">
    <label for="csv_file" class="custom-file-upload">
        Chose CSV File
    </label>
    <input type="file" name="csv_file" id="csv_file" style="display: none;">
    <span id="file-name" class="file-name-display">No file selected</span>
    <div class="upload-btn-wrapper">
        <button type="submit" class="save-btn button-flex mt-10" id="upload_csv_btn">
            <span>Upload</span>
            <span class="spinner-loader-wrapper-csv"></span>
        </button>
    </div>
</form>

<h4 class="common-title mt-20">Get Properties From API</h4>

<div class="settings-wrapper overflow-hidden">
    <button type="button" class="save-btn mt-20 button-flex" id="generate_hash">
        <span>Generate Hash</span>
        <span class="generate-hash-spinner-loader-wrapper"></span>
    </button>
    <button type="button" class="save-btn mt-10 button-flex" id="fetch_properties">
        <span>Get Properties</span>
        <span class="fetch-properties-spinner-loader-wrapper"></span>
    </button>
</div>

<h4 class="common-title mt-20">Generate Description</h4>

<div class="settings-wrapper overflow-hidden">
    <button type="button" class="save-btn mt-20 button-flex" id="generate_description">
        <span>Generate Description</span>
        <span class="generate-description-spinner-loader-wrapper"></span>
    </button>
</div>